<?php

use App\Models\Event;
use App\Models\User;
use App\Services\RegistrationCardPdf;
use Carbon\Carbon;

function cardEvent(): Event
{
    return Event::create(['name' => 'Test Cup', 'slug' => 'test-cup', 'cost' => 0, 'startdatetime' => Carbon::parse('2026-02-01 09:00')]);
}

function registerFor(Event $event, string $sex, int $rankId, string $dob): User
{
    $user = User::factory()->create();
    // rank_id/sex/dob drive the natural division; write them directly.
    \DB::table('users')->where('id', $user->id)->update(['sex' => $sex, 'rank_id' => $rankId, 'dob' => $dob]);
    $event->registrations()->attach($user->id, ['amount_due' => 0, 'amount_paid' => 0]);

    return $user->fresh();
}

it('marks the executive black-belt cell with the degree', function () {
    $event = cardEvent();
    registerFor($event, 'M', 3, '1984-01-01'); // age 42 -> Executive; 3rd dan

    $card = (new RegistrationCardPdf())->payload($event)['cards'][0];

    expect($card['mark'])->toBe(['row' => 3, 'col' => 0, 'degree' => 3]);
    expect($card['sex'])->toBe('M');
    expect($card['age'])->toBe('42');
});

it('marks a youth color-belt cell without a degree', function () {
    $event = cardEvent();
    registerFor($event, 'F', -4, '2016-01-01'); // age 10 -> Pee Wee; green (rank -4)

    $card = (new RegistrationCardPdf())->payload($event)['cards'][0];

    expect($card['mark'])->toBe(['row' => 1, 'col' => 4, 'degree' => null]);
});

it('pads an odd registrant count with a trailing blank card', function () {
    $event = cardEvent();
    registerFor($event, 'M', 1, '2000-01-01');

    $cards = (new RegistrationCardPdf())->payload($event)['cards'];

    expect($cards)->toHaveCount(2);
    expect($cards[1])->toBeNull();
});

it('renders a blanks-only page as two null cards', function () {
    $payload = (new RegistrationCardPdf())->payload(cardEvent(), blanksOnly: true);

    expect($payload['cards'])->toBe([null, null]);
});

it('refuses to build division cards with no published version', function () {
    $event = Event::create(['name' => 'Unpub', 'slug' => 'unpub', 'cost' => 0, 'startdatetime' => Carbon::parse('2027-01-01')]);

    expect(fn () => (new RegistrationCardPdf())->divisionPayload($event))
        ->toThrow(RuntimeException::class);
});

it('groups cards by the published division arrangement', function () {
    $event = Event::create(['name' => 'Grp', 'slug' => 'grp', 'cost' => 0, 'startdatetime' => Carbon::parse('2027-01-01')]);
    $user = User::factory()->create();
    $event->registrations()->attach($user->id, ['amount_due' => 0, 'amount_paid' => 0]);
    $reg = App\Models\EventRegistration::where('event_id', $event->id)->where('user_id', $user->id)->first();

    $v = App\Models\EventDivisionVersion::create([
        'event_id' => $event->id,
        'data' => [['label' => 'Test Division', 'members' => [$reg->id]], ['label' => 'Empty', 'members' => []]],
        'created_at' => now(),
    ]);
    $event->update(['published_version_id' => $v->id]);

    $payload = (new RegistrationCardPdf())->divisionPayload($event->fresh(), true);

    expect($payload['covers'])->toBeTrue();
    expect($payload['divisions'])->toHaveCount(1); // the empty division is dropped
    expect($payload['divisions'][0]['label'])->toBe('Test Division');
    expect($payload['divisions'][0]['cards'])->toHaveCount(1);
});

it('compiles a PDF via the typst binary', function () {
    if (! trim(shell_exec('command -v '.escapeshellarg(config('events.typst_bin', 'typst')).' 2>/dev/null') ?? '')) {
        $this->markTestSkipped('typst binary not available');
    }

    $event = cardEvent();
    registerFor($event, 'M', 3, '1984-01-01');

    $pdf = (new RegistrationCardPdf())->generate($event);

    expect($pdf)->toBeFile();
    expect(filesize($pdf))->toBeGreaterThan(1000);
    expect(file_get_contents($pdf, false, null, 0, 5))->toBe('%PDF-');

    @unlink($pdf);
});

function tournamentEvent(): Event
{
    return Event::create(['name' => 'Combined Cup', 'slug' => 'combined-cup-'.uniqid(), 'type' => 'combined', 'startdatetime' => Carbon::parse('2026-02-01 09:00')]);
}

it('maps the new tournament grid: executive black to row 4 col 0', function () {
    $event = tournamentEvent();
    registerFor($event, 'M', 3, '1984-01-01'); // age 42 -> Executives; 3rd degree black

    $card = (new RegistrationCardPdf())->tournamentPayload($event, 'forms')['cards'][0];

    expect($card['variant'])->toBe('forms');
    expect($card['mark'])->toBe(['row' => 4, 'col' => 0]);
});

it('maps a youth green belt to the pee-wee green cell', function () {
    $event = tournamentEvent();
    registerFor($event, 'F', -4, '2016-01-01'); // age 10 -> Pee Wee (row 1); green (-4) -> col 5

    $card = (new RegistrationCardPdf())->tournamentPayload($event, 'sparring')['cards'][0];

    expect($card['variant'])->toBe('sparring');
    expect($card['mark'])->toBe(['row' => 1, 'col' => 5]);
});

it('x-marks paid only when the fee is covered', function () {
    $event = tournamentEvent();
    foreach ([[50, 50], [50, 0]] as [$due, $paid]) {
        $u = User::factory()->create();
        \DB::table('users')->where('id', $u->id)->update(['sex' => 'M', 'rank_id' => 1, 'dob' => '2000-01-01']);
        $event->registrations()->attach($u->id, ['amount_due' => $due, 'amount_paid' => $paid]);
    }

    $cards = collect((new RegistrationCardPdf())->tournamentPayload($event, 'forms')['cards'])->filter();

    expect($cards->pluck('paid')->sort()->values()->all())->toBe([false, true]);
});

it('prints only the cards a competitor signed up for', function () {
    $event = tournamentEvent();
    $addon = App\Models\EventAddon::create(['event_id' => $event->id, 'type' => 'participation', 'enabled' => true]);
    $answer = function ($u, string $choice) use ($event, $addon) {
        $reg = App\Models\EventRegistration::where('event_id', $event->id)->where('user_id', $u->id)->first();
        App\Models\EventRegistrationAddon::create([
            'event_registration_id' => $reg->id, 'event_addon_id' => $addon->id,
            'type' => 'participation', 'selected' => true, 'value' => $choice,
        ]);
    };

    $answer(registerFor($event, 'M', 1, '2000-01-01'), 'sparring');
    $answer(registerFor($event, 'F', 1, '2000-01-01'), 'forms');
    $pdf = new RegistrationCardPdf();

    $formsCards = collect($pdf->tournamentPayload($event->fresh()->load('addons'), 'forms')['cards'])->filter();
    $sparringCards = collect($pdf->tournamentPayload($event->fresh()->load('addons'), 'sparring')['cards'])->filter();

    expect($formsCards)->toHaveCount(1);    // only the forms competitor
    expect($sparringCards)->toHaveCount(1); // only the sparring competitor
});

it('refuses to build by-division cards with no published forms version', function () {
    expect(fn () => (new RegistrationCardPdf())->tournamentDivisionPayload(tournamentEvent(), 'forms'))
        ->toThrow(RuntimeException::class);
});
