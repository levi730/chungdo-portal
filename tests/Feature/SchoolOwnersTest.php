<?php

use App\Livewire\Admin\SchoolOwners;
use App\Models\School;
use App\Models\SchoolInstructor;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Managing a school's owners.
 *
 * school_instructors is not a cosmetic list: SchoolPolicy reads it to decide who
 * may edit a school, so adding someone here grants them access. That is why the
 * component needs school.manage rather than merely being an owner — otherwise an
 * owner could hand a stranger the keys, or remove their co-owners and take sole
 * control, without anyone in the association involved.
 */
function ownedSchool(): School
{
    static $n = 0;
    $n++;

    return School::create(['name' => "Owned School {$n}", 'shortname' => "OS{$n}"]);
}

function ownerAdmin(): User
{
    Permission::findOrCreate('school.manage', 'web');
    $user = User::factory()->create();
    $user->markEmailAsVerified();
    $user->givePermissionTo('school.manage');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function candidate(array $attrs = []): User
{
    return User::factory()->create($attrs);
}

/* -------------------------------------------------------------------- *
 * Adding and removing
 * -------------------------------------------------------------------- */

it('finds a user by name and adds them as an owner', function () {
    $school = ownedSchool();
    $user = candidate(['firstname' => 'Wilhelmina', 'lastname' => 'Ashdown']);

    Livewire::actingAs(ownerAdmin())
        ->test(SchoolOwners::class, ['school' => $school])
        ->set('search', 'Ashdown')
        ->assertSee('Ashdown')
        ->call('addOwner', $user->id);

    expect(SchoolInstructor::where('school_id', $school->id)->where('user_id', $user->id)->exists())
        ->toBeTrue();
});

it('finds a user by email too', function () {
    $school = ownedSchool();
    candidate(['email' => 'findme@example.test', 'firstname' => 'Zed', 'lastname' => 'Quill']);

    Livewire::actingAs(ownerAdmin())
        ->test(SchoolOwners::class, ['school' => $school])
        ->set('search', 'findme@')
        ->assertSee('Quill');
});

it('says nothing until the search is worth running', function () {
    $school = ownedSchool();
    candidate(['lastname' => 'Ashdown']);

    Livewire::actingAs(ownerAdmin())
        ->test(SchoolOwners::class, ['school' => $school])
        ->set('search', 'A')
        ->assertDontSee('Ashdown');
});

it('grants edit rights by adding an owner', function () {
    // The whole point: this list is the access list.
    $school = ownedSchool();
    $user = candidate();

    expect($user->can('update', $school))->toBeFalse();

    Livewire::actingAs(ownerAdmin())
        ->test(SchoolOwners::class, ['school' => $school])
        ->call('addOwner', $user->id);

    expect($user->fresh()->can('update', $school->fresh()))->toBeTrue();
});

it('takes edit rights away by removing an owner', function () {
    $school = ownedSchool();
    $user = candidate();
    SchoolInstructor::create(['school_id' => $school->id, 'user_id' => $user->id, 'principal' => 1]);

    Livewire::actingAs(ownerAdmin())
        ->test(SchoolOwners::class, ['school' => $school])
        ->call('removeOwner', $user->id);

    expect($user->fresh()->can('update', $school->fresh()))->toBeFalse();
});

it('does not add the same person twice', function () {
    $school = ownedSchool();
    $user = candidate();

    $component = Livewire::actingAs(ownerAdmin())->test(SchoolOwners::class, ['school' => $school]);
    $component->call('addOwner', $user->id);
    $component->call('addOwner', $user->id);

    expect(SchoolInstructor::where('school_id', $school->id)->count())->toBe(1);
});

it('leaves other schools alone when removing an owner', function () {
    $theirs = ownedSchool();
    $other = ownedSchool();
    $user = candidate();

    SchoolInstructor::create(['school_id' => $theirs->id, 'user_id' => $user->id, 'principal' => 1]);
    SchoolInstructor::create(['school_id' => $other->id, 'user_id' => $user->id, 'principal' => 1]);

    Livewire::actingAs(ownerAdmin())
        ->test(SchoolOwners::class, ['school' => $theirs])
        ->call('removeOwner', $user->id);

    expect(SchoolInstructor::where('school_id', $other->id)->where('user_id', $user->id)->exists())
        ->toBeTrue();
});

/* -------------------------------------------------------------------- *
 * Who may change the list
 * -------------------------------------------------------------------- */

it('refuses an owner who is not association staff', function () {
    // An owner may edit their school's details, but must not be able to hand
    // out access to it or remove their co-owners.
    $school = ownedSchool();
    $owner = candidate();
    $owner->markEmailAsVerified();
    SchoolInstructor::create(['school_id' => $school->id, 'user_id' => $owner->id, 'principal' => 1]);

    Livewire::actingAs($owner)
        ->test(SchoolOwners::class, ['school' => $school])
        ->assertForbidden();
});

it('refuses an ordinary member', function () {
    $school = ownedSchool();

    Livewire::actingAs(candidate())
        ->test(SchoolOwners::class, ['school' => $school])
        ->assertForbidden();
});

it('keeps the owners panel off the edit form for an instructor', function () {
    $school = ownedSchool();
    $owner = candidate();
    $owner->markEmailAsVerified();
    SchoolInstructor::create(['school_id' => $school->id, 'user_id' => $owner->id, 'principal' => 1]);

    // They can still edit the school itself — just not who owns it.
    $this->actingAs($owner)
        ->get(route('school.edit', $school->id))
        ->assertOk()
        ->assertDontSee('Add an owner');
});

it('shows the owners panel to association staff', function () {
    $school = ownedSchool();

    $this->actingAs(ownerAdmin())
        ->get(route('school.edit', $school->id))
        ->assertOk()
        ->assertSee('Add an owner');
});

/* -------------------------------------------------------------------- *
 * The directory listing
 * -------------------------------------------------------------------- */

it('lists a new owner on the directory by default', function () {
    $school = ownedSchool();
    $user = candidate(['firstname' => 'Dana', 'lastname' => 'Prentice']);

    Livewire::actingAs(ownerAdmin())
        ->test(SchoolOwners::class, ['school' => $school])
        ->call('addOwner', $user->id);

    // principal drives principal_instructors_text, which both directories show.
    expect($school->fresh()->principal_instructors_text)->toContain('Prentice');
});

it('can keep an owner off the directory without removing their access', function () {
    $school = ownedSchool();
    $user = candidate(['firstname' => 'Quiet', 'lastname' => 'Partner']);

    $component = Livewire::actingAs(ownerAdmin())->test(SchoolOwners::class, ['school' => $school]);
    $component->call('addOwner', $user->id);
    $component->call('togglePrincipal', $user->id);

    expect($school->fresh()->principal_instructors_text)->not->toContain('Partner')
        // Still an owner, so still able to edit.
        ->and($user->fresh()->can('update', $school->fresh()))->toBeTrue();
});
