<?php

use App\Models\Product;
use App\Models\ProductRun;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Store\Cart;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The public storefront and the session cart.
 *
 * These are the portal's first guest-accessible member-facing pages, so the
 * "a signed-out visitor can reach this" assertions below are load-bearing, not
 * incidental.
 */
function shopProduct(array $attrs = [], array $runAttrs = []): Product
{
    static $n = 0;
    $n++;

    $product = Product::create(array_merge([
        'name' => "Shop Product {$n}",
        'slug' => "shop-product-{$n}",
        'status' => Product::STATUS_ACTIVE,
        'stripe_account' => 'association',
        'option_names' => ['Item', 'Size'],
    ], $attrs));

    $product->runs()->create(array_merge(['name' => "Run {$n}"], $runAttrs));

    return $product->fresh();
}

function shopVariant(Product $product, array $attrs = []): ProductVariant
{
    return $product->runs()->first()->variants()->create(array_merge([
        'name' => 'Adult T-Shirt / M',
        'options' => ['Item' => 'Adult T-Shirt', 'Size' => 'M'],
        'price' => 20,
        'enabled' => true,
    ], $attrs));
}

function cart(): Cart
{
    return app(Cart::class);
}

/* -------------------------------------------------------------------- *
 * Public access
 * -------------------------------------------------------------------- */

it('lets a signed-out visitor browse the store', function () {
    $product = shopProduct();
    shopVariant($product);

    $this->get(route('store.index'))->assertOk()->assertSee($product->name);
    $this->get(route('store.show', $product->slug))->assertOk()->assertSee($product->name);
    $this->get(route('store.cart'))->assertOk();
});

it('shows a signed-in member the same pages', function () {
    $product = shopProduct();
    shopVariant($product);
    $user = User::factory()->create();
    $user->markEmailAsVerified();

    $this->actingAs($user)->get(route('store.index'))->assertOk()->assertSee($product->name);
    $this->actingAs($user)->get(route('store.show', $product->slug))->assertOk();
});

it('lists only products with an open run', function () {
    $open = shopProduct();
    shopVariant($open);

    $closed = shopProduct([], ['closes_at' => now()->subDay()]);
    shopVariant($closed);

    $draft = shopProduct(['status' => Product::STATUS_DRAFT]);
    shopVariant($draft);

    $response = $this->get(route('store.index'));

    $response->assertOk()
        ->assertSee($open->name)
        ->assertDontSee($closed->name)
        ->assertDontSee($draft->name);
});

it('still serves the page for a product between runs', function () {
    // A shared link shouldn't 404 just because the window shut.
    $product = shopProduct([], ['closes_at' => now()->subDay()]);
    shopVariant($product);

    $this->get(route('store.show', $product->slug))
        ->assertOk()
        ->assertSee("isn't open for orders", false);
});

it('404s an unknown slug', function () {
    $this->get(route('store.show', 'no-such-product'))->assertNotFound();
});

it('shows the product image whole rather than cropping it', function () {
    // A hero exists to show the design. Cropping to a fixed ratio chops a
    // portrait photo top and bottom, and the focal point cannot prevent that —
    // it only decides WHERE the chop lands. Only the thumbnails crop.
    Storage::fake(config('media-library.disk_name'));

    $product = shopProduct();
    shopVariant($product);
    $product->addMedia(UploadedFile::fake()->image('design.jpg', 1200, 1600))
        ->toMediaCollection('product-images');

    $response = $this->get(route('store.show', $product->slug));

    $response->assertOk()
        // Resized to width only...
        ->assertSee('w=1200', false)
        // ...never with a height + crop, which is what chopped it before.
        ->assertDontSee('w=1200&h=800', false)
        ->assertDontSee('w=1200&amp;h=800', false)
        // Thumbnails still crop — they have to be uniform squares.
        ->assertSee('fit=crop', false);
});

it('puts every image in the gallery strip, including the first', function () {
    // The first image used to be excluded from the strip, so once you clicked
    // away from it there was no way back.
    Storage::fake(config('media-library.disk_name'));

    $product = shopProduct();
    shopVariant($product);
    foreach (['one.jpg', 'two.jpg', 'three.jpg'] as $name) {
        $product->addMedia(UploadedFile::fake()->image($name, 800, 800))
            ->toMediaCollection('product-images');
    }

    $response = $this->get(route('store.show', $product->slug));

    // The gallery hands Alpine a JSON array of all three.
    $response->assertOk()->assertSee('Click an image to see it larger');

    expect(substr_count($response->getContent(), 'w=2000'))->toBe(3);
});

/* -------------------------------------------------------------------- *
 * The cart
 * -------------------------------------------------------------------- */

it('adds an item to the cart as a guest', function () {
    $product = shopProduct();
    $variant = shopVariant($product);

    $this->post(route('store.cart.add'), [
        'product_variant_id' => $variant->id,
        'quantity' => 2,
    ])->assertRedirect(route('store.cart'));

    $this->get(route('store.cart'))
        ->assertOk()
        ->assertSee($product->name)
        ->assertSee('40.00');
});

it('adds to the existing quantity rather than replacing it', function () {
    $variant = shopVariant(shopProduct());

    $this->post(route('store.cart.add'), ['product_variant_id' => $variant->id, 'quantity' => 2]);
    $this->post(route('store.cart.add'), ['product_variant_id' => $variant->id, 'quantity' => 3]);

    expect(cart()->count())->toBe(5);
});

