<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sign-in respects can_login.
 *
 * A dependent (can_login = 0) is refused at the form even with the right
 * password, and a session it already holds is ended on its next request. An
 * admin impersonating a dependent keeps working — that session is the admin's.
 */
function accountWithPassword(bool $canLogin): User
{
    $user = User::factory()->create([
        'can_login' => $canLogin ? 1 : 0,
        'password' => Hash::make('right-Password-123'),
    ]);
    $user->markEmailAsVerified();

    return $user;
}

it('signs in an account that can log in', function () {
    $user = accountWithPassword(true);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'right-Password-123']);

    $this->assertAuthenticatedAs($user);
});

it('refuses an account that cannot log in, even with the right password', function () {
    $user = accountWithPassword(false);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'right-Password-123'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('gives the same message as a wrong password, so it reveals nothing', function () {
    $dependent = accountWithPassword(false);
    $adult = accountWithPassword(true);

    $refused = $this->post(route('login'), ['email' => $dependent->email, 'password' => 'right-Password-123'])
        ->getSession()->get('errors')->first('email');
    $wrong = $this->post(route('login'), ['email' => $adult->email, 'password' => 'wrong-Password-123'])
        ->getSession()->get('errors')->first('email');

    expect($refused)->toBe($wrong);
});

it('ends a session an account that cannot log in already holds', function () {
    $user = accountWithPassword(false);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('leaves an admin impersonating a dependent signed in', function () {
    Role::findOrCreate('super.admin', 'web');
    $admin = accountWithPassword(true);
    $admin->assignRole('super.admin');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $dependent = accountWithPassword(false);

    $this->actingAs($admin)
        ->get(route('user.impersonate', $dependent));

    $this->get(route('dashboard'))->assertOk();
    $this->assertAuthenticatedAs($dependent);
});
