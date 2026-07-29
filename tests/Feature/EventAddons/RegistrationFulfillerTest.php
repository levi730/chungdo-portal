<?php

use App\Models\Event;
use App\Models\EventAddon;
use App\Models\EventRegistration;
use App\Models\EventRegistrationAddon;
use App\Models\Payment;
use App\Models\PendingEventRegistration;
use App\Models\PotluckOptions;
use App\Models\User;
use App\Services\RegistrationFulfiller;
use Illuminate\Support\Facades\Mail;

/**
 * Build a pending registration for one user with a meal add-on answer.
 */
function pendingFor(User $user, float $mealAmount = 24, array $group = [], ?string $piId = null, ?float $amountPaid = null): array
{
    $event = Event::create(['name' => 'Fulfill Test', 'cost' => 65]);
    $addon = EventAddon::create(['event_id' => $event->id, 'type' => 'meal_ticket', 'enabled' => true, 'sort_order' => 0, 'settings' => ['price' => 12]]);

    $pending = PendingEventRegistration::create([
        'reference' => (string) Illuminate\Support\Str::uuid(),
        'stripe_payment_intent_id' => $piId,
        'event_id' => $event->id,
        'registering_user_id' => $user->id,
        'amount' => 65 + $mealAmount,
        'amount_paid' => $amountPaid,
        'status' => 'pending',
        'payload' => [
            'event_id' => $event->id,
            'amount_due_each' => 65,
            'group' => array_merge(['potluck_item_id' => null, 'potluck_open_item' => null, 'donation_amount' => 0], $group),
            'users' => [[
                'user_id' => $user->id,
                'tshirt_size' => 'L',
                'volunteer_selections' => [],
                'guests' => null,
                'addons' => [
                    ['event_addon_id' => $addon->id, 'type' => 'meal_ticket', 'attrs' => ['selected' => true, 'quantity' => 1, 'amount' => $mealAmount]],
                ],
            ]],
        ],
    ]);

    return [$event, $pending, $addon];
}

beforeEach(fn () => Mail::fake());

it('fulfills a free pending registration and records add-on answers', function () {
    $u = User::factory()->create();
    [$event, $pending] = pendingFor($u);

    $did = (new RegistrationFulfiller())->fulfill($pending);

    expect($did)->toBeTrue();
    $reg = EventRegistration::where('event_id', $event->id)->where('user_id', $u->id)->first();
    expect($reg)->not->toBeNull();
    expect(EventRegistrationAddon::where('event_registration_id', $reg->id)->where('type', 'meal_ticket')->exists())->toBeTrue();
    expect($pending->fresh()->status)->toBe('fulfilled');
});

it('is idempotent — a second fulfill does nothing', function () {
    $u = User::factory()->create();
    [$event, $pending] = pendingFor($u);

    $first = (new RegistrationFulfiller())->fulfill($pending);
    $second = (new RegistrationFulfiller())->fulfill($pending->fresh());

    expect($first)->toBeTrue();
    expect($second)->toBeFalse();
    expect(EventRegistration::where('event_id', $event->id)->where('user_id', $u->id)->count())->toBe(1);
});

it('creates exactly one Payment for a paid registration even if fulfilled twice', function () {
    $u = User::factory()->create();
    [$event, $pending] = pendingFor($u, piId: 'pi_test_123', amountPaid: 89);

    (new RegistrationFulfiller())->fulfill($pending);
    (new RegistrationFulfiller())->fulfill($pending->fresh());

    expect(Payment::where('stripe_payment_intent_id', 'pi_test_123')->count())->toBe(1);
    $reg = EventRegistration::where('event_id', $event->id)->where('user_id', $u->id)->first();
    expect((float) $reg->amount_paid)->toBe(89.0);
});

it('reconciles from the webhook when the sync path never fulfilled', function () {
    $u = User::factory()->create();
    // Pending created but sync response was lost: no PI id / amount recorded yet.
    [$event, $pending] = pendingFor($u);

    (new RegistrationFulfiller())->reconcileSucceeded('pi_hook_9', $pending->id, 89.0);

    $pending->refresh();
    expect($pending->status)->toBe('fulfilled');
    expect($pending->stripe_payment_intent_id)->toBe('pi_hook_9');
    expect((float) $pending->amount_paid)->toBe(89.0);
    expect(EventRegistration::where('event_id', $event->id)->where('user_id', $u->id)->exists())->toBeTrue();
    expect(Payment::where('stripe_payment_intent_id', 'pi_hook_9')->count())->toBe(1);
});

it('does not double-fulfill when sync and webhook both fire', function () {
    $u = User::factory()->create();
    [$event, $pending] = pendingFor($u, piId: 'pi_race_1', amountPaid: 89);

    (new RegistrationFulfiller())->fulfill($pending);                                  // sync
    (new RegistrationFulfiller())->reconcileSucceeded('pi_race_1', $pending->id, 89);  // webhook

    expect(EventRegistration::where('event_id', $event->id)->where('user_id', $u->id)->count())->toBe(1);
    expect(Payment::where('stripe_payment_intent_id', 'pi_race_1')->count())->toBe(1);
});

it('increments a potluck item count exactly once', function () {
    $u = User::factory()->create();
    $event = Event::create(['name' => 'Potluck Fulfill', 'cost' => 0]);
    $option = PotluckOptions::forceCreate(['event_id' => $event->id, 'category' => 'Sides', 'item' => 'Chips', 'limit' => '10', 'current_count' => 0]);

    $pending = PendingEventRegistration::create([
        'reference' => (string) Illuminate\Support\Str::uuid(),
        'event_id' => $event->id,
        'registering_user_id' => $u->id,
        'amount' => 0,
        'status' => 'pending',
        'payload' => [
            'event_id' => $event->id,
            'amount_due_each' => 0,
            'group' => ['potluck_item_id' => $option->id, 'potluck_open_item' => null, 'donation_amount' => 0],
            'users' => [['user_id' => $u->id, 'tshirt_size' => null, 'volunteer_selections' => [], 'guests' => null, 'addons' => []]],
        ],
    ]);

    (new RegistrationFulfiller())->fulfill($pending);
    (new RegistrationFulfiller())->fulfill($pending->fresh());

    expect((int) $option->fresh()->current_count)->toBe(1);
});
