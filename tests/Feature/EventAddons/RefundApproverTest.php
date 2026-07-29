<?php

use App\Models\AddonChangeRequest;
use App\Models\Event;
use App\Models\EventAddon;
use App\Models\EventRegistration;
use App\Models\EventRegistrationAddon;
use App\Models\Refund;
use App\Models\User;
use App\Services\RefundApprover;

/**
 * A registration with a 2-meal ($24) answer and a pending request to drop it to
 * one meal ($12) — a $12 refund.
 *
 * @return array{0: AddonChangeRequest, 1: EventRegistrationAddon, 2: User}
 */
function pendingRefund(): array
{
    $event = Event::create(['name' => 'Refund Test', 'cost' => 65]);
    $meal = EventAddon::create(['event_id' => $event->id, 'type' => 'meal_ticket', 'enabled' => true, 'sort_order' => 0, 'settings' => ['price' => 12]]);
    $event->load('addons');

    $user = User::factory()->create();
    $event->registrations()->attach($user->id, ['amount_due' => 65, 'amount_paid' => 89, 'docs_printed' => 0]);
    $reg = EventRegistration::where('event_id', $event->id)->where('user_id', $user->id)->first();

    $answer = EventRegistrationAddon::create([
        'event_registration_id' => $reg->id, 'event_addon_id' => $meal->id,
        'type' => 'meal_ticket', 'selected' => true, 'quantity' => 1, 'amount' => 24, 'payment_id' => 500,
    ]);

    $request = AddonChangeRequest::create([
        'event_id' => $event->id,
        'event_registration_id' => $reg->id,
        'requested_by_user_id' => $user->id,
        'new_state' => [[
            'event_addon_id' => $meal->id, 'type' => 'meal_ticket',
            'attrs' => ['selected' => true, 'quantity' => 0, 'amount' => 12],
        ]],
        'refund_amount' => 12,
        'stripe_payment_intent_id' => 'pi_refund_x',
        'status' => 'pending',
    ]);

    return [$request, $answer, $user];
}

it('approves a refund: charges Stripe, applies the reduction, records the decision', function () {
    [$request, $answer, $user] = pendingRefund();
    $calls = [];
    $approver = new RefundApprover(function ($pi, $cents) use (&$calls) {
        $calls[] = [$pi, $cents];

        return 're_fake_1';
    });

    $approver->approve($request->fresh(), 12.0, $user);

    expect($calls)->toBe([['pi_refund_x', 1200]]); // full $12 refunded to the right PI
    expect((float) $answer->fresh()->amount)->toBe(12.0); // reduced to one meal
    $request->refresh();
    expect($request->status)->toBe('approved');
    expect($request->stripe_refund_id)->toBe('re_fake_1');
    expect($request->decided_by_user_id)->toBe($user->id);
});

it('honors an admin-edited (reduced) refund amount', function () {
    [$request, , $user] = pendingRefund();
    $calls = [];
    $approver = new RefundApprover(function ($pi, $cents) use (&$calls) {
        $calls[] = $cents;

        return 're_fake_2';
    });

    // Admin withholds ~the Stripe fee: refund $11.35 instead of $12.
    $approver->approve($request->fresh(), 11.35, $user);

    expect($calls)->toBe([1135]);
    expect((float) $request->fresh()->refund_amount)->toBe(11.35);
});

it('writes a refund ledger row with the category breakdown, snapshotted before the reduction', function () {
    [$request, , $user] = pendingRefund();
    $approver = new RefundApprover(fn ($pi, $cents) => 're_fake_led');

    $approver->approve($request->fresh(), 12.0, $user);

    $refund = Refund::where('addon_change_request_id', $request->id)->first();
    expect($refund)->not->toBeNull();
    expect((float) $refund->amount)->toBe(12.0);
    expect($refund->person_id)->toBe($user->id);
    expect($refund->refunded_to_user_id)->toBe($user->id);
    expect($refund->stripe_payment_intent_id)->toBe('pi_refund_x');
    expect($refund->stripe_refund_id)->toBe('re_fake_led');
    // Breakdown captures the $12 dropped from the meal, taken before the add-on
    // row was reduced (24 -> 12).
    expect($refund->breakdown)->toHaveCount(1);
    expect((float) $refund->breakdown['meal_ticket'])->toBe(12.0);
});

it('records the actual (reduced) refund amount while the breakdown reflects the full reduction', function () {
    [$request, , $user] = pendingRefund();
    $approver = new RefundApprover(fn ($pi, $cents) => 're_fake_led2');

    $approver->approve($request->fresh(), 11.35, $user); // admin withheld the fee

    $refund = Refund::where('addon_change_request_id', $request->id)->first();
    expect((float) $refund->amount)->toBe(11.35);
    expect($refund->breakdown)->toHaveCount(1);
    expect((float) $refund->breakdown['meal_ticket'])->toBe(12.0);
});

it('writes no refund ledger row on denial', function () {
    [$request, , $user] = pendingRefund();
    $approver = new RefundApprover(fn () => 're_x');

    $approver->deny($request->fresh(), $user, 'Past the refund deadline');

    expect(Refund::where('addon_change_request_id', $request->id)->exists())->toBeFalse();
});

it('denies a request without refunding or changing the registration', function () {
    [$request, $answer, $user] = pendingRefund();
    $called = false;
    $approver = new RefundApprover(function () use (&$called) {
        $called = true;

        return 're_x';
    });

    $approver->deny($request->fresh(), $user, 'Past the refund deadline');

    expect($called)->toBeFalse();
    expect((float) $answer->fresh()->amount)->toBe(24.0); // unchanged
    $request->refresh();
    expect($request->status)->toBe('denied');
    expect($request->admin_note)->toBe('Past the refund deadline');
});

it('ignores a second decision on an already-decided request', function () {
    [$request, , $user] = pendingRefund();
    $count = 0;
    $approver = new RefundApprover(function () use (&$count) {
        $count++;

        return 're_y';
    });

    $approver->approve($request->fresh(), 12.0, $user);
    $approver->approve($request->fresh(), 12.0, $user); // no-op

    expect($count)->toBe(1);
});
