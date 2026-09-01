<?php

use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\ProductRun;
use App\Models\ProductVariant;

/**
 * Create an active product with one open run holding one variant.
 *
 * $attrs go to the product; $runAttrs to its run, so a test can shift the
 * ordering window without knowing the run exists.
 */
function storeProduct(array $attrs = [], float $price = 25, array $runAttrs = []): Product
{
    static $n = 0;
    $n++;

    $product = Product::create(array_merge([
        'name' => "Product {$n}",
        'slug' => "product-{$n}",
        'status' => Product::STATUS_ACTIVE,
        'stripe_account' => 'association',
    ], $attrs));

    $run = $product->runs()->create(array_merge([
        'name' => "Run {$n}",
    ], $runAttrs));

    ProductVariant::create([
        'product_run_id' => $run->id,
        'name' => 'Medium',
        'options' => ['Item' => 'T-Shirt', 'Size' => 'M'],
        'price' => $price,
    ]);

    return $product->fresh();
}

/**
 * An order carrying one line for $product, with a Stripe id attached (i.e. a
 * charge was attempted).
 */
function storeOrderFor(Product $product, array $attrs = []): ProductOrder
{
    $order = ProductOrder::create(array_merge([
        'status' => ProductOrder::STATUS_PENDING,
        'stripe_account' => $product->stripe_account,
        'stripe_payment_intent_id' => 'pi_'.uniqid(),
        'email' => 'buyer@example.com',
        'name' => 'Buyer',
        'subtotal' => 25,
        'total' => 25,
        'payload' => ['items' => []],
    ], $attrs));

    $run = $product->runs()->first();
    $variant = $run?->variants()->first();

    ProductOrderItem::create([
        'product_order_id' => $order->id,
        'product_id' => $product->id,
        'product_run_id' => $run?->id,
        'product_variant_id' => $variant?->id,
        'product_name' => $product->name,
        'variant_name' => $variant?->name ?? 'Item',
        'unit_price' => 25,
        'quantity' => 1,
        'amount' => 25,
    ]);

    return $order->fresh();
}

it('gives every order a reference without being told to', function () {
    $order = storeOrderFor(storeProduct());

    expect($order->reference)->not->toBeEmpty()
        ->and(strlen($order->reference))->toBe(36);
});

it('shows featured products highest highlight_order first', function () {
    $low = storeProduct(['highlighted' => true, 'highlight_order' => 1]);
    $high = storeProduct(['highlighted' => true, 'highlight_order' => 9]);

    expect(Product::forHomepage()->pluck('id')->all())
        ->toBe([$high->id, $low->id]);
});

it('shows nothing on the home page when nothing is featured', function () {
    storeProduct();
    storeProduct();

    expect(Product::forHomepage())->toHaveCount(0);
});

it('shows every featured product, dropping none', function () {
    // The checkbox is the only way a product reaches the home page, so a cap
    // would silently override an explicit choice by whoever ticked it.
    foreach (range(1, 5) as $i) {
        storeProduct(['highlighted' => true, 'highlight_order' => $i]);
    }

    expect(Product::forHomepage())->toHaveCount(5);
});

it('keeps a featured product off the home page once its run closes', function () {
    $product = storeProduct(['highlighted' => true], 25, ['closes_at' => now()->subMinute()]);

    expect($product->isOrderable())->toBeFalse()
        ->and(Product::forHomepage()->pluck('id'))->not->toContain($product->id);
});

it('keeps a featured product off the home page before its run opens', function () {
    $product = storeProduct(['highlighted' => true], 25, ['opens_at' => now()->addDay()]);

    expect($product->isOrderable())->toBeFalse()
        ->and(Product::forHomepage()->pluck('id'))->not->toContain($product->id);
});

it('keeps a featured product off the home page when it has no run at all', function () {
    // A design with no run is not for sale — there is no window and no price
    // list, so it must not surface anywhere public.
    $product = Product::create([
        'name' => 'Runless', 'slug' => 'runless',
        'status' => Product::STATUS_ACTIVE, 'stripe_account' => 'association',
        'highlighted' => true,
    ]);

    expect($product->isOrderable())->toBeFalse()
        ->and(Product::forHomepage()->pluck('id'))->not->toContain($product->id);
});

it('becomes orderable again when a later run opens', function () {
    $product = storeProduct(['highlighted' => true], 25, [
        'opens_at' => now()->subMonths(2), 'closes_at' => now()->subMonth(),
    ]);

    expect($product->isOrderable())->toBeFalse();

    $product->runs()->create([
        'name' => 'Second run',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
    ]);

    expect($product->fresh()->isOrderable())->toBeTrue()
        ->and($product->fresh()->openRun()->name)->toBe('Second run')
        ->and(Product::forHomepage()->pluck('id'))->toContain($product->id);
});

it('keeps a draft product off the home page even when featured', function () {
    $product = storeProduct(['status' => Product::STATUS_DRAFT, 'highlighted' => true]);

    expect(Product::forHomepage()->pluck('id'))->not->toContain($product->id);
});

it('treats an open-ended window as orderable', function () {
    expect(storeProduct()->isOrderable())->toBeTrue();
});

it('does not lock the Stripe account before any charge is attempted', function () {
    $product = storeProduct();
    storeOrderFor($product, ['stripe_payment_intent_id' => null, 'stripe_checkout_session_id' => null]);

    expect($product->hasPayments())->toBeFalse();
});

it('locks the Stripe account once a charge is attempted, even while pending', function () {
    $product = storeProduct();
    storeOrderFor($product);   // pending, but a PaymentIntent exists

    expect($product->fresh()->hasPayments())->toBeTrue();
});

it('locks the Stripe account for a guest checkout session with no intent yet', function () {
    $product = storeProduct();
    storeOrderFor($product, [
        'stripe_payment_intent_id' => null,
        'stripe_checkout_session_id' => 'cs_test_'.uniqid(),
    ]);

    expect($product->fresh()->hasPayments())->toBeTrue();
});

it('does not lock one product because a different product was charged', function () {
    $charged = storeProduct();
    $untouched = storeProduct();
    storeOrderFor($charged);

    expect($untouched->fresh()->hasPayments())->toBeFalse();
});

it('finds stale pending orders for the reconcile sweep', function () {
    $product = storeProduct();

    $fresh = storeOrderFor($product);
    $stale = storeOrderFor($product);
    $stale->forceFill(['created_at' => now()->subHour()])->save();

    $paid = storeOrderFor($product);
    $paid->forceFill(['created_at' => now()->subHour(), 'status' => ProductOrder::STATUS_PAID])->save();

    $ids = ProductOrder::stalePending()->pluck('id')->all();

    expect($ids)->toContain($stale->id)
        ->and($ids)->not->toContain($fresh->id)
        ->and($ids)->not->toContain($paid->id);
});
