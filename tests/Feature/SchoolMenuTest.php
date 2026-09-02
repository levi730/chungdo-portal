<?php

use App\Models\School;
use App\Models\SchoolInstructor;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The Schools menu.
 *
 * /school was reachable only by typing the URL — nothing linked to it — so the
 * management screens were effectively invisible. The dropdown was also gated on
 * event.viewAllSchoolRegistrants, an EVENT permission, which would have excluded
 * the instructors who can now edit their own school.
 */
function menuSchool(): School
{
    static $n = 0;
    $n++;

    return School::create(['name' => "Menu School {$n}", 'shortname' => "MS{$n}"]);
}

function verifiedUser(): User
{
    $user = User::factory()->create();
    $user->markEmailAsVerified();

    return $user;
}

it('shows the management link to someone who can add schools', function () {
    Permission::findOrCreate('school.manage', 'web');
    $user = verifiedUser();
    $user->givePermissionTo('school.manage');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Manage Schools')
        ->assertSee('+ Add School');
});

it('shows the management link to an instructor of a school', function () {
    // The point of widening the gate: they can edit their own school, so they
    // need a way to reach it.
    $school = menuSchool();
    $user = verifiedUser();
    SchoolInstructor::create(['school_id' => $school->id, 'user_id' => $user->id, 'principal' => 0]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Manage Schools')
        // Editing their own school is not permission to create new ones.
        ->assertDontSee('+ Add School');
});

it('hides the management link from an ordinary member', function () {
    menuSchool();

    $this->actingAs(verifiedUser())
        ->get('/dashboard')
        ->assertOk()
        ->assertDontSee('Manage Schools');
});

it('still shows the schools dropdown to someone who can view registrants', function () {
    // The pre-existing audience must not lose the menu. Asserted on the
    // dropdown's own link rather than a school name: $school_menu is captured
    // once in AppServiceProvider::boot(), which has already run by the time a
    // test body creates anything, so schools made here never reach the nav.
    Permission::findOrCreate('event.viewAllSchoolRegistrants', 'web');
    $user = verifiedUser();
    $user->givePermissionTo('event.viewAllSchoolRegistrants');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('nav-link-title')
        // ...but no management link, since they manage no school.
        ->assertDontSee('Manage Schools');
});

it('gives no schools dropdown to a member with neither right', function () {
    $this->actingAs(verifiedUser())
        ->get('/dashboard')
        ->assertOk()
        ->assertDontSee(route('school.index'), false)
        ->assertDontSee('Manage Schools');
});
