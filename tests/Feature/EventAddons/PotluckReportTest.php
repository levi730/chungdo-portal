<?php

use App\Exports\Event\RegistrantExport;
use App\Models\Event;
use App\Models\EventAddon;
use App\Models\EventRegistration;
use App\Models\EventRegistrationAddon;
use App\Models\Rank;
use App\Models\School;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function registrantViewer(): User
{
    Permission::findOrCreate('event.viewAllSchoolRegistrants', 'web');
    $user = User::factory()->create();
    $user->markEmailAsVerified();
    $user->givePermissionTo('event.viewAllSchoolRegistrants');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

it('lists open-signup potluck dishes by registrant', function () {
    $admin = registrantViewer();
    $school = School::create(['name' => 'Test Dojang', 'shortname' => 'TD']);
    $rank = Rank::forceCreate(['id' => 1, 'rank' => 'White', 'color' => '#fff', 'content_color' => '#000']);

    $event = Event::create(['name' => 'Potluck Event', 'slug' => 'potluck-event', 'cost' => 0]);
    $addon = EventAddon::create([
        'event_id' => $event->id, 'type' => 'potluck', 'enabled' => true,
        'sort_order' => 0, 'settings' => ['open_signup' => true],
    ]);

    $bringer = User::factory()->create();
    $bringer->forceFill(['school_id' => $school->id, 'rank_id' => $rank->id])->save();
    $event->registrations()->attach($bringer->id, ['amount_due' => 0, 'amount_paid' => 0]);
    $reg = EventRegistration::where('event_id', $event->id)->where('user_id', $bringer->id)->first();
    EventRegistrationAddon::create([
        'event_registration_id' => $reg->id, 'event_addon_id' => $addon->id,
        'type' => 'potluck', 'selected' => true, 'value' => 'Potato Salad',
        'data' => ['open_item' => 'Potato Salad'],
    ]);

    $response = $this->actingAs($admin)->get(route('event.registrants-by-potluck', $event->slug));

    $response->assertOk();
    $response->assertSee('Potato Salad');
    $response->assertSee($bringer->firstname);
});

it('includes open-signup potluck dishes in the registrant spreadsheet', function () {
    $event = Event::create(['name' => 'Potluck Export', 'slug' => 'potluck-export', 'cost' => 0]);
    $addon = EventAddon::create([
        'event_id' => $event->id, 'type' => 'potluck', 'enabled' => true,
        'sort_order' => 0, 'settings' => ['open_signup' => true],
    ]);

    $bringer = User::factory()->create();
    $event->registrations()->attach($bringer->id, ['amount_due' => 0, 'amount_paid' => 0]);
    $reg = EventRegistration::where('event_id', $event->id)->where('user_id', $bringer->id)->first();
    EventRegistrationAddon::create([
        'event_registration_id' => $reg->id, 'event_addon_id' => $addon->id,
        'type' => 'potluck', 'selected' => true, 'value' => 'Deviled Eggs',
        'data' => ['open_item' => 'Deviled Eggs'],
    ]);

    $html = (new RegistrantExport($event))->view()->render();

    expect($html)->toContain('Deviled Eggs');
});
