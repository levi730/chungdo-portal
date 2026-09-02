<?php

use App\Models\School;
use App\Models\SchoolInstructor;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The Schools menu and the All Schools directory.
 *
 * /school was reachable only by typing the URL — nothing linked to it. The
 * dropdown was also gated on event.viewAllSchoolRegistrants, an EVENT
 * permission, which hid a link to pages any signed-in member could already open:
 * SchoolController::view has never had an authorization check. It is now a
 * directory open to every member, with the management controls inside it gated
 * per permission.
 */
function menuSchool(array $attrs = []): School
{
    static $n = 0;
    $n++;

    return School::create(array_merge([
        'name' => "Menu School {$n}",
        'shortname' => "MS{$n}",
        'city' => 'St. Charles',
        'state' => 'MO',
    ], $attrs));
}

function verifiedUser(): User
{
    $user = User::factory()->create();
    $user->markEmailAsVerified();

    return $user;
}

function schoolCreator(): User
{
    Permission::findOrCreate('school.manage', 'web');
    $user = verifiedUser();
    $user->givePermissionTo('school.manage');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

it('puts All Schools at the top of the dropdown for any member', function () {
    $school = menuSchool();

    $response = $this->actingAs(verifiedUser())->get('/dashboard')->assertOk();
    $content = $response->getContent();

    $response->assertSee('All Schools')->assertSee($school->name);

    // First item: it comes before the individual schools beneath it.
    expect(strpos($content, 'All Schools'))->toBeLessThan(strpos($content, $school->name));
});

it('offers Add School only to someone who can create', function () {
    $this->actingAs(schoolCreator())->get('/dashboard')->assertOk()->assertSee('+ Add School');
    $this->actingAs(verifiedUser())->get('/dashboard')->assertOk()->assertDontSee('+ Add School');
});

it('keeps an archived school out of the menu', function () {
    // Only assertable because the menu resolves at render time now; shared from
    // boot() it was queried before the test body ran.
    $live = menuSchool();
    $gone = menuSchool();
    $gone->delete();

    $this->actingAs(verifiedUser())
        ->get('/dashboard')
        ->assertOk()
        ->assertSee($live->name)
        ->assertDontSee($gone->name);
});

/* -------------------------------------------------------------------- *
 * The directory itself
 * -------------------------------------------------------------------- */

it('lists every school with its contact details', function () {
    $school = menuSchool([
        'name' => 'Riverside Dojang',
        'address1' => '12 River Rd',
        'phone' => '555-0100',
        'email' => 'hello@riverside.test',
        'url' => 'https://riverside.test',
    ]);

    $this->actingAs(verifiedUser())
        ->get(route('school.index'))
        ->assertOk()
        ->assertSee($school->name)
        ->assertSee('12 River Rd')
        ->assertSee('555-0100')
        ->assertSee('hello@riverside.test')
        // Shown without the scheme, linked with it.
        ->assertSee('riverside.test');
});

it('shows an edit link only for a school the member may edit', function () {
    $theirs = menuSchool(['name' => 'Theirs To Edit']);
    $other = menuSchool(['name' => 'Someone Elses']);

    $instructor = verifiedUser();
    SchoolInstructor::create(['school_id' => $theirs->id, 'user_id' => $instructor->id, 'principal' => 0]);

    $content = $this->actingAs($instructor)->get(route('school.index'))->assertOk()->getContent();

    expect($content)->toContain(route('school.edit', $theirs->id))
        ->not->toContain(route('school.edit', $other->id));
});

it('shows no edit links at all to an ordinary member', function () {
    $school = menuSchool();

    $this->actingAs(verifiedUser())
        ->get(route('school.index'))
        ->assertOk()
        ->assertDontSee(route('school.edit', $school->id));
});

it('does not render the broken template avatar', function () {
    // public/static/avatars/010m.jpg has never existed, so every card carried a
    // broken image. The short name stands in until real photos arrive.
    menuSchool(['shortname' => 'RVD']);

    $this->actingAs(verifiedUser())
        ->get(route('school.index'))
        ->assertOk()
        ->assertDontSee('static/avatars')
        ->assertSee('RVD');
});
