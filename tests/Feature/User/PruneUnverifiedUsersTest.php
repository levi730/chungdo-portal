<?php

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\Users\AccountFootprint;
use Illuminate\Support\Facades\DB;

function staleSignup(array $attrs = []): User
{
    $user = User::factory()->create(array_merge([
        'can_login' => 1,
        'email_verified_at' => null,
        'school_id' => null,
    ], $attrs));
    $user->forceFill(['created_at' => now()->subDays(45)])->saveQuietly();

    return $user;
}

it('finds nothing on a bare sign-up and calls it deletable', function () {
    $user = staleSignup();

    expect(app(AccountFootprint::class)->for($user->id))->toBe(['blocking' => [], 'disposable' => []]);
    expect(app(AccountFootprint::class)->isDeletable($user->id))->toBeTrue();
});

it('blocks deleting an account that has a dependent', function () {
    $parent = staleSignup();
    User::factory()->is_kid()->belongs_to($parent)->create();

    $found = app(AccountFootprint::class)->for($parent->id);

    expect($found['blocking'])->toHaveKeys(['users.responsible_user_id', 'guardianships.guardian_user_id']);
    expect(app(AccountFootprint::class)->isDeletable($parent->id))->toBeFalse();
});

it('blocks deleting an account with an event registration', function () {
    $user = staleSignup();
    $event = Event::create(['name' => 'Prune Cup', 'slug' => 'prune-cup-'.uniqid(), 'cost' => 0, 'startdatetime' => now()->addMonth()]);
    EventRegistration::create(['event_id' => $event->id, 'user_id' => $user->id]);

    expect(app(AccountFootprint::class)->for($user->id)['blocking'])->toHaveKey('event_registrations.user_id');
});

it('blocks deleting an account that carries a Stripe customer', function () {
    $user = staleSignup(['stripe_id' => 'cus_test_'.uniqid()]);

    expect(app(AccountFootprint::class)->for($user->id)['blocking'])->toHaveKey('users.stripe_id');
});

it('treats sessions as disposable and removes them with the account', function () {
    $user = staleSignup();
    DB::table('sessions')->insert(['id' => 'sess-'.uniqid(), 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    $footprint = app(AccountFootprint::class);
    expect($footprint->for($user->id)['disposable'])->toHaveKey('sessions.user_id');

    $footprint->delete($user->id);

    expect(User::find($user->id))->toBeNull();
    expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();
});

it('prunes stale unverified sign-ups and nothing else', function () {
    $bot = staleSignup(['firstname' => 'Georgegooxy', 'lastname' => 'GeorgegooxyVM']);
    $fresh = User::factory()->create(['can_login' => 1, 'email_verified_at' => null]);
    $verified = staleSignup(['email_verified_at' => now()->subDays(40)]);
    $parent = staleSignup();
    User::factory()->is_kid()->belongs_to($parent)->create();

    $this->artisan('users:prune-unverified')->assertSuccessful();

    expect(User::find($bot->id))->toBeNull();
    expect(User::find($fresh->id))->not->toBeNull();     // younger than --days
    expect(User::find($verified->id))->not->toBeNull();  // confirmed their email
    expect(User::find($parent->id))->not->toBeNull();    // has a dependent
});

it('deletes nothing on a dry run', function () {
    $bot = staleSignup();

    $this->artisan('users:prune-unverified', ['--dry-run' => true])
        ->expectsOutputToContain("#{$bot->id}")
        ->assertSuccessful();

    expect(User::find($bot->id))->not->toBeNull();
});

it('is not on the schedule', function () {
    $this->artisan('schedule:list')->doesntExpectOutputToContain('users:prune-unverified');
});
