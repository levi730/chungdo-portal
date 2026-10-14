<?php

namespace App\Services\CashRegistration;

use App\Mail\CashRegistrationPending;
use App\Mail\CashRemittanceSlip;
use App\Mail\CashRemittancesOutstanding;
use App\Models\CashRemittance;
use App\Models\Committee;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\Coordinator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use QR_Code\QR_Code;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\TemporaryDirectory\TemporaryDirectory;

/**
 * Who hears about a remittance. Mail failures are reported, never thrown: the
 * registrations are already committed by the time anything is sent, and a bad
 * address must not look like a failed registration.
 */
class RemittanceNotifier
{
    /**
     * A new remittance: each household gets a confirmation naming only its own
     * students and what they owe, and the instructor gets the slip.
     */
    public function submitted(CashRemittance $remittance): void
    {
        $remittance->loadMissing(['event', 'responsible']);
        $lines = collect($remittance->lines())->keyBy('user_id');
        $students = User::findMany($lines->keys());

        foreach ($students->groupBy(fn (User $u) => $this->recipientFor($u)?->id ?? 0) as $recipientId => $household) {
            $recipient = $recipientId ? User::find($recipientId) : null;
            if (! $recipient || ! $recipient->email) {
                continue;
            }

            $this->send(fn () => Mail::to($recipient)->send(new CashRegistrationPending(
                $remittance,
                $recipient,
                $household->map(fn ($u) => $lines[$u->id])->values()->all(),
                $this->checkInQr($remittance, $household->pluck('id')->all()),
            )));
        }

        if ($remittance->responsible?->email) {
            $this->send(fn () => Mail::to($remittance->responsible)->send(new CashRemittanceSlip($remittance)));
        }
    }

    /**
     * Remittances whose event has passed by at least $graceDays and that are
     * still outstanding — the sweep this flow has in place of asking Stripe.
     */
    public function overdue(int $graceDays = 3): Collection
    {
        return CashRemittance::outstanding()
            ->whereHas('event', fn ($q) => $q->where('startdatetime', '<', now()->subDays($graceDays)))
            ->with(['event', 'responsible'])
            ->orderBy('created_at')
            ->get();
    }

    /** Send the overdue list to everyone who can mark remittances received. */
    public function remindOverdue(int $graceDays = 3): int
    {
        $overdue = $this->overdue($graceDays);
        if ($overdue->isEmpty()) {
            return 0;
        }

        foreach ($this->receivers() as $user) {
            $this->send(fn () => Mail::to($user)->send(new CashRemittancesOutstanding($overdue)));
        }

        return $overdue->count();
    }

    /**
     * Everyone holding payments.receive, by any of the routes a permission
     * arrives here: directly or through a role (Spatie), through a committee,
     * or as coordinator. super.admin's Gate::before bypass is deliberately not
     * counted — it would mail every super admin about every envelope.
     */
    public function receivers(): Collection
    {
        $permission = 'payments.receive';

        try {
            $users = User::permission($permission)->get();
        } catch (PermissionDoesNotExist) {
            return collect(); // the seeder has not run here
        }

        $users = $users->merge(
            Committee::whereHas('roles.permissions', fn ($q) => $q->where('name', $permission))
                ->with('members')->get()->flatMap->members
        );

        $coordinator = app(Coordinator::class);
        if (in_array($permission, $coordinator->permissionNames(), true) && ($c = $coordinator->user())) {
            $users->push($c);
        }

        return $users->filter(fn ($u) => $u->email)->unique('id')->values();
    }

    /** A student's mail goes to their primary guardian, or to them. */
    private function recipientFor(User $student): ?User
    {
        return $student->primaryGuardian() ?? $student;
    }

    /** The check-in QR the card path sends, for events that require a ticket. */
    private function checkInQr(CashRemittance $remittance, array $userIds): ?string
    {
        if (! $remittance->event->require_ticket) {
            return null;
        }

        $regIds = EventRegistration::where('event_id', $remittance->event_id)
            ->whereIn('user_id', $userIds)
            ->pluck('id');
        $file = (new TemporaryDirectory())->create()->path('qrcode.png');
        QR_Code::png(json_encode(['registrants' => $regIds]), $file);

        return $file;
    }

    private function send(callable $send): void
    {
        try {
            $send();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
