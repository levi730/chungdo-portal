<?php

use App\EventAddons\AddonRegistrar;
use App\EventAddons\PotluckAddon;
use App\EventAddons\VolunteerAddon;
use App\Models\Event;
use App\Models\EventAddon;
use App\Models\EventRegistrationAddon;
use App\Models\PotluckOptions;
use App\Models\User;
use Illuminate\Http\Request;

function addonEvent(string $type, array $settings): array
{
    $event = Event::create(['name' => 'Test Event', 'cost' => 0]);
    $addon = EventAddon::create([
        'event_id' => $event->id, 'type' => $type, 'enabled' => true,
        'sort_order' => 0, 'settings' => $settings,
    ]);
    $event->load('addons');

    return [$event, $addon];
}

/** Encode the double-JSON volunteer_selections payload the form posts. */
function volunteerPayload(array $picks): string
{
    return json_encode(array_map(fn ($p) => json_encode($p), $picks));
}

it('collects a students volunteer roles', function () {
    [$event] = addonEvent('volunteer', ['options' => ['Scoring', 'Concessions']]);
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();

    $request = Request::create('/', 'POST', ['volunteer_selections' => volunteerPayload([
        ['user_id' => $u1->id, 'item' => 'Scoring'],
        ['user_id' => $u1->id, 'item' => 'Concessions'],
        ['user_id' => $u2->id, 'item' => 'Scoring'],
    ])]);

    $result = (new AddonRegistrar())->parse($request, $event, [$u1, $u2]);

    expect($result['perUser'][$u1->id][0]['attrs']['data'])->toBe(['Scoring', 'Concessions']);
    expect($result['perUser'][$u2->id][0]['attrs']['data'])->toBe(['Scoring']);
});

it('renders the volunteer roles as a badge', function () {
    $answer = new EventRegistrationAddon(['data' => ['Scoring', 'Concessions']]);
    expect((new VolunteerAddon())->badgeLabel($answer))->toBe('Volunteer: Scoring, Concessions');
});

it('sanitizes volunteer roles from a newline list', function () {
    expect((new VolunteerAddon())->sanitizeSettings(['options' => "Scoring\n  Concessions \n\nSetup"]))
        ->toBe(['options' => ['Scoring', 'Concessions', 'Setup']]);
});

it('records an open-signup potluck item only once for the group', function () {
    [$event] = addonEvent('potluck', ['open_signup' => true]);
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();

    $request = Request::create('/', 'POST', ['potluck_open_item' => 'Potato Salad']);
    $result = (new AddonRegistrar())->parse($request, $event, [$u1, $u2]);

    // Group scope: recorded against the first registrant only.
    expect($result['perUser'][$u1->id][0]['attrs']['value'])->toBe('Potato Salad');
    expect($result['perUser'][$u2->id])->toBeEmpty();
});

it('resolves a catalog potluck item to its label', function () {
    [$event, $addon] = addonEvent('potluck', ['open_signup' => false]);
    $option = PotluckOptions::forceCreate([
        'event_id' => $event->id, 'category' => 'Sides', 'item' => 'Chips', 'limit' => '10', 'current_count' => 0,
    ]);
    $u = User::factory()->create();

    $request = Request::create('/', 'POST', ['potluck_item_id' => $option->id]);
    $result = (new AddonRegistrar())->parse($request, $event, [$u]);

    $attrs = $result['perUser'][$u->id][0]['attrs'];
    expect($attrs['value'])->toBe('Sides - Chips');
    expect($attrs['data']['potluck_item_id'])->toBe($option->id);
});

it('renders the potluck choice as a badge', function () {
    $answer = new EventRegistrationAddon(['value' => 'Sides - Chips']);
    expect((new PotluckAddon())->badgeLabel($answer))->toBe('Potluck: Sides - Chips');
});
