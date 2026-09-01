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
 * Print runs and the variants on sale during each — the part of the store that
 * repeats. See docs/store-design.md.
 */
function runManager(): User
{
    Permission::findOrCreate('store.manage', 'web');
    $user = User::factory()->create();
    $user->markEmailAsVerified();
    $user->givePermissionTo('store.manage');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function runProduct(array $attrs = []): Product
{
    static $n = 0;
    $n++;

    return Product::create(array_merge([
        'name' => "Run Product {$n}",
        'slug' => "run-product-{$n}",
        'status' => Product::STATUS_ACTIVE,
        'stripe_account' => 'association',
        'option_names' => ['Item', 'Size'],
    ], $attrs));
}

/** The minimum valid run form payload. */
function runPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Fall 2026',
        'sort_order' => 0,
        'variants_present' => 1,
        'variants' => [
            [
                'id' => 0,
                'name' => '',
                'sku' => '',
                'price' => '20.00',
                'enabled' => 1,
                'sort_order' => 0,
                'options' => ['Item' => 'Adult T-Shirt', 'Size' => 'M'],
            ],
        ],
    ], $overrides);
}

function orderRunVariant(Product $product, ProductRun $run, ProductVariant $variant): ProductOrder
{
    $order = ProductOrder::create([
        'status' => ProductOrder::STATUS_PENDING,
        'stripe_account' => $product->stripe_account,
        'stripe_payment_intent_id' => 'pi_'.uniqid(),
        'email' => 'buyer@example.com',
        'name' => 'Buyer',
        'subtotal' => 20,
        'total' => 20,
        'payload' => ['items' => []],
    ]);

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

    return $order;
}

/* -------------------------------------------------------------------- *
 * Creating and editing a run
 * -------------------------------------------------------------------- */

it('keeps runs away from users without store.manage', function () {
    $product = runProduct();

    // Verified, so this is a 403 from the permission and not a redirect from
    // the `verified` middleware.
    $outsider = User::factory()->create();
    $outsider->markEmailAsVerified();

    $this->actingAs($outsider)
        ->get(route('products.runs.create', $product))
        ->assertForbidden();
});

it('creates a run with its variants', function () {
    $product = runProduct();

    $this->actingAs(runManager())
        ->post(route('products.runs.store', $product), runPayload([
            'opens_at' => '2026-09-01T09:00',
            'closes_at' => '2026-09-30T17:00',
            'expected_arrival_at' => '2026-10-15',
            'pickup_note' => 'Pick up at your school after Oct 15',
        ]))
        ->assertRedirect();

    $run = $product->runs()->firstOrFail();

    expect($run->name)->toBe('Fall 2026')
        ->and($run->expected_arrival_at->format('Y-m-d'))->toBe('2026-10-15')
        ->and($run->pickup_note)->toBe('Pick up at your school after Oct 15')
        ->and($run->variants)->toHaveCount(1);

    $variant = $run->variants->first();

    expect($variant->options)->toBe(['Item' => 'Adult T-Shirt', 'Size' => 'M'])
        ->and((float) $variant->price)->toBe(20.0)
        // Name was left blank, so it comes from the option values.
        ->and($variant->name)->toBe('Adult T-Shirt / M');
});

it('derives a name from only the axes that were filled in', function () {
    // The gym bag has no size, so a design whose axes are Item + Size still has
    // to name it "Gym Bag" rather than "Gym Bag / ".
    $product = runProduct();
    $payload = runPayload();
    $payload['variants'][0]['options'] = ['Item' => 'Gym Bag', 'Size' => ''];

    $this->actingAs(runManager())->post(route('products.runs.store', $product), $payload)->assertRedirect();

    $variant = $product->runs()->firstOrFail()->variants->first();

    expect($variant->name)->toBe('Gym Bag')
        ->and($variant->options)->toBe(['Item' => 'Gym Bag']);
});

it('drops option values that are not one of the design axes', function () {
    $product = runProduct(['option_names' => ['Item', 'Size']]);
    $payload = runPayload();
    $payload['variants'][0]['options'] = ['Item' => 'Adult T-Shirt', 'Colour' => 'Navy', 'Size' => 'M'];

    $this->actingAs(runManager())->post(route('products.runs.store', $product), $payload)->assertRedirect();

    expect($product->runs()->firstOrFail()->variants->first()->options)
        ->toBe(['Item' => 'Adult T-Shirt', 'Size' => 'M']);
});

