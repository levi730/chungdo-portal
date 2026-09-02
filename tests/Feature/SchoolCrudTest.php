<?php

use App\Models\School;
use App\Models\SchoolInstructor;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Managing schools.
 *
 * Two rights, kept apart on purpose: a school's own instructors edit its
 * details, while creating and archiving schools needs `school.manage`. Someone
 * correcting their phone number must not be able to archive a sibling school.
 *
 * The policy used to gate editing on hasRole('school-instructor'), a role that
 * has never existed here, so it was false for everyone and the 12 rows in
 * school_instructors did nothing. It reads the relationship now.
 */
function schoolManager(): User
{
    Permission::findOrCreate('school.manage', 'web');
    $user = User::factory()->create();
    $user->markEmailAsVerified();
    $user->givePermissionTo('school.manage');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function plainMember(): User
{
    $user = User::factory()->create();
    $user->markEmailAsVerified();

    return $user;
}

function aSchool(array $attrs = []): School
{
    static $n = 0;
    $n++;

    return School::create(array_merge([
        'name' => "Test School {$n}",
        'shortname' => "TS{$n}",
        'city' => 'St. Charles',
        'state' => 'MO',
    ], $attrs));
}

/** Make $user an instructor of $school, which is what grants edit rights. */
function instructorOf(School $school): User
{
    $user = plainMember();

    SchoolInstructor::create([
        'school_id' => $school->id,
        'user_id' => $user->id,
        'principal' => 0,
    ]);

    return $user;
}

function schoolPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'New Dojang',
        'shortname' => 'ND',
        'address1' => '1 Main St',
        'city' => 'St. Charles',
        'state' => 'MO',
        'zip' => '63301',
        'email' => 'info@example.com',
        'url' => 'example.com',
    ], $overrides);
}

/* -------------------------------------------------------------------- *
 * Creating
 * -------------------------------------------------------------------- */

it('lets a school manager create a school', function () {
    $this->actingAs(schoolManager())
        ->post(route('school.store'), schoolPayload())
        ->assertRedirect();

    $school = School::where('name', 'New Dojang')->firstOrFail();

    expect($school->city)->toBe('St. Charles')
        // A bare domain is what people type.
        ->and($school->url)->toBe('https://example.com');
});

it('refuses to let an ordinary member create a school', function () {
    $this->actingAs(plainMember())
        ->post(route('school.store'), schoolPayload())
        ->assertForbidden();

    expect(School::where('name', 'New Dojang')->exists())->toBeFalse();
});

it('refuses to let an instructor create a school', function () {
    // Editing your own school is not the same right as adding new ones.
    $instructor = instructorOf(aSchool());

    $this->actingAs($instructor)
        ->post(route('school.store'), schoolPayload())
        ->assertForbidden();
});

it('rejects a duplicate school name', function () {
    aSchool(['name' => 'Taken Name']);

    $this->actingAs(schoolManager())
        ->post(route('school.store'), schoolPayload(['name' => 'Taken Name']))
        ->assertSessionHasErrors('name');
});

/* -------------------------------------------------------------------- *
 * Editing
 * -------------------------------------------------------------------- */

it('lets an instructor edit their own school', function () {
    $school = aSchool();
    $instructor = instructorOf($school);

    $this->actingAs($instructor)->get(route('school.edit', $school->id))->assertOk();

    $this->actingAs($instructor)
        ->put(route('school.update', $school->id), schoolPayload(['name' => 'Renamed By Instructor']))
        ->assertRedirect();

    expect($school->fresh()->name)->toBe('Renamed By Instructor');
});

it('does not let an instructor edit a different school', function () {
    $theirs = aSchool();
    $someoneElses = aSchool();
    $instructor = instructorOf($theirs);

    $this->actingAs($instructor)
        ->put(route('school.update', $someoneElses->id), schoolPayload())
        ->assertForbidden();
});

it('does not let an ordinary member edit any school', function () {
    $school = aSchool();

    $this->actingAs(plainMember())->get(route('school.edit', $school->id))->assertForbidden();
});

it('lets a school manager edit any school', function () {
    $school = aSchool();

    $this->actingAs(schoolManager())
        ->put(route('school.update', $school->id), schoolPayload(['name' => 'Renamed By Admin']))
        ->assertRedirect();

    expect($school->fresh()->name)->toBe('Renamed By Admin');
});

it('lets a school keep its own name when saving', function () {
    // The uniqueness rule has to ignore the row being edited.
    $school = aSchool(['name' => 'Unchanged']);

    $this->actingAs(schoolManager())
        ->put(route('school.update', $school->id), schoolPayload(['name' => 'Unchanged']))
        ->assertSessionHasNoErrors();
});

/* -------------------------------------------------------------------- *
 * Archiving
 * -------------------------------------------------------------------- */

it('archives rather than deletes', function () {
    // users.school_id, school_instructors and product_orders.pickup_school_id
    // all point here; a hard delete would orphan every one of them.
    $school = aSchool();

    $this->actingAs(schoolManager())->delete(route('school.destroy', $school->id))->assertRedirect();

    expect(School::find($school->id))->toBeNull()
        ->and(School::withTrashed()->find($school->id)->trashed())->toBeTrue();
});

it('keeps a member attached to an archived school', function () {
    $school = aSchool();
    $member = plainMember();
    $member->update(['school_id' => $school->id]);

    $this->actingAs(schoolManager())->delete(route('school.destroy', $school->id));

    expect($member->fresh()->school_id)->toBe($school->id);
});

it('restores an archived school', function () {
    $school = aSchool();
    $school->delete();

    $this->actingAs(schoolManager())->post(route('school.restore', $school->id))->assertRedirect();

    expect(School::find($school->id))->not->toBeNull();
});

it('does not let an instructor archive their own school', function () {
    $school = aSchool();
    $instructor = instructorOf($school);

    $this->actingAs($instructor)
        ->delete(route('school.destroy', $school->id))
        ->assertForbidden();

    expect(School::find($school->id))->not->toBeNull();
});

/* -------------------------------------------------------------------- *
 * The list
 * -------------------------------------------------------------------- */

it('hides archived schools from an ordinary member', function () {
    $live = aSchool(['name' => 'Still Open']);
    $gone = aSchool(['name' => 'Closed Down']);
    $gone->delete();

    $this->actingAs(plainMember())
        ->get(route('school.index'))
        ->assertOk()
        ->assertSee('Still Open')
        ->assertDontSee('Closed Down');
});

it('shows archived schools to a school manager so they can be restored', function () {
    $gone = aSchool(['name' => 'Closed Down']);
    $gone->delete();

    $this->actingAs(schoolManager())
        ->get(route('school.index'))
        ->assertOk()
        ->assertSee('Closed Down')
        ->assertSee('Archived');
});

it('offers the add button only to someone who can create', function () {
    aSchool();

    $this->actingAs(schoolManager())->get(route('school.index'))->assertOk()->assertSee('Add school');
    $this->actingAs(plainMember())->get(route('school.index'))->assertOk()->assertDontSee('Add school');
});
