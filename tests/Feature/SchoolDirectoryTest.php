<?php

use App\Models\School;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The public school directory at /schools, and school photos.
 *
 * /schools is a link meant to be handed to anyone — students, parents, people
 * who have never heard of the portal — so it sits outside the auth group beside
 * the storefront. It must never show an archived school, and never show a
 * management control.
 */
beforeEach(function () {
    Storage::fake(config('media-library.disk_name'));
});

function directorySchool(array $attrs = []): School
{
    static $n = 0;
    $n++;

    return School::create(array_merge([
        'name' => "Directory School {$n}",
        'shortname' => "DS{$n}",
        'city' => 'St. Charles',
        'state' => 'MO',
    ], $attrs));
}

function schoolAdmin(): User
{
    Permission::findOrCreate('school.manage', 'web');
    $user = User::factory()->create();
    $user->markEmailAsVerified();
    $user->givePermissionTo('school.manage');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

/* -------------------------------------------------------------------- *
 * Public access
 * -------------------------------------------------------------------- */

it('lets a signed-out visitor read the directory', function () {
    $school = directorySchool(['name' => 'Riverside Dojang', 'phone' => '555-0100']);

    $this->get(route('schools.public'))
        ->assertOk()
        ->assertSee($school->name)
        ->assertSee('555-0100');
});

it('hides archived schools from the public directory', function () {
    $live = directorySchool(['name' => 'Still Teaching']);
    $gone = directorySchool(['name' => 'Closed Long Ago']);
    $gone->delete();

    $this->get(route('schools.public'))
        ->assertOk()
        ->assertSee($live->name)
        ->assertDontSee($gone->name);
});

it('does not print email addresses on the public directory', function () {
    $school = directorySchool(['email' => 'info@riverside.test']);

    $this->get(route('schools.public'))
        ->assertOk()
        ->assertSee('Email this school')
        // Not shown as readable text...
        ->assertDontSee('info@riverside.test');
});

it('keeps the address out of the public HTML entirely', function () {
    // Relabelling the link is not enough: a mailto href is exactly what an
    // address harvester reads. Neither the address nor a mailto for it may
    // appear in the source.
    directorySchool(['email' => 'info@riverside.test']);

    $content = $this->get(route('schools.public'))->assertOk()->getContent();

    expect($content)->not->toContain('info@riverside.test')
        ->not->toContain('mailto:info@riverside.test')
        // ...but the material to rebuild it in the browser is there.
        ->toContain(base64_encode('info@riverside.test'));
});

it('shows the address as text to members, who need to read and copy it', function () {
    $school = directorySchool(['email' => 'info@riverside.test']);

    $this->actingAs(schoolAdmin())
        ->get(route('school.index'))
        ->assertOk()
        ->assertSee('info@riverside.test');
});

it('shows no management controls on the public directory', function () {
    // Not even to an admin who happens to be signed in — this page is the
    // handout, and its job is to look the same for everyone.
    $school = directorySchool();

    $this->actingAs(schoolAdmin())
        ->get(route('schools.public'))
        ->assertOk()
        ->assertDontSee(route('school.edit', $school->id))
        ->assertDontSee('Add school')
        ->assertDontSee('Restore');
});

it('keeps the members-only index behind login', function () {
    directorySchool();

    $this->get(route('school.index'))->assertRedirect();
});

it('links the public directory from the members index', function () {
    directorySchool();

    $this->actingAs(schoolAdmin())
        ->get(route('school.index'))
        ->assertOk()
        ->assertSee(route('schools.public'), false);
});

/* -------------------------------------------------------------------- *
 * Photos
 * -------------------------------------------------------------------- */

it('shows a school photo on both directories', function () {
    $school = directorySchool();
    $school->addMedia(UploadedFile::fake()->image('dojang.jpg', 1200, 800))
        ->toMediaCollection('photo');

    $this->get(route('schools.public'))->assertOk()->assertSee('glide', false);
    $this->actingAs(schoolAdmin())->get(route('school.index'))->assertOk()->assertSee('glide', false);
});

it('falls back to the short name when there is no photo', function () {
    directorySchool(['shortname' => 'RVD']);

    $this->get(route('schools.public'))
        ->assertOk()
        ->assertSee('RVD')
        // Never the broken template avatar that used to sit here.
        ->assertDontSee('static/avatars');
});

it('accepts a photo upload when editing', function () {
    $school = directorySchool();

    $this->actingAs(schoolAdmin())
        ->put(route('school.update', $school->id), [
            'name' => $school->name,
            'photo' => UploadedFile::fake()->image('dojang.jpg', 1200, 800),
        ])
        ->assertRedirect();

    expect($school->fresh()->photo())->not->toBeNull();
});

it('replaces rather than accumulates photos', function () {
    // The collection is singleFile(); a second upload must not leave two.
    $school = directorySchool();
    $school->addMedia(UploadedFile::fake()->image('first.jpg'))->toMediaCollection('photo');

    $this->actingAs(schoolAdmin())->put(route('school.update', $school->id), [
        'name' => $school->name,
        'photo' => UploadedFile::fake()->image('second.jpg'),
    ]);

    expect($school->fresh()->getMedia('photo'))->toHaveCount(1)
        ->and($school->fresh()->photo()->file_name)->toContain('second');
});

it('removes a photo', function () {
    $school = directorySchool();
    $school->addMedia(UploadedFile::fake()->image('dojang.jpg'))->toMediaCollection('photo');

    $this->actingAs(schoolAdmin())
        ->delete(route('school.photo.delete', $school->id))
        ->assertRedirect();

    expect($school->fresh()->photo())->toBeNull();
});

it('will not let an unrelated member touch a school photo', function () {
    $school = directorySchool();
    $school->addMedia(UploadedFile::fake()->image('dojang.jpg'))->toMediaCollection('photo');

    $outsider = User::factory()->create();
    $outsider->markEmailAsVerified();

    $this->actingAs($outsider)
        ->delete(route('school.photo.delete', $school->id))
        ->assertForbidden();

    expect($school->fresh()->photo())->not->toBeNull();
});

it('keeps a saved photo when the school is edited without uploading one', function () {
    $school = directorySchool();
    $school->addMedia(UploadedFile::fake()->image('dojang.jpg'))->toMediaCollection('photo');

    $this->actingAs(schoolAdmin())->put(route('school.update', $school->id), [
        'name' => 'Renamed, same photo',
    ]);

    expect($school->fresh()->photo())->not->toBeNull()
        ->and($school->fresh()->name)->toBe('Renamed, same photo');
});
