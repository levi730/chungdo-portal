<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    private $users = [];

    private $roles = [];

    private $permissions = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->users = [
            'admin' => [
                'email' => 'admin@example.com',
                'roles' => 'super.admin',
                //'permissions' => ['directperm1','directperm2']
            ],
            'eventadmin1' => [
                'email' => 'eventadmin1@example.com',
                'roles' => 'event.admin',
            ],
            'eventadmin2' => [
                'email' => 'eventadmin2@example.com',
                'roles' => 'event.admin',
            ],
            'eventadmin3' => [
                'email' => 'eventadmin3@example.com',
                'roles' => 'event.admin',
            ],
            'eventadmin4' => [
                'email' => 'eventadmin4@example.com',
                'roles' => 'event.admin',
            ],
            'eventadmin5' => [
                'email' => 'eventadmin5@example.com',
                'roles' => 'event.admin',
            ],
        ];

        $this->roles = [
            [
                'name' => 'super.admin',
            ],
            [
                'name' => 'event.admin',
                'permissions' => [
                    'event.viewAllSchoolRegistrants',
                    'event.reorganizeDivisions',
                    'event.manageAddons',
                    'event.approveRefunds',
                    'event.manage',
                    'store.manage',
                ],
            ],
        ];

        $this->permissions = [
            'event.viewAllSchoolRegistrants',
            'event.reorganizeDivisions',
            'event.manageAddons',
            'event.approveRefunds',
            'event.manage',
            // The merchandise store. Held by the same people who run events so
            // it isn't gated on manage-users, which is super.admin only.
            'store.manage',
            // Creating and archiving schools. Deliberately granted to no role
            // by default: editing a school's own details already belongs to its
            // instructors via SchoolPolicy, and this is the wider right to add
            // and remove schools. Grant it to individuals as needed.
            'school.manage',
        ];

        // Spatie caches the permission list for the life of the request. A
        // seeder that creates permissions and then assigns them has to reset
        // that cache in between, or the assignment fails with
        // PermissionDoesNotExist against a permission that demonstrably exists.
        $this->forgetPermissionCache();

        $this->buildPermissions();

        $this->forgetPermissionCache();

        $this->buildRoles();

        $this->setUsers();

        $this->forgetPermissionCache();
    }

    private function forgetPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function buildPermissions()
    {
        $good_ids = [];
        foreach ($this->permissions as $perm) {
            // findOrCreate is Spatie's own, and names the guard explicitly
            // rather than leaving it to a default that may not match the rows
            // already in the table.
            $good_ids[] = Permission::findOrCreate($perm, 'web')->id;
        }

        $stale = Permission::whereNotIn('id', $good_ids)->pluck('name', 'id');

        if ($stale->isEmpty()) {
            return;
        }

        // Deleting a permission cascades to role_has_permissions and
        // model_has_permissions, so pruning silently strips grants from roles
        // and users. It is off by default: this seeder is the only record of
        // which permissions are "known", and anything introduced outside it —
        // by a package, or by hand in production — looks stale to this list
        // without being wrong.
        if (! env('PERMISSION_SEEDER_PRUNE', false)) {
            $this->command?->warn(
                'Leaving '.$stale->count().' unmanaged permission(s) alone: '.$stale->values()->implode(', ')
            );
            $this->command?->warn('Add them to PermissionSeeder, or set PERMISSION_SEEDER_PRUNE=true to delete them.');

            return;
        }

        $this->command?->warn('Deleting unmanaged permission(s): '.$stale->values()->implode(', '));

        Permission::whereIn('id', $stale->keys())->delete();
    }

    public function buildRoles()
    {
        foreach ($this->roles as $role) {
            $rolerec = Role::findOrCreate($role['name'], 'web');

            // super.admin carries no permissions key — it passes everything via
            // the Gate::before hook in AppServiceProvider. Defaulting to an
            // empty list keeps the revoke loop below from reading a key that
            // isn't there.
            $wanted = $role['permissions'] ?? [];
            $current = $rolerec->permissions->pluck('name');

            foreach ($wanted as $perm) {
                if (! $current->contains($perm)) {
                    $rolerec->givePermissionTo($perm);
                }
            }

            foreach ($current as $perm) {
                if (! in_array($perm, $wanted, true)) {
                    $rolerec->revokePermissionTo($perm);
                }
            }
        }
    }

    public function setUsers()
    {
        foreach ($this->users as $name => $user) {
            $userrec = User::where('email', '=', $user['email'])->first();
            if ($userrec) {
                if (array_key_exists('roles', $user)) {
                    if ($user['roles']) {
                        $userrec->syncRoles($user['roles']);
                    }
                }

                if (array_key_exists('permissions', $user)) {
                    if ($user['permissions']) {
                        $userrec->syncPermissions($user['permissions']);
                    }
                }

            }
        }

    }
}
