<?php

use App\Exports\Store\FinancialsExport;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\ProductRun;
use App\Models\ProductVariant;
use App\Models\School;
use App\Models\User;
use App\Services\Store\PickList;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The orders admin, the pick list and the financials export — step 7 of
 * docs/store-design.md. What happens after the money moved.
 */
function oaManager(): User
{
    Permission::findOrCreate('store.manage', 'web');
    $user = User::factory()->create();
    $user->markEmailAsVerified();
    $user->givePermissionTo('store.manage');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function oaMember(): User
{
    $user = User::factory()->create();
    $user->markEmailAsVerified();

    return $user;
}

/** A product with one run and two variants (M, L), in that sort order. */
function oaProduct(): Product
{
    static $n = 0;
    $n++;

    $product = Product::create([
        'name' => "Orders Product {$n}",
        'slug' => "orders-product-{$n}",
        'status' => Product::STATUS_ACTIVE,
        'stripe_account' => 'association',
    ]);
    $run = $product->runs()->create(['name' => 'Fall 2026']);
    $run->variants()->create(['name' => 'Adult T-Shirt / M', 'price' => 20, 'sort_order' => 0]);
    $run->variants()->create(['name' => 'Adult T-Shirt / L', 'price' => 20, 'sort_order' => 1]);

    return $product->fresh();
}

function oaSchool(string $name): School
{
    return School::create(['name' => $name, 'shortname' => strtoupper(substr($name, 0, 3))]);
}

/**
 * An order with the given lines: [[variant, qty], ...]. Paid unless told
 * otherwise, with amount_paid equal to the total.
 */
function oaOrder(array $lines, array $attrs = []): ProductOrder
{
    static $n = 0;
    $n++;

    $total = collect($lines)->sum(fn ($l) => (float) $l[0]->price * $l[1]);

    $order = ProductOrder::create(array_merge([
        'status' => ProductOrder::STATUS_PAID,
        'stripe_account' => 'association',
        'stripe_payment_intent_id' => 'pi_oa_'.$n,
        'email' => "buyer{$n}@example.com",
        'name' => "Buyer {$n}",
        'subtotal' => $total,
        'tax' => 0,
        'total' => $total,
        'amount_paid' => $total,
        'paid_at' => now()->subMinutes($n),
        'payload' => ['items' => []],
    ], $attrs));

    /** @var ProductVariant $variant */
    foreach ($lines as [$variant, $qty]) {
        ProductOrderItem::create([
            'product_order_id' => $order->id,
            'product_id' => $variant->run->product_id,
            'product_run_id' => $variant->product_run_id,
            'product_variant_id' => $variant->id,
            'product_name' => $variant->run->product->name,
            'variant_name' => $variant->name,
            'unit_price' => $variant->price,
            'quantity' => $qty,
            'amount' => (float) $variant->price * $qty,
        ]);
    }

    return $order->fresh();
}

/* ------------------------------------------------------------------ *
 * Access
 * ------------------------------------------------------------------ */

it('keeps the orders admin behind store.manage', function () {
    $product = oaProduct();
    $run = $product->runs->first();
    $order = oaOrder([[$run->variants->first(), 1]]);

    $this->actingAs(oaMember())->get(route('orders.index'))->assertForbidden();
    $this->actingAs(oaMember())->get(route('orders.show', $order))->assertForbidden();
    $this->actingAs(oaMember())->get(route('products.runs.pick-list', [$product, $run]))->assertForbidden();
    $this->actingAs(oaMember())->get(route('orders.financials'))->assertForbidden();
    $this->actingAs(oaMember())->put(route('orders.update', $order), ['fulfillment_status' => 'collected'])->assertForbidden();

    expect($order->fresh()->fulfillment_status)->toBe(ProductOrder::FULFILLMENT_AWAITING);
});

it('shows a manager the orders list and one order', function () {
    $product = oaProduct();
    $run = $product->runs->first();
    $school = oaSchool('Eastside Dojang');
    $order = oaOrder([[$run->variants->first(), 2]], ['pickup_school_id' => $school->id]);

    $this->actingAs(oaManager())->get(route('orders.index'))->assertOk()->assertSee('Store orders');

    $this->actingAs(oaManager())
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee($order->name)
        ->assertSee('Eastside Dojang')
        ->assertSee('Adult T-Shirt / M')
        ->assertSee($order->shortReference())
        // Refunds are a separate grant; a plain manager sees why there is no form.
        ->assertSee('store.issueRefunds')
        ->assertDontSee(route('orders.refund', $order));
});

/* ------------------------------------------------------------------ *
 * Hand-over
 * ------------------------------------------------------------------ */

it('records hand-over status and the admin note', function () {
    $product = oaProduct();
    $run = $product->runs->first();
    $order = oaOrder([[$run->variants->first(), 1]]);

    $this->actingAs(oaManager())
        ->put(route('orders.update', $order), [
            'fulfillment_status' => ProductOrder::FULFILLMENT_COLLECTED,
            'admin_note' => 'Picked up by grandmother',
        ])
        ->assertRedirect(route('orders.show', $order));

    $order->refresh();
    expect($order->fulfillment_status)->toBe(ProductOrder::FULFILLMENT_COLLECTED)
        ->and($order->collected_at)->not->toBeNull()
        ->and($order->fulfilled_at)->not->toBeNull()
        ->and($order->admin_note)->toBe('Picked up by grandmother');

    // Back to awaiting clears both stamps.
    $this->actingAs(oaManager())
        ->put(route('orders.update', $order), ['fulfillment_status' => ProductOrder::FULFILLMENT_AWAITING]);

    $order->refresh();
    expect($order->collected_at)->toBeNull()
        ->and($order->fulfilled_at)->toBeNull()
        ->and($order->admin_note)->toBeNull();
});

it('rejects an unknown hand-over status', function () {
    $product = oaProduct();
    $order = oaOrder([[$product->runs->first()->variants->first(), 1]]);

    $this->actingAs(oaManager())
        ->from(route('orders.show', $order))
        ->put(route('orders.update', $order), ['fulfillment_status' => 'shipped'])
        ->assertSessionHasErrors('fulfillment_status');
});

it('toggles collected from the pick list', function () {
    $product = oaProduct();
    $order = oaOrder([[$product->runs->first()->variants->first(), 1]]);

    $this->actingAs(oaManager())->post(route('orders.collected', $order), ['collected' => 1]);
    expect($order->fresh()->isCollected())->toBeTrue();

    $this->actingAs(oaManager())->post(route('orders.collected', $order), ['collected' => 0]);
    $order->refresh();
    expect($order->isCollected())->toBeFalse()
        ->and($order->fulfillment_status)->toBe(ProductOrder::FULFILLMENT_READY)
        ->and($order->collected_at)->toBeNull();
});

it('marks a whole run ready for pickup without touching collected orders', function () {
    $product = oaProduct();
    $run = $product->runs->first();
    $variant = $run->variants->first();
    $awaiting = oaOrder([[$variant, 1]]);
    $collected = oaOrder([[$variant, 1]], ['fulfillment_status' => ProductOrder::FULFILLMENT_COLLECTED, 'collected_at' => now()]);
    $unpaid = oaOrder([[$variant, 1]], ['status' => ProductOrder::STATUS_PENDING, 'amount_paid' => null, 'paid_at' => null]);
    $otherRun = oaOrder([[oaProduct()->runs->first()->variants->first(), 1]]);

    $this->actingAs(oaManager())
        ->post(route('products.runs.mark-ready', [$product, $run]))
        ->assertRedirect();

    expect($awaiting->fresh()->fulfillment_status)->toBe(ProductOrder::FULFILLMENT_READY)
        ->and($collected->fresh()->fulfillment_status)->toBe(ProductOrder::FULFILLMENT_COLLECTED)
        ->and($unpaid->fresh()->fulfillment_status)->toBe(ProductOrder::FULFILLMENT_AWAITING)
        ->and($otherRun->fresh()->fulfillment_status)->toBe(ProductOrder::FULFILLMENT_AWAITING);
});

/* ------------------------------------------------------------------ *
 * Pick list
 * ------------------------------------------------------------------ */

it('groups the pick list by school and totals each variant', function () {
    $product = oaProduct();
    $run = $product->runs->first();
    [$m, $l] = $run->variants->all();
    $west = oaSchool('Westside');
    $east = oaSchool('Eastside');

    oaOrder([[$m, 2], [$l, 1]], ['pickup_school_id' => $west->id, 'name' => 'Wendy West']);
    oaOrder([[$l, 3]], ['pickup_school_id' => $east->id, 'name' => 'Eddie East']);
    oaOrder([[$m, 1]], ['pickup_school_id' => null, 'name' => 'Nora Nowhere']);
    // Not owed anything: never paid, or paid and fully refunded.
    oaOrder([[$m, 5]], ['status' => ProductOrder::STATUS_PENDING, 'amount_paid' => null, 'name' => 'Penny Pending']);
    oaOrder([[$m, 5]], ['refunded_amount' => 100, 'name' => 'Rita Refunded']);
    // Another run of another product: not on this list.
    oaOrder([[oaProduct()->runs->first()->variants->first(), 9]], ['name' => 'Otto Other']);

    $list = app(PickList::class)->build($run);

    expect($list['order_count'])->toBe(3)
        ->and($list['totals'])->toBe([
            ['variant' => 'Adult T-Shirt / M', 'quantity' => 3],
            ['variant' => 'Adult T-Shirt / L', 'quantity' => 4],
        ])
        // Alphabetical, no-school last.
        ->and(array_map(fn ($b) => $b['school']?->name, $list['schools']))->toBe(['Eastside', 'Westside', null])
        ->and($list['schools'][1]['totals'])->toBe([
            ['variant' => 'Adult T-Shirt / M', 'quantity' => 2],
            ['variant' => 'Adult T-Shirt / L', 'quantity' => 1],
        ])
        ->and($list['schools'][1]['orders'][0]['order']->name)->toBe('Wendy West');

    $this->actingAs(oaManager())
        ->get(route('products.runs.pick-list', [$product, $run]))
        ->assertOk()
        ->assertSeeInOrder(['Eastside', 'Eddie East', 'Westside', 'Wendy West', 'No pickup school chosen', 'Nora Nowhere'])
        ->assertDontSee('Penny Pending')
        ->assertDontSee('Rita Refunded')
        ->assertDontSee('Otto Other');
});

it('shows only this run\'s lines of an order that spans two runs', function () {
    $shirt = oaProduct();
    $bag = oaProduct();
    $shirtRun = $shirt->runs->first();
    $order = oaOrder([[$shirtRun->variants->first(), 1], [$bag->runs->first()->variants->first(), 2]]);

    $list = app(PickList::class)->build($shirtRun);

    expect($list['schools'][0]['orders'][0]['lines'])->toHaveCount(1)
        ->and($list['schools'][0]['orders'][0]['other_lines'])->toBe(2)
        ->and($list['totals'])->toBe([['variant' => 'Adult T-Shirt / M', 'quantity' => 1]]);

    $this->actingAs(oaManager())
        ->get(route('products.runs.pick-list', [$shirt, $shirtRun]))
        ->assertOk()
        ->assertSee('2 items from another run');
});

it('404s a pick list for a run under the wrong product', function () {
    $product = oaProduct();
    $other = oaProduct();

    $this->actingAs(oaManager())
        ->get(route('products.runs.pick-list', [$other, $product->runs->first()]))
        ->assertNotFound();
});

/* ------------------------------------------------------------------ *
 * Financials export
 * ------------------------------------------------------------------ */

it('downloads the financials export', function () {
    oaProduct();

    $this->actingAs(oaManager())
        ->get(route('orders.financials'))
        ->assertOk()
        ->assertDownload('store-financials.xlsx');
});

it('ledgers one row per paid order and one per refund, with the account', function () {
    config(['services.stripe.accounts.association.label' => 'Association Account']);
    $product = oaProduct();
    $run = $product->runs->first();
    $school = oaSchool('Northside');
    $order = oaOrder([[$run->variants->first(), 2]], ['pickup_school_id' => $school->id, 'name' => 'Olive Order']);
    $order->refunds()->create([
        'stripe_account' => 'association',
        'stripe_payment_intent_id' => $order->stripe_payment_intent_id,
        'stripe_refund_id' => 're_oa_1',
        'amount' => 15,
    ]);
    $order->update(['refunded_amount' => 15]);
    oaOrder([[$run->variants->first(), 1]], ['status' => ProductOrder::STATUS_PENDING, 'amount_paid' => null, 'name' => 'Penny Pending']);

    $html = (new FinancialsExport(run: $run))->view()->render();

    expect(substr_count($html, 'Olive Order'))->toBe(2)
        ->and($html)->toContain('Northside')
        ->toContain('Association Account')
        ->toContain('<td>40</td>')
        ->toContain('<td>-15</td>')
        ->toContain('re_oa_1')
        ->toContain('<b>25</b>')
        ->toContain($product->name.' — Fall 2026')
        ->not->toContain('Penny Pending');
});

it('scopes a run export to that run\'s lines of a mixed order', function () {
    $shirt = oaProduct();
    $bag = oaProduct();
    $shirtRun = $shirt->runs->first();
    $order = oaOrder([[$shirtRun->variants->first(), 1], [$bag->runs->first()->variants->first(), 2]]); // 20 + 40

    $run = (new FinancialsExport(run: $shirtRun))->view()->render();
    $all = (new FinancialsExport())->view()->render();

    expect($run)->toContain('<b>20</b>')
        ->and($all)->toContain('<b>60</b>')
        ->and($all)->toContain($shirt->name)
        ->and($all)->toContain($bag->name)
        ->and($run)->not->toContain('<th><b>'.$bag->name.'</b></th>');
});
