<?php

use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\ProductRun;
use App\Models\ProductVariant;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The product admin — the DESIGN. Ordering windows, prices and variants belong
 * to a print run and are covered in ProductRunTest.
 *
 * Two configured accounts, so the account select has something to choose
 * between and the lock has somewhere to refuse to move to.
 */
beforeEach(function () {
    config([
        'services.stripe.default_account' => 'association',
        'services.stripe.accounts' => [
            'association' => [
                'label' => 'Association Account',
                'key' => 'pk_test_assoc',
                'secret' => 'sk_test_assoc',
                'webhook_secret' => 'whsec_assoc',
            ],
            'main_school' => [
                'label' => 'Main School Account',
                'key' => 'pk_test_school',
                'secret' => 'sk_test_school',
                'webhook_secret' => 'whsec_school',
            ],
        ],
    ]);
});

/**
 * Helpers are local to this file (rather than reusing another test's) so it can
 * be run on its own.
 */
function storeManager(): User
{
    Permission::findOrCreate('store.manage', 'web');
    $user = User::factory()->create();
    $user->markEmailAsVerified();
    $user->givePermissionTo('store.manage');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function plainUser(): User
{
    $user = User::factory()->create();
    $user->markEmailAsVerified();

    return $user;
}

function adminProduct(array $attrs = []): Product
{
    static $n = 0;
    $n++;

    return Product::create(array_merge([
        'name' => "Admin Product {$n}",
        'slug' => "admin-product-{$n}",
        'status' => Product::STATUS_ACTIVE,
        'stripe_account' => 'association',
    ], $attrs));
}

/** The minimum valid product form payload. */
function productPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Chung Do 2026 Design',
        'status' => Product::STATUS_ACTIVE,
        'stripe_account' => 'association',
        'highlighted' => 0,
        'highlight_order' => 0,
        'sort_order' => 0,
        'option_names' => 'Item, Size',
    ], $overrides);
}

/** A charge attempted against $product, so the Stripe account locks. */
function chargeAgainst(Product $product): ProductOrder
{
    $run = $product->runs()->first() ?? $product->runs()->create(['name' => 'Run']);
    $variant = $run->variants()->first() ?? $run->variants()->create(['name' => 'Item', 'price' => 25]);

    $order = ProductOrder::create([
        'status' => ProductOrder::STATUS_PENDING,
        'stripe_account' => $product->stripe_account,
        'stripe_payment_intent_id' => 'pi_'.uniqid(),
        'email' => 'buyer@example.com',
        'name' => 'Buyer',
        'subtotal' => 25,
        'total' => 25,
        'payload' => ['items' => []],
    ]);

    ProductOrderItem::create([
        'product_order_id' => $order->id,
        'product_id' => $product->id,
        'product_run_id' => $run->id,
        'product_variant_id' => $variant->id,
        'product_name' => $product->name,
        'variant_name' => $variant->name,
        'unit_price' => 25,
        'quantity' => 1,
        'amount' => 25,
    ]);

    return $order;
}

/* -------------------------------------------------------------------- *
 * Access
 * -------------------------------------------------------------------- */

it('keeps the store admin away from users without store.manage', function () {
    $this->actingAs(plainUser())->get(route('products.index'))->assertForbidden();
    $this->actingAs(plainUser())->get(route('products.create'))->assertForbidden();
    $this->actingAs(plainUser())->post(route('products.store'), productPayload())->assertForbidden();
});

it('does not gate the store on the event permission', function () {
    // store.manage is its own permission — someone granted only event.manage
    // has no business in the store, and vice versa.
    Permission::findOrCreate('event.manage', 'web');
    $user = plainUser();
    $user->givePermissionTo('event.manage');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($user)->get(route('products.index'))->assertForbidden();
});

it('shows the index and the form to a store manager', function () {
    $product = adminProduct();
    $product->runs()->create(['name' => 'A run']);

    $this->actingAs(storeManager())->get(route('products.index'))->assertOk();
    $this->actingAs(storeManager())->get(route('products.create'))->assertOk();
    $this->actingAs(storeManager())->get(route('products.edit', $product))->assertOk();
});

/* -------------------------------------------------------------------- *
 * The design itself
 * -------------------------------------------------------------------- */

