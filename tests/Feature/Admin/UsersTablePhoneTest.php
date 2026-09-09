<?php

use App\Livewire\Admin\UsersTable;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The Phone column on the admin users table.
 *
 * It shows the number stored on the row, not the accessor's guardian fallback:
 * an admin list that quietly shows a parent's number under a child's name is
 * misleading, and the fallback costs a query per row.
 */
function userAdmin(): User
{
    Role::findOrCreate('super.admin', 'web');
    $user = User::factory()->create();
    $user->markEmailAsVerified();
    $user->assignRole('super.admin');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

it('shows a members stored phone number in the table', function () {
    $member = User::factory()->create();
    \DB::table('users')->where('id', $member->id)->update(['phone' => '(555) 555-3333']);

    Livewire::actingAs(userAdmin())
        ->test(UsersTable::class)
        ->assertSee('Phone')
        ->assertSee('(555) 555-3333');
});

it('leaves the column blank for a member whose number is their guardians', function () {
    $guardian = User::factory()->create();
    \DB::table('users')->where('id', $guardian->id)->update(['phone' => '(555) 555-4444']);

    $child = User::factory()->create();
    \DB::table('users')
        ->where('id', $child->id)
        ->update(['responsible_user_id' => $guardian->id, 'lastname' => 'Zzz-Inherits']);

    // The accessor still answers with the guardian's number everywhere else.
    expect($child->fresh()->phone)->toBe('(555) 555-4444');

    Livewire::actingAs(userAdmin())
        ->test(UsersTable::class)
        ->set('search', 'Zzz-Inherits')
        ->assertSee('Zzz-Inherits')
        ->assertDontSee('(555) 555-4444');
});
