<?php

namespace App\Services;

use App\EventAddons\AddonHandler;
use App\EventAddons\AddonRegistry;
use App\Models\AddonChangeRequest;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventRegistrationAddon;
use App\Models\Payment;
use App\Models\PotluckOptions;
use App\Models\Refund;
use App\Models\User;
use App\Services\Stripe\StripeAccounts;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Withdraws a registrant from an event on an admin's say-so and refunds what
 * they paid — the face-to-face counterpart of the member-initiated flow
 * (AddonChangeRequest + RefundApprover), which waits for the member to ask and
 * can only reduce add-ons, never the registration fee.
 *
 * Gated on `event.issueRefunds`. Money out on one person's word, so no role
 * holds it: super.admin passes through the Gate::before bypass, the coordinator
 * through config/portal.php, anyone else by direct grant.
 *
 * Shape of a withdrawal:
 *
 *  - The registration's money is itemized from its event_registration_addons
 *    rows (the fee is one of them). Registrations older than the add-on
 *    refactor have no fee line; for those the fee is ESTIMATED as the payment's
 *    residual (amount paid minus everyone's itemized add-ons) split evenly over
 *    the registrants on that payment. The admin sees the estimate flagged and
 *    edits the amount.
 *  - Lines are grouped by the Payment they were charged on (a top-up is a second
 *    payment). The amount the admin enters is allocated across those groups in
 *    order, each capped at what that payment can still return; one Stripe
 *    refund and one Refund row per group. A shortfall comes off the
 *    registration fee first — "keep the processing fee" is the usual reason.
 *  - Group-scope add-ons (a donation, the potluck dish) are the household's,
 *    not this person's, so they are left out of the SUGGESTED amount but count
 *    toward the maximum; the admin can choose to return them.
 *  - Cash/check payments have no Stripe intent: the Refund row is written with
 *    no stripe_refund_id and the money is handed back by hand (the financials
 *    export already shows such rows as outside Stripe). A registration whose
 *    envelope is still OUTSTANDING is refused — void the remittance instead.
 *  - The registration is then archived to event_registrations_deleted with a
 *    snapshot of its lines (see the 2026_11_02 migration for why) and removed.
 *    Pending refund requests on it are superseded.
 *
 * Everything runs in one transaction holding a row lock on the registration,
 * Stripe calls included, so a double-click converges on one withdrawal. If the
 * records fail AFTER a Stripe refund has gone out, the rollback cannot recall
 * the money: the refund ids are logged at error level for hand reconciliation.
 */
class RegistrationWithdrawal
{
    public const REASON = 'withdrawn';

    /** @var callable(string, int, ?string): string returns the Stripe refund id */
    private $refunder;

    public function __construct(?callable $refunder = null)
    {
        // The third argument is the event's Stripe account slug: a refund must
        // be issued on the account that took the charge.
        $this->refunder = $refunder ?? function (string $paymentIntentId, int $amountCents, ?string $account = null): string {
            \Stripe\Stripe::setApiKey(app(StripeAccounts::class)->secret($account));
            $refund = \Stripe\Refund::create([
                'payment_intent' => $paymentIntentId,
                'amount' => $amountCents,
            ]);

            return $refund->id;
        };
    }

    /**
     * What one registration paid for, and what can come back.
     *
     * @return array{
     *   lines: array<int, array{type: string, label: string, amount: float, payment_id: ?int, group: bool, estimated: bool}>,
     *   groups: array<int, array{payment: ?Payment, lines: array, total: float, cap: float, method: string}>,
     *   suggested: float, max: float, methods: string[], awaiting_cash: bool
     * }
     */
    public function summary(EventRegistration $registration): array
    {
        return $this->summaries(collect([$registration]))[$registration->id];
    }

    /**
     * Summaries for many registrations with the lookups batched — the
     * registrants list calls this once per page, not once per row.
     *
     * @param  Collection<int, EventRegistration>  $registrations
     * @return array<int, array> keyed by registration id
     */
    public function summaries(Collection $registrations): array
    {
        if ($registrations->isEmpty()) {
            return [];
        }

        $events = Event::with('addons')->findMany($registrations->pluck('event_id')->unique())->keyBy('id');
        foreach ($registrations as $reg) {
            $reg->setRelation('event', $events->get($reg->event_id));
            $reg->loadMissing('addonAnswers');
        }

        $paymentIds = $registrations->pluck('payment_id')
            ->merge($registrations->flatMap(fn ($r) => $r->addonAnswers->pluck('payment_id')))
            ->filter()->unique()->values();
        $payments = Payment::findMany($paymentIds)->keyBy('id');
        $refunded = Refund::whereIn('payment_id', $paymentIds)
            ->groupBy('payment_id')->selectRaw('payment_id, sum(amount) as total')
            ->pluck('total', 'payment_id');

        // Legacy registrations (no fee line) need the other registrations on
        // their payment to estimate the fee. Load those once.
        $legacyPaymentIds = $registrations
            ->filter(fn ($r) => $r->payment_id && ! $r->addonAnswers->contains('type', 'registration_fee'))
            ->pluck('payment_id')->unique()->values();
        $siblings = $legacyPaymentIds->isEmpty()
            ? collect()
            : EventRegistration::whereIn('payment_id', $legacyPaymentIds)->with('addonAnswers')->get()->groupBy('payment_id');

        $out = [];
        foreach ($registrations as $reg) {
            $lines = $this->lines($reg, $payments, $siblings);
            $groups = $this->groups($reg, $lines, $payments, $refunded);

            $max = round(array_sum(array_column($groups, 'cap')), 2);
            $personal = round(array_sum(array_map(fn ($l) => $l['group'] ? 0 : $l['amount'], $lines)), 2);

            $out[$reg->id] = [
                'lines' => $lines,
                'groups' => $groups,
                'suggested' => min($personal, $max),
                'max' => $max,
                'methods' => array_values(array_unique(array_column($groups, 'method'))),
                'awaiting_cash' => $reg->awaitingCash(),
            ];
        }

        return $out;
    }

    /**
     * Withdraw the registrant, refunding $amount. Returns the Refund rows
     * written (none when nothing came back).
     *
     * @return Refund[]
     *
     * @throws ValidationException when the registration cannot be withdrawn as asked
     * @throws \Throwable when Stripe refuses the refund — nothing is recorded or removed
     */
    public function withdraw(EventRegistration $registration, float $amount, User $admin, ?string $note = null): array
    {
        $amount = round(max(0, $amount), 2);
        $note = trim((string) $note) ?: null;

        return DB::transaction(function () use ($registration, $amount, $admin, $note) {
            /** @var EventRegistration|null $reg */
            $reg = EventRegistration::whereKey($registration->id)->lockForUpdate()->first();

            if (! $reg) {
                throw ValidationException::withMessages(['registration' => 'This registration has already been withdrawn.']);
            }

            if ($reg->awaitingCash()) {
                throw ValidationException::withMessages([
                    'registration' => 'This registration is waiting on a cash/check envelope ('
                        .$reg->cashRemittance->reference.'). Void the remittance instead, or wait until it has been received.',
                ]);
            }

            $summary = $this->summary($reg);

            if ($amount > $summary['max'] + 0.005) {
                throw ValidationException::withMessages([
                    'amount' => 'At most $'.number_format($summary['max'], 2).' can be refunded for this registration.',
                ]);
            }

            $issued = [];

            try {
                $refunds = [];
                foreach ($this->allocate($summary['groups'], $amount) as $allocation) {
                    /** @var Payment $payment */
                    $payment = $allocation['payment'];

                    $refundId = null;
                    if ($payment->isStripe() && $payment->stripe_payment_intent_id) {
                        // Throws on failure: nothing below has happened yet.
                        $refundId = ($this->refunder)(
                            $payment->stripe_payment_intent_id,
                            (int) round($allocation['amount'] * 100),
                            app(StripeAccounts::class)->forEvent($reg->event),
                        );
                        $issued[] = $refundId;
                    }

                    $refunds[] = Refund::create([
                        'event_id' => $reg->event_id,
                        'event_registration_id' => $reg->id,
                        'person_id' => $reg->user_id,
                        'refunded_to_user_id' => $payment->user_id ?? $reg->registering_user_id ?? $reg->user_id,
                        'payment_id' => $payment->id,
                        'stripe_payment_intent_id' => $payment->stripe_payment_intent_id,
                        'stripe_refund_id' => $refundId,
                        'amount' => $allocation['amount'],
                        'breakdown' => $allocation['breakdown'],
                        'decided_by_user_id' => $admin->id,
                        'admin_note' => $note,
                    ]);
                }

                $this->archive($reg, $admin);

                return $refunds;
            } catch (\Throwable $e) {
                if ($issued) {
                    Log::error('Registration withdrawal failed after Stripe refund(s) were issued; the rollback cannot recall them. Record by hand.', [
                        'event_registration_id' => $reg->id,
                        'event_id' => $reg->event_id,
                        'user_id' => $reg->user_id,
                        'stripe_refund_ids' => $issued,
                        'error' => $e->getMessage(),
                    ]);
                }

                throw $e;
            }
        });
    }

    /**
     * The money lines of one registration: every answer with an amount, plus an
     * estimated fee line for legacy registrations that never stored one.
     *
     * @param  Collection<int, Payment>  $payments  keyed by id
     * @param  Collection<int, Collection<int, EventRegistration>>  $siblings  registrations by payment id, legacy payments only
     */
    private function lines(EventRegistration $reg, Collection $payments, Collection $siblings): array
    {
        $lines = [];
        foreach ($reg->addonAnswers as $answer) {
            $amount = round((float) $answer->amount, 2);
            if ($amount <= 0) {
                continue;
            }
            $handler = AddonRegistry::for($answer->type);
            $lines[] = [
                'type' => $answer->type,
                'label' => $reg->event?->addon($answer->type)?->label() ?? $handler?->label() ?? ucwords(str_replace('_', ' ', $answer->type)),
                'amount' => $amount,
                // Add-ons charged with the registration carry its payment; a
                // top-up carries its own. Unpaid answers inherit the registration's.
                'payment_id' => $answer->payment_id ?? $reg->payment_id,
                'group' => $handler?->scope() === AddonHandler::SCOPE_GROUP,
                'estimated' => false,
            ];
        }

        $hasFee = collect($lines)->contains('type', 'registration_fee');
        if (! $hasFee && $reg->payment_id && ($payment = $payments->get($reg->payment_id))) {
            $fee = $this->estimatedFee($payment, $siblings->get($payment->id, collect([$reg])));
            if ($fee > 0) {
                $lines[] = [
                    'type' => 'registration_fee',
                    'label' => 'Registration fee',
                    'amount' => $fee,
                    'payment_id' => $payment->id,
                    'group' => false,
                    'estimated' => true,
                ];
            }
        }

        // Registry order: fee first, then the add-ons as the admin UI lists them.
        $order = array_flip(array_keys(AddonRegistry::all()));
        usort($lines, fn ($a, $b) => ($order[$a['type']] ?? 99) <=> ($order[$b['type']] ?? 99));

        return $lines;
    }

    /**
     * A legacy registration's share of the fee: the payment's residual after
     * everyone's itemized add-ons, split evenly over its registrants. Household
     * discounts make the true split uneven; this is a suggestion the admin edits.
     */
    private function estimatedFee(Payment $payment, Collection $registrationsOnPayment): float
    {
        $itemized = $registrationsOnPayment->sum(
            fn ($r) => $r->addonAnswers->where('type', '!=', 'registration_fee')->sum('amount')
        );
        $residual = (float) $payment->amount_paid - (float) $itemized;
        $count = max(1, $registrationsOnPayment->count());

        return round(max(0, $residual) / $count, 2);
    }

    /**
     * Lines grouped by the payment that charged them, the registration's own
     * payment first. Each group is capped at what the payment can still return
     * (amount paid minus refunds already issued against it); lines with no
     * payment behind them (nothing was paid) cap at zero.
     *
     * @return array<int, array{payment: ?Payment, lines: array, total: float, cap: float, method: string}>
     */
    private function groups(EventRegistration $reg, array $lines, Collection $payments, Collection $refunded): array
    {
        $byPayment = collect($lines)->groupBy(fn ($l) => $l['payment_id'] ?? 0);
        $ids = $byPayment->keys()->sort()->values();
        if ($reg->payment_id && $ids->contains($reg->payment_id)) {
            $ids = $ids->reject(fn ($id) => $id === $reg->payment_id)->prepend($reg->payment_id);
        }

        $groups = [];
        foreach ($ids as $paymentId) {
            $payment = $paymentId ? $payments->get($paymentId) : null;
            $groupLines = $byPayment->get($paymentId)->values()->all();
            $total = round(array_sum(array_column($groupLines, 'amount')), 2);

            $cap = 0.0;
            if ($payment) {
                $remaining = round((float) $payment->amount_paid - (float) ($refunded->get($payment->id) ?? 0), 2);
                $cap = round(max(0, min($total, $remaining)), 2);
            }

            $groups[] = [
                'payment' => $payment,
                'lines' => $groupLines,
                'total' => $total,
                'cap' => $cap,
                'method' => $payment ? ($payment->isStripe() ? 'stripe' : ($payment->method ?? 'cash')) : 'none',
            ];
        }

        return $groups;
    }

    /**
     * Spread the admin's amount over the payment groups in order, each up to its
     * cap, and work out which lines the money represents.
     *
     * @return array<int, array{payment: Payment, amount: float, breakdown: array<string, float>}>
     */
    private function allocate(array $groups, float $amount): array
    {
        $left = $amount;
        $allocations = [];

        foreach ($groups as $group) {
            if ($left <= 0 || ! $group['payment'] || $group['cap'] <= 0) {
                continue;
            }

            $take = round(min($left, $group['cap']), 2);
            $left = round($left - $take, 2);

            $allocations[] = [
                'payment' => $group['payment'],
                'amount' => $take,
                'breakdown' => $this->breakdown($group['lines'], $take),
            ];
        }

        return $allocations;
    }

    /**
     * Per-type amounts for a refund of $amount against these lines. When the
     * refund is short of the lines' total, the shortfall comes off the
     * household lines first (a donation the admin did not mean to return),
     * then the registration fee (a withheld processing fee), then the rest.
     *
     * @return array<string, float>
     */
    private function breakdown(array $lines, float $amount): array
    {
        $shortfall = round(array_sum(array_column($lines, 'amount')) - $amount, 2);

        $ordered = collect($lines)
            ->sortBy(fn ($l) => $l['group'] ? 0 : ($l['type'] === 'registration_fee' ? 1 : 2))
            ->values();

        $breakdown = [];
        foreach ($ordered as $line) {
            $keep = round($line['amount'], 2);
            if ($shortfall > 0) {
                $cut = min($keep, $shortfall);
                $keep = round($keep - $cut, 2);
                $shortfall = round($shortfall - $cut, 2);
            }
            if ($keep > 0) {
                $breakdown[$line['type']] = round(($breakdown[$line['type']] ?? 0) + $keep, 2);
            }
        }

        return $breakdown;
    }

    /**
     * Copy the registration to event_registrations_deleted — with its lines, so
     * the financials export can still itemize the charge — and remove it.
     */
    private function archive(EventRegistration $reg, User $admin): void
    {
        $row = DB::table('event_registrations')->where('id', $reg->id)->first();

        DB::table('event_registrations_deleted')->insert([
            'registration_id' => $row->id,
            'created_at' => $row->created_at,
            'updated_at' => now(),
            'deleted_at' => now(),
            'user_id' => $row->user_id,
            'event_id' => $row->event_id,
            'amount_due' => $row->amount_due,
            'amount_paid' => $row->amount_paid,
            'payment_id' => $row->payment_id,
            'cash_remittance_id' => $row->cash_remittance_id,
            'checkin' => $row->checkin,
            'event_division_id' => $row->event_division_id,
            'registering_user_id' => $row->registering_user_id,
            'docs_printed' => $row->docs_printed,
            'addon_answers' => json_encode(
                $reg->addonAnswers->map(fn ($a) => $a->only(['event_addon_id', 'type', 'selected', 'quantity', 'value', 'amount', 'payment_id', 'data']))->values()
            ),
            'deleted_by_user_id' => $admin->id,
            'reason' => self::REASON,
        ]);

        // A refund request computed against this registration can no longer be
        // approved — the registration it would reduce is gone.
        AddonChangeRequest::where('event_registration_id', $reg->id)
            ->where('status', AddonChangeRequest::STATUS_PENDING)
            ->update(['status' => AddonChangeRequest::STATUS_SUPERSEDED]);

        // Give the potluck slot back (the fulfiller took it on registration).
        if ($itemId = $reg->potluckItemId()) {
            PotluckOptions::whereKey($itemId)->where('current_count', '>', 0)->decrement('current_count');
        }

        // The FK cascades, but say it: the answers go with the registration.
        EventRegistrationAddon::where('event_registration_id', $reg->id)->delete();
        DB::table('event_registrations')->where('id', $reg->id)->delete();
    }
}