it('updates, adds and removes variants in one save', function () {
    $product = runProduct();
    $run = $product->runs()->create(['name' => 'Fall 2026']);
    $keep = ProductVariant::create(['product_run_id' => $run->id, 'name' => 'Small', 'options' => ['Size' => 'S'], 'price' => 20]);
    $drop = ProductVariant::create(['product_run_id' => $run->id, 'name' => 'Large', 'options' => ['Size' => 'L'], 'price' => 20]);

    $this->actingAs(runManager())->put(route('products.runs.update', [$product, $run]), runPayload([
        'variants' => [
            ['id' => $keep->id, 'name' => 'Small', 'price' => '22.50', 'enabled' => 1, 'sort_order' => 0, 'options' => ['Size' => 'S']],
            ['id' => 0, 'name' => 'Extra Large', 'price' => '27.00', 'enabled' => 0, 'sort_order' => 1, 'options' => ['Size' => 'XL']],
        ],
    ]))->assertRedirect();

    $variants = $run->fresh()->variants;

    expect($variants->pluck('name')->all())->toBe(['Small', 'Extra Large'])
        ->and((float) $variants->firstWhere('name', 'Small')->price)->toBe(22.5)
        ->and($variants->firstWhere('name', 'Extra Large')->enabled)->toBeFalse()
        ->and(ProductVariant::find($drop->id))->toBeNull();
});

it('refuses to delete a variant that has already been ordered', function () {
    $product = runProduct();
    $run = $product->runs()->create(['name' => 'Fall 2026']);
    $ordered = ProductVariant::create(['product_run_id' => $run->id, 'name' => 'Small', 'price' => 20]);
    orderRunVariant($product, $run, $ordered);

    $this->actingAs(runManager())->put(route('products.runs.update', [$product, $run]), runPayload([
        'variants' => [],
    ]))->assertRedirect();

    expect(ProductVariant::find($ordered->id))->not->toBeNull();
});

it('leaves variants alone when the editor was not on the page', function () {
    $product = runProduct();
    $run = $product->runs()->create(['name' => 'Fall 2026']);
    ProductVariant::create(['product_run_id' => $run->id, 'name' => 'Small', 'price' => 20]);

    $payload = runPayload();
    unset($payload['variants_present'], $payload['variants']);

    $this->actingAs(runManager())->put(route('products.runs.update', [$product, $run]), $payload)->assertRedirect();

    expect($run->fresh()->variants)->toHaveCount(1);
});

/* -------------------------------------------------------------------- *
 * One open run at a time
 * -------------------------------------------------------------------- */

it('rejects a run whose window overlaps another', function () {
    $product = runProduct();
    $product->runs()->create([
        'name' => 'Fall 2026',
        'opens_at' => '2026-09-01 09:00:00',
        'closes_at' => '2026-09-30 17:00:00',
    ]);

    $this->actingAs(runManager())
        ->post(route('products.runs.store', $product), runPayload([
            'name' => 'Overlapping',
            'opens_at' => '2026-09-15T09:00',
            'closes_at' => '2026-10-15T17:00',
        ]))
        ->assertSessionHasErrors('opens_at');

    expect($product->runs()->count())->toBe(1);
});

it('accepts a run that starts after the previous one closes', function () {
    $product = runProduct();
    $product->runs()->create([
        'name' => 'Fall 2026',
        'opens_at' => '2026-09-01 09:00:00',
        'closes_at' => '2026-09-30 17:00:00',
    ]);

    $this->actingAs(runManager())
        ->post(route('products.runs.store', $product), runPayload([
            'name' => 'Spring 2027',
            'opens_at' => '2027-02-01T09:00',
            'closes_at' => '2027-02-28T17:00',
        ]))
        ->assertSessionHasNoErrors();

    expect($product->runs()->count())->toBe(2);
});

it('lets different designs run at the same time', function () {
    // The one-open-run rule is per DESIGN, not store-wide. A shirt design and a
    // bag design must be able to take orders in the same weeks.
    $shirts = runProduct();
    $bags = runProduct();

    $shirts->runs()->create([
        'name' => 'Shirts Fall 2026',
        'opens_at' => '2026-09-01 09:00:00',
        'closes_at' => '2026-09-30 17:00:00',
    ]);

    $this->actingAs(runManager())
        ->post(route('products.runs.store', $bags), runPayload([
            'name' => 'Bags Fall 2026',
            'opens_at' => '2026-09-01T09:00',   // exactly the same window
            'closes_at' => '2026-09-30T17:00',
        ]))
        ->assertSessionHasNoErrors();

    expect($bags->runs()->count())->toBe(1)
        ->and($shirts->runs()->count())->toBe(1);
});

it('opens both designs at once', function () {
    // The same thing seen from the read side: two designs orderable together.
    $shirts = runProduct();
    $bags = runProduct();

    $shirts->runs()->create(['name' => 'Shirts now', 'closes_at' => now()->addMonth()]);
    $bags->runs()->create(['name' => 'Bags now', 'closes_at' => now()->addMonth()]);

    expect($shirts->fresh()->isOrderable())->toBeTrue()
        ->and($bags->fresh()->isOrderable())->toBeTrue()
        ->and(Product::orderable()->pluck('id'))
        ->toContain($shirts->id)
        ->toContain($bags->id);
});

