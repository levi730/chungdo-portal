<?php

namespace App\Services;

use App\EventAddons\AddonAdjuster;
use App\Mail\RefundRequested;
use App\Models\AddonChangeRequest;
use App\Models\EventRegistration;
use App\Models\Payment;
use App\Models\User;
use App\Services\Stripe\StripeAccounts;
use App\Services\Stripe\StripeCustomerResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Handles a registered student's post-registration add-on change. Routes on the
 * net cost:
 *   net == 0  -> apply immediately (e.g. a t-shirt size swap)
 *   net <  0  -> create a refund AddonChangeRequest (held for admin approval)
 *   net >  0  -> charge the difference (enter card each time); apply on success
 */
class AddonAdjustmentService
{
    private AddonAdjuster $adjuster;

    public function __construct()
    {
        $this->adjuster = new AddonAdjuster();
    }

    /**
     * @return array{status: string, message?: string, amount?: float, client_secret?: string}
     */
    public function submit(Request $request, EventRegistration $registration, User $payer): array
    {
        $registration->loadMissing(['addonAnswers', 'event', 'user']);

        // Any earlier pending refund request for this registration is now stale
        // (it was computed against a state we're about to change). Supersede it so
        // it can't be approved and double-refund.
        AddonChangeRequest::where('event_registration_id', $registration->id)
            ->where('status', AddonChangeRequest::STATUS_PENDING)
            ->update(['status' => AddonChangeRequest::STATUS_SUPERSEDED]);

        $plan = $this->adjuster->planChange($request, $registration);
        $net = $plan['net'];

        if (abs($net) < 0.005) {
            $this->adjuster->apply($registration, $plan['changes']);

            return ['status' => 'applied', 'message' => 'Your add-ons were updated.'];
        }

        if ($net < 0) {
            $this->createRefundRequest($registration, $plan['changes'], $payer, -$net);

            return ['status' => 'refund_requested', 'message' => 'Your change was submitted for approval. Any refund will be issued once an admin approves it.'];
        }

        return $this->charge($request, $registration, $plan['changes'], $payer, $net);
    }

    private function createRefundRequest(EventRegistration $registration, array $changes, User $payer, float $refund): AddonChangeRequest
    {
        $request = AddonChangeRequest::create([
            'event_id' => $registration->event_id,
            'event_registration_id' => $registration->id,
            'requested_by_user_id' => $payer->id,
            'new_state' => $this->adjuster->serialize($changes),
            'refund_amount' => round($refund, 2),
            'stripe_payment_intent_id' => $this->refundPaymentIntent($registration, $changes),
            'status' => AddonChangeRequest::STATUS_PENDING,
        ]);

        $this->notifyRefundRequested($request);

        return $request;
    }

    /** Best-effort admin notification; never let a mail failure break the request. */
    private function notifyRefundRequested(AddonChangeRequest $request): void
    {
        $recipients = config('events.refund_notification_emails', []);
        if (empty($recipients)) {
            return;
        }

        try {
            Mail::to($recipients)->send(new RefundRequested($request));
        } catch (\Throwable $e) {
            Log::warning('Refund request notification failed: '.$e->getMessage());
        }
    }

    private function charge(Request $request, EventRegistration $registration, array $changes, User $payer, float $amount): array
    {
        $paymentMethod = $request->input('payment_method');
        if (! $paymentMethod) {
            // Client must collect a card and resubmit with a payment_method.
            return ['status' => 'payment_required', 'amount' => round($amount, 2)];
        }

        $changeRequest = AddonChangeRequest::create([
            'event_id' => $registration->event_id,
            'event_registration_id' => $registration->id,
            'requested_by_user_id' => $payer->id,
            'new_state' => $this->adjuster->serialize($changes),
            'refund_amount' => 0,
            'status' => AddonChangeRequest::STATUS_AWAITING_PAYMENT,
        ]);

        try {
            // Adjustments are charged on the same Stripe account as the event's
            // original registration payments.
            $stripeAccount = app(StripeAccounts::class)->forEvent($registration->event);
            \Stripe\Stripe::setApiKey(app(StripeAccounts::class)->secret($stripeAccount));

            $customerId = app(StripeCustomerResolver::class)
                ->resolve($payer, $stripeAccount, $paymentMethod);

            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => (int) round($amount * 100),
                'currency' => 'usd',
                'customer' => $customerId,
                'payment_method' => $paymentMethod,
                'payment_method_types' => ['card'],
                'confirm' => true,
                'metadata' => ['addon_change_request_id' => $changeRequest->id],
            ]);

            $changeRequest->update(['stripe_payment_intent_id' => $paymentIntent->id]);

            if ($paymentIntent->status === 'succeeded') {
                $this->applyCharge($changeRequest, ($paymentIntent->amount_received ?? 0) / 100);

                return ['status' => 'charged', 'message' => 'Your add-ons were updated and your card was charged.'];
            }

            if (in_array($paymentIntent->status, ['requires_action', 'requires_source_action'])) {
                return ['status' => 'requires_action', 'client_secret' => $paymentIntent->client_secret];
            }

            return ['status' => 'error', 'message' => 'Payment could not be completed ('.$paymentIntent->status.').'];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /** Apply a paid change exactly once (shared by the sync path and finalize). */
    public function applyCharge(AddonChangeRequest $request, float $amountPaid): void
    {
        DB::transaction(function () use ($request, $amountPaid) {
            $r = AddonChangeRequest::whereKey($request->id)->lockForUpdate()->first();
            if (! $r || $r->status === AddonChangeRequest::STATUS_APPLIED) {
                return;
            }

            $payment = Payment::firstOrCreate(
                ['stripe_payment_intent_id' => $r->stripe_payment_intent_id],
                ['user_id' => $r->requested_by_user_id, 'amount_paid' => $amountPaid]
            );

            $this->adjuster->applySerialized($r->registration, $r->new_state, $payment->id);

            $r->update(['status' => AddonChangeRequest::STATUS_APPLIED, 'decided_at' => now()]);
        });
    }

    /** Complete a charge after in-browser 3-D Secure (called from finalize). */
    public function finalizeCharge(string $paymentIntentId): bool
    {
        $request = AddonChangeRequest::where('stripe_payment_intent_id', $paymentIntentId)
            ->where('status', AddonChangeRequest::STATUS_AWAITING_PAYMENT)
            ->first();

        if (! $request) {
            return false;
        }

        \Stripe\Stripe::setApiKey(
            app(StripeAccounts::class)->secretForEvent($request->registration?->event)
        );
        $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);
        if ($paymentIntent->status !== 'succeeded') {
            return false;
        }

        $this->applyCharge($request, ($paymentIntent->amount_received ?? 0) / 100);

        return true;
    }

    /** The PaymentIntent to refund a reduction against. */
    private function refundPaymentIntent(EventRegistration $registration, array $changes): ?string
    {
        foreach ($changes as $change) {
            if (($change['currentAmount'] ?? 0) <= 0) {
                continue;
            }
            $answer = $registration->addonAnswers->firstWhere('event_addon_id', $change['addon']->id);
            if ($answer && $answer->payment_id && ($payment = Payment::find($answer->payment_id))) {
                return $payment->stripe_payment_intent_id;
            }
        }

        return optional(Payment::find($registration->payment_id))->stripe_payment_intent_id;
    }
}
