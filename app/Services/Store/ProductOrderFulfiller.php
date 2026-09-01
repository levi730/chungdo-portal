<?php

namespace App\Services\Store;

use App\Mail\ProductOrderPlaced;
use App\Models\Payment;
use App\Models\ProductOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Completes a ProductOrder exactly once, whether triggered by the synchronous
 * payment response, the payment_intent.succeeded webhook, or the reconcile
 * sweep. A near-copy of App\Services\RegistrationFulfiller — see
 * docs/payment-flow-pattern.md, which says to copy it rather than invent.
 *
 * The idempotency is structural, not hopeful: a row lock plus a status check
 * inside the transaction, and Payment::firstOrCreate keyed on the payment intent
 * id. `exists()` would not do — two concurrent webhook deliveries can both pass
 * an exists() check, but they cannot both hold the lock.
 *
 * Line items are NOT written here. They were written with the order, before the
 * charge, so the order is self-describing while it is still pending and the
 * fulfiller only has to flip a status.
 */
class ProductOrderFulfiller
{
    /**
     * Fulfill the order. Returns true if THIS call did the work, false if it was
     * already paid — which is what keeps the confirmation email to exactly one.
     */
    public function fulfill(ProductOrder $order): bool
    {
        $result = DB::transaction(function () use ($order) {
            /** @var ProductOrder|null $o */
            $o = ProductOrder::whereKey($order->id)->lockForUpdate()->first();

            if (! $o || $o->status === ProductOrder::STATUS_PAID) {
                return null; // already done — no work this call
            }

            // One Payment per PaymentIntent, whichever caller gets here first.
            $paymentId = null;
            if ($o->stripe_payment_intent_id) {
                $payment = Payment::firstOrCreate(
                    ['stripe_payment_intent_id' => $o->stripe_payment_intent_id],
                    [
                        'user_id' => $o->user_id,          // null for a guest
                        'product_order_id' => $o->id,
                        'amount_paid' => $o->amount_paid ?? 0,
                    ]
                );
                $paymentId = $payment->id;
                $o->payment_id = $paymentId;
            }

            $o->status = ProductOrder::STATUS_PAID;
            $o->paid_at = now();
            $o->save();

            return ['order_id' => $o->id];
        });

        if ($result === null) {
            return false;
        }

        // Side effects only on the call that did the work, and outside the
        // transaction so a mail failure cannot roll back a completed payment.
        $this->sendConfirmation($order->fresh());

        return true;
    }

    /**
     * Reconcile a succeeded PaymentIntent, from the webhook or the sweep: stamp
     * the order with the intent id and captured amount, then fulfill.
     *
     * $orderId is nullable and falls back to a lookup by intent id, because a
     * Checkout Session's metadata does not reach the PaymentIntent unless it was
     * set through payment_intent_data.
     */
    public function reconcileSucceeded(string $paymentIntentId, ?int $orderId, float $amountReceived): void
    {
        $order = $orderId
            ? ProductOrder::find($orderId)
            : ProductOrder::where('stripe_payment_intent_id', $paymentIntentId)->first();

        if (! $order) {
            return;
        }

        if (! $order->stripe_payment_intent_id) {
            $order->stripe_payment_intent_id = $paymentIntentId;
        }
        if ($order->amount_paid === null) {
            $order->amount_paid = $amountReceived;
        }
        $order->save();

        $this->fulfill($order);
    }

    /**
     * Mark an order failed after payment_intent.payment_failed. A paid order is
     * never downgraded — a later failure event for an intent that did succeed
     * must not undo the fulfillment.
     */
    public function markFailed(string $paymentIntentId, ?int $orderId): void
    {
        $order = $orderId
            ? ProductOrder::find($orderId)
            : ProductOrder::where('stripe_payment_intent_id', $paymentIntentId)->first();

        if (! $order || $order->status !== ProductOrder::STATUS_PENDING) {
            return;
        }

        $order->status = ProductOrder::STATUS_FAILED;
        $order->save();
    }

    private function sendConfirmation(?ProductOrder $order): void
    {
        if (! $order || blank($order->email)) {
            return;
        }

        try {
            Mail::to($order->email)->send(new ProductOrderPlaced($order->load('items')));
        } catch (\Throwable $e) {
            // The money is taken and the order is recorded; a bounced
            // confirmation must not make the request look like a failure.
            Log::error('Store order confirmation failed to send', [
                'product_order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