it('does not count a run as overlapping itself when edited', function () {
    $product = runProduct();
    $run = $product->runs()->create([
        'name' => 'Fall 2026',
        'opens_at' => '2026-09-01 09:00:00',
        'closes_at' => '2026-09-30 17:00:00',
    ]);

    $this->actingAs(runManager())
        ->put(route('products.runs.update', [$product, $run]), runPayload([
            'name' => 'Fall 2026 (extended)',
            'opens_at' => '2026-09-01T09:00',
            'closes_at' => '2026-10-10T17:00',
        ]))
        ->assertSessionHasNoErrors();

    expect($run->fresh()->name)->toBe('Fall 2026 (extended)');
});

it('rejects a window that closes before it opens', function () {
    $product = runProduct();

    $this->actingAs(runManager())
        ->post(route('products.runs.store', $product), runPayload([
            'opens_at' => '2026-10-01T09:00',
            'closes_at' => '2026-09-01T09:00',
        ]))
        ->assertSessionHasErrors('closes_at');
});

/* -------------------------------------------------------------------- *
 * Copying a run's price list
 * -------------------------------------------------------------------- */

it('copies the price list from an earlier run', function () {
    $product = runProduct();
    $first = $product->runs()->create([
        'name' => 'Fall 2026',
        'opens_at' => '2026-09-01 09:00:00',
        'closes_at' => '2026-09-30 17:00:00',
    ]);
    ProductVariant::create(['product_run_id' => $first->id, 'name' => 'Adult T-Shirt / M', 'options' => ['Item' => 'Adult T-Shirt', 'Size' => 'M'], 'price' => 20]);
    ProductVariant::create(['product_run_id' => $first->id, 'name' => 'Adult T-Shirt / 2XL', 'options' => ['Item' => 'Adult T-Shirt', 'Size' => '2XL'], 'price' => 22]);

    $this->actingAs(runManager())
        ->post(route('products.runs.store', $product), [
            'name' => 'Spring 2027',
            'sort_order' => 1,
            'opens_at' => '2027-02-01T09:00',
            'closes_at' => '2027-02-28T17:00',
            'copy_from_run_id' => $first->id,
        ])
        ->assertRedirect();

    $second = $product->runs()->where('name', 'Spring 2027')->firstOrFail();

    expect($second->variants)->toHaveCount(2)
        ->and($second->variants->pluck('name')->all())->toBe(['Adult T-Shirt / M', 'Adult T-Shirt / 2XL'])
        ->and((float) $second->variants->firstWhere('name', 'Adult T-Shirt / 2XL')->price)->toBe(22.0);

    // Copies, not shares — repricing the new run must not touch the old one.
    $second->variants->first()->update(['price' => 24]);

    expect((float) $first->fresh()->variants->firstWhere('name', 'Adult T-Shirt / M')->price)->toBe(20.0);
});

it('does not duplicate when copying twice', function () {
    $product = runProduct();
    $first = $product->runs()->create(['name' => 'First', 'closes_at' => '2026-01-31 00:00:00']);
    ProductVariant::create(['product_run_id' => $first->id, 'name' => 'A', 'options' => ['Item' => 'A'], 'price' => 10]);

    $second = $product->runs()->create(['name' => 'Second', 'opens_at' => '2026-02-01 00:00:00']);

    $sync = app(App\Services\Store\ProductVariantSync::class);

    expect($sync->copy($first, $second))->toBe(1)
        ->and($sync->copy($first, $second))->toBe(0)
        ->and($second->fresh()->variants)->toHaveCount(1);
});

/* -------------------------------------------------------------------- *
 * Deleting
 * -------------------------------------------------------------------- */

it('deletes a run that has taken no orders', function () {
    $product = runProduct();
    $run = $product->runs()->create(['name' => 'Fall 2026']);
    ProductVariant::create(['product_run_id' => $run->id, 'name' => 'A', 'price' => 10]);

    $this->actingAs(runManager())->delete(route('products.runs.destroy', [$product, $run]))->assertRedirect();

    expect(ProductRun::find($run->id))->toBeNull()
        ->and(ProductVariant::where('product_run_id', $run->id)->count())->toBe(0);
});

it('refuses to delete a run that has taken orders', function () {
    $product = runProduct();
    $run = $product->runs()->create(['name' => 'Fall 2026']);
    $variant = ProductVariant::create(['product_run_id' => $run->id, 'name' => 'A', 'price' => 20]);
    orderRunVariant($product, $run, $variant);

    $this->actingAs(runManager())->delete(route('products.runs.destroy', [$product, $run]));

    expect(ProductRun::find($run->id))->not->toBeNull();
});

it('will not edit a run through another product', function () {
    $mine = runProduct();
    $theirs = runProduct();
    $run = $theirs->runs()->create(['name' => 'Theirs']);

    $this->actingAs(runManager())
        ->get(route('products.runs.edit', [$mine, $run]))
        ->assertNotFound();
});