it('creates a product and auto-generates its slug', function () {
    $this->actingAs(storeManager())
        ->post(route('products.store'), productPayload())
        ->assertRedirect();

    $product = Product::where('name', 'Chung Do 2026 Design')->firstOrFail();

    expect($product->slug)->toBe('chung-do-2026-design')
        ->and($product->option_names)->toBe(['Item', 'Size'])
        // A new design has no run, so nothing is for sale yet.
        ->and($product->runs)->toHaveCount(0)
        ->and($product->isOrderable())->toBeFalse();
});

it('trims and de-duplicates the option axes', function () {
    $this->actingAs(storeManager())
        ->post(route('products.store'), productPayload(['option_names' => ' Item ,, Size , Item ']))
        ->assertRedirect();

    expect(Product::latest('id')->first()->option_names)->toBe(['Item', 'Size']);
});

it('rejects a slug that another product already holds', function () {
    adminProduct(['slug' => 'taken-slug']);

    $this->actingAs(storeManager())
        ->post(route('products.store'), productPayload(['slug' => 'taken-slug']))
        ->assertSessionHasErrors('slug');
});

/* -------------------------------------------------------------------- *
 * Stripe account lock
 * -------------------------------------------------------------------- */

it('lets the Stripe account be set before any charge', function () {
    $product = adminProduct(['stripe_account' => 'association']);

    $this->actingAs(storeManager())->put(route('products.update', $product), productPayload([
        'name' => $product->name,
        'stripe_account' => 'main_school',
    ]))->assertRedirect();

    expect($product->fresh()->stripe_account)->toBe('main_school');
});

it('rejects a Stripe account change once a charge has been attempted', function () {
    $product = adminProduct(['stripe_account' => 'association']);
    chargeAgainst($product);

    $this->actingAs(storeManager())
        ->put(route('products.update', $product), productPayload([
            'name' => $product->name,
            'stripe_account' => 'main_school',
        ]))
        ->assertSessionHasErrors('stripe_account');

    expect($product->fresh()->stripe_account)->toBe('association');
});

it('still saves the rest of the product while the account is locked', function () {
    // The locked form re-posts the current account in a hidden field, so an
    // unchanged value must not trip the error.
    $product = adminProduct(['stripe_account' => 'association']);
    chargeAgainst($product);

    $this->actingAs(storeManager())
        ->put(route('products.update', $product), productPayload([
            'name' => 'Renamed',
            'slug' => $product->slug,
            'stripe_account' => 'association',
        ]))
        ->assertSessionHasNoErrors();

    expect($product->fresh()->name)->toBe('Renamed');
});

/* -------------------------------------------------------------------- *
 * Archive
 * -------------------------------------------------------------------- */

it('archives and restores a product', function () {
    $product = adminProduct();

    $this->actingAs(storeManager())->delete(route('products.destroy', $product))->assertRedirect();
    expect(Product::find($product->id))->toBeNull()
        ->and(Product::withTrashed()->find($product->id)->trashed())->toBeTrue();

    $this->actingAs(storeManager())->post(route('products.restore', $product->id))->assertRedirect();
    expect(Product::find($product->id))->not->toBeNull();
});

it('counts variants across every run of the design', function () {
    $product = adminProduct();
    $first = $product->runs()->create(['name' => 'First']);
    $second = $product->runs()->create(['name' => 'Second']);

    ProductVariant::create(['product_run_id' => $first->id, 'name' => 'A', 'price' => 10]);
    ProductVariant::create(['product_run_id' => $second->id, 'name' => 'B', 'price' => 10]);
    ProductVariant::create(['product_run_id' => $second->id, 'name' => 'C', 'price' => 10]);

    expect($product->variants()->count())->toBe(3)
        ->and($first->variants()->count())->toBe(1);
});

it('reaches the design from a variant through its run', function () {
    $product = adminProduct();
    $run = ProductRun::create(['product_id' => $product->id, 'name' => 'Only']);
    $variant = ProductVariant::create(['product_run_id' => $run->id, 'name' => 'A', 'price' => 10]);

    expect($variant->product->id)->toBe($product->id)
        ->and($variant->run->id)->toBe($run->id);
});
