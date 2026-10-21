<?php

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Saving a dependent (no login) from the admin user edit form.
 *
 * A dependent usually has no email or address of its own and inherits the
 * guardian's through the accessors. The form used to show those inherited
 * values, require the email, and then refuse it as already taken by the
 * guardian — so no child could be saved at all, and had the check passed, the
 * save would have copied the guardian's details onto the child.
 */
function dependentEditAdmin(): User
{
    Role::findOrCreate('super.admin', 'web');
    $user = User::factory()->create();
    $user->markEmailAsVerified();
    $user->assignRole('super.admin');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function householdWithChild(): array
{
    $guardian = User::factory()->create(['can_login' => 1, 'city' => 'Guardianville']);
    $child = User::factory()->is_kid()->create([
        'can_login' => 0,
        'responsible_user_id' => $guardian->id,
        'city' => null,
    ]);
    // Creating with responsible_user_id makes the primary guardianship.
    expect($child->primaryGuardian()?->id)->toBe($guardian->id);

    return [$guardian, $child];
}

function childForm(User $child, array $overrides = []): array
{
    return array_merge([
        'firstname' => $child->firstname,
        'lastname' => $child->lastname,
        'email' => '',
        'sex' => $child->sex ?? 'F',
    ], $overrides);
}

it('saves a dependent with the email left empty, and it keeps inheriting', function () {
    [$guardian, $child] = householdWithChild();

    $this->actingAs(dependentEditAdmin())
        ->put(route('admin.users.update', $child), childForm($child, ['firstname' => 'Renamed']))
        ->assertSessionHasNoErrors();

    $child->refresh();
    expect($child->firstname)->toBe('Renamed')
        ->and($child->getAttributes()['email'])->toBeNull()
        ->and($child->email)->toBe($guardian->email)
        ->and($child->getAttributes()['city'])->toBeNull();
});

it('treats the guardians own email on a dependent as inherited, not taken', function () {
    [$guardian, $child] = householdWithChild();

    $this->actingAs(dependentEditAdmin())
        ->put(route('admin.users.update', $child), childForm($child, ['email' => strtoupper($guardian->email)]))
        ->assertSessionHasNoErrors();

    expect($child->fresh()->getAttributes()['email'])->toBeNull();
});

it('still refuses a dependent an email that belongs to someone else', function () {
    [, $child] = householdWithChild();
    $stranger = User::factory()->create();

    $this->actingAs(dependentEditAdmin())
        ->put(route('admin.users.update', $child), childForm($child, ['email' => $stranger->email]))
        ->assertSessionHasErrors('email');
});

it('still requires an email on an account that can log in', function () {
    [$guardian] = householdWithChild();

    $this->actingAs(dependentEditAdmin())
        ->put(route('admin.users.update', $guardian), childForm($guardian))
        ->assertSessionHasErrors('email');
});

it('shows the inherited email as a placeholder, not a value the form would save', function () {
    [$guardian, $child] = householdWithChild();

    $this->actingAs(dependentEditAdmin())
        ->get(route('admin.users.edit', $child))
        ->assertOk()
        ->assertSee('placeholder="'.$guardian->email.'"', false)
        ->assertDontSee('value="'.$guardian->email.'"', false)
        ->assertDontSee('value="Guardianville"', false);
});
