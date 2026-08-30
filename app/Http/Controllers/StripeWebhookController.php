<?php

namespace App\Http\Controllers;

use App\Models\PendingEventRegistration;
use App\Services\RegistrationFulfiller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Webhook;

/**
 * Receives Stripe events for this account.
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
        $secret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sig_header, $secret);
        } catch (\Exception $e) {
            return response('Webhook error: ' . $e->getMessage(), 400);
        }

        if ($event->type === 'checkout.session.completed') {
            // Nothing in the portal creates Checkout Sessions since Project
            // United was retired (docs/project-united-retirement.md); event
            // registration uses PaymentIntents. Acknowledge and ignore, so a
            // session from anywhere else can't fail this endpoint.
            $session = $event->data->object;

            Log::info('Stripe webhook: ignoring checkout session (no handler)', [
                'session_id' => $session->id ?? null,
                'trans_type' => $session->metadata->trans_type ?? null,
            ]);
        } elseif ($event->type === 'payment_intent.succeeded') {
            // Backstop for event registrations: complete the pending registration
            // if the synchronous response never finished it. Idempotent.
            $intent = $event->data->object;
            $pendingId = $intent->metadata->pending_registration_id ?? null;

            (new RegistrationFulfiller())->reconcileSucceeded(
                $intent->id,
                $pendingId ? (int) $pendingId : null,
                ($intent->amount_received ?? 0) / 100
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
        }

        return response('Webhook handled', 200);
    }
}
