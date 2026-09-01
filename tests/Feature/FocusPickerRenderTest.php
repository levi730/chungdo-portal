<?php

use App\Models\Event;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

// DatabaseTransactions rolls the rows back; without this the uploaded files
// would pile up in storage/app/public for real.
beforeEach(fn () => Storage::fake(config('media-library.disk_name')));

/**
 * The focal-point picker's Alpine component is defined once, in
 * partials/focus-picker-script, and included by both the event slideshow
 * partial and the product image partial.
 *
 * Both pickers are pure Blade + Alpine with no test of their own, so a rename
 * or a bad include would only show up as a blank admin page in production.
 * These render the two admin forms with an image actually attached — the only
 * condition under which either partial is included at all.
 */
function pickerAdmin(string $permission): User
{
    Permission::findOrCreate($permission, 'web');
    $user = User::factory()->create();
    $user->markEmailAsVerified();
    $user->givePermissionTo($permission);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

it('renders the event admin form with a slideshow image', function () {
    $event = Event::create(['name' => 'Picker Event', 'cost' => 0]);
    $event->addMedia(UploadedFile::fake()->image('slide.jpg'))
        ->toMediaCollection('slideshow-images');

    $this->actingAs(pickerAdmin('event.manage'))
        ->get(route('events.edit', $event))
        ->assertOk()
        ->assertSee('function focusPicker(config)', escape: false)
        ->assertSee('Adjust crop');
});

it('renders the product admin form with a product image', function () {
    $product = Product::create([
        'name' => 'Picker Product',
        'slug' => 'picker-product',
        'status' => Product::STATUS_ACTIVE,
        'stripe_account' => 'association',
    ]);
    $product->addMedia(UploadedFile::fake()->image('shirt.jpg'))
        ->toMediaCollection('product-images');

    $this->actingAs(pickerAdmin('store.manage'))
        ->get(route('products.edit', $product))
        ->assertOk()
        ->assertSee('function focusPicker(config)', escape: false)
        ->assertSee('Adjust crop');
});
