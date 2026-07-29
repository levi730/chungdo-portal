<?php

use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventDivisionVersion;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\EventDivisionService;

/** @return array{0: Event, 1: array<int>} an event plus $n registration ids */
function divEventWithRegs(int $n, string $slug): array
{
    $event = Event::create(['name' => $slug, 'slug' => $slug, 'cost' => 0, 'startdatetime' => now()]);
    $regs = [];
    for ($i = 0; $i < $n; $i++) {
        $u = User::factory()->create();
        $event->registrations()->attach($u->id, ['amount_due' => 0, 'amount_paid' => 0]);
        $regs[] = (int) EventRegistration::where('event_id', $event->id)->where('user_id', $u->id)->value('id');
    }

    return [$event, $regs];
}

it('save assigns registrations, deletes removed divisions, and snapshots a version', function () {
    [$event, $r] = divEventWithRegs(3, 'save-test');
    $svc = new EventDivisionService();

    $vid = $svc->save($event, [
        ['id' => null, 'label' => 'Div A', 'members' => [$r[0], $r[1]]],
        ['id' => null, 'label' => 'Div B', 'members' => [$r[2]]],
    ]);

    expect(EventDivision::where('event_id', $event->id)->count())->toBe(2);
    expect(EventRegistration::find($r[0])->event_division_id)->not->toBeNull();
    $version = EventDivisionVersion::find($vid);
    expect($version->data)->toHaveCount(2);
    expect($version->data[0]['members'])->toBe([$r[0], $r[1]]);

    // Save again dropping Div B: its division is deleted and its member unassigned.
    $divA = EventDivision::where('event_id', $event->id)->where('name', 'Div A')->first();
    $svc->save($event, [['id' => $divA->id, 'label' => 'Div A', 'members' => [$r[0], $r[1]]]]);

    expect(EventDivision::where('event_id', $event->id)->count())->toBe(1);
    expect(EventRegistration::find($r[2])->event_division_id)->toBeNull();
});

it('versionBoard drops registration ids that belong to another event', function () {
    [$eventA, $ra] = divEventWithRegs(1, 'scope-a');
    [$eventB, $rb] = divEventWithRegs(1, 'scope-b');

    $v = EventDivisionVersion::create([
        'event_id' => $eventA->id,
        'data' => [['label' => 'X', 'members' => [$ra[0], $rb[0]]]],
        'created_at' => now(),
    ]);

    $board = (new EventDivisionService())->versionBoard($v);

    expect($board[0]['members'])->toHaveCount(1); // eventB's registration is not surfaced
});

it('stars and unstars a version', function () {
    $event = Event::create(['name' => 'T', 'slug' => 't', 'cost' => 0]);
    $v = EventDivisionVersion::create(['event_id' => $event->id, 'data' => [], 'created_at' => now()]);
    $svc = new EventDivisionService();

    expect($svc->toggleStar($v))->toBeTrue();
    expect($v->fresh()->starred)->toBeTrue();
    expect($svc->toggleStar($v->fresh()))->toBeFalse();
});

it('saves and clears a version note', function () {
    $event = Event::create(['name' => 'N', 'slug' => 'n', 'cost' => 0]);
    $v = EventDivisionVersion::create(['event_id' => $event->id, 'data' => [], 'created_at' => now()]);
    $svc = new EventDivisionService();

    $svc->updateNote($v, 'need a spot for Timmy');
    expect($v->fresh()->note)->toBe('need a spot for Timmy');

    $svc->updateNote($v->fresh(), '   '); // blank clears
    expect($v->fresh()->note)->toBeNull();
});

it('publishes and unpublishes a version', function () {
    $event = Event::create(['name' => 'T2', 'slug' => 't2', 'cost' => 0]);
    $v = EventDivisionVersion::create(['event_id' => $event->id, 'data' => [['label' => 'A', 'members' => []]], 'created_at' => now()]);
    $svc = new EventDivisionService();

    $svc->publish($event, $v);
    expect($event->fresh()->published_version_id)->toBe($v->id);
    expect($svc->publishedInfo($event->fresh()))->not->toBeNull();

    $svc->unpublish($event->fresh());
    expect($event->fresh()->published_version_id)->toBeNull();
    expect($svc->publishedInfo($event->fresh()))->toBeNull();
});