it('updates and removes a line', function () {
    $variant = shopVariant(shopProduct());
    $this->post(route('store.cart.add'), ['product_variant_id' => $variant->id]);

    $this->patch(route('store.cart.update'), ['product_variant_id' => $variant->id, 'quantity' => 4]);
    expect(cart()->count())->toBe(4);

    $this->delete(route('store.cart.remove', $variant->id));
    expect(cart()->isEmpty())->toBeTrue();
});

it('treats a quantity of zero as a removal', function () {
    $variant = shopVariant(shopProduct());
    $this->post(route('store.cart.add'), ['product_variant_id' => $variant->id]);

    $this->patch(route('store.cart.update'), ['product_variant_id' => $variant->id, 'quantity' => 0]);

    expect(cart()->isEmpty())->toBeTrue();
});

it('prices the cart from the variant, not from the session', function () {
    // A cart left open across a price edit must charge the new price.
    $variant = shopVariant(shopProduct());
    $this->post(route('store.cart.add'), ['product_variant_id' => $variant->id, 'quantity' => 2]);

    expect(cart()->subtotal())->toBe(40.0);

    $variant->update(['price' => 25]);

    expect(cart()->subtotal())->toBe(50.0);
});

/* -------------------------------------------------------------------- *
 * The rules the cart has to enforce
 * -------------------------------------------------------------------- */

it('refuses to mix Stripe accounts in one cart', function () {
    $association = shopProduct(['stripe_account' => 'association']);
    $school = shopProduct(['stripe_account' => 'main_school']);

    $this->post(route('store.cart.add'), ['product_variant_id' => shopVariant($association)->id])
        ->assertRedirect();

    $this->post(route('store.cart.add'), ['product_variant_id' => shopVariant($school)->id])
        ->assertSessionHas('error');

    expect(cart()->count())->toBe(1)
        ->and(cart()->stripeAccount())->toBe('association');
});

it('honours max_per_order across a product\'s variants', function () {
    $product = shopProduct(['max_per_order' => 3]);
    $small = shopVariant($product, ['name' => 'S', 'options' => ['Item' => 'Tee', 'Size' => 'S']]);
    $large = shopVariant($product, ['name' => 'L', 'options' => ['Item' => 'Tee', 'Size' => 'L']]);

    $this->post(route('store.cart.add'), ['product_variant_id' => $small->id, 'quantity' => 2]);

    // 2 + 2 would be 4 across the same product, over the limit of 3.
    $this->post(route('store.cart.add'), ['product_variant_id' => $large->id, 'quantity' => 2])
        ->assertSessionHas('error');

    expect(cart()->count())->toBe(2);
});

it('will not add a disabled variant', function () {
    $variant = shopVariant(shopProduct(), ['enabled' => false]);

    $this->post(route('store.cart.add'), ['product_variant_id' => $variant->id])
        ->assertSessionHas('error');

    expect(cart()->isEmpty())->toBeTrue();
});

it('will not add an item whose run has closed', function () {
    $product = shopProduct([], ['closes_at' => now()->subDay()]);

    $this->post(route('store.cart.add'), ['product_variant_id' => shopVariant($product)->id])
        ->assertSessionHas('error');

    expect(cart()->isEmpty())->toBeTrue();
});

it('drops a line once its run closes underneath the buyer', function () {
    // The window can shut while a cart sits open. The line has to disappear
    // rather than carry a price that can no longer be honoured.
    $product = shopProduct();
    $variant = shopVariant($product);

    $this->post(route('store.cart.add'), ['product_variant_id' => $variant->id, 'quantity' => 2]);
    expect(cart()->count())->toBe(2);

    $product->runs()->first()->update(['closes_at' => now()->subMinute()]);

    expect(cart()->isEmpty())->toBeTrue()
        ->and(cart()->subtotal())->toBe(0.0);
});

it('drops a line when the product is archived', function () {
    $product = shopProduct();
    $this->post(route('store.cart.add'), ['product_variant_id' => shopVariant($product)->id]);

    $product->update(['status' => Product::STATUS_ARCHIVED]);

    expect(cart()->isEmpty())->toBeTrue();
});

it('keeps one product\'s lines when another product\'s run closes', function () {
    $staying = shopProduct();
    $going = shopProduct();

    $this->post(route('store.cart.add'), ['product_variant_id' => shopVariant($staying)->id]);
    $this->post(route('store.cart.add'), ['product_variant_id' => shopVariant($going)->id]);
    expect(cart()->count())->toBe(2);

    $going->runs()->first()->update(['closes_at' => now()->subMinute()]);

    expect(cart()->count())->toBe(1)
        ->and(cart()->products()->pluck('id')->all())->toBe([$staying->id]);
});

it('rejects an unknown variant id', function () {
    $this->post(route('store.cart.add'), ['product_variant_id' => 999999])
        ->assertSessionHasErrors('product_variant_id');
});

it('does not create an order row while shopping', function () {
    // The reconcile sweep looks for stale pending orders. Writing one at
    // add-to-cart time would have it interrogating Stripe about abandoned carts.
    $this->post(route('store.cart.add'), ['product_variant_id' => shopVariant(shopProduct())->id]);

    expect(App\Models\ProductOrder::count())->toBe(0);
});

it('resolves the run and product from a cart line', function () {
    $product = shopProduct();
    $variant = shopVariant($product);
    $this->post(route('store.cart.add'), ['product_variant_id' => $variant->id]);

    $line = cart()->lines()->first();

    expect($line->product()->id)->toBe($product->id)
        ->and($line->run()->id)->toBe($product->runs()->first()->id)
        ->and($line->label())->toBe('Adult T-Shirt / M')
        ->and($line->amount())->toBe(20.0);
});
