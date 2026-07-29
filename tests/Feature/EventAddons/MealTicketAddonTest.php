<?php

use App\EventAddons\AddonRegistrar;
use App\EventAddons\MealTicketAddon;
use App\Models\Event;
use App\Models\EventAddon;
use App\Models\EventRegistration;
use App\Models\EventRegistrationAddon;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Create an event with the meal_ticket add-on and return [event, addon].
 */
function mealEvent(float $price = 10, bool $enabled = true): array
{
    $event = Event::create(['name' => 'Test Event', 'cost' => 0]);

    $addon = EventAddon::create([
        'event_id' => $event->id,
        'type' => 'meal_ticket',
        'enabled' => $enabled,
        'sort_order' => 0,
        'settings' => ['price' => $price, 'label' => 'Meal', 'description' => ''],
    ]);

    $event->load('addons');

    return [$event, $addon];
}

it('exposes the enabled add-on through the Event helpers', function () {
    [$event] = mealEvent();

    expect($event->hasAddon('meal_ticket'))->toBeTrue();
    expect($event->enabledAddons()->has('meal_ticket'))->toBeTrue();
    expect($event->addon('meal_ticket')->label())->toBe('Meal Ticket');
});

it('hides a disabled add-on from the enabled set', function () {
    [$event] = mealEvent(price: 10, enabled: false);

    expect($event->hasAddon('meal_ticket'))->toBeFalse();
    expect($event->enabledAddons()->has('meal_ticket'))->toBeFalse();
});

it('prices meals as (attending + additional) x price across students', function () {
    [$event] = mealEvent(12);
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();

    $request = Request::create('/', 'POST', ['meals' => json_encode([
        $u1->id => ['attending' => true, 'additional' => 2], // 3 meals
        $u2->id => ['attending' => true, 'additional' => 0], // 1 meal
    ])]);

    $result = (new AddonRegistrar())->parse($request, $event, [$u1, $u2]);

    expect($result['total'])->toBe(48.0); // (3 + 1) * 12
});

it('records nothing for a student not attending and buying no extra meals', function () {
    [$event] = mealEvent(12);
    $u = User::factory()->create();

    $request = Request::create('/', 'POST', ['meals' => json_encode([
        $u->id => ['attending' => false, 'additional' => 0],
    ])]);

    $result = (new AddonRegistrar())->parse($request, $event, [$u]);

    expect($result['total'])->toBe(0.0);
    expect($result['perUser'][$u->id])->toBeEmpty();
});

it('charges additional meals even when the registrant is not eating', function () {
    [$event] = mealEvent(10);
    $u = User::factory()->create();

    $request = Request::create('/', 'POST', ['meals' => json_encode([
        $u->id => ['attending' => false, 'additional' => 3],
    ])]);

    $result = (new AddonRegistrar())->parse($request, $event, [$u]);

    expect($result['total'])->toBe(30.0);
    $attrs = $result['perUser'][$u->id][0]['attrs'];
    expect($attrs['selected'])->toBeFalse();
    expect($attrs['quantity'])->toBe(3);
});

it('persists a meal answer and is idempotent on re-submit', function () {
    [$event, $addon] = mealEvent(10);
    $u = User::factory()->create();

    $event->registrations()->attach($u->id, [
        'amount_due' => 0, 'amount_paid' => 0, 'docs_printed' => 0,
    ]);
    $reg = EventRegistration::where('event_id', $event->id)->where('user_id', $u->id)->first();

    $registrar = new AddonRegistrar();
    $registrar->persist($reg, [['addon' => $addon, 'attrs' => ['selected' => true, 'quantity' => 2, 'amount' => 30]]]);
    $registrar->persist($reg, [['addon' => $addon, 'attrs' => ['selected' => true, 'quantity' => 4, 'amount' => 50]]]);

    $rows = EventRegistrationAddon::where('event_registration_id', $reg->id)->get();

    expect($rows)->toHaveCount(1);
    expect((int) $rows->first()->quantity)->toBe(4);
    expect((float) $rows->first()->amount)->toBe(50.0);
    expect($rows->first()->type)->toBe('meal_ticket');
});

it('treats an add-on as open until its deadline passes', function () {
    [$event, $addon] = mealEvent();

    expect($addon->isOpen())->toBeTrue(); // no deadline

    $addon->update(['closes_at' => now()->addDay()]);
    expect($addon->fresh()->isOpen())->toBeTrue();

    $addon->update(['closes_at' => now()->subDay()]);
    expect($addon->fresh()->isOpen())->toBeFalse();

    $addon->update(['closes_at' => null, 'enabled' => false]);
    expect($addon->fresh()->isOpen())->toBeFalse(); // disabled is never open
});

it('does not record or charge a closed add-on even if inputs are present', function () {
    [$event, $addon] = mealEvent(12);
    $addon->update(['closes_at' => now()->subDay()]);
    $event->load('addons');

    $u = User::factory()->create();
    $request = Request::create('/', 'POST', ['meals' => json_encode([
        $u->id => ['attending' => true, 'additional' => 2],
    ])]);

    $result = (new AddonRegistrar())->parse($request, $event, [$u]);

    expect($result['total'])->toBe(0.0);
    expect($result['perUser'][$u->id])->toBeEmpty();
});

it('builds a badge label reflecting attendance and extra meals', function () {
    [$event, $addon] = mealEvent(10);
    $addon->update(['settings' => ['price' => 10, 'label' => 'BBQ Dinner', 'description' => '']]);
    $handler = new MealTicketAddon();

    $attendingWithExtras = new EventRegistrationAddon(['selected' => true, 'quantity' => 2]);
    $attendingWithExtras->setRelation('addon', $addon);
    expect($handler->badgeLabel($attendingWithExtras))->toBe('BBQ Dinner +2');

    $attendingOnly = new EventRegistrationAddon(['selected' => true, 'quantity' => 0]);
    $attendingOnly->setRelation('addon', $addon);
    expect($handler->badgeLabel($attendingOnly))->toBe('BBQ Dinner');

    $none = new EventRegistrationAddon(['selected' => false, 'quantity' => 0]);
    $none->setRelation('addon', $addon);
    expect($handler->badgeLabel($none))->toBeNull();
});

it('describes a meal answer in plain language', function () {
    $handler = new MealTicketAddon();
    $addon = new EventAddon(['type' => 'meal_ticket', 'settings' => ['price' => 12]]);

    expect($handler->summarize($addon, ['selected' => true, 'quantity' => 1]))->toBe('2 meals');
    expect($handler->summarize($addon, ['selected' => true, 'quantity' => 0]))->toBe('1 meal');
    expect($handler->summarize($addon, ['selected' => false, 'quantity' => 0]))->toBe('No meal');
    expect($handler->summarize($addon, null))->toBe('No meal');
});

it('sanitizes meal settings from admin input', function () {
    $handler = new MealTicketAddon();

    expect($handler->sanitizeSettings(['price' => '12.5', 'label' => '', 'description' => '  BBQ ']))
        ->toBe(['price' => 12.5, 'label' => 'Meal', 'description' => 'BBQ']);

    expect($handler->sanitizeSettings(['price' => '-5']))
        ->toBe(['price' => 0.0, 'label' => 'Meal', 'description' => '']);
});
