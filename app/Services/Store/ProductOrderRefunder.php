<?php

namespace App\Services\Store;

use App\Models\ProductOrder;
use App\Models\ProductOrderRefund;
use App\Models\User;
use App\Services\Stripe\StripeAccounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Refunds some or all of a paid store order, from the order's admin page.
 *
 * Gated on `store.issueRefunds`, held by no role — the same shape as the event
 * withdrawal (App\Services\RegistrationWithdrawal): super.admin through the
 * Gate::before bypass, the coordinator through config/portal.php, anyone else
 * by direct grant from the admin user form.
 *
 *  - The refund is issued on the ORDER's Stripe account, never the default
 *    one — the two accounts do not share charges, and a refund on the wrong
 *    account fails at best. That account was snapshotted on the order at
 *    checkout and locked the moment the charge was attempted.
 *  - The amount is the admin's: up to what was captured less what has already
 *    gone back. Lowering it keeps a processing fee, or returns one shirt of
 *    two. Each call is one Stripe refund and one ProductOrderRefund row;
 *    `refunded_amount` on the order is the running total.
 *  - The order stays `paid` (the money did arrive) and its items are left
 *    alone, because they are the itemization of a charge that really moved.
 *    A fully refunded order is simply dropped from the pick list.
 *
 * Runs in one transaction holding a row lock on the order, Stripe call
 * included, so a double-submit converges on one refund. If the records fail
 * AFTER the Stripe refund went out, the rollback cannot recall the money: the
 * refund id is logged at error level for hand reconciliation.
 */
class ProductOrderRefunder
{
    /** @var callable(string, int, string): string returns the Stripe refund id */
    private $refunder;

    public function __construct(?callable $refunder = null)
    {
        $this->refunder = $refunder ?? function (string $paymentIntentId, int $amountCents, string $account): string {
            \Stripe\Stripe::setApiKey(app(StripeAccounts::class)->secret($account));
            $refund = \Stripe\Refund::create([
                'payment_intent' => $paymentIntentId,
                'amount' => $amountCents,
            ]);

            return $refund->id;
        };
    }

    /**
     * @throws ValidationException when the order cannot take this refund
     * @throws \Throwable when Stripe or the records fail
     */
    public function refund(ProductOrder $order, float $amount, User $by, ?string $note = null): ProductOrderRefund
    {
        $amount = round($amount, 2);
        $stripeRefundId = null;

        try {
            return DB::transaction(function () use ($order, $amount, $by, $note, &$stripeRefundId) {
                /** @var ProductOrder $o */
                $o = ProductOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();

                if (! $o->isPaid()) {
                    throw ValidationException::withMessages(['amount' => 'Only a paid order can be refunded.']);
                }
                if (! $o->stripe_payment_intent_id) {
                    throw ValidationException::withMessages(['amount' => 'This order has no Stripe charge to refund against.']);
                }
                if ($amount <= 0) {
                    throw ValidationException::withMessages(['amount' => 'The refund amount must be more than zero.']);
                }
                if ($amount > $o->refundable() + 0.001) {
                    throw ValidationException::withMessages([
                        'amount' => 'Only $'.number_format($o->refundable(), 2).' can still be refunded on this order.',
                    ]);
                }

                $stripeRefundId = ($this->refunder)(
                    $o->stripe_payment_intent_id,
                    (int) round($amount * 100),
                    $o->stripe_account
                );

                $refund = $o->refunds()->create([
                    'payment_id' => $o->payment_id,
                    'stripe_account' => $o->stripe_account,
                    'stripe_payment_intent_id' => $o->stripe_payment_intent_id,
                    'stripe_refund_id' => $stripeRefundId,
                    'amount' => $amount,
                    'refunded_by_user_id' => $by->id,
                    'admin_note' => $note,
                ]);

                $o->refunded_amount = round((float) $o->refunded_amount + $amount, 2);
                $o->stripe_refund_id = $stripeRefundId;
                $o->save();

                // Copy the outcome onto the caller's instance so the page that
                // redirects can show the new totals without a refetch.
                $order->refunded_amount = $o->refunded_amount;
                $order->stripe_refund_id = $o->stripe_refund_id;

                return $refund;
            });
        } catch (\Throwable $e) {
            if ($stripeRefundId !== null) {
                Log::error('Store refund issued on Stripe but the records failed to save; reconcile by hand.', [
                    'product_order_id' => $order->id,
                    'reference' => $order->reference,
                    'stripe_account' => $order->stripe_account,
                    'stripe_refund_id' => $stripeRefundId,
                    'amount' => $amount,
                    'error' => $e->getMessage(),
                ]);
            }
            throw $e;
        }
    }
}
