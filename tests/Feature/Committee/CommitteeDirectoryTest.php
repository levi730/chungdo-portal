<?php

use App\Models\Committee;
use App\Models\User;

/**
 * The members-only committee directory.
 *
 * The point of the page is contact details, which is exactly why it is not
 * public: `/schools` publishes a school's phone number, this publishes people's.
 */
function committeeMember(array $attrs = []): User
{
    $user = User::factory()->create();
    $user->markEmailAsVerified();

    if ($attrs) {
        \DB::table('users')->where('id', $user->id)->update($attrs);
    }

    return $user->fresh();
}

function aCommittee(string $name = 'Technical Committee', ?string $description = 'Sets the standards for rank testing.'): Committee
{
    return Committee::create([
        'name' => $name,
        'slug' => \Illuminate\Support\Str::slug($name),
        'description' => $description,
    ]);
}

it('turns a signed-out visitor away', function () {
    $committee = aCommittee();

    $this->get('/committees')->assertRedirect('/login');
    $this->get("/committees/{$committee->slug}")->assertRedirect('/login');
});

it('shows every committee with its role and members to a signed-in member', function () {
    $committee = aCommittee();
    $member = committeeMember(['phone' => '(555) 555-1234']);
    $committee->members()->attach($member->id);

    $this->actingAs(committeeMember())
        ->get('/committees')
        ->assertOk()
        ->assertSee('Technical Committee')
        ->assertSee('Sets the standards for rank testing.')
        ->assertSee($member->fullname)
        ->assertSee($member->email)
        ->assertSee('(555) 555-1234');
});

it('links a single committee by the slug the admin form has always written', function () {
    $committee = aCommittee('Steering Committee', 'Sets direction for the association.');
    $member = committeeMember();
    $committee->members()->attach($member->id);

    $this->actingAs(committeeMember())
        ->get('/committees/steering-committee')
        ->assertOk()
        ->assertSee('Steering Committee')
        ->assertSee('Sets direction for the association.')
        ->assertSee($member->fullname);
});

it('says so rather than showing a blank where a member has no phone', function () {
    $committee = aCommittee();
    $committee->members()->attach(committeeMember()->id);

    $this->actingAs(committeeMember())
        ->get('/committees')
        ->assertOk()
        ->assertSee('No phone on file');
});

it('falls back to a guardian\'s phone for a member who has none', function () {
    $guardian = committeeMember(['phone' => '(555) 555-9999']);
    $child = committeeMember(['responsible_user_id' => $guardian->id]);

    // The accessor, not the column: this is what the directory renders.
    expect($child->fresh()->phone)->toBe('(555) 555-9999');
    expect($guardian->fresh()->phone)->toBe('(555) 555-9999');
});

it('keeps a members own phone in preference to their guardians', function () {
    $guardian = committeeMember(['phone' => '(555) 555-9999']);
    $child = committeeMember(['responsible_user_id' => $guardian->id, 'phone' => '(555) 555-1111']);

    expect($child->fresh()->phone)->toBe('(555) 555-1111');
});

it('lets a member save a phone number on their profile', function () {
    $user = committeeMember();

    $this->actingAs($user)
        ->put('/user/profile-information', [
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'phone' => '(555) 555-2222',
            'address1' => null, 'address2' => null, 'city' => null,
            'state' => null, 'zip' => null, 'school_id' => null,
            'rank_id' => null, 'dob' => null, 'height' => null,
            'weight' => null, 'sex' => null,
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->getAttributes()['phone'])->toBe('(555) 555-2222');
});
