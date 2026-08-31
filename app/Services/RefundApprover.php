<?php

namespace App\Services;

use App\EventAddons\AddonAdjuster;
use App\Models\AddonChangeRequest;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Stripe\StripeAccounts;
use Illuminate\Support\Facades\DB;

/**
 * Decides pending add-on refund requests. Approving issues the Stripe refund
 * FIRST (money out), then applies the reduced add-on state and records the
 * decision — so a failed refund leaves the request pending and the registration
 * untouched. The refund amount is admin-editable and may differ from the
 * originally computed amount (e.g. to withhold the Stripe fee).
 */
class RefundApprover
{
    /** @var callable(string, int, ?string): string returns the Stripe refund id */
    private $refunder;

    public function __construct(?callable $refunder = null)
    {
        // The third argument is the event's Stripe account slug: a refund must
        // be issued on the account that took the charge, so it cannot use the
        // association's key unconditionally.
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
     * Approve a request: refund $amount, apply the new add-on state, record it.
     */
    public function approve(AddonChangeRequest $request, float $amount, User $admin, ?string $note = null): void
    {
        if (! $request->isPending()) {
            return;
        }

        $amount = round(max(0, $amount), 2);

        $refundId = null;
        if ($amount > 0 && $request->stripe_payment_intent_id) {
            // Throws on failure — the request stays pending, nothing applied.
            $refundId = ($this->refunder)(
                $request->stripe_payment_intent_id,
                (int) round($amount * 100),
                app(StripeAccounts::class)->forEvent($request->event),
            );
        }

        DB::transaction(function () use ($request, $amount, $admin, $note, $refundId) {
            $registration = $request->registration;

            // Snapshot which add-on categories this refund reduces, BEFORE applying
            // the new state — once applied, the original amounts are gone and this
            // can't be reconstructed. Feeds the Financials export's refund lines.
            $breakdown = $this->breakdown($request);

            (new AddonAdjuster())->applySerialized($registration, $request->new_state);

            $request->update([
                'status' => AddonChangeRequest::STATUS_APPROVED,
                'refund_amount' => $amount,
                'stripe_refund_id' => $refundId,
                'decided_by_user_id' => $admin->id,
                'decided_at' => now(),
                'admin_note' => $note,
            ]);

            if ($amount > 0) {
                $paymentId = optional(
                    Payment::where('stripe_payment_intent_id', $request->stripe_payment_intent_id)->first()
                )->id;

                Refund::create([
                    'event_id' => $request->event_id,
                    'event_registration_id' => $registration->id,
                    'person_id' => $registration->user_id,
                    'refunded_to_user_id' => $request->requested_by_user_id ?? $registration->registering_user_id,
                    'addon_change_request_id' => $request->id,
                    'payment_id' => $paymentId,
                    'stripe_payment_intent_id' => $request->stripe_payment_intent_id,
                    'stripe_refund_id' => $refundId,
                    'amount' => $amount,
                    'breakdown' => $breakdown,
                    'decided_by_user_id' => $admin->id,
                    'admin_note' => $note,
                ]);
            }
        });
    }

    /**
     * The per-add-on-type amount this refund removes, from the current stored
     * answers vs. the request's target state. Keyed by add-on type (e.g.
     * ['meal_ticket' => 30.0, 'donation' => 20.0]). Only reductions are recorded.
     *
     * @return array<string, float>
     */
    private function breakdown(AddonChangeRequest $request): array
    {
        $registration = $request->registration;
        $registration->loadMissing('addonAnswers');
        $current = $registration->addonAnswers->keyBy('event_addon_id');

        $breakdown = [];
        foreach ($request->new_state as $item) {
            $from = (float) ($current->get($item['event_addon_id'])->amount ?? 0);
            $to = (float) ($item['attrs']['amount'] ?? 0);
            $delta = round($from - $to, 2);
            if ($delta > 0) {
                $type = $item['type'];
                $breakdown[$type] = round(($breakdown[$type] ?? 0) + $delta, 2);
            }
        }

        return $breakdown;
    }

    public function deny(AddonChangeRequest $request, User $admin, ?string $note = null): void
    {
        if (! $request->isPending()) {
            return;
        }

        $request->update([
            'status' => AddonChangeRequest::STATUS_DENIED,
            'decided_by_user_id' => $admin->id,
            'decided_at' => now(),
            'admin_note' => $note,
        ]);
    }
}
