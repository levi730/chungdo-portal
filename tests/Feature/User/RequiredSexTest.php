<?php

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Rank;
use App\Models\School;
use App\Models\User;
use Carbon\Carbon;

/*
 * Sex is required for divisions and check-in, but only a browser `required`
 * attribute ever asked for it, so 191 accounts reached production without one
 * (e.g. user 436, registered for event 12 with sex NULL).
 */

/** A school and rank to point students at; the test database has neither. */
function sexTestDojang(): array
{
    $school = School::create(['name' => 'Sex Test Dojang', 'shortname' => 'STD']);
    // ranks.id is not auto-increment, so a created model reports id 0; use the literal.
    Rank::find(1) ?? Rank::forceCreate(['id' => 1, 'rank' => 'White', 'color' => 'white', 'content_color' => 'black']);

    return ['school_id' => $school->id, 'rank_id' => 1];
}

function completeStudent(array $overrides = []): User
{
    return User::factory()->single_student()->create(array_merge(sexTestDojang(), $overrides));
}

function familyHead(): User
{
    return completeStudent(['can_login' => 1, 'email_verified_at' => now()]);
}

function familyMemberInput(array $overrides = []): array
{
    return array_merge([
        'firstname' => 'Kid',
        'lastname' => 'Test',
        'school_id' => null,
        'rank_id' => null,
        'dob' => '2015-01-01',
        'height' => 50,
        'weight' => 60,
        'sex' => 'F',
    ], $overrides);
}

it('lists sex among the fields an incomplete registrant is missing', function () {
    $user = completeStudent(['sex' => null]);

    expect($user->missingRegistrationFields())->toBe(['sex']);
    expect($user->can_register_for_tournaments)->toBeFalse();
});

it('treats a missing date of birth as incomplete', function () {
    // Operator precedence used to make dob only matter when weight was missing.
    $user = completeStudent(['dob' => null]);

    expect($user->missingRegistrationFields())->toBe(['date of birth']);
    expect($user->can_register_for_tournaments)->toBeFalse();
});

it('accepts a complete student profile', function () {
    $user = completeStudent(['sex' => 'M']);

    expect($user->missingRegistrationFields())->toBe([]);
    expect($user->can_register_for_tournaments)->toBeTrue();
});

it('refuses to register someone with no sex on file', function () {
    $head = familyHead();
    $kid = User::factory()->is_kid()->belongs_to($head)->create(['is_student' => 1, 'rank_id' => $head->rank_id, 'sex' => null]);
    $event = Event::create(['name' => 'Sex Cup', 'slug' => 'sex-cup', 'cost' => 0, 'startdatetime' => Carbon::parse('2027-02-01 09:00')]);

    $this->actingAs($head)
        ->from(route('event.register', $event->slug))
        ->post(route('event.register-process', $event->slug), [
            'registered' => (string) $kid->id,
            'ranks' => '{}',
            'volunteer_selections' => '{}',
        ])
        ->assertRedirect(route('event.register', $event->slug))
        ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'sex'));

    expect(EventRegistration::where('event_id', $event->id)->where('user_id', $kid->id)->exists())->toBeFalse();
});

it('requires sex when adding a family member', function () {
    $head = familyHead();

    $this->actingAs($head)
        ->post(route('profile-family.create'), familyMemberInput(['sex' => '']))
        ->assertSessionHasErrorsIn('updateFamilyMember', 'sex');

    expect(User::where('responsible_user_id', $head->id)->where('firstname', 'Kid')->exists())->toBeFalse();
});

it('requires sex when editing a family member', function () {
    $head = familyHead();
    $kid = User::factory()->is_kid()->belongs_to($head)->create(['sex' => 'M']);

    $this->actingAs($head)
        ->post(route('profile-family.update', $kid->id), familyMemberInput(['sex' => '']))
        ->assertSessionHasErrorsIn('updateFamilyMember', 'sex');

    expect($kid->fresh()->sex)->toBe('M');
});

it('requires sex on your own profile only when you are a student', function () {
    $head = familyHead();
    $profile = [
        'firstname' => $head->firstname,
        'lastname' => $head->lastname,
        'email' => $head->email,
        'phone' => '',
        'address1' => null, 'address2' => null, 'city' => null, 'state' => null, 'zip' => null,
        'school_id' => $head->school_id,
        'rank_id' => $head->rank_id,
        'dob' => '1990-01-01',
        'height' => 70,
        'weight' => 180,
        'sex' => '',
    ];

    $this->actingAs($head)
        ->put(route('user-profile-information.update'), $profile + ['is_student' => 'on'])
        ->assertSessionHasErrorsIn('updateProfileInformation', 'sex');

    $this->actingAs($head)
        ->put(route('user-profile-information.update'), $profile)
        ->assertSessionHasNoErrors();
});
