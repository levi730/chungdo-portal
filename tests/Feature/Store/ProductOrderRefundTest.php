<?php

use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\ProductOrderRefund;
use App\Models\User;
use App\Services\Store\ProductOrderRefunder;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Refunding a store order — step 8 of docs/store-design.md. Money out on one
 * person's say-so, so the permission is its own and no role holds it.
 */
function rfUser(string ...$permissions): User
{
    foreach ($permissions as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $user = User::factory()->create();
    $user->markEmailAsVerified();
    if ($permissions) {
        $user->givePermissionTo(...$permissions);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function rfOrder(array $attrs = []): ProductOrder
{
    static $n = 0;
    $n++;

    $product = Product::create([
        'name' => "Refund Product {$n}",
        'slug' => "refund-product-{$n}",
        'status' => Product::STATUS_ACTIVE,
        'stripe_account' => 'main_school',
    ]);
    $run = $product->runs()->create(['name' => 'Run']);
    $variant = $run->variants()->create(['name' => 'Adult T-Shirt / M', 'price' => 20]);

    $order = ProductOrder::create(array_merge([
        'status' => ProductOrder::STATUS_PAID,
        'stripe_account' => 'main_school',
        'stripe_payment_intent_id' => 'pi_rf_'.$n,
        'email' => 'buyer@example.com',
        'name' => 'Buyer',
        'subtotal' => 40,
        'tax' => 0,
        'total' => 40,
        'amount_paid' => 40,
        'paid_at' => now(),
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
        'quantity' => 2,
        'amount' => 40,
    ]);

    return $order->fresh();
}

/** A refunder whose Stripe call is recorded instead of made. */
function rfService(array &$calls): ProductOrderRefunder
{
    return new ProductOrderRefunder(function (string $intent, int $cents, string $account) use (&$calls): string {
        $calls[] = compact('intent', 'cents', 'account');

        return 're_rf_'.count($calls);
    });
}

it('refunds on the order\'s own Stripe account and records it', function () {
    $order = rfOrder();
    $admin = rfUser();
    $calls = [];

    $refund = rfService($calls)->refund($order, 15.5, $admin, 'Wrong size');

    expect($calls)->toBe([['intent' => $order->stripe_payment_intent_id, 'cents' => 1550, 'account' => 'main_school']])
        ->and($refund)->toBeInstanceOf(ProductOrderRefund::class)
        ->and((float) $refund->amount)->toBe(15.5)
        ->and($refund->stripe_refund_id)->toBe('re_rf_1')
        ->and($refund->stripe_account)->toBe('main_school')
        ->and($refund->refunded_by_user_id)->toBe($admin->id)
        ->and($refund->admin_note)->toBe('Wrong size');

    $order->refresh();
    expect((float) $order->refunded_amount)->toBe(15.5)
        ->and($order->stripe_refund_id)->toBe('re_rf_1')
        ->and($order->status)->toBe(ProductOrder::STATUS_PAID)
        ->and($order->refundable())->toBe(24.5)
        ->and($order->isFullyRefunded())->toBeFalse()
        ->and($order->items)->toHaveCount(1);
});

it('allows a second refund up to what is left, then no more', function () {
    $order = rfOrder();
    $calls = [];
    $service = rfService($calls);

    $service->refund($order, 15, rfUser());
    $service->refund($order, 25, rfUser());

    $order->refresh();
    expect($order->refunds)->toHaveCount(2)
        ->and((float) $order->refunded_amount)->toBe(40.0)
        ->and($order->isFullyRefunded())->toBeTrue()
        ->and($order->refundable())->toBe(0.0);

    expect(fn () => $service->refund($order, 0.01, rfUser()))->toThrow(ValidationException::class);
    expect($calls)->toHaveCount(2);
});

it('refuses more than was paid, less than a cent, and an unpaid order', function () {
    $calls = [];
    $service = rfService($calls);

    expect(fn () => $service->refund(rfOrder(), 40.01, rfUser()))->toThrow(ValidationException::class);
    expect(fn () => $service->refund(rfOrder(), 0, rfUser()))->toThrow(ValidationException::class);
    expect(fn () => $service->refund(rfOrder(['status' => ProductOrder::STATUS_PENDING, 'amount_paid' => null]), 10, rfUser()))
        ->toThrow(ValidationException::class);
    expect(fn () => $service->refund(rfOrder(['stripe_payment_intent_id' => null]), 10, rfUser()))
        ->toThrow(ValidationException::class);

    expect($calls)->toBe([])
        ->and(ProductOrderRefund::count())->toBe(0);
});

it('writes nothing when Stripe refuses', function () {
    $order = rfOrder();
    $service = new ProductOrderRefunder(function () {
        throw new RuntimeException('Charge has already been refunded.');
    });

    expect(fn () => $service->refund($order, 10, rfUser()))->toThrow(RuntimeException::class);

    expect((float) $order->fresh()->refunded_amount)->toBe(0.0)
        ->and(ProductOrderRefund::count())->toBe(0);
});

/* ------------------------------------------------------------------ *
 * The route
 * ------------------------------------------------------------------ */

it('refuses the refund route to a store manager without store.issueRefunds', function () {
    $order = rfOrder();

    $this->actingAs(rfUser('store.manage'))
        ->post(route('orders.refund', $order), ['amount' => 10])
        ->assertForbidden();

    expect((float) $order->fresh()->refunded_amount)->toBe(0.0);
});

it('shows the refund form to a holder and refunds through it', function () {
    $order = rfOrder();
    $admin = rfUser('store.manage', 'store.issueRefunds');
    $calls = [];
    $this->app->instance(ProductOrderRefunder::class, rfService($calls));

    $this->actingAs($admin)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee(route('orders.refund', $order))
        ->assertSee('Up to $40.00 can be returned');

    $this->actingAs($admin)
        ->post(route('orders.refund', $order), ['amount' => '12.34', 'note' => 'One shirt returned'])
        ->assertRedirect(route('orders.show', $order))
        ->assertSessionHas('success');

    expect($calls)->toHaveCount(1)
        ->and($calls[0]['cents'])->toBe(1234)
        ->and((float) $order->fresh()->refunded_amount)->toBe(12.34);

    $this->actingAs($admin)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('One shirt returned')
        ->assertSee('Up to $27.66 can be returned');
});

it('reports an over-refund as a flash error, not a 500', function () {
    $order = rfOrder();
    $admin = rfUser('store.manage', 'store.issueRefunds');
    $calls = [];
    $this->app->instance(ProductOrderRefunder::class, rfService($calls));

    $this->actingAs($admin)
        ->from(route('orders.show', $order))
        ->post(route('orders.refund', $order), ['amount' => '41'])
        ->assertRedirect(route('orders.show', $order))
        ->assertSessionHas('error');

    expect($calls)->toBe([]);
});

it('is in the coordinator allowlist and the permission seeder', function () {
    expect(config('portal.coordinator_permissions'))->toContain('store.issueRefunds');

    $this->seed(\Database\Seeders\PermissionSeeder::class);
    expect(Permission::where('name', 'store.issueRefunds')->exists())->toBeTrue();
});
