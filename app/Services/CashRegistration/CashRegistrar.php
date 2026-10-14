<?php

namespace App\Services\CashRegistration;

use App\EventAddons\AddonHandler;
use App\EventAddons\AddonRegistrar;
use App\Models\CashRemittance;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\Stripe\StripeAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registers a batch of students who paid an instructor in cash or check.
 *
 * The registrations are created straight away, unpaid, and one CashRemittance
 * records what the instructor owes for them. Nothing is charged: marking the
 * remittance received (RemittanceReceiver) is what creates the Payment.
 *
 * Pricing goes through the same add-on handlers as the card form, fed a
 * synthetic request, so a student costs the same whichever door they come in
 * by. The one difference is the multi-person discount: on the card form it is
 * by position in the submission, which is one family. An instructor's batch is
 * many families, so the discount is applied per household within the batch —
 * otherwise ten unrelated students would get the fifth-person rate.
 *
 * Group-scoped add-ons (donation, potluck) are not offered here: they are a
 * family's choice, not something an instructor answers for them.
 */
class CashRegistrar
{
    /** Per-student add-on types this flow can answer, matching the handlers' request keys. */
    public const SUPPORTED_ADDONS = ['registration_fee', 'tshirt', 'participation', 'meal_ticket', 'guests', 'volunteer'];

    public function __construct(private AddonRegistrar $addons = new AddonRegistrar())
    {
    }

    /**
     * Price a batch without writing anything: the per-student lines and the
     * total, for the live summary on the form and for submit().
     *
     * @param  array<int, array>  $answers  [user_id => ['tshirt' => 'M', 'participation' => 'both',
     *                                       'meal' => ['attending' => bool, 'additional' => int],
     *                                       'guests' => int, 'volunteer' => string[]]]
     * @return array{students: array<int, array>, total: float, perUser: array}
     */
    public function quote(Event $event, array $answers): array
    {
        $event->loadMissing('addons');
        $users = User::whereIn('id', array_keys($answers))->get()->keyBy('id');

        // Keep the order the instructor added them in.
        $ordered = collect(array_keys($answers))->map(fn ($id) => $users->get($id))->filter();

        $students = [];
        $perUser = [];
        $total = 0.0;

        foreach ($this->households($ordered) as $household) {
            $request = $this->requestFor($household, $answers);
            $parsed = $this->addons->parse($request, $this->perStudentOnly($event), $household->all());

            foreach ($household as $user) {
                $items = $parsed['perUser'][$user->id] ?? [];
                $perUser[$user->id] = $items;

                $lines = [];
                $amount = 0.0;
                foreach ($items as $item) {
                    $lineAmount = round((float) ($item['attrs']['amount'] ?? 0), 2);
                    $amount += $lineAmount;
                    $lines[] = [
                        'type' => $item['addon']->type,
                        'label' => $item['addon']->label(),
                        'summary' => $item['addon']->handler()?->summarize($item['addon'], $item['attrs']),
                        'amount' => $lineAmount,
                    ];
                }

                $students[$user->id] = [
                    'user_id' => $user->id,
                    'name' => $user->full_name,
                    'amount' => round($amount, 2),
                    'lines' => $lines,
                ];
                $total += $amount;
            }
        }

        // Back into the order they were added.
        $students = collect(array_keys($answers))
            ->filter(fn ($id) => isset($students[$id]))
            ->map(fn ($id) => $students[$id])
            ->values()
            ->all();

        return ['students' => $students, 'total' => round($total, 2), 'perUser' => $perUser];
    }

