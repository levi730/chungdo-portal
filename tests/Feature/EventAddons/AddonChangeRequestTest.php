<?php

use App\Models\AddonChangeRequest;
use App\Models\Event;
use App\Models\EventAddon;
use App\Models\EventRegistration;
use App\Models\User;

it('renders summary lines even when the registration was deleted after the request was decided', function () {
    $event = Event::create(['name' => 'Summary Test', 'cost' => 65]);
    $meal = EventAddon::create(['event_id' => $event->id, 'type' => 'meal_ticket', 'enabled' => true, 'sort_order' => 0, 'settings' => ['price' => 12]]);
    $event->load('addons');

    $user = User::factory()->create();
    $event->registrations()->attach($user->id, ['amount_due' => 65, 'amount_paid' => 89, 'docs_printed' => 0]);
    $reg = EventRegistration::where('event_id', $event->id)->where('user_id', $user->id)->first();

    $request = AddonChangeRequest::create([
        'event_id' => $event->id,
        'event_registration_id' => $reg->id,
        'requested_by_user_id' => $user->id,
        'new_state' => [[
            'event_addon_id' => $meal->id, 'type' => 'meal_ticket',
            'attrs' => ['selected' => true, 'quantity' => 0, 'amount' => 12],
        ]],
        'refund_amount' => 12,
        'status' => 'approved',
    ]);

    // The registrant is later removed entirely — the request now dangles.
    $reg->delete();

    $lines = $request->fresh()->summaryLines();

    // With no current answers, "from" is 0 and "to" is the requested amount.
    expect($lines)->toHaveCount(1);
    expect($lines[0]['label'])->toBe('Meal Ticket');
    expect($lines[0]['from'])->toBe(0.0);
    expect($lines[0]['to'])->toBe(12.0);
});
