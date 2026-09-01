<?php

use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\School;
use App\Models\User;
use App\Services\Store\Cart;
use App\Services\Store\OrderBuilder;

/**
 * Checkout up to the Stripe boundary.
 *
 * The charge itself isn't exercised here — that needs Stripe. What IS covered is
 * the rule that makes the whole flow safe: the order and its line items exist,
 * fully described, BEFORE anything is sent to Stripe. See
 * docs/payment-flow-pattern.md.
 */
function checkoutProduct(array $attrs = []): Product
{
    static $n = 0;
    $n++;

    $product = Product::create(array_merge([
        'name' => "Checkout Product {$n}",
        'slug' => "checkout-product-{$n}",
        'status' => Product::STATUS_ACTIVE,
        'stripe_account' => 'association',
        'option_names' => ['Item', 'Size'],
    ], $attrs));

    $run = $product->runs()->create([
        'name' => "Run {$n}",
        'expected_arrival_at' => now()->addMonth()->toDateString(),
        'pickup_note' => 'Pick up at your school.',
    ]);

    $run->variants()->create([
        'name' => 'Adult Hoodie / 3XL',
        'options' => ['Item' => 'Adult Hoodie', 'Size' => '3XL'],
        'price' => 48,
    ]);

    return $product->fresh();
}

function checkoutSchool(): School
{
    return School::create(['name' => 'Test Dojang '.uniqid(), 'shortname' => 'TD']);
}

function fillCart(Product $product, int $qty = 2): void
{
    $variant = $product->runs()->first()->variants()->first();
    app(Cart::class)->add($variant, $qty);
}

/* -------------------------------------------------------------------- *
 * The checkout page
 * -------------------------------------------------------------------- */

it('sends an empty cart back to the store', function () {
    $this->get(route('store.checkout'))
        ->assertRedirect(route('store.index'))
        ->assertSessionHas('error');
});

it('shows a guest the checkout page without card fields', function () {
    // Guests pay on Stripe's hosted page, so no Elements here.
    $product = checkoutProduct();
    checkoutSchool();
    $variant = $product->runs()->first()->variants()->first();

    $this->post(route('store.cart.add'), ['product_variant_id' => $variant->id, 'quantity' => 2]);

    $this->get(route('store.checkout'))
        ->assertOk()
        ->assertSee('Continue to payment')
        ->assertDontSee('card-number');
});

/* -------------------------------------------------------------------- *
 * The order is written before the charge
 * -------------------------------------------------------------------- */

it('writes the order and its items from the cart', function () {
    $product = checkoutProduct();
    $school = checkoutSchool();
    fillCart($product, 2);

    $order = app(OrderBuilder::class)->build([
        'name' => 'Jane Buyer',
        'email' => 'jane@example.com',
        'phone' => '555-1234',
        'pickup_school_id' => $school->id,
    ]);

    expect($order->status)->toBe(ProductOrder::STATUS_PENDING)
        ->and($order->reference)->not->toBeEmpty()
        ->and($order->user_id)->toBeNull()
        ->and($order->stripe_account)->toBe('association')
        ->and($order->pickup_school_id)->toBe($school->id)
        ->and((float) $order->subtotal)->toBe(96.0)
        ->and((float) $order->tax)->toBe(0.0)
        ->and((float) $order->total)->toBe(96.0)
        // No Stripe reference yet — the row exists before the charge.
        ->and($order->stripe_payment_intent_id)->toBeNull()
        ->and($order->items)->toHaveCount(1);

    $item = $order->items->first();

    expect($item->product_name)->toBe($product->name)
        ->and($item->variant_name)->toBe('Adult Hoodie / 3XL')
        ->and((float) $item->unit_price)->toBe(48.0)
        ->and($item->quantity)->toBe(2)
        ->and((float) $item->amount)->toBe(96.0)
        ->and($item->product_run_id)->toBe($product->runs()->first()->id);
});