    /**
     * Create the registrations and the remittance. Throws a ValidationException
     * naming the student when anyone is already registered or below the event's
     * minimum rank — nothing is written in that case.
     */
    public function submit(Event $event, User $responsible, User $creator, array $answers, ?string $note = null): CashRemittance
    {
        if (empty($answers)) {
            throw ValidationException::withMessages(['students' => 'Choose at least one student to register.']);
        }

        $remittance = DB::transaction(function () use ($event, $responsible, $creator, $answers, $note) {
            // Serialise cash batches for this event so two instructors adding
            // the same student at once cannot both register them.
            Event::whereKey($event->id)->lockForUpdate()->first();

            $this->assertRegistrable($event, array_keys($answers));

            $quote = $this->quote($event, $answers);
            $baseFee = (float) ($event->addon('registration_fee')?->setting('cost', 0) ?? 0);
            $account = app(StripeAccounts::class)->forEvent($event);

            $remittance = CashRemittance::create([
                'event_id' => $event->id,
                'responsible_user_id' => $responsible->id,
                'created_by_user_id' => $creator->id,
                'stripe_account' => $account,
                'payee' => app(StripeAccounts::class)->payee($account),
                'amount_due' => $quote['total'],
                'status' => CashRemittance::STATUS_OUTSTANDING,
                'note' => $note ?: null,
                'payload' => ['students' => $quote['students']],
            ]);
            $remittance->reference = CashRemittance::referenceFor($remittance->id);
            $remittance->save();

            foreach ($quote['students'] as $student) {
                // A Pivot is not auto-incrementing, so create() would hand back
                // no id to hang the add-on answers on.
                $id = DB::table('event_registrations')->insertGetId([
                    'event_id' => $event->id,
                    'user_id' => $student['user_id'],
                    'amount_due' => $baseFee,
                    'amount_paid' => 0,
                    'registering_user_id' => $creator->id,
                    'cash_remittance_id' => $remittance->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $registration = EventRegistration::find($id);

                $this->addons->persist($registration, $quote['perUser'][$student['user_id']] ?? []);
            }

            return $remittance;
        });

        app(RemittanceNotifier::class)->submitted($remittance);

        return $remittance;
    }

    /**
     * Everyone in the batch must be unregistered and meet the minimum rank.
     *
     * @param  int[]  $userIds
     */
    public function assertRegistrable(Event $event, array $userIds): void
    {
        $users = User::whereIn('id', $userIds)->with('rank')->get()->keyBy('id');

        foreach ($userIds as $id) {
            if (! $users->has($id)) {
                throw ValidationException::withMessages(['students' => 'One of the selected students no longer exists.']);
            }
        }

        $already = EventRegistration::where('event_id', $event->id)
            ->whereIn('user_id', $userIds)
            ->pluck('user_id');

        if ($already->isNotEmpty()) {
            $names = $already->map(fn ($id) => $users[$id]->full_name)->implode(', ');

            throw ValidationException::withMessages(['students' => "Already registered for this event: {$names}."]);
        }

        foreach ($users as $user) {
            if (! $event->userMeetsMinimumRank($user)) {
                $min = $event->minimumRank?->rank;

                throw ValidationException::withMessages(['students' => "{$user->full_name} does not meet the minimum rank for this event".($min ? " ({$min} or higher)" : '').'.']);
            }
        }
    }

    /**
     * Group the batch into households, keeping batch order inside each: a
     * student goes with their primary guardian, or stands alone. A guardian
     * who is in the batch themselves keys to their own id, so a parent and
     * their children land together.
     *
     * @param  Collection<int, User>  $users
     * @return Collection<string|int, Collection<int, User>>
     */
    public function households(Collection $users): Collection
    {
        return $users->groupBy(fn (User $u) => $u->primaryGuardian()?->id ?? $u->id);
    }

    /**
     * The card form's request shape, for one household: the handlers read
     * user-id-keyed maps out of these keys.
     */
    private function requestFor(Collection $household, array $answers): Request
    {
        $tshirts = $participation = $meals = $guests = $volunteer = [];

        foreach ($household as $user) {
            $a = $answers[$user->id] ?? [];
            $tshirts[$user->id] = $a['tshirt'] ?? null;
            if (! empty($a['participation'])) {
                $participation[$user->id] = $a['participation'];
            }
            $meals[$user->id] = [
                'attending' => (bool) ($a['meal']['attending'] ?? false),
                'additional' => (int) ($a['meal']['additional'] ?? 0),
            ];
            $guests[$user->id] = (int) ($a['guests'] ?? 0);
            foreach ((array) ($a['volunteer'] ?? []) as $item) {
                $volunteer[] = ['user_id' => $user->id, 'item' => $item];
            }
        }

        return new Request([
            'tshirts' => $tshirts,
            'participation' => $participation,
            'meals' => $meals,
            'guests' => $guests,
            'volunteer_selections' => json_encode($volunteer),
        ]);
    }

    /**
     * The event as the registrar should see it: per-student add-ons only.
     * A clone, so hiding the group ones does not leak into the caller's event.
     */
    private function perStudentOnly(Event $event): Event
    {
        $copy = clone $event;
        $copy->setRelation('addons', $event->addons->filter(
            fn ($a) => in_array($a->type, self::SUPPORTED_ADDONS, true)
                && $a->handler()?->scope() === AddonHandler::SCOPE_PER_STUDENT
        )->values());

        return $copy;
    }
}
