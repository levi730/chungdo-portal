<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * STORE_MENU hides the Store item in the navigation while the store is being
 * set up, without deleting any code.
 *
 * It hides the MENU ONLY, deliberately: /store is a public route and someone
 * may already hold the link, so pulling it would break a URL that is already
 * out there. Taking something off sale is a Draft status or a closed run.
 */
function storeMenuUser(): User
{
    Permission::findOrCreate('store.manage', 'web');
    $user = User::factory()->create();
    $user->markEmailAsVerified();
    $user->givePermissionTo('store.manage');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

it('shows the Store menu when enabled', function () {
    config(['store.menu' => true]);

    $this->actingAs(storeMenuUser())
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Manage Products');
});

it('hides the Store menu when disabled', function () {
    config(['store.menu' => false]);

    $this->actingAs(storeMenuUser())
        ->get('/dashboard')
        ->assertOk()
        ->assertDontSee('Manage Products')
        ->assertDontSee('Shop the store');
});

it('leaves the storefront reachable by URL while the menu is hidden', function () {
    // Hiding the menu must not 404 a public page somebody may have linked to.
    config(['store.menu' => false]);

    $this->get(route('store.index'))->assertOk();
});

it('leaves the product admin reachable while the menu is hidden', function () {
    // The whole point is to keep working on the store while it is out of sight.
    config(['store.menu' => false]);

    $this->actingAs(storeMenuUser())
        ->get(route('products.index'))
        ->assertOk();
});

it('still hides the menu from someone without store.manage', function () {
    config(['store.menu' => true]);

    $user = User::factory()->create();
    $user->markEmailAsVerified();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertDontSee('Manage Products');
});
