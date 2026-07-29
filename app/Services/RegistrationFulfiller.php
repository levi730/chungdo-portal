<?php

namespace App\Services;

use App\Mail\EventRegistered;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventRegistrationAddon;
use App\Models\Payment;
use App\Models\PendingEventRegistration;
use App\Models\PotluckOptions;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use QR_Code\QR_Code;
use Spatie\TemporaryDirectory\TemporaryDirectory;

/**
 * Completes a PendingEventRegistration exactly once, whether triggered by the
 * synchronous payment response or the payment_intent.succeeded webhook. The
 * DB work is guarded by a row lock + status check, and per-user attach is
 * skipped if the registration already exists, so concurrent callers converge on
 * a single fulfillment. The confirmation email + QR pass are sent only by the
 * caller that actually did the work.
 */
class RegistrationFulfiller
{
    /**
     * Fulfill the pending registration. Returns true if this call performed the
     * fulfillment, false if it was already fulfilled (a no-op).
     */
    public function fulfill(PendingEventRegistration $pending): bool
    {
        $result = DB::transaction(function () use ($pending) {
            /** @var PendingEventRegistration|null $p */
            $p = PendingEventRegistration::whereKey($pending->id)->lockForUpdate()->first();

            if (! $p || $p->status === PendingEventRegistration::STATUS_FULFILLED) {
                return null; // already done — no work this call
            }

            $payload = $p->payload;
            $event = Event::findOrFail($payload['event_id']);

            // One Payment per PaymentIntent (idempotent on re-run / webhook race).
            $paymentId = null;
            if ($p->stripe_payment_intent_id) {
                $payment = Payment::firstOrCreate(
                    ['stripe_payment_intent_id' => $p->stripe_payment_intent_id],
                    ['user_id' => $p->registering_user_id, 'amount_paid' => $p->amount_paid ?? 0]
                );
                $paymentId = $payment->id;
                $p->payment_id = $paymentId;
            }

            $amountPaid = (float) ($p->amount_paid ?? 0);
            $regUserIds = [];
            $potluckRecorded = false;

            foreach ($payload['users'] as $i => $up) {
                $u = User::find($up['user_id']);
                if (! $u || $u->events()->find($event->id)) {
                    continue; // missing user, or already registered (idempotent)
                }

                // Add-on answers (t-shirt, meal, donation, potluck, ...) are stored
                // in event_registration_addons below — not on the pivot.
                $event->registrations()->attach([$u->id => [
                    'amount_due' => $payload['amount_due_each'],
                    'amount_paid' => $amountPaid,
                    'payment_id' => $paymentId,
                ]]);

                $reg = EventRegistration::where('event_id', $event->id)
                    ->where('user_id', $u->id)
                    ->orderByDesc('id')
                    ->first();

                foreach (($up['addons'] ?? []) as $a) {
                    $attrs = $a['attrs'] ?? [];
                    EventRegistrationAddon::updateOrCreate(
                        ['event_registration_id' => $reg->id, 'event_addon_id' => $a['event_addon_id']],
                        [
                            'type' => $a['type'],
                            'selected' => $attrs['selected'] ?? false,
                            'quantity' => $attrs['quantity'] ?? null,
                            'value' => $attrs['value'] ?? null,
                            'amount' => $attrs['amount'] ?? null,
                            // Only paid add-ons carry a payment link (for refunds).
                            'payment_id' => ($attrs['amount'] ?? 0) > 0 ? $paymentId : null,
                            'data' => $attrs['data'] ?? null,
                        ]
                    );
                }

                $potluckId = $payload['group']['potluck_item_id'] ?? null;
                if ($potluckId && ! $potluckRecorded) {
                    optional(PotluckOptions::find($potluckId))->increment('current_count');
                    $potluckRecorded = true;
                }

                $regUserIds[] = $u->id;
            }

            $p->status = PendingEventRegistration::STATUS_FULFILLED;
            $p->fulfilled_at = now();
            $p->save();

            return ['event_id' => $event->id, 'reg_user_ids' => $regUserIds];
        });

        if ($result === null) {
            return false;
        }

        $this->sendConfirmation($pending, $result['event_id'], $result['reg_user_ids']);

        return true;
    }

    /**
     * Reconcile a succeeded PaymentIntent (called from the webhook): stamp the
     * pending record with the intent id + captured amount, then fulfill.
     */
    public function reconcileSucceeded(string $paymentIntentId, ?int $pendingId, float $amountReceived): void
    {
        $pending = $pendingId
            ? PendingEventRegistration::find($pendingId)
            : PendingEventRegistration::where('stripe_payment_intent_id', $paymentIntentId)->first();

        if (! $pending) {
            return;
        }

        if (! $pending->stripe_payment_intent_id) {
            $pending->stripe_payment_intent_id = $paymentIntentId;
        }
        if ($pending->amount_paid === null) {
            $pending->amount_paid = $amountReceived;
        }
        $pending->save();

        $this->fulfill($pending);
    }

    private function sendConfirmation(PendingEventRegistration $pending, int $eventId, array $regUserIds): void
    {
        if (empty($regUserIds)) {
            return;
        }

        $event = Event::find($eventId);
        $regUsers = User::findMany($regUserIds)->all();

        $tmpFile = null;
        if ($event->require_ticket) {
            $regIds = EventRegistration::where('event_id', $event->id)
                ->whereIn('user_id', $regUserIds)
                ->pluck('event_registrations.id');
            $dir = (new TemporaryDirectory())->create();
            $tmpFile = $dir->path('qrcode.png');
            QR_Code::png(json_encode(['registrants' => $regIds]), $tmpFile);
        }

        $recipient = User::find($pending->registering_user_id);
        if ($recipient) {
            Mail::to($recipient)->send(new EventRegistered($event, $regUsers, $tmpFile));
        }
    }
}
