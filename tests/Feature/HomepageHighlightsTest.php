<?php

use App\Models\Event;
use App\Models\Product;
use App\Models\User;
use App\Services\HomepageHighlights;

/**
 * Events and store items share one highlight_order scale on the home page, so a
 * shirt can sit between two events. Before this the page rendered every event
 * and then every product, and a product could never outrank an event however it
 * was numbered.
 */
function highlightUser(): User
{
    $user = User::factory()->create();
    $user->markEmailAsVerified();

    return $user;
}

function highlightEvent(string $name, array $attrs = []): Event
{
    return Event::create(array_merge([
        'name' => $name,
        'slug' => Str::slug($name).'-'.uniqid(),
        'cost' => 0,
        'startdatetime' => now()->addMonth(),
    ], $attrs));
}

function highlightProduct(string $name, array $attrs = [], array $runAttrs = []): Product
{
    $product = Product::create(array_merge([
        'name' => $name,
        'slug' => Str::slug($name).'-'.uniqid(),
        'status' => Product::STATUS_ACTIVE,
        'stripe_account' => 'association',
    ], $attrs));

    $run = $product->runs()->create(array_merge(['name' => 'Run'], $runAttrs));
    $run->variants()->create(['name' => 'Item', 'price' => 20]);

    return $product->fresh();
}

function highlights(): HomepageHighlights
{
    return app(HomepageHighlights::class);
}

/* -------------------------------------------------------------------- *
 * One shared priority scale
 * -------------------------------------------------------------------- */

it('ranks a product above an event when its highlight_order is higher', function () {
    highlightEvent('Lower Event', ['highlighted' => true, 'highlight_order' => 1]);
    $shirt = highlightProduct('Top Shirt', ['highlighted' => true, 'highlight_order' => 5]);

    expect(highlights()->all()->first()->name)->toBe($shirt->name);
});

it('interleaves a product between two events', function () {
    highlightEvent('First Event', ['highlighted' => true, 'highlight_order' => 9]);
    highlightProduct('Middle Shirt', ['highlighted' => true, 'highlight_order' => 5]);
    highlightEvent('Third Event', ['highlighted' => true, 'highlight_order' => 1]);

    expect(highlights()->all()->pluck('name')->all())
        ->toBe(['First Event', 'Middle Shirt', 'Third Event']);
});

it('puts featured things ahead of unfeatured upcoming events', function () {
    highlightEvent('Soon But Ordinary', ['startdatetime' => now()->addDay()]);
    highlightProduct('Featured Shirt', ['highlighted' => true, 'highlight_order' => 1]);

    expect(highlights()->all()->first()->name)->toBe('Featured Shirt');
});

/* -------------------------------------------------------------------- *
 * The feature row
 * -------------------------------------------------------------------- */

it('gives a lone featured item the whole row', function () {
    highlightEvent('The Only Featured', ['highlighted' => true, 'highlight_order' => 1]);
    highlightEvent('Ordinary', ['startdatetime' => now()->addDays(2)]);

    expect(highlights()->featured())->toHaveCount(1)
        ->and(highlights()->featured()->first()->name)->toBe('The Only Featured');
});

it('features two side by side', function () {
    highlightEvent('Tournament', ['highlighted' => true, 'highlight_order' => 2]);
    highlightProduct('Shirt', ['highlighted' => true, 'highlight_order' => 1]);

    expect(highlights()->featured()->pluck('name')->all())->toBe(['Tournament', 'Shirt']);
});

it('never fills the feature row with something unfeatured', function () {
    // An empty feature row is correct; promoting an ordinary event into it
    // would put something on the page nobody chose to push.
    highlightEvent('Ordinary One');
    highlightEvent('Ordinary Two', ['startdatetime' => now()->addDays(2)]);

    expect(highlights()->featured())->toBeEmpty()
        ->and(highlights()->rest())->toHaveCount(2);
});

it('drops a third featured item into the grid rather than the feature row', function () {
    highlightEvent('One', ['highlighted' => true, 'highlight_order' => 3]);
    highlightEvent('Two', ['highlighted' => true, 'highlight_order' => 2]);
    highlightProduct('Three', ['highlighted' => true, 'highlight_order' => 1]);

    expect(highlights()->featured()->pluck('name')->all())->toBe(['One', 'Two'])
        ->and(highlights()->rest()->pluck('name')->all())->toContain('Three');
});

it('does not repeat a featured item in the grid', function () {
    highlightEvent('Tournament', ['highlighted' => true, 'highlight_order' => 2]);
    highlightProduct('Shirt', ['highlighted' => true, 'highlight_order' => 1]);

    expect(highlights()->rest()->pluck('name')->all())
        ->not->toContain('Tournament')
        ->not->toContain('Shirt');
});

/* -------------------------------------------------------------------- *
 * Rendering
 * -------------------------------------------------------------------- */

it('shows both a featured event and a featured product on the dashboard', function () {
    $event = highlightEvent('Gateway Championships', ['highlighted' => true, 'highlight_order' => 2]);
    $product = highlightProduct('Membership Oath Shirt', ['highlighted' => true, 'highlight_order' => 1]);

    $content = $this->actingAs(highlightUser())->get('/dashboard')->assertOk()->getContent();

    expect($content)->toContain($event->name)
        ->toContain($product->name)
        // The product outranks the two ordinary events that follow it.
        ->and(strpos($content, $event->name))->toBeLessThan(strpos($content, $product->name));
});

/* -------------------------------------------------------------------- *
 * Countdowns
 * -------------------------------------------------------------------- */

it('counts down to an event', function () {
    expect(highlightEvent('Soon', ['startdatetime' => now()->addDays(12)])->countdown())->toBe('In 12 days')
        ->and(highlightEvent('Tomorrow', ['startdatetime' => now()->addDay()])->countdown())->toBe('Tomorrow')
        ->and(highlightEvent('Today', ['startdatetime' => now()->addHours(2)])->countdown())->toBe('Today');
});

it('stops counting down once an event has passed', function () {
    expect(highlightEvent('Gone', ['startdatetime' => now()->subDay()])->countdown())->toBeNull();
});

it('counts down to a product order deadline', function () {
    $product = highlightProduct('Closing Soon', [], ['closes_at' => now()->addDays(12)]);

    expect($product->ordersCloseCountdown())->toBe('Orders close in 12 days');
});

it('has no order countdown when the run has no deadline', function () {
    expect(highlightProduct('Open Ended')->ordersCloseCountdown())->toBeNull();
});
