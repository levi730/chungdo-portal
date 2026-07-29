<?php

use App\EventAddons\AddonRegistrar;
use App\EventAddons\DonationAddon;
use App\EventAddons\RegistrationFeeAddon;
use App\Models\Event;
use App\Models\EventAddon;
use App\Models\EventRegistrationAddon;
use App\Models\User;
use Illuminate\Http\Request;

function feeAddon(float $cost, array $discounts = []): EventAddon
{
    return new EventAddon([
        'type' => 'registration_fee',
        'enabled' => true,
        'settings' => ['cost' => $cost, 'cost_type' => 'per person', 'discounts' => $discounts],
    ]);
}

it('applies household discounts by registrant position', function () {
    $handler = new RegistrationFeeAddon();
    $addon = feeAddon(50, ['2' => 10, '3' => 15, '4' => 20, '5' => 25]);

    expect($handler->amountForPosition($addon, 0))->toBe(50.0); // 1st: full
    expect($handler->amountForPosition($addon, 1))->toBe(40.0); // 2nd: -10
    expect($handler->amountForPosition($addon, 2))->toBe(35.0); // 3rd: -15
    expect($handler->amountForPosition($addon, 5))->toBe(25.0); // 6th: uses 5th tier (-25)
});

it('records no fee for a free event', function () {
    expect((new RegistrationFeeAddon())->parseAnswer(Request::create('/'), feeAddon(0), new User(), 0))
        ->toBeNull();
});

it('records a donation once for the whole group', function () {
    $event = Event::create(['name' => 'D', 'cost' => 0]);
    EventAddon::create(['event_id' => $event->id, 'type' => 'donation', 'enabled' => true, 'sort_order' => 0, 'settings' => []]);
    $event->load('addons');

    $u1 = User::factory()->create();
    $u2 = User::factory()->create();
    $request = Request::create('/', 'POST', ['donation_amount' => '25']);

    $result = (new AddonRegistrar())->parse($request, $event, [$u1, $u2]);

    expect($result['total'])->toBe(25.0);
    expect($result['perUser'][$u1->id][0]['attrs']['amount'])->toBe(25.0);
    expect($result['perUser'][$u2->id])->toBeEmpty(); // group scope: first only
});

it('shows the donation as a badge', function () {
    $answer = new EventRegistrationAddon(['amount' => 25]);
    expect((new DonationAddon())->badgeLabel($answer))->toBe('Donation: $25.00');
});

it('sums fee, meal and donation once each with no double charge', function () {
    $event = Event::create(['name' => 'Combo', 'cost' => 0]);
    EventAddon::create(['event_id' => $event->id, 'type' => 'registration_fee', 'enabled' => true, 'sort_order' => 0,
        'settings' => ['cost' => 50, 'cost_type' => 'per person', 'discounts' => ['2' => 10, '3' => 0, '4' => 0, '5' => 0]]]);
    EventAddon::create(['event_id' => $event->id, 'type' => 'meal_ticket', 'enabled' => true, 'sort_order' => 1, 'settings' => ['price' => 10]]);
    EventAddon::create(['event_id' => $event->id, 'type' => 'donation', 'enabled' => true, 'sort_order' => 2, 'settings' => []]);
    $event->load('addons');

    $u1 = User::factory()->create();
    $u2 = User::factory()->create();
    $request = Request::create('/', 'POST', [
        'donation_amount' => '25',
        'meals' => json_encode([
            $u1->id => ['attending' => true, 'additional' => 0], // 1 meal = 10
            $u2->id => ['attending' => true, 'additional' => 1], // 2 meals = 20
        ]),
    ]);

    $result = (new AddonRegistrar())->parse($request, $event, [$u1, $u2]);

    // fees 50 + 40, meals 10 + 20, donation 25 = 145
    expect($result['total'])->toBe(145.0);
});
