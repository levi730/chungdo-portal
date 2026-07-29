<?php

use App\Models\Event;
use App\Models\EventAddon;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function eventManager(): User
{
    Permission::findOrCreate('event.manage', 'web');
    $user = User::factory()->create();
    $user->markEmailAsVerified();
    $user->givePermissionTo('event.manage');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

it('creates an event with an enabled registration fee add-on', function () {
    $user = eventManager();

    $response = $this->actingAs($user)->post(route('events.store'), [
        'name' => 'Spring Open',
        'startdatetime' => '2027-03-01T09:00',
        'location' => 'Main Dojang',
        'details' => 'Come compete!',
        'require_ticket' => '1',
        'enabled' => ['registration_fee' => '1'],
        'settings' => ['registration_fee' => ['cost' => '55', 'cost_type' => 'per person', 'discounts' => ['2' => '5', '3' => '0', '4' => '0', '5' => '0']]],
    ]);

    $event = Event::where('name', 'Spring Open')->first();
    expect($event)->not->toBeNull();
    expect($event->slug)->toBe('spring-open');
    expect((bool) $event->require_ticket)->toBeTrue();
    $response->assertRedirect(route('events.edit', $event));

    $fee = EventAddon::where('event_id', $event->id)->where('type', 'registration_fee')->first();
    expect($fee->enabled)->toBeTrue();
    expect((float) $fee->setting('cost'))->toBe(55.0);
    expect((float) $fee->setting('discounts')['2'])->toBe(5.0);
});

it('updates an event', function () {
    $user = eventManager();
    $event = Event::create(['name' => 'Old Name', 'slug' => 'old-name', 'cost' => 0]);

    $this->actingAs($user)->put(route('events.update', $event), [
        'name' => 'New Name',
        'slug' => 'old-name',
    ])->assertRedirect();

    expect($event->fresh()->name)->toBe('New Name');
});

it('archives (soft-deletes) and restores an event', function () {
    $user = eventManager();
    $event = Event::create(['name' => 'Archive Me', 'slug' => 'archive-me', 'cost' => 0]);

    $this->actingAs($user)->delete(route('events.destroy', $event));
    expect(Event::find($event->id))->toBeNull();
    expect(Event::withTrashed()->find($event->id)->trashed())->toBeTrue();

    $this->actingAs($user)->post(route('events.restore', $event->id));
    expect(Event::find($event->id))->not->toBeNull();
});

it('uploads slideshow images and forms to the event media collections', function () {
    \Illuminate\Support\Facades\Storage::fake(config('media-library.disk_name'));
    $user = eventManager();

    $this->actingAs($user)->post(route('events.store'), [
        'name' => 'With Media',
        'slug' => 'with-media',
        'slideshow' => [\Illuminate\Http\UploadedFile::fake()->image('slide1.jpg')],
        'forms' => [\Illuminate\Http\UploadedFile::fake()->create('waiver.pdf', 100, 'application/pdf')],
    ]);

    $event = Event::where('slug', 'with-media')->first();
    expect($event->getMedia('slideshow-images'))->toHaveCount(1);
    expect($event->getMedia('forms'))->toHaveCount(1);
});

it('forbids event management without the permission', function () {
    $user = User::factory()->create();
    $user->markEmailAsVerified();

    $this->actingAs($user)->get(route('events.create'))->assertForbidden();
    $this->actingAs($user)->post(route('events.store'), ['name' => 'Nope'])->assertForbidden();
});

it('auto-enables a deadline-free participation add-on for a combined tournament', function () {
    $user = eventManager();

    $this->actingAs($user)->post(route('events.store'), [
        'name' => 'Combined Champs',
        'type' => 'combined',
        'startdatetime' => '2027-03-01T09:00',
    ])->assertRedirect();

    $event = Event::where('name', 'Combined Champs')->first();
    $part = EventAddon::where('event_id', $event->id)->where('type', 'participation')->first();

    expect($part)->not->toBeNull();
    expect($part->enabled)->toBeTrue();
    expect($part->closes_at)->toBeNull();
});

it('does not enable participation for a non-combined event', function () {
    $user = eventManager();

    $this->actingAs($user)->post(route('events.store'), [
        'name' => 'Sparring Only',
        'type' => 'sparring',
        'startdatetime' => '2027-03-01T09:00',
    ])->assertRedirect();

    $event = Event::where('name', 'Sparring Only')->first();
    $part = EventAddon::where('event_id', $event->id)->where('type', 'participation')->first();

    expect($part?->enabled ?? false)->toBeFalse();
});
