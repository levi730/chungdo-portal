<?php

use App\EventAddons\EventParticipationAddon;
use App\Models\Event;
use App\Models\EventAddon;
use App\Models\EventRegistration;
use App\Models\EventRegistrationAddon;
use App\Models\User;
use Illuminate\Http\Request;

it('applies only to combined events', function () {
    $addon = new EventParticipationAddon();

    expect($addon->appliesTo(new Event(['type' => 'combined'])))->toBeTrue();
    expect($addon->appliesTo(new Event(['type' => 'sparring'])))->toBeFalse();
    expect($addon->appliesTo(new Event(['type' => 'forms'])))->toBeFalse();
    expect($addon->appliesTo(new Event(['type' => 'social'])))->toBeFalse();
});

it('parses a participation choice, defaulting to both', function () {
    $addon = new EventParticipationAddon();
    $eventAddon = new EventAddon();
    $user = new User();
    $user->id = 7;

    expect($addon->parseAnswer(new Request(['participation' => [7 => 'forms']]), $eventAddon, $user, 0))
        ->toBe(['selected' => true, 'value' => 'forms']);

    // No entry for this user -> defaults to both.
    expect($addon->parseAnswer(new Request(['participation' => []]), $eventAddon, $user, 0)['value'])
        ->toBe('both');

    // Tampered / unknown value clamps to both.
    expect($addon->parseAnswer(new Request(['participation' => [7 => 'nonsense']]), $eventAddon, $user, 0)['value'])
        ->toBe('both');
});

it('labels each choice', function () {
    expect(EventParticipationAddon::choiceLabel('sparring'))->toBe('Sparring');
    expect(EventParticipationAddon::choiceLabel('forms'))->toBe('Forms');
    expect(EventParticipationAddon::choiceLabel('both'))->toBe('Sparring + Forms');
    expect(EventParticipationAddon::choiceLabel(null))->toBe('Sparring + Forms');
});

it('reads a stored participation answer and defaults to both', function () {
    $event = Event::create(['name' => 'P', 'slug' => 'part-'.uniqid(), 'type' => 'combined', 'startdatetime' => now()]);
    $user = User::factory()->create();
    $event->registrations()->attach($user->id, ['amount_due' => 0, 'amount_paid' => 0]);
    $reg = EventRegistration::where('event_id', $event->id)->where('user_id', $user->id)->first();

    // No answer yet -> both.
    expect($reg->fresh()->load('addonAnswers')->participation())->toBe('both');

    $addon = EventAddon::create(['event_id' => $event->id, 'type' => 'participation', 'enabled' => true]);
    EventRegistrationAddon::create([
        'event_registration_id' => $reg->id,
        'event_addon_id' => $addon->id,
        'type' => 'participation',
        'selected' => true,
        'value' => 'forms',
    ]);

    expect($reg->fresh()->load('addonAnswers')->participation())->toBe('forms');
});
