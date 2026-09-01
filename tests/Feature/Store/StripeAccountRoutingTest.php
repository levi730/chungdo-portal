<?php

use App\Models\Event;
use App\Models\Product;
use App\Services\Stripe\StripeAccounts;

/**
 * Two configured accounts, so resolution has something to choose between.
 */
beforeEach(function () {
    config([
        'services.stripe.default_account' => 'association',
        'services.stripe.accounts' => [
            'association' => [
                'label' => 'Association Account',
                'key' => 'pk_test_assoc',
                'secret' => 'sk_test_assoc',
                'webhook_secret' => 'whsec_assoc',
            ],
            'main_school' => [
                'label' => 'Main School Account',
                'key' => 'pk_test_school',
                'secret' => 'sk_test_school',
                'webhook_secret' => 'whsec_school',
            ],
        ],
    ]);
});

it('resolves credentials for a product through the generic interface', function () {
    $product = Product::create([
        'name' => 'Gym Bag',
        'slug' => 'gym-bag',
        'status' => Product::STATUS_ACTIVE,
        'stripe_account' => 'main_school',
    ]);

    $accounts = app(StripeAccounts::class);

    expect($accounts->for($product))->toBe('main_school')
        ->and($accounts->secretFor($product))->toBe('sk_test_school')
        ->and($accounts->publishableKeyFor($product))->toBe('pk_test_school')
        ->and($product->stripeAccountLabel())->toBe('Main School Account');
});

it('still resolves an event the old way after the generalization', function () {
    $event = Event::create(['name' => 'Routing Test', 'cost' => 0, 'stripe_account' => 'main_school']);

    $accounts = app(StripeAccounts::class);

    expect($accounts->forEvent($event))->toBe('main_school')
        ->and($accounts->secretForEvent($event))->toBe('sk_test_school')
        ->and($accounts->publishableKeyForEvent($event))->toBe('pk_test_school');
});

it('falls back to the default account for an unknown or missing slug', function () {
    $product = Product::create([
        'name' => 'Mystery',
        'slug' => 'mystery',
        'status' => Product::STATUS_ACTIVE,
        'stripe_account' => 'no_such_account',
    ]);

    $accounts = app(StripeAccounts::class);

    expect($accounts->for($product))->toBe('association')
        ->and($accounts->for(null))->toBe('association')
        ->and($accounts->secretFor($product))->toBe('sk_test_assoc');
});

it('hides an account with no secret configured', function () {
    config(['services.stripe.accounts.main_school.secret' => null]);

    $accounts = app(StripeAccounts::class);

    expect($accounts->options())->toHaveKey('association')
        ->and($accounts->options())->not->toHaveKey('main_school')
        ->and($accounts->resolve('main_school'))->toBe('association');
});
