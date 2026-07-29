<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessProjectUnitedPayment;
use App\Models\PendingEventRegistration;
use App\Services\RegistrationFulfiller;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Webhook;

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
            $session = $event->data->object;

            switch($session->metadata->trans_type) {
                case "project_united_donation":
                case "project_united_tshirt":
                case "project_united_hoodie":
                case "project_united_2026_hoodie":
                case "project_united_2026_tshirt":
                    dispatch(new ProcessProjectUnitedPayment($session));
                    break;
                default:
                    return response('Webhook error: ' . "Invalid Transaction Type", 400);
            }
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
