<?php

use App\Livewire\Event\CheckInDetails;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Rank;
use App\Models\School;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Who may run day-of check-in.
 *
 * Before `event.checkIn` existed the check-in pages sat in the plain auth group
 * with no gate at all, and the nav link was wrapped in @can('event.admin') — a
 * role NAME, which can() never matches. So every verified member could check
 * people in by URL, and only super admins could find the link. These tests pin
 * the permission on the routes, on the Livewire components (whose actions never
 * pass through route middleware), and on the nav.
 */
function checkInEvent(): Event
{
    return Event::create(['name' => 'Door Test', 'slug' => 'door-test', 'cost' => 0, 'startdatetime' => Carbon::parse('2026-12-05 09:00')]);
}

function verifiedMember(): User
{
    $user = User::factory()->create();
    $user->markEmailAsVerified();

    return $user;
}

function checkInStaff(): User
{
    $user = verifiedMember();
    $user->givePermissionTo(Permission::findOrCreate('event.checkIn', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function registrant(Event $event): User
{
    $user = User::factory()->create();
    $school = School::create(['name' => 'Door School', 'shortname' => 'DOOR', 'city' => 'St. Charles', 'state' => 'MO']);
    \DB::table('users')->where('id', $user->id)->update([
        'school_id' => $school->id,
        'rank_id' => Rank::first()?->id ?? Rank::forceCreate(['id' => 9901, 'rank' => 'Door Belt', 'color' => 'white', 'content_color' => 'black'])->id,
        'sex' => 'F',
        'dob' => '2010-04-01',
        'height' => 60,
        'weight' => 100,
    ]);
    $event->registrations()->attach($user->id, ['amount_due' => 0, 'amount_paid' => 0]);

    return $user->fresh();
}

it('refuses a verified member who holds nothing', function () {
    $event = checkInEvent();

    $this->actingAs(verifiedMember())
        ->get(route('event.check-in', $event->slug))
        ->assertForbidden();

    $this->actingAs(verifiedMember())
        ->get(route('event.check-in-details', [$event->slug, '1']))
        ->assertForbidden();
});

it('lets a holder of event.checkIn open the check-in list', function () {
    $event = checkInEvent();

    $this->actingAs(checkInStaff())
        ->get(route('event.check-in', $event->slug))
        ->assertOk()
        ->assertSee('Registrant Check-In');
});

it('offers the camera scanner on the check-in list', function () {
    $event = checkInEvent();

    $this->actingAs(checkInStaff())
        ->get(route('event.check-in', $event->slug))
        ->assertOk()
        ->assertSee('Scan QR')
        ->assertSee('js/check_in_camera.js')
        ->assertSee('js/qr-scanner/qr-scanner.min.js')
        // The camera script needs both files, and a hardware scan needs this one.
        ->assertSee('js/barcode_scanner.js');

    expect(public_path('js/check_in_camera.js'))->toBeFile()
        ->and(public_path('js/qr-scanner/qr-scanner.min.js'))->toBeFile()
        ->and(public_path('js/qr-scanner/qr-scanner-worker.min.js'))->toBeFile();
});

it('lets the event.checkin role in without making its holder an event admin', function () {
    $event = checkInEvent();

    $role = Role::findOrCreate('event.checkin', 'web');
    $role->givePermissionTo(Permission::findOrCreate('event.checkIn', 'web'));
    $user = verifiedMember();
    $user->assignRole($role);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($user)
        ->get(route('event.check-in', $event->slug))
        ->assertOk()
        // The nav shows the door, and only the door: registrant lists and
        // cards stay behind event.viewAllSchoolRegistrants.
        ->assertSee('Registrant Check-In')
        ->assertDontSee('View Registrants')
        ->assertDontSee('Registration Cards');

    expect($user->can('event.viewAllSchoolRegistrants'))->toBeFalse();
});

it('shows the link to an event admin by permission, not by role name', function () {
    $event = checkInEvent();

    $admin = verifiedMember();
    $admin->givePermissionTo(Permission::findOrCreate('event.viewAllSchoolRegistrants', 'web'));
    $admin->givePermissionTo(Permission::findOrCreate('event.checkIn', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($admin)
        ->get(route('event.registrants', $event->slug))
        ->assertOk()
        ->assertSee('Registrant Check-In')
        ->assertSee('View Registrants');
});

it('blocks the check-in component itself, not just the page route', function () {
    $event = checkInEvent();
    $who = registrant($event);

    Livewire::actingAs(verifiedMember())
        ->test(CheckInDetails::class, ['slug' => $event->slug, 'users' => (string) $who->id])
        ->assertForbidden();
});

it('marks a registrant checked in for a holder of event.checkIn', function () {
    $event = checkInEvent();
    $who = registrant($event);

    Livewire::actingAs(checkInStaff())
        ->test(CheckInDetails::class, ['slug' => $event->slug, 'users' => (string) $who->id])
        ->call('checkIn', 0)
        ->assertHasNoErrors();

    expect(EventRegistration::where('event_id', $event->id)->where('user_id', $who->id)->value('checkin'))->not->toBeNull();
});
