<?php

use App\Mail\ProductOrderPlaced;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductOrder;
use Illuminate\Support\Facades\Mail;

/**
 * The reconcile sweep — the only backstop that doesn't depend on a webhook
 * arriving, which matters because Stripe disables endpoints after sustained
 * delivery failures (docs/payment-flow-pattern.md).
 *
 * Stripe itself isn't reachable from tests, so what's covered here is which
 * orders the sweep picks up and which it leaves alone. That selection is the
 * part that can quietly go wrong: too greedy and it interrogates Stripe about
 * abandoned carts, too narrow and a charged customer stays unfulfilled.
 */
beforeEach(function () {
    Mail::fake();
});

function sweepOrder(array $attrs = []): ProductOrder
{
    static $n = 0;
    $n++;

    $product = Product::create([
        'name' => "Sweep Product {$n}",
        'slug' => "sweep-product-{$n}",
        'status' => Product::STATUS_ACTIVE,
        'stripe_account' => 'association',
    ]);
    $run = $product->runs()->create(['name' => 'Run']);
    $variant = $run->variants()->create(['name' => 'Item', 'price' => 20]);

    $order = ProductOrder::create(array_merge([
        'status' => ProductOrder::STATUS_PENDING,
        'stripe_account' => 'association',
        'email' => 'buyer@example.com',
        'name' => 'Buyer',
        'subtotal' => 20, 'tax' => 0, 'total' => 20,
        'payload' => [],
    ], $attrs));

    $order->items()->create([
        'product_id' => $product->id,
        'product_run_id' => $run->id,
        'product_variant_id' => $variant->id,
        'product_name' => $product->name,
        'variant_name' => $variant->name,
        'unit_price' => 20, 'quantity' => 1, 'amount' => 20,
    ]);

    return $order->fresh();
}

/** Push a row's created_at into the past — stalePending is age-based. */
function age(ProductOrder $order, int $minutes): ProductOrder
{
    $order->forceFill(['created_at' => now()->subMinutes($minutes)])->saveQuietly();

    return $order->fresh();
}

/* -------------------------------------------------------------------- *
 * What the sweep picks up
 * -------------------------------------------------------------------- */

it('ignores a cart that never reached Stripe', function () {
    // No intent and no session means the buyer never got as far as paying.
    // Asking Stripe about these would be asking about abandoned carts.
    age(sweepOrder(), 60);

    $this->artisan('store:reconcile-orders')
        ->expectsOutputToContain('Nothing to reconcile.')
        ->assertSuccessful();
});

it('ignores an order that is too recent', function () {
    // Still in flight — the synchronous response may not even have returned.
    sweepOrder(['stripe_payment_intent_id' => 'pi_fresh']);

    $this->artisan('store:reconcile-orders')
        ->expectsOutputToContain('Nothing to reconcile.')
        ->assertSuccessful();
});

it('ignores an order that is already paid', function () {
    age(sweepOrder([
        'status' => ProductOrder::STATUS_PAID,
        'stripe_payment_intent_id' => 'pi_done',
        'paid_at' => now(),
    ]), 60);

    $this->artisan('store:reconcile-orders')
        ->expectsOutputToContain('Nothing to reconcile.')
        ->assertSuccessful();
});

it('ignores an order already marked failed', function () {
    age(sweepOrder([
        'status' => ProductOrder::STATUS_FAILED,
        'stripe_payment_intent_id' => 'pi_failed',
    ]), 60);

    $this->artisan('store:reconcile-orders')
        ->expectsOutputToContain('Nothing to reconcile.')
        ->assertSuccessful();
});

it('picks up a stale pending order that has a payment intent', function () {
    $order = age(sweepOrder(['stripe_payment_intent_id' => 'pi_stale']), 60);

    // Stripe is unreachable in tests, so the lookup fails — the point is that
    // this order was SELECTED, and that a failure is survived rather than fatal.
    $this->artisan('store:reconcile-orders')
        ->expectsOutputToContain('1 pending order(s)')
        ->expectsOutputToContain($order->reference)
        ->assertSuccessful();

    expect($order->fresh()->status)->toBe(ProductOrder::STATUS_PENDING);
});

it('picks up a stale guest order that only has a checkout session', function () {
    $order = age(sweepOrder(['stripe_checkout_session_id' => 'cs_stale']), 60);

    $this->artisan('store:reconcile-orders')
        ->expectsOutputToContain('1 pending order(s)')
        ->expectsOutputToContain($order->reference)
        ->assertSuccessful();
});

it('honours the minutes option', function () {
    sweepOrder(['stripe_payment_intent_id' => 'pi_five_min_old'])
        ->forceFill(['created_at' => now()->subMinutes(5)])->saveQuietly();

    $this->artisan('store:reconcile-orders', ['--minutes' => 15])
        ->expectsOutputToContain('Nothing to reconcile.')
        ->assertSuccessful();

    $this->artisan('store:reconcile-orders', ['--minutes' => 1])
        ->expectsOutputToContain('1 pending order(s)')
        ->assertSuccessful();
});

it('survives a Stripe failure and keeps going', function () {
    // One bad account must not abort the whole sweep.
    age(sweepOrder(['stripe_payment_intent_id' => 'pi_a']), 60);
    age(sweepOrder(['stripe_payment_intent_id' => 'pi_b']), 60);

    $this->artisan('store:reconcile-orders')
        ->expectsOutputToContain('2 pending order(s)')
        ->assertSuccessful();
});

it('changes nothing on a dry run', function () {
    $order = age(sweepOrder(['stripe_payment_intent_id' => 'pi_dry']), 60);

    $this->artisan('store:reconcile-orders', ['--dry-run' => true])->assertSuccessful();

    expect($order->fresh()->status)->toBe(ProductOrder::STATUS_PENDING)
        ->and(Payment::count())->toBe(0);

    Mail::assertNothingSent();
});

it('is registered on the schedule', function () {
    // The sweep is worthless if nothing runs it.
    $events = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())
        ->filter(fn ($e) => str_contains($e->command ?? '', 'store:reconcile-orders'));

    expect($events)->not->toBeEmpty()
        ->and($events->first()->expression)->toBe('*/15 * * * *');
});
