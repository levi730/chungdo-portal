<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
                ],
            ],
        ];

        $this->permissions = [
            'event.viewAllSchoolRegistrants',
            'event.reorganizeDivisions',
            'event.manageAddons',
            'event.approveRefunds',
            'event.manage',
        ];

        $this->buildPermissions();

        $this->buildRoles();

        $this->setUsers();

    }

    public function buildPermissions()
    {
        $good_ids = [];
        foreach ($this->permissions as $perm) {
            $perm = Permission::firstOrCreate([
                'name' => $perm,
            ]);
            $good_ids[] = $perm->id;

        }

        Permission::whereNotIn('id', $good_ids)->delete();
    }

    public function buildRoles()
    {
        $good_ids = [];
        foreach ($this->roles as $role) {
            $rolerec = Role::firstOrCreate([
                'name' => $role['name'],
            ]);
            $good_ids[] = $rolerec->id;
            $cur_perms = $rolerec->permissions->pluck('name');

            if (array_key_exists('permissions', $role)) {
                foreach ($role['permissions'] as $perm) {
                    if (! $rolerec->hasPermissionTo($perm)) {
                        $rolerec->givePermissionTo($perm);
                    }
                }
            }

            if ($cur_perms) {
                foreach ($cur_perms as $perm) {
                    if ($rolerec->hasPermissionTo($perm)) {
                        if (! in_array($perm, $role['permissions'])) {
                            $rolerec->revokePermissionTo($perm);
                        }
                    }
                }
            }
        }
    }

    public function setUsers()
    {
        foreach ($this->users as $name => $user) {
            $userrec = User::where('email', '=', $user['email'])->first();
            var_dump($userrec);
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
