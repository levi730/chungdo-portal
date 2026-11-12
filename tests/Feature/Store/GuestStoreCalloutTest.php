<?php

use App\Models\Product;

/**
 * A guest who types chungdo.org lands on /first-visit (first time) or /login
 * (every time after). Neither said a word about the store, so the only way in
 * was a link someone handed you. The callout fixes that — but only while the
 * store is switched on and has something to sell.
 */
function calloutProduct(array $attrs = [], array $runAttrs = []): Product
{
    static $n = 0;
    $n++;

    $product = Product::create(array_merge([
        'name' => "Callout Shirt {$n}",
        'slug' => "callout-shirt-{$n}",
        'status' => Product::STATUS_ACTIVE,
        'stripe_account' => 'association',
    ], $attrs));

    $run = $product->runs()->create(array_merge(['name' => "Run {$n}"], $runAttrs));
    $run->variants()->create(['name' => 'Item', 'price' => 20]);

    return $product->fresh();
}

beforeEach(function () {
    // The test database may hold products of its own; start with none on sale.
    Product::query()->update(['status' => Product::STATUS_DRAFT]);
    config(['store.menu' => true]);
});

it('links to the store from the login page when something is on sale', function () {
    $product = calloutProduct();

    $this->get('/login')
        ->assertOk()
        ->assertSee('data-store-callout', false)
        ->assertSee(route('store.index'), false)
        ->assertSee($product->name);
});

it('links to the store from the first-visit page', function () {
    calloutProduct();

    $this->get('/first-visit')
        ->assertOk()
        ->assertSee('data-store-callout', false);
});

it('shows nothing when the store is switched off', function () {
    config(['store.menu' => false]);
    calloutProduct();

    $this->get('/login')->assertOk()->assertDontSee('data-store-callout', false);
});

it('shows nothing when no product can be ordered', function () {
    calloutProduct(['status' => Product::STATUS_DRAFT]);
    calloutProduct([], ['closes_at' => now()->subDay()]);

    $this->get('/login')->assertOk()->assertDontSee('data-store-callout', false);
});
