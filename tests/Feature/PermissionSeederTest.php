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
 * event.manageAddons, having already run its destructive prune.
 *
 * NONE OF THESE TESTS REPRODUCE THAT FAILURE. They were checked by removing the
 * cache reset from the seeder, and they still pass. The suite runs with
 * CACHE_STORE=array (phpunit.xml), where Spatie's permission cache lives and
 * dies inside one process; production uses a persistent store where the cached
 * list survives between requests, which is the only material difference found.
 * The cache reset in the seeder is Spatie's documented requirement and is
 * therefore kept — but treat it as unproven for that environment, not as a
 * demonstrated fix.
 *
 * What these tests DO pin is the behaviour that stops it being destructive:
 * unmanaged permissions and their grants survive, and a full run leaves
 * event.admin holding every permission it is supposed to have.
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

it('grants every listed permission when most of them do not exist yet', function () {
    // Production's exact starting state on 2026-08-31: the event.admin role
    // existed holding only the two permissions that had ever been created, and
    // the other four rows were absent. The seeder created them, then failed
    // assigning the first NEWLY created one, having assigned the two that
    // pre-dated the run — which is precisely where it stopped.
    $existing = ['event.viewAllSchoolRegistrants', 'event.reorganizeDivisions'];

    $role = Role::findOrCreate('event.admin', 'web');
    foreach ($existing as $name) {
        $role->givePermissionTo(Permission::findOrCreate($name, 'web'));
    }

    // Load the cache while only those two exist, as any earlier query would.
    warmPermissionCache();

    $this->seed(PermissionSeeder::class);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Role::findByName('event.admin', 'web')->permissions->pluck('name')->sort()->values()->all())
        ->toBe([
            'event.approveRefunds',
            'event.manage',
            'event.manageAddons',
            'event.reorganizeDivisions',
            'event.viewAllSchoolRegistrants',
            'store.manage',
        ]);
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
