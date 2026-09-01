<?php

use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The permission seeder is how store.manage (and every other permission) comes
 * into existence, so it has to survive being run against a real environment.
 *
 * It failed on production on 2026-08-31 with PermissionDoesNotExist for
 * event.manageAddons — a permission that existed — because Spatie caches the
 * permission list for the request and the seeder assigned permissions without
 * resetting that cache after creating them. It had already run its destructive
 * prune by that point.
 */
function warmPermissionCache(): void
{
    // Populate Spatie's cache, which is what production had and a fresh test
    // process does not.
    app(PermissionRegistrar::class)->getPermissions();
}

it('seeds cleanly when the permission cache is already warm', function () {
    warmPermissionCache();

    $this->seed(PermissionSeeder::class);

    expect(Permission::where('name', 'store.manage')->exists())->toBeTrue();
});

it('grants store.manage to the event admin role', function () {
    $this->seed(PermissionSeeder::class);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Role::findByName('event.admin', 'web')->hasPermissionTo('store.manage'))->toBeTrue();
});

it('is idempotent', function () {
    $this->seed(PermissionSeeder::class);
    $first = Permission::pluck('name')->sort()->values()->all();

    $this->seed(PermissionSeeder::class);

    expect(Permission::pluck('name')->sort()->values()->all())->toBe($first);
});

it('leaves a permission it does not manage alone', function () {
    // The old behaviour deleted anything absent from its hardcoded list, which
    // cascades to role_has_permissions and model_has_permissions — that is how
    // a live grant disappears without anyone touching it.
    Permission::findOrCreate('admin.sendEmails', 'web');

    $this->seed(PermissionSeeder::class);

    expect(Permission::where('name', 'admin.sendEmails')->exists())->toBeTrue();
});

it('keeps a role grant for an unmanaged permission', function () {
    $permission = Permission::findOrCreate('admin.sendEmails', 'web');
    $role = Role::findOrCreate('super.admin', 'web');
    $role->givePermissionTo($permission);

    $this->seed(PermissionSeeder::class);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // super.admin declares no permission list, so the seeder must not treat
    // "not listed" as "revoke everything".
    expect(Permission::where('name', 'admin.sendEmails')->exists())->toBeTrue();
});

it('does not throw when a role declares no permissions', function () {
    // super.admin has no 'permissions' key; the revoke loop used to read it
    // unguarded, which only surfaced once that role had a permission attached.
    $role = Role::findOrCreate('super.admin', 'web');
    $role->givePermissionTo(Permission::findOrCreate('event.manage', 'web'));

    $this->seed(PermissionSeeder::class);
})->throwsNoExceptions();
