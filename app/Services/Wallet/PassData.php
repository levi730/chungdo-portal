<?php

namespace App\Services\Wallet;

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The data behind one user's event check-in pass: the event, the registrations
 * that user paid for (self + dependents), and the QR payload the check-in
 * scanner reads. Shared by the Apple and Google pass builders so both carry an
 * identical barcode.
 */
class PassData
{
    /**
     * @param  Collection<int, User>  $registrants  the registered users (with a `pivot` EventRegistration)
     * @param  array<int>  $registrationIds  event_registrations.id values (the QR payload)
     */
    public function __construct(
        public readonly Event $event,
        public readonly User $purchaser,
        public readonly Collection $registrants,
        public readonly array $registrationIds,
    ) {}

    /**
     * Build from a signed-in user: their own registration plus any dependents'
     * registrations for this event. Returns null when the user has no
     * registration for the event (nothing to add to a wallet).
     */
    public static function forUserEvent(User $user, Event $event): ?self
    {
        $ids = $user->dependents->pluck('id')->push($user->id);

        $registrants = $event->registrations()
            ->whereIn('user_id', $ids)
            ->with('rank')
            ->get();

        if ($registrants->isEmpty()) {
            return null;
        }

        $registrationIds = $registrants->pluck('pivot.id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return new self($event, $user, $registrants, $registrationIds);
    }

    /** The QR/barcode message both wallets encode. */
    public function qrPayload(): string
    {
        return json_encode(['registrants' => $this->registrationIds]);
    }

    public function registrantCount(): int
    {
        return count($this->registrationIds);
    }

    /** "Full Name (Rank)" lines for the pass back / details. */
    public function registrantLines(): array
    {
        return $this->registrants->map(function (User $u) {
            $rank = $u->rank?->rank;

            return $rank ? "{$u->full_name} ({$rank})" : $u->full_name;
        })->all();
    }
}
