<?php

use App\Mail\ProductOrderPlaced;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\User;
use App\Services\Store\ProductOrderFulfiller;
use Illuminate\Support\Facades\Mail;

/**
 * The money backbone. Everything here is about doing the work exactly once —
 * the synchronous response, the webhook and the reconcile sweep all call the
 * same fulfiller, and any of them can arrive first, twice, or concurrently.
 *
 * See docs/payment-flow-pattern.md.
 */
beforeEach(function () {
    Mail::fake();
});

function fulfiller(): ProductOrderFulfiller
{
    return app(ProductOrderFulfiller::class);
}

function orderProduct(): Product
{
    static $n = 0;
    $n++;

    $product = Product::create([
        'name' => "Fulfil Product {$n}",
        'slug' => "fulfil-product-{$n}",
        'status' => Product::STATUS_ACTIVE,
        'stripe_account' => 'association',
    ]);

    $run = $product->runs()->create(['name' => 'Run']);
    $run->variants()->create(['name' => 'Adult T-Shirt / M', 'price' => 20]);

    return $product->fresh();
}

/** A pending order with one line, as checkout would have written it. */
function pendingOrder(array $attrs = []): ProductOrder
{
    $product = orderProduct();
    $run = $product->runs()->first();
    $variant = $run->variants()->first();

    $order = ProductOrder::create(array_merge([
        'status' => ProductOrder::STATUS_PENDING,
        'stripe_account' => 'association',
        'stripe_payment_intent_id' => 'pi_'.uniqid(),
        'email' => 'buyer@example.com',
        'name' => 'Buyer',
        'subtotal' => 20,
        'tax' => 0,
        'total' => 20,
        'amount_paid' => 20,
        'payload' => ['items' => []],
    ], $attrs));

    ProductOrderItem::create([
        'product_order_id' => $order->id,
        'product_id' => $product->id,
        'product_run_id' => $run->id,
        'product_variant_id' => $variant->id,
        'product_name' => $product->name,
        'variant_name' => $variant->name,
        'unit_price' => 20,
        'quantity' => 1,
        'amount' => 20,
    ]);

    return $order->fresh();
}

/* -------------------------------------------------------------------- *
 * Doing the work exactly once
 * -------------------------------------------------------------------- */

it('fulfills a pending order and records the payment', function () {
    $order = pendingOrder();

    expect(fulfiller()->fulfill($order))->toBeTrue();

    $order->refresh();
    $payment = Payment::where('stripe_payment_intent_id', $order->stripe_payment_intent_id)->first();

    expect($order->status)->toBe(ProductOrder::STATUS_PAID)
        ->and($order->paid_at)->not->toBeNull()
        ->and($payment)->not->toBeNull()
        ->and((float) $payment->amount_paid)->toBe(20.0)
        ->and($payment->product_order_id)->toBe($order->id)
        ->and($order->payment_id)->toBe($payment->id);
});

it('reports false and does nothing on a second call', function () {
    $order = pendingOrder();

    expect(fulfiller()->fulfill($order))->toBeTrue()
        ->and(fulfiller()->fulfill($order->fresh()))->toBeFalse();

    // One ledger row, not two — that's what firstOrCreate on the intent buys.
    expect(Payment::where('stripe_payment_intent_id', $order->stripe_payment_intent_id)->count())->toBe(1);
});

it('sends the confirmation exactly once even when fulfilled twice', function () {
    $order = pendingOrder();

    fulfiller()->fulfill($order);
    fulfiller()->fulfill($order->fresh());

    Mail::assertSent(ProductOrderPlaced::class, 1);
});

it('emails the address on the order, which may not be a user', function () {
    $order = pendingOrder(['email' => 'guest@example.com', 'user_id' => null]);

    fulfiller()->fulfill($order);

    Mail::assertSent(ProductOrderPlaced::class, fn ($mail) => $mail->hasTo('guest@example.com'));
});

it('records a guest payment with no user', function () {
    // payments.user_id was made nullable for exactly this.
    $order = pendingOrder(['user_id' => null]);

    fulfiller()->fulfill($order);

    $payment = Payment::where('stripe_payment_intent_id', $order->stripe_payment_intent_id)->first();

    expect($payment)->not->toBeNull()
        ->and($payment->user_id)->toBeNull();
});

it('links the payment to the buyer when there is one', function () {
    $user = User::factory()->create();
    $order = pendingOrder(['user_id' => $user->id]);

    fulfiller()->fulfill($order);

    expect(Payment::where('stripe_payment_intent_id', $order->stripe_payment_intent_id)->first()->user_id)
        ->toBe($user->id);
});

/* -------------------------------------------------------------------- *
 * The webhook / sweep entry point
 * -------------------------------------------------------------------- */

it('reconciles by order id', function () {
    $order = pendingOrder(['amount_paid' => null]);

    fulfiller()->reconcileSucceeded($order->stripe_payment_intent_id, $order->id, 20.0);

    $order->refresh();

    expect($order->status)->toBe(ProductOrder::STATUS_PAID)
        ->and((float) $order->amount_paid)->toBe(20.0);
});

it('falls back to the intent id when metadata carried no pointer', function () {
    // A Checkout Session's metadata does not reach the PaymentIntent, so the
    // webhook often has no product_order_id to work with.
    $order = pendingOrder(['amount_paid' => null]);

    fulfiller()->reconcileSucceeded($order->stripe_payment_intent_id, null, 20.0);

    expect($order->fresh()->status)->toBe(ProductOrder::STATUS_PAID);
});

it('ignores an intent belonging to no order', function () {
    // Another integration's payment on the same account must not error.
    fulfiller()->reconcileSucceeded('pi_belongs_to_nobody', null, 50.0);

    expect(ProductOrder::count())->toBe(0);
});

it('does not resend the confirmation when the webhook follows the sync path', function () {
    $order = pendingOrder();

    fulfiller()->fulfill($order);                                  // synchronous
    fulfiller()->reconcileSucceeded($order->stripe_payment_intent_id, $order->id, 20.0);  // webhook

    Mail::assertSent(ProductOrderPlaced::class, 1);
    expect(Payment::count())->toBe(1);
});

it('back-fills the intent id when the sync path never saved it', function () {
    $order = pendingOrder(['stripe_payment_intent_id' => null, 'amount_paid' => null]);

    fulfiller()->reconcileSucceeded('pi_late_arrival', $order->id, 20.0);

    $order->refresh();

    expect($order->stripe_payment_intent_id)->toBe('pi_late_arrival')
        ->and($order->status)->toBe(ProductOrder::STATUS_PAID)
        ->and(Payment::where('stripe_payment_intent_id', 'pi_late_arrival')->count())->toBe(1);
});

/* -------------------------------------------------------------------- *
 * Failure
 * -------------------------------------------------------------------- */

it('marks a pending order failed', function () {
    $order = pendingOrder();

    fulfiller()->markFailed($order->stripe_payment_intent_id, $order->id);

    expect($order->fresh()->status)->toBe(ProductOrder::STATUS_FAILED);
});

it('never downgrades an order that already paid', function () {
    // A late payment_failed for an intent that did succeed must not undo it.
    $order = pendingOrder();
    fulfiller()->fulfill($order);

    fulfiller()->markFailed($order->stripe_payment_intent_id, $order->id);

    expect($order->fresh()->status)->toBe(ProductOrder::STATUS_PAID);
});

it('leaves the line items alone — they were written before the charge', function () {
    $order = pendingOrder();

    fulfiller()->fulfill($order);

    expect($order->fresh()->items)->toHaveCount(1)
        ->and((float) $order->fresh()->items->first()->unit_price)->toBe(20.0);
});
