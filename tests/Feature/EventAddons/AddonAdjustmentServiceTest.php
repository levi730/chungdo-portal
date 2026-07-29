<?php

use App\Models\AddonChangeRequest;
use App\Models\Event;
use App\Models\EventAddon;
use App\Models\EventRegistration;
use App\Models\EventRegistrationAddon;
use App\Models\Payment;
use App\Models\User;
use App\Services\AddonAdjustmentService;
use Illuminate\Http\Request;

/**
 * A registration with a current meal ($12, paid on 'pi_orig') and a t-shirt (L).
 *
 * @return array{0: EventRegistration, 1: EventAddon, 2: EventAddon, 3: User}
 */
function adjustmentFixture(): array
{
    $event = Event::create(['name' => 'Adjust Svc', 'cost' => 65]);
    $meal = EventAddon::create(['event_id' => $event->id, 'type' => 'meal_ticket', 'enabled' => true, 'sort_order' => 0, 'settings' => ['price' => 12]]);
    $tshirt = EventAddon::create(['event_id' => $event->id, 'type' => 'tshirt', 'enabled' => true, 'sort_order' => 1, 'settings' => []]);
    $event->load('addons');

    $user = User::factory()->create();
    $payment = Payment::create(['user_id' => $user->id, 'amount_paid' => 77, 'stripe_payment_intent_id' => 'pi_orig']);
    $event->registrations()->attach($user->id, ['amount_due' => 65, 'amount_paid' => 77, 'docs_printed' => 0, 'payment_id' => $payment->id]);
    $reg = EventRegistration::where('event_id', $event->id)->where('user_id', $user->id)->first();

    EventRegistrationAddon::create(['event_registration_id' => $reg->id, 'event_addon_id' => $meal->id, 'type' => 'meal_ticket', 'selected' => true, 'quantity' => 0, 'amount' => 12, 'payment_id' => $payment->id]);
    EventRegistrationAddon::create(['event_registration_id' => $reg->id, 'event_addon_id' => $tshirt->id, 'type' => 'tshirt', 'selected' => true, 'value' => 'L']);

    $reg->load(['addonAnswers', 'event', 'user']);

    return [$reg, $meal, $tshirt, $user];
}

function adjustReq(User $user, array $extra = []): Request
{
    return Request::create('/', 'POST', array_merge([
        'user_id' => $user->id,
        'meals' => json_encode([$user->id => ['attending' => true, 'additional' => 0]]),
        'tshirts' => json_encode([$user->id => 'L']),
    ], $extra));
}

it('applies a net-zero change immediately (t-shirt size swap)', function () {
    [$reg, , $tshirt, $user] = adjustmentFixture();
    $request = adjustReq($user, ['tshirts' => json_encode([$user->id => 'M'])]);

    $result = (new AddonAdjustmentService())->submit($request, $reg, $user);

    expect($result['status'])->toBe('applied');
    expect(EventRegistrationAddon::where('event_registration_id', $reg->id)->where('event_addon_id', $tshirt->id)->first()->value)->toBe('M');
});

it('creates a refund request for a reduction, targeting the original charge', function () {
    [$reg, , , $user] = adjustmentFixture();
    // Drop the meal (12 -> 0): net -12.
    $request = adjustReq($user, ['meals' => json_encode([$user->id => ['attending' => false, 'additional' => 0]])]);

    $result = (new AddonAdjustmentService())->submit($request, $reg, $user);

    expect($result['status'])->toBe('refund_requested');
    $req = AddonChangeRequest::where('event_registration_id', $reg->id)->first();
    expect($req->status)->toBe('pending');
    expect((float) $req->refund_amount)->toBe(12.0);
    expect($req->stripe_payment_intent_id)->toBe('pi_orig'); // refund the original charge
    // Nothing changed on the registration yet (held for approval).
    expect((float) EventRegistrationAddon::where('event_registration_id', $reg->id)->where('type', 'meal_ticket')->first()->amount)->toBe(12.0);
});

it('emails the configured recipients when a refund is requested', function () {
    \Illuminate\Support\Facades\Mail::fake();
    config(['events.refund_notification_emails' => ['admin@example.com']]);
    [$reg, , , $user] = adjustmentFixture();

    $request = adjustReq($user, ['meals' => json_encode([$user->id => ['attending' => false, 'additional' => 0]])]);
    (new AddonAdjustmentService())->submit($request, $reg, $user);

    \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\RefundRequested::class, fn ($mail) => $mail->hasTo('admin@example.com'));
});

it('asks for a card when the change is a net charge', function () {
    [$reg, , , $user] = adjustmentFixture();
    // Add a meal (12 -> 24): net +12, no payment_method provided.
    $request = adjustReq($user, ['meals' => json_encode([$user->id => ['attending' => true, 'additional' => 1]])]);

    $result = (new AddonAdjustmentService())->submit($request, $reg, $user);

    expect($result['status'])->toBe('payment_required');
    expect($result['amount'])->toBe(12.0);
});

it('supersedes an earlier pending refund request when a newer change comes in', function () {
    [$reg, , , $user] = adjustmentFixture();
    $service = new AddonAdjustmentService();

    // First: drop the meal -> a pending refund request.
    $service->submit(adjustReq($user, ['meals' => json_encode([$user->id => ['attending' => false, 'additional' => 0]])]), $reg, $user);
    $first = AddonChangeRequest::where('event_registration_id', $reg->id)->latest('id')->first();
    expect($first->status)->toBe('pending');

    // A newer change makes the first one stale.
    $reg->refresh()->load(['addonAnswers', 'event', 'user']);
    $service->submit(adjustReq($user, ['tshirts' => json_encode([$user->id => 'M'])]), $reg, $user);

    expect($first->fresh()->status)->toBe('superseded');
});

it('applies a paid charge once and records the payment', function () {
    [$reg, $meal, , $user] = adjustmentFixture();
    $change = AddonChangeRequest::create([
        'event_id' => $reg->event_id,
        'event_registration_id' => $reg->id,
        'requested_by_user_id' => $user->id,
        'new_state' => [['event_addon_id' => $meal->id, 'type' => 'meal_ticket', 'attrs' => ['selected' => true, 'quantity' => 1, 'amount' => 24]]],
        'refund_amount' => 0,
        'stripe_payment_intent_id' => 'pi_topup',
        'status' => 'awaiting_payment',
    ]);

    $service = new AddonAdjustmentService();
    $service->applyCharge($change->fresh(), 12.0);
    $service->applyCharge($change->fresh(), 12.0); // idempotent

    $answer = EventRegistrationAddon::where('event_registration_id', $reg->id)->where('event_addon_id', $meal->id)->first();
    expect((float) $answer->amount)->toBe(24.0);
    expect(Payment::where('stripe_payment_intent_id', 'pi_topup')->count())->toBe(1);
    expect($answer->payment_id)->toBe(Payment::where('stripe_payment_intent_id', 'pi_topup')->first()->id);
    expect($change->fresh()->status)->toBe('applied');
});
