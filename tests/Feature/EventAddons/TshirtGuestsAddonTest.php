<?php

use App\EventAddons\AddonRegistrar;
use App\EventAddons\GuestsAddon;
use App\EventAddons\TshirtAddon;
use App\Models\Event;
use App\Models\EventAddon;
use App\Models\EventRegistrationAddon;
use App\Models\User;
use Illuminate\Http\Request;

function eventWithAddon(string $type, array $settings = [], array $overrides = []): array
{
    $event = Event::create(['name' => 'Test Event', 'cost' => 0]);

    $addon = EventAddon::create(array_merge([
        'event_id' => $event->id,
        'type' => $type,
        'enabled' => true,
        'sort_order' => 0,
        'settings' => $settings,
    ], $overrides));

    $event->load('addons');

    return [$event, $addon];
}

it('parses a t-shirt size from the register form', function () {
    [$event] = eventWithAddon('tshirt');
    $u = User::factory()->create();

    $request = Request::create('/', 'POST', ['tshirts' => json_encode([$u->id => 'YL'])]);
    $result = (new AddonRegistrar())->parse($request, $event, [$u]);

    $attrs = $result['perUser'][$u->id][0]['attrs'];
    expect($attrs['value'])->toBe('YL');
    expect($attrs['selected'])->toBeTrue();
    expect($result['total'])->toBe(0.0); // no charge
});

it('records no t-shirt answer when no size is chosen', function () {
    [$event] = eventWithAddon('tshirt');
    $u = User::factory()->create();

    $request = Request::create('/', 'POST', ['tshirts' => json_encode([$u->id => ''])]);
    $result = (new AddonRegistrar())->parse($request, $event, [$u]);

    expect($result['perUser'][$u->id])->toBeEmpty();
});

it('shows the t-shirt size as its badge', function () {
    $answer = new EventRegistrationAddon(['value' => 'M']);
    expect((new TshirtAddon())->badgeLabel($answer))->toBe('M');
});

it('clamps guests to the configured maximum', function () {
    [$event] = eventWithAddon('guests', ['max' => 3]);
    $u = User::factory()->create();

    $request = Request::create('/', 'POST', ['guests' => json_encode([$u->id => 10])]);
    $result = (new AddonRegistrar())->parse($request, $event, [$u]);

    expect($result['perUser'][$u->id][0]['attrs']['quantity'])->toBe(3);
});

it('records no guests answer for zero guests or a zero maximum', function () {
    [$eventOpen] = eventWithAddon('guests', ['max' => 3]);
    [$eventClosed] = eventWithAddon('guests', ['max' => 0]);
    $u = User::factory()->create();

    $zero = Request::create('/', 'POST', ['guests' => json_encode([$u->id => 0])]);
    expect((new AddonRegistrar())->parse($zero, $eventOpen, [$u])['perUser'][$u->id])->toBeEmpty();

    $noMax = Request::create('/', 'POST', ['guests' => json_encode([$u->id => 2])]);
    expect((new AddonRegistrar())->parse($noMax, $eventClosed, [$u])['perUser'][$u->id])->toBeEmpty();
});

it('pluralizes the guests badge', function () {
    $handler = new GuestsAddon();
    expect($handler->badgeLabel(new EventRegistrationAddon(['quantity' => 1])))->toBe('1 guest');
    expect($handler->badgeLabel(new EventRegistrationAddon(['quantity' => 3])))->toBe('3 guests');
    expect($handler->badgeLabel(new EventRegistrationAddon(['quantity' => 0])))->toBeNull();
});

it('parses several add-ons from one request', function () {
    $event = Event::create(['name' => 'Combo', 'cost' => 0]);
    EventAddon::create(['event_id' => $event->id, 'type' => 'tshirt', 'enabled' => true, 'sort_order' => 0, 'settings' => []]);
    EventAddon::create(['event_id' => $event->id, 'type' => 'guests', 'enabled' => true, 'sort_order' => 1, 'settings' => ['max' => 4]]);
    EventAddon::create(['event_id' => $event->id, 'type' => 'meal_ticket', 'enabled' => true, 'sort_order' => 2, 'settings' => ['price' => 10]]);
    $event->load('addons');

    $u = User::factory()->create();
    $request = Request::create('/', 'POST', [
        'tshirts' => json_encode([$u->id => 'L']),
        'guests' => json_encode([$u->id => 2]),
        'meals' => json_encode([$u->id => ['attending' => true, 'additional' => 1]]),
    ]);

    $result = (new AddonRegistrar())->parse($request, $event, [$u]);

    expect($result['perUser'][$u->id])->toHaveCount(3);
    expect($result['total'])->toBe(20.0); // 2 meals * 10; tshirt & guests are free
});