it('snapshots the price so a later edit cannot rewrite history', function () {
    $product = checkoutProduct();
    $school = checkoutSchool();
    fillCart($product, 1);

    $order = app(OrderBuilder::class)->build([
        'name' => 'Jane', 'email' => 'jane@example.com', 'pickup_school_id' => $school->id,
    ]);

    $product->runs()->first()->variants()->first()->update(['price' => 99]);

    expect((float) $order->fresh()->items->first()->unit_price)->toBe(48.0)
        ->and((float) $order->fresh()->subtotal)->toBe(48.0);
});

it('attaches the order to a signed-in buyer', function () {
    $product = checkoutProduct();
    $school = checkoutSchool();
    $user = User::factory()->create();
    fillCart($product, 1);

    $order = app(OrderBuilder::class)->build([
        'name' => 'Member', 'email' => $user->email, 'pickup_school_id' => $school->id,
    ], $user);

    expect($order->user_id)->toBe($user->id);
});

it('refuses to build an order from an empty cart', function () {
    expect(fn () => app(OrderBuilder::class)->build([
        'name' => 'Nobody', 'email' => 'n@example.com', 'pickup_school_id' => checkoutSchool()->id,
    ]))->toThrow(RuntimeException::class);
});

it('carries every line of a multi-product cart', function () {
    $shirts = checkoutProduct();
    $bags = checkoutProduct();
    $school = checkoutSchool();

    fillCart($shirts, 2);
    fillCart($bags, 1);

    $order = app(OrderBuilder::class)->build([
        'name' => 'Jane', 'email' => 'jane@example.com', 'pickup_school_id' => $school->id,
    ]);

    expect($order->items)->toHaveCount(2)
        ->and((float) $order->subtotal)->toBe(144.0)
        ->and($order->items->pluck('product_run_id')->unique())->toHaveCount(2);
});

/* -------------------------------------------------------------------- *
 * Validation
 * -------------------------------------------------------------------- */

it('requires a pickup school', function () {
    $product = checkoutProduct();
    fillCart($product);

    $this->post(route('store.checkout.store'), [
        'name' => 'Jane',
        'email' => 'jane@example.com',
    ])->assertSessionHasErrors('pickup_school_id');

    expect(ProductOrder::count())->toBe(0);
});

it('requires a name and email', function () {
    $product = checkoutProduct();
    $school = checkoutSchool();
    fillCart($product);

    $this->post(route('store.checkout.store'), ['pickup_school_id' => $school->id])
        ->assertSessionHasErrors(['name', 'email']);

    expect(ProductOrder::count())->toBe(0);
});

it('will not check out an empty cart', function () {
    $this->post(route('store.checkout.store'), [
        'name' => 'Jane', 'email' => 'jane@example.com', 'pickup_school_id' => checkoutSchool()->id,
    ])->assertSessionHas('error');

    expect(ProductOrder::count())->toBe(0);
});

/* -------------------------------------------------------------------- *
 * The completion page
 * -------------------------------------------------------------------- */

it('shows a paid order to whoever holds the reference', function () {
    $product = checkoutProduct();
    $school = checkoutSchool();
    fillCart($product, 1);

    $order = app(OrderBuilder::class)->build([
        'name' => 'Jane', 'email' => 'jane@example.com', 'pickup_school_id' => $school->id,
    ]);
    $order->update(['status' => ProductOrder::STATUS_PAID, 'paid_at' => now()]);

    $this->get(route('store.complete', $order->reference))
        ->assertOk()
        ->assertSee($order->reference)
        ->assertSee('your order is in', false);
});

it('reassures rather than alarms when an order is still pending', function () {
    // The webhook and the sweep will finish it; telling the buyer it failed
    // would invite a second payment.
    $product = checkoutProduct();
    $school = checkoutSchool();
    fillCart($product, 1);

    $order = app(OrderBuilder::class)->build([
        'name' => 'Jane', 'email' => 'jane@example.com', 'pickup_school_id' => $school->id,
    ]);

    $this->get(route('store.complete', $order->reference))
        ->assertOk()
        ->assertSee('confirming your payment', false)
        ->assertSee('No need to pay again');
});

it('404s an unknown order reference', function () {
    $this->get(route('store.complete', 'not-a-real-reference'))->assertNotFound();
});
