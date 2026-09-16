<?php

use App\Models\Committee;
use App\Models\User;
use App\Models\UserNote;
use App\Services\Coordinator;
use App\Services\RoleAssignment;
use App\Services\ZulipGroupResolver;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Committees as permission holders, and the coordinator position.
 *
 * A committee confers roles on its members for as long as they sit on it.
 * Nothing is written onto the user, so the interesting cases are the ones where
 * a stale copy would have shown: access appearing the moment someone is added,
 * and disappearing the moment they are removed.
 *
 * The coordinator is the top of the organisation chart and deliberately not the
 * top of the technical one — the tests below pin both halves of that, because
 * "super duper admin but not super.admin" is only worth anything if the second
 * half actually holds.
 */
/**
 * Make $user the coordinator the way production does it — by config, not by a
 * role assignment. Nothing is written to model_has_roles, on purpose.
 */
function seatCoordinator(User $user): User
{
    config(['portal.coordinator_user_id' => $user->id]);
    app()->forgetInstance(Coordinator::class);

    return $user->fresh();
}

function committeeWithRole(string $roleName, array $permissions = []): Committee
{
    static $n = 0;
    $n++;

    $role = Role::findOrCreate($roleName, 'web');

    foreach ($permissions as $permission) {
        $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    $committee = Committee::create([
        'name' => "Test Committee {$n}",
        'slug' => "test-committee-{$n}",
    ]);

    $committee->roles()->attach($role->id);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $committee;
}

it('confers the committee roles permissions on a member', function () {
    $committee = committeeWithRole('committee.events', ['event.manage']);
    $user = User::factory()->create();

    expect($user->can('event.manage'))->toBeFalse();

    $committee->members()->attach($user->id);

    expect($user->fresh()->can('event.manage'))->toBeTrue();
});

it('takes the access away again when the member is removed', function () {
    $committee = committeeWithRole('committee.events2', ['event.manage']);
    $user = User::factory()->create();
    $committee->members()->attach($user->id);

    expect($user->fresh()->can('event.manage'))->toBeTrue();

    $committee->members()->detach($user->id);

    // Nothing was ever written onto the user, so there is nothing to clean up.
    expect($user->fresh()->can('event.manage'))->toBeFalse();
});

it('grants permissions without granting the role name', function () {
    $committee = committeeWithRole('committee.events3', ['event.manage']);
    $user = User::factory()->create();
    $committee->members()->attach($user->id);
    $user = $user->fresh();

    // Documented consequence of computing rather than materialising: authorize
    // on permissions, never on hasRole(), or committee members are missed.
    expect($user->can('event.manage'))->toBeTrue()
        ->and($user->hasRole('committee.events3'))->toBeFalse();
});

it('does not let the committee hook deny an ability granted elsewhere', function () {
    // The Gate::before hook must return null on a miss, not false: returning
    // false would deny outright and short-circuit every check behind it.
    Permission::findOrCreate('event.manage', 'web');
    $user = User::factory()->create();
    $user->givePermissionTo('event.manage');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($user->fresh()->can('event.manage'))->toBeTrue();
});

it('lets a committee-granted event admin edit someone elses note', function () {
    $committee = committeeWithRole('committee.events4', ['event.manage']);
    $author = User::factory()->create();
    $member = User::factory()->create();
    $committee->members()->attach($member->id);

    $note = new UserNote([
        'user_id' => $author->id,
        'added_by' => $author->id,
        'note' => 'Cannot stay for finals.',
        'scope' => 'temporary',
    ]);
    $note->save();

    // UserNotePolicy used to check hasRole('event.admin'), which this user does
    // not have and never will.
    expect($member->fresh()->can('update', $note))->toBeTrue();
});

it('gives the coordinator user administration but not the technical tier', function () {
    $this->seed(Database\Seeders\PermissionSeeder::class);

    $coordinator = seatCoordinator(User::factory()->create());
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // No role assignment anywhere — the position is config alone.
    expect($coordinator->hasRole('coordinator'))->toBeFalse();

    expect($coordinator->can('manage-users'))->toBeTrue()
        ->and($coordinator->can('users.manage'))->toBeTrue()
        ->and($coordinator->can('event.manage'))->toBeTrue()
        ->and($coordinator->can('store.manage'))->toBeTrue()
        ->and($coordinator->can('school.manage'))->toBeTrue();

    // The allowlist is the whole point: an ability nobody granted stays denied,
    // where super.admin would have passed it through the before() hook.
    expect($coordinator->can('some.future.permission'))->toBeFalse()
        ->and($coordinator->hasRole('super.admin'))->toBeFalse();
});

it('will not let a coordinator hand out super.admin', function () {
    $this->seed(Database\Seeders\PermissionSeeder::class);

    $coordinator = seatCoordinator(User::factory()->create());
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super.admin');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(RoleAssignment::assignableBy($coordinator)->contains('super.admin'))->toBeFalse()
        ->and(RoleAssignment::assignableBy($superAdmin->fresh())->contains('super.admin'))->toBeTrue();

    expect(RoleAssignment::conferrableByCommittee()->contains('super.admin'))->toBeFalse();
});

it('offers the coordinator role on no surface at all', function () {
    $this->seed(Database\Seeders\PermissionSeeder::class);

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super.admin');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Not even a super.admin may tick it, and no committee may confer it: the
    // role row exists only to carry the permission list.
    expect(RoleAssignment::assignableBy($superAdmin->fresh())->contains('coordinator'))->toBeFalse()
        ->and(RoleAssignment::conferrableByCommittee()->contains('coordinator'))->toBeFalse();
});

it('moves the position when the config changes, and vacates it when unset', function () {
    $this->seed(Database\Seeders\PermissionSeeder::class);

    $first = User::factory()->create();
    $second = User::factory()->create();

    seatCoordinator($first);
    expect($first->fresh()->can('users.manage'))->toBeTrue()
        ->and($second->fresh()->can('users.manage'))->toBeFalse();

    seatCoordinator($second);
    expect($second->fresh()->can('users.manage'))->toBeTrue()
        ->and($first->fresh()->can('users.manage'))->toBeFalse();

    // Unset fails closed rather than open.
    config(['portal.coordinator_user_id' => null]);
    app()->forgetInstance(Coordinator::class);
    expect($first->fresh()->can('users.manage'))->toBeFalse()
        ->and($second->fresh()->can('users.manage'))->toBeFalse();
});

it('grants nothing when a stray coordinator role assignment exists', function () {
    $this->seed(Database\Seeders\PermissionSeeder::class);

    // Somebody assigns the role directly in the database. It must confer
    // nothing — config is the only thing that seats the position.
    $impostor = User::factory()->create();
    $impostor->assignRole('coordinator');
    config(['portal.coordinator_user_id' => null]);
    app()->forgetInstance(Coordinator::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($impostor->fresh()->can('users.manage'))->toBeFalse()
        ->and($impostor->fresh()->can('manage-users'))->toBeFalse();
});

it('keeps a role the editor cannot assign when that editor saves the form', function () {
    $this->seed(Database\Seeders\PermissionSeeder::class);

    $coordinator = seatCoordinator(User::factory()->create());
    $coordinator->markEmailAsVerified();

    $target = User::factory()->create(['is_student' => 1]);
    $target->assignRole('super.admin');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // super.admin was never rendered on the form, so it comes back absent
    // rather than unticked. Saving must not read that as "remove it".
    $this->actingAs($coordinator->fresh())
        ->put(route('admin.users.update', $target), [
            'firstname' => $target->firstname,
            'lastname' => $target->lastname,
            'email' => $target->email,
            'roles' => ['event.admin'],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($target->fresh()->hasRole('super.admin'))->toBeTrue()
        ->and($target->fresh()->hasRole('event.admin'))->toBeTrue();
});

it('puts the coordinator in every committee zulip group', function () {
    $committee = committeeWithRole('committee.events5', []);

    $coordinator = seatCoordinator(User::factory()->create());

    $groups = app(ZulipGroupResolver::class)->for($coordinator);

    expect($groups)->toContain($committee->slug);

    // Reach, not membership: the coordinator is not on the published roster.
    expect($committee->members()->pluck('users.id')->all())->not->toContain($coordinator->id);
});

it('renders the admin user edit page', function () {
    // Regression: the edit action read $request without taking it as a
    // parameter, so every user edit page 500'd in production while the whole
    // suite stayed green — nothing here had ever GET'd the page, only PUT to
    // the update action. Render it.
    $this->seed(Database\Seeders\PermissionSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('super.admin');
    $admin->markEmailAsVerified();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $target = User::factory()->create(['is_student' => 1]);

    $this->actingAs($admin->fresh())
        ->get(route('admin.users.edit', $target))
        ->assertOk()
        ->assertSee('Roles');
});

it('shows a read-only coordinator notice on the coordinator edit page', function () {
    $this->seed(Database\Seeders\PermissionSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('super.admin');
    $admin->markEmailAsVerified();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $coordinator = seatCoordinator(User::factory()->create(['is_student' => 1]));
    $other = User::factory()->create(['is_student' => 1]);

    // The position appears on no checkbox, so the page has to say it some
    // other way or there is no way to see who holds it.
    $this->actingAs($admin->fresh())
        ->get(route('admin.users.edit', $coordinator))
        ->assertOk()
        ->assertSee('Coordinator');

    $this->actingAs($admin->fresh())
        ->get(route('admin.users.edit', $other))
        ->assertOk()
        ->assertDontSee('coordinator position', false);
});