it('lists versions newest-first with star and published flags', function () {
    $event = Event::create(['name' => 'T3', 'slug' => 't3', 'cost' => 0]);
    $v1 = EventDivisionVersion::create(['event_id' => $event->id, 'data' => [['label' => 'A', 'members' => [1, 2]]], 'starred' => true, 'created_at' => now()]);
    $v2 = EventDivisionVersion::create(['event_id' => $event->id, 'data' => [], 'created_at' => now()]);
    (new EventDivisionService())->publish($event, $v2);

    $versions = (new EventDivisionService())->versions($event->fresh());

    expect($versions[0]['id'])->toBe($v2->id);      // newest first
    expect($versions[0]['published'])->toBeTrue();
    $v1row = collect($versions)->firstWhere('id', $v1->id);
    expect($v1row['starred'])->toBeTrue();
    expect($v1row['members'])->toBe(2);
    expect($v1row['published'])->toBeFalse();
});

/** A combined event plus a helper to register traited competitors. */
function divCombinedEvent(string $slug): Event
{
    return Event::create(['name' => $slug, 'slug' => $slug, 'type' => 'combined', 'startdatetime' => now()]);
}

function traitReg(Event $e, string $sex, int $rankId, string $dob, ?string $participation = null): int
{
    $u = User::factory()->create();
    \DB::table('users')->where('id', $u->id)->update(['sex' => $sex, 'rank_id' => $rankId, 'dob' => $dob]);
    $e->registrations()->attach($u->id, ['amount_due' => 0, 'amount_paid' => 0]);
    $reg = EventRegistration::where('event_id', $e->id)->where('user_id', $u->id)->first();

    if ($participation !== null) {
        $addon = App\Models\EventAddon::firstOrCreate(['event_id' => $e->id, 'type' => 'participation'], ['enabled' => true]);
        App\Models\EventRegistrationAddon::create([
            'event_registration_id' => $reg->id, 'event_addon_id' => $addon->id,
            'type' => 'participation', 'selected' => true, 'value' => $participation,
        ]);
    }

    return $reg->id;
}

it('saves a forms arrangement to the forms column, leaving sparring untouched', function () {
    $event = divCombinedEvent('disc-save-'.uniqid());
    $r1 = traitReg($event, 'M', -4, '2010-01-01');
    $r2 = traitReg($event, 'F', -4, '2010-01-01');

    (new EventDivisionService())->save($event, [
        ['id' => null, 'label' => 'Forms Div', 'members' => [$r1, $r2]],
    ], 'forms');

    $reg = EventRegistration::find($r1);
    expect($reg->forms_event_division_id)->not->toBeNull();
    expect($reg->event_division_id)->toBeNull();
    expect(EventDivision::where('event_id', $event->id)->where('discipline', 'forms')->count())->toBe(1);
    expect(EventDivisionVersion::where('event_id', $event->id)->latest('id')->first()->discipline)->toBe('forms');
});

it('publishes a forms version to the forms pointer only', function () {
    $event = divCombinedEvent('disc-pub-'.uniqid());
    $v = EventDivisionVersion::create([
        'event_id' => $event->id, 'discipline' => 'forms',
        'data' => [['label' => 'A', 'members' => []]], 'created_at' => now(),
    ]);

    (new EventDivisionService())->publish($event, $v);

    expect($event->fresh()->published_forms_version_id)->toBe($v->id);
    expect($event->fresh()->published_version_id)->toBeNull();
});

it('auto-arranges forms from forms/both competitors, combining sexes', function () {
    $event = divCombinedEvent('disc-auto-'.uniqid());
    traitReg($event, 'M', -4, '2000-01-01', 'both');
    traitReg($event, 'M', -4, '2000-01-01', 'forms');
    traitReg($event, 'F', -4, '2000-01-01', 'both');
    traitReg($event, 'F', -4, '2000-01-01', 'forms');
    traitReg($event, 'M', -4, '2000-01-01', 'sparring'); // excluded from forms

    $forms = (new EventDivisionService())->auto($event->fresh()->load('addons'), 'forms');

    expect(collect($forms)->sum(fn ($d) => count($d['members'])))->toBe(4); // sparring-only excluded
    expect($forms)->toHaveCount(1);                                          // combined into one
    expect($forms[0]['label'])->not->toContain('Boys');
    expect($forms[0]['label'])->not->toContain('Girls');
});
