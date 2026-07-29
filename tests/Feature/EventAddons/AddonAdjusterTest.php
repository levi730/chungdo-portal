<?php

use App\EventAddons\AddonAdjuster;
use App\Models\Event;
use App\Models\EventAddon;
use App\Models\EventRegistration;
use App\Models\EventRegistrationAddon;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * A registration for one user with a current meal (1 @ $12) and a t-shirt (L).
 *
 * @return array{0: Event, 1: EventRegistration, 2: EventAddon, 3: EventAddon, 4: User}
 */
function registrationWithAddons(): array
{
    $event = Event::create(['name' => 'Adjust Test', 'cost' => 65]);
    $meal = EventAddon::create(['event_id' => $event->id, 'type' => 'meal_ticket', 'enabled' => true, 'sort_order' => 0, 'settings' => ['price' => 12]]);
    $tshirt = EventAddon::create(['event_id' => $event->id, 'type' => 'tshirt', 'enabled' => true, 'sort_order' => 1, 'settings' => []]);
    $event->load('addons');

    $user = User::factory()->create();
    $event->registrations()->attach($user->id, ['amount_due' => 65, 'amount_paid' => 77, 'docs_printed' => 0]);
    $reg = EventRegistration::where('event_id', $event->id)->where('user_id', $user->id)->first();

    EventRegistrationAddon::create(['event_registration_id' => $reg->id, 'event_addon_id' => $meal->id, 'type' => 'meal_ticket', 'selected' => true, 'quantity' => 0, 'amount' => 12]);
    EventRegistrationAddon::create(['event_registration_id' => $reg->id, 'event_addon_id' => $tshirt->id, 'type' => 'tshirt', 'selected' => true, 'value' => 'L']);

    $reg->load(['addonAnswers', 'event', 'user']);

    return [$event, $reg, $meal, $tshirt, $user];
}

function editRequest(array $overrides): Request
{
    return Request::create('/', 'POST', $overrides);
}

it('detects a net charge when a meal is added', function () {
    [, $reg, , , $user] = registrationWithAddons();

    $request = editRequest([
        'meals' => json_encode([$user->id => ['attending' => true, 'additional' => 1]]), // 2 meals = 24
        'tshirts' => json_encode([$user->id => 'L']),
    ]);

    $plan = (new AddonAdjuster())->planChange($request, $reg);

    expect($plan['net'])->toBe(12.0); // 24 - 12
});

it('detects a net refund when the meal is removed', function () {
    [, $reg, , , $user] = registrationWithAddons();

    $request = editRequest([
        'meals' => json_encode([$user->id => ['attending' => false, 'additional' => 0]]),
        'tshirts' => json_encode([$user->id => 'L']),
    ]);

    $plan = (new AddonAdjuster())->planChange($request, $reg);

    expect($plan['net'])->toBe(-12.0);
});

it('detects a net-zero change when only the t-shirt size changes', function () {
    [, $reg, , , $user] = registrationWithAddons();

    $request = editRequest([
        'meals' => json_encode([$user->id => ['attending' => true, 'additional' => 0]]), // unchanged 1 meal
        'tshirts' => json_encode([$user->id => 'M']),
    ]);

    $plan = (new AddonAdjuster())->planChange($request, $reg);

    expect($plan['net'])->toBe(0.0);
});

it('applies an added meal and links it to the payment', function () {
    [, $reg, $meal, , $user] = registrationWithAddons();

    $request = editRequest(['meals' => json_encode([$user->id => ['attending' => true, 'additional' => 1]]), 'tshirts' => json_encode([$user->id => 'L'])]);
    $adjuster = new AddonAdjuster();
    $plan = $adjuster->planChange($request, $reg);
    $adjuster->apply($reg, $plan['changes'], paymentId: 999);

    $answer = EventRegistrationAddon::where('event_registration_id', $reg->id)->where('event_addon_id', $meal->id)->first();
    expect((float) $answer->amount)->toBe(24.0);
    expect((int) $answer->quantity)->toBe(1);
    expect($answer->payment_id)->toBe(999);
});

it('deletes the answer when an add-on is removed', function () {
    [, $reg, $meal, , $user] = registrationWithAddons();

    $request = editRequest(['meals' => json_encode([$user->id => ['attending' => false, 'additional' => 0]]), 'tshirts' => json_encode([$user->id => 'L'])]);
    $adjuster = new AddonAdjuster();
    $plan = $adjuster->planChange($request, $reg);
    $adjuster->apply($reg, $plan['changes']);

    expect(EventRegistrationAddon::where('event_registration_id', $reg->id)->where('event_addon_id', $meal->id)->exists())->toBeFalse();
});

it('changes a t-shirt size in place with no payment link', function () {
    [, $reg, , $tshirt, $user] = registrationWithAddons();

    $request = editRequest(['meals' => json_encode([$user->id => ['attending' => true, 'additional' => 0]]), 'tshirts' => json_encode([$user->id => 'M'])]);
    $adjuster = new AddonAdjuster();
    $plan = $adjuster->planChange($request, $reg);
    $adjuster->apply($reg, $plan['changes']);

    $answer = EventRegistrationAddon::where('event_registration_id', $reg->id)->where('event_addon_id', $tshirt->id)->first();
    expect($answer->value)->toBe('M');
    expect($answer->payment_id)->toBeNull();
});

it('does not allow adjusting a closed add-on', function () {
    [$event, $reg, $meal, , $user] = registrationWithAddons();
    $meal->update(['closes_at' => now()->subDay()]);
    $reg->load('event');

    $request = editRequest(['meals' => json_encode([$user->id => ['attending' => true, 'additional' => 3]]), 'tshirts' => json_encode([$user->id => 'L'])]);
    $plan = (new AddonAdjuster())->planChange($request, $reg);

    // Meal is closed, so it isn't part of the plan and the net stays 0.
    expect($plan['net'])->toBe(0.0);
    expect(collect($plan['changes'])->pluck('addon.type')->contains('meal_ticket'))->toBeFalse();
});
