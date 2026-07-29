<?php

use App\Models\Event;
use App\Models\EventAddon;
use App\Models\EventRegistration;
use App\Models\EventRegistrationAddon;
use App\Models\PotluckOptions;
use App\Models\User;
use App\Services\PotluckCatalog;

it('creates, updates, and deletes catalog items', function () {
    $event = Event::create(['name' => 'Cat', 'slug' => 'cat', 'cost' => 0]);
    $keep = PotluckOptions::forceCreate(['event_id' => $event->id, 'category' => 'Sides', 'item' => 'Chips', 'limit' => 0, 'current_count' => 0]);
    $drop = PotluckOptions::forceCreate(['event_id' => $event->id, 'category' => 'Sides', 'item' => 'Old', 'limit' => 0, 'current_count' => 0]);

    $blocked = (new PotluckCatalog())->sync($event->id, [
        ['id' => $keep->id, 'category' => 'Sides', 'item' => 'Tortilla Chips'], // update
        ['id' => 0, 'category' => 'Dessert', 'item' => 'Brownies'],             // create
        ['id' => 0, 'category' => '', 'item' => ''],                            // blank ignored
        // $drop omitted -> deleted
    ]);

    expect($blocked)->toBe([]);
    expect($keep->fresh()->item)->toBe('Tortilla Chips');
    expect(PotluckOptions::find($drop->id))->toBeNull();
    expect(PotluckOptions::where('event_id', $event->id)->where('item', 'Brownies')->exists())->toBeTrue();
    expect(PotluckOptions::where('event_id', $event->id)->count())->toBe(2);
});

it('blocks removing a catalog item a registrant has chosen', function () {
    $event = Event::create(['name' => 'Cat2', 'slug' => 'cat2', 'cost' => 0]);
    $addon = EventAddon::create(['event_id' => $event->id, 'type' => 'potluck', 'enabled' => true, 'sort_order' => 0, 'settings' => ['open_signup' => false]]);
    $chosen = PotluckOptions::forceCreate(['event_id' => $event->id, 'category' => 'Main', 'item' => 'Lasagna', 'limit' => 0, 'current_count' => 1]);

    $user = User::factory()->create();
    $event->registrations()->attach($user->id, ['amount_due' => 0, 'amount_paid' => 0]);
    $reg = EventRegistration::where('event_id', $event->id)->where('user_id', $user->id)->first();
    EventRegistrationAddon::create([
        'event_registration_id' => $reg->id, 'event_addon_id' => $addon->id,
        'type' => 'potluck', 'selected' => true, 'value' => 'Main - Lasagna',
        'data' => ['potluck_item_id' => $chosen->id],
    ]);

    // Try to remove everything.
    $blocked = (new PotluckCatalog())->sync($event->id, []);

    expect($blocked)->toBe(['Lasagna']);
    expect(PotluckOptions::find($chosen->id))->not->toBeNull(); // kept
});
