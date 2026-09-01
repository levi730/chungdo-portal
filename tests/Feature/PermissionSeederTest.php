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
 * ROOT CAUSE, confirmed: a stale Spatie permission cache that the deploying
 * user could not invalidate. Not a code bug at all — a file ownership one.
 *
 * cache.default is `file`. storage/framework/cache/data/* is written by the web
 * user (www-data) as drwxr-xr-x, and artisan runs as a different user. Removing
 * a file needs write permission on its containing directory, so the CLI could
 * READ the cached permission list — written back when only two permissions
 * existed — but every attempt to invalidate it silently failed.
 *
 * The chain: the seeder created four permissions, could not clear the cache, so
 * givePermissionTo() resolved against the stale list and threw
 * PermissionDoesNotExist for a row that existed. Then
 * Permission::findOrCreate() missed the same way and attempted an INSERT, which
 * MySQL rejected for violating permissions_name_guard_name_unique — the
 * database proving the row was there while Spatie insisted it wasn't. Then
 * `php artisan permission:cache-reset` reported "Unable to flush cache",
 * because Cache::forget() returned false on a file it could not unlink.
 *
 * So the seeder's cache reset only helps when the process running it can
 * actually write to the cache. The real fix is on the server: the cache tree
 * needs to be group-writable with the setgid bit, or artisan has to run as the
 * web user. Anything that resets a cache — this seeder, cache:clear,
 * config:cache — is broken on that host until it is.
 *
 * NONE OF THESE TESTS REPRODUCE IT, and that is a property of the environment
 * rather than an oversight: the suite runs CACHE_STORE=array (phpunit.xml),
 * where the cache lives and dies inside one process and cannot go stale between
 * requests. Reproducing it would mean a persistent cache store in the test
 * environment. Removing the seeder's cache reset still leaves everything below
 * green, so do not read these as covering the bug.
 *
 * What they DO pin is the behaviour that stops the seeder being destructive:
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
