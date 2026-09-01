<?php

namespace App\Http\Controllers;

use App\Models\PendingEventRegistration;
use App\Models\ProductOrder;
use App\Services\RegistrationFulfiller;
use App\Services\Store\ProductOrderFulfiller;
use App\Services\Stripe\StripeAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Webhook;

/**
 * Receives Stripe events. One URL serves every Stripe account the portal
 * transacts on, so the signature is checked against each configured signing
 * secret until one verifies.
 *
 * Only a failed signature check is answered with a 4xx. Anything the portal
 * recognizes but has no work for — another integration's Checkout Session, a
 * payment intent belonging to no pending registration — is acknowledged with a
 * 200. Stripe retries non-2xx responses for days and disables endpoints with
 * sustained failures, so returning an error for an event we will never be able
 * to process would eventually take this endpoint down.
 */
class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');

        $event = null;
        $lastError = 'no Stripe webhook signing secret is configured';

        foreach (app(StripeAccounts::class)->webhookSecrets() as $slug => $secret) {
            try {
                $event = Webhook::constructEvent($payload, $sig_header, $secret);
                break;
            } catch (\Exception $e) {
                // Wrong account for this delivery — try the next secret.
                $lastError = $e->getMessage();
            }
        }

        if (! $event) {
            return response('Webhook error: ' . $lastError, 400);
        }

        if ($event->type === 'checkout.session.completed') {
            // Store guest checkout uses Checkout Sessions. This is a SECONDARY
            // backstop only: the pointer that matters is on the PaymentIntent
            // (set via payment_intent_data.metadata), because a session's own
            // metadata does not reach the intent it creates. Anything without a
            // product_order_id is somebody else's session — log and ignore, so
            // it can't fail this endpoint.
            $session = $event->data->object;
            $orderId = $session->metadata->product_order_id ?? null;

            if ($orderId) {
                $order = ProductOrder::find((int) $orderId);

                if ($order && $order->status === ProductOrder::STATUS_PENDING) {
                    if (! $order->stripe_checkout_session_id) {
                        $order->stripe_checkout_session_id = $session->id;
                    }
                    if (! $order->stripe_payment_intent_id && ($session->payment_intent ?? null)) {
                        $order->stripe_payment_intent_id = $session->payment_intent;
                    }
                    $order->save();

                    // Only a paid session fulfills; an unpaid one just records ids.
                    if (($session->payment_status ?? null) === 'paid') {
                        app(ProductOrderFulfiller::class)->reconcileSucceeded(
                            (string) ($order->stripe_payment_intent_id ?? $session->id),
                            $order->id,
                            ($session->amount_total ?? 0) / 100
                        );
                    }
                }
            } else {
                Log::info('Stripe webhook: ignoring checkout session (no handler)', [
                    'session_id' => $session->id ?? null,
                    'trans_type' => $session->metadata->trans_type ?? null,
                ]);
            }
        } elseif ($event->type === 'payment_intent.succeeded') {
            // Backstop for both flows. Each tolerates a null pointer and falls
            // back to a lookup by intent id, and each is idempotent, so running
            // both for every intent is safe — at most one will find a row.
            $intent = $event->data->object;
            $amount = ($intent->amount_received ?? 0) / 100;

            $pendingId = $intent->metadata->pending_registration_id ?? null;
            (new RegistrationFulfiller())->reconcileSucceeded(
                $intent->id,
                $pendingId ? (int) $pendingId : null,
                $amount
            );

            $orderId = $intent->metadata->product_order_id ?? null;
            app(ProductOrderFulfiller::class)->reconcileSucceeded(
                $intent->id,
                $orderId ? (int) $orderId : null,
                $amount
            );
        } elseif ($event->type === 'payment_intent.payment_failed') {
            $intent = $event->data->object;
            $pendingId = $intent->metadata->pending_registration_id ?? null;

            $pending = $pendingId
                ? PendingEventRegistration::find($pendingId)
                : PendingEventRegistration::where('stripe_payment_intent_id', $intent->id)->first();

            if ($pending && $pending->status === PendingEventRegistration::STATUS_PENDING) {
                $pending->update(['status' => PendingEventRegistration::STATUS_FAILED]);
            }

            $orderId = $intent->metadata->product_order_id ?? null;
            app(ProductOrderFulfiller::class)->markFailed(
                $intent->id,
                $orderId ? (int) $orderId : null
            );
        }

        return response('Webhook handled', 200);
    }
}
