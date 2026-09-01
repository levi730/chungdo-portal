<?php

use App\Models\Product;
use App\Models\User;

/**
 * "Feature this product on the portal home page" — the dashboard everyone lands
 * on, alongside the upcoming events. Not the store page, which already lists
 * everything on sale.
 *
 * Product::forHomepage() existed and was tested from the start, but nothing
 * called it: the checkbox was wired to a model method the dashboard never
 * asked for, so ticking it did nothing visible. These cover the wiring.
 */
function dashboardUser(): User
{
    $user = User::factory()->create();
    $user->markEmailAsVerified();

    return $user;
}

function homepageProduct(array $attrs = [], array $runAttrs = []): Product
{
    static $n = 0;
    $n++;

    $product = Product::create(array_merge([
        'name' => "Homepage Product {$n}",
        'slug' => "homepage-product-{$n}",
        'status' => Product::STATUS_ACTIVE,
        'stripe_account' => 'association',
    ], $attrs));

    $run = $product->runs()->create(array_merge(['name' => "Run {$n}"], $runAttrs));
    $run->variants()->create(['name' => 'Item', 'price' => 20]);

    return $product->fresh();
}

it('shows a featured product on the dashboard', function () {
    $product = homepageProduct(['highlighted' => true]);

    $this->actingAs(dashboardUser())
        ->get('/dashboard')
        ->assertOk()
        ->assertSee($product->name)
        ->assertSee(route('store.show', $product->slug));
});

it('keeps an unfeatured product off the dashboard', function () {
    // On sale, but nobody ticked the box. The store lists it; the dashboard
    // must not.
    $product = homepageProduct(['highlighted' => false]);

    $this->actingAs(dashboardUser())
        ->get('/dashboard')
        ->assertOk()
        ->assertDontSee($product->name);

    $this->get(route('store.index'))->assertSee($product->name);
});

it('drops a featured product from the dashboard once its run closes', function () {
    $product = homepageProduct(['highlighted' => true], ['closes_at' => now()->subDay()]);

    $this->actingAs(dashboardUser())
        ->get('/dashboard')
        ->assertOk()
        ->assertDontSee($product->name);
});

it('keeps a featured draft off the dashboard', function () {
    $product = homepageProduct(['highlighted' => true, 'status' => Product::STATUS_DRAFT]);

    $this->actingAs(dashboardUser())
        ->get('/dashboard')
        ->assertOk()
        ->assertDontSee($product->name);
});

it('orders featured products by highlight_order, highest first', function () {
    $low = homepageProduct(['highlighted' => true, 'highlight_order' => 1]);
    $high = homepageProduct(['highlighted' => true, 'highlight_order' => 9]);

    $content = $this->actingAs(dashboardUser())->get('/dashboard')->getContent();

    expect(strpos($content, $high->name))->toBeLessThan(strpos($content, $low->name));
});

it('shows every featured product rather than capping them', function () {
    $names = collect(range(1, 4))
        ->map(fn ($i) => homepageProduct(['highlighted' => true, 'highlight_order' => $i])->name);

    $response = $this->actingAs(dashboardUser())->get('/dashboard')->assertOk();

    foreach ($names as $name) {
        $response->assertSee($name);
    }
});

it('renders the dashboard normally when nothing is featured', function () {
    homepageProduct(['highlighted' => false]);

    // No store row at all, and the rest of the dashboard is untouched.
    $this->actingAs(dashboardUser())
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Welcome back')
        ->assertSee('Linktree')
        ->assertDontSee('Orders close');
});
