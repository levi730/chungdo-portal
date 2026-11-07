<?php

use App\Livewire\Event\ParticipationTrophies as TrophyPage;
use App\Models\DivisionResult;
use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventDivisionVersion;
use App\Models\EventRegistration;
use App\Models\ParticipationTrophy;
use App\Models\User;
use App\Services\DivisionResults;
use App\Services\EventDivisionService;
use App\Services\ParticipationTrophies;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function trophyEvent(): Event
{
    return Event::create(['name' => 'Trophy Test', 'slug' => 'trophy-test-'.uniqid(), 'cost' => 0, 'startdatetime' => '2026-11-14 09:00:00']);
}

function trophyKid(Event $event, ?int $age, bool $checkedIn = true): int
{
    $dob = $age === null ? null : Carbon::parse('2026-11-14')->subYears($age)->subDay()->toDateString();
    $user = User::factory()->create(['dob' => $dob]);
    $event->registrations()->attach($user->id, ['amount_due' => 0, 'amount_paid' => 0]);
    $id = (int) EventRegistration::where('event_id', $event->id)->where('user_id', $user->id)->value('id');

    if ($checkedIn) {
        DB::table('event_registrations')->where('id', $id)->update(['checkin' => now()]);
    }

    return $id;
}

/** @param  array<string, array<int>>  $divisions  label => registration ids */
function trophyBoard(Event $event, array $divisions, string $discipline = EventDivision::DISCIPLINE_SPARRING): EventDivisionVersion
{
    $svc = new EventDivisionService();
    $board = [];
    foreach ($divisions as $label => $members) {
        $board[] = ['id' => null, 'label' => $label, 'members' => $members];
    }
    $version = EventDivisionVersion::find($svc->save($event, $board, $discipline));
    $svc->publish($event, $version);

    return $version->fresh();
}

function trophyList(Event $event, string $list = 'ready'): array
{
    return app(ParticipationTrophies::class)->build($event)[$list]->pluck('registration_id')->sort()->values()->all();
}

function trophyStaff(string ...$permissions): User
{
    $user = User::factory()->create();
    $user->markEmailAsVerified();
    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

it('owes a trophy to a child who did not place in the top three by default', function () {
    $event = trophyEvent();
    $kids = array_map(fn () => trophyKid($event, 7), range(1, 5));
    $version = trophyBoard($event, ['Mini' => $kids]);

    (new DivisionResults())->record($version, 0, [$kids[0] => 1, $kids[1] => 2, $kids[2] => 3, $kids[3] => 4], User::factory()->create());

    expect(trophyList($event))->toBe([$kids[3], $kids[4]]);
});

it('goes by the winning places set on the event', function () {
    $event = trophyEvent();
    $event->update(['winning_places' => 4]);
    $kids = array_map(fn () => trophyKid($event, 7), range(1, 5));
    $version = trophyBoard($event, ['Mini' => $kids]);

    (new DivisionResults())->record($version, 0, [$kids[0] => 1, $kids[1] => 2, $kids[2] => 3, $kids[3] => 4], User::factory()->create());

    expect(trophyList($event))->toBe([$kids[4]]);

    $event->update(['winning_places' => 1]);
    expect(trophyList($event->fresh()))->toBe([$kids[1], $kids[2], $kids[3], $kids[4]]);
});

it('states the winning places on the page', function () {
    $event = trophyEvent();
    $event->update(['winning_places' => 5]);

    Livewire::actingAs(trophyStaff('event.recordResults'))->test(TrophyPage::class, ['eventId' => $event->id])
        ->assertSee('did not place in the top 5');
});

it('counts a tied last winning place as a win', function () {
    $event = trophyEvent();
    $kids = array_map(fn () => trophyKid($event, 10), range(1, 5));
    $version = trophyBoard($event, ['Pee Wee' => $kids]);

    (new DivisionResults())->record($version, 0, [$kids[0] => 1, $kids[1] => 2, $kids[2] => 3, $kids[3] => 3], User::factory()->create());

    expect(trophyList($event))->toBe([$kids[4]]);
});

it('leaves off a child who won in the other discipline', function () {
    $event = trophyEvent();
    [$a, $b] = [trophyKid($event, 7), trophyKid($event, 7)];
    $sparring = trophyBoard($event, ['Sparring' => [$a, $b]]);
    $forms = trophyBoard($event, ['Forms' => [$a, $b]], EventDivision::DISCIPLINE_FORMS);

    (new DivisionResults())->record($sparring, 0, [$a => 1], User::factory()->create());
    (new DivisionResults())->record($forms, 0, [$a => 2], User::factory()->create());

    expect(trophyList($event))->toBe([$b]);
});

it('takes a win as settled, but waits for every division before owing a trophy', function () {
    $event = trophyEvent();
    [$won, $lost] = [trophyKid($event, 7), trophyKid($event, 7)];
    $sparring = trophyBoard($event, ['Sparring' => [$won, $lost]]);
    trophyBoard($event, ['Forms' => [$won, $lost]], EventDivision::DISCIPLINE_FORMS);

    expect(trophyList($event, 'waiting'))->toBe([$won, $lost]);

    (new DivisionResults())->record($sparring, 0, [$won => 1], User::factory()->create());

    $waiting = app(ParticipationTrophies::class)->build($event)['waiting'];
    expect($waiting->pluck('registration_id')->all())->toBe([$lost]);
    expect($waiting->first()['unsaved'])->toBe(['Forms']);
    expect(trophyList($event))->toBe([]);
});

it('goes by age at the event, not the division name, and stops at 11', function () {
    $event = trophyEvent();
    [$eight, $nine, $eleven, $twelve] = [trophyKid($event, 8), trophyKid($event, 9), trophyKid($event, 11), trophyKid($event, 12)];
    trophyBoard($event, ['Boys & Girls 8-12 White' => [$eight, $nine, $eleven, $twelve]]);
    $version = EventDivisionVersion::where('event_id', $event->id)->latest('id')->first();
    (new DivisionResults())->record($version, 0, [$twelve => 4], User::factory()->create());

    $ready = app(ParticipationTrophies::class)->build($event)['ready'];
    expect($ready->pluck('registration_id')->sort()->values()->all())->toBe([$eight, $nine, $eleven]);
    expect($ready->firstWhere('registration_id', $eight)['group'])->toBe('Mini Pee Wee');
    expect($ready->firstWhere('registration_id', $nine)['group'])->toBe('Pee Wee');
});

it('sets aside anyone not checked in or with no birth date', function () {
    $event = trophyEvent();
    [$absent, $unknown, $here] = [trophyKid($event, 7, false), trophyKid($event, null), trophyKid($event, 7)];
    $version = trophyBoard($event, ['Mini' => [$absent, $unknown, $here, trophyKid($event, 30)]]);
    (new DivisionResults())->record($version, 0, [$here => 4], User::factory()->create());

    expect(trophyList($event, 'not_checked_in'))->toBe([$absent]);
    expect(trophyList($event, 'no_birth_date'))->toBe([$unknown]);
});

it('follows a correction', function () {
    $event = trophyEvent();
    [$a, $b] = [trophyKid($event, 7), trophyKid($event, 7)];
    $version = trophyBoard($event, ['Mini' => [$a, $b]]);
    $svc = new DivisionResults();
    $result = $svc->record($version, 0, [$a => 1], User::factory()->create());

    expect(trophyList($event))->toBe([$b]);

    $svc->reopen($result);
    expect(trophyList($event))->toBe([]);
    expect(trophyList($event, 'waiting'))->toBe([$a, $b]);

    $svc->record($version, 0, [$b => 1], User::factory()->create());
    expect(trophyList($event))->toBe([$a]);
});

it('records a trophy as given and can take that back', function () {
    $event = trophyEvent();
    [$a, $b] = [trophyKid($event, 7), trophyKid($event, 7)];
    $version = trophyBoard($event, ['Mini' => [$a, $b]]);
    (new DivisionResults())->record($version, 0, [$a => 1], User::factory()->create());
    $staff = trophyStaff('event.recordResults');

    $page = Livewire::actingAs($staff)->test(TrophyPage::class, ['eventId' => $event->id])
        ->call('setGiven', $b, true)
        ->call('setGiven', $b, true);

    expect(ParticipationTrophy::where('event_registration_id', $b)->sole()->given_by_user_id)->toBe($staff->id);

    $page->call('setGiven', $b, false);
    expect(ParticipationTrophy::where('event_registration_id', $b)->exists())->toBeFalse();
});

it('will not record a trophy for a child who is not owed one', function () {
    $event = trophyEvent();
    [$a, $b] = [trophyKid($event, 7), trophyKid($event, 7)];
    $version = trophyBoard($event, ['Mini' => [$a, $b]]);
    (new DivisionResults())->record($version, 0, [$a => 1], User::factory()->create());

    Livewire::actingAs(trophyStaff('event.recordResults'))->test(TrophyPage::class, ['eventId' => $event->id])
        ->call('setGiven', $a, true);

    expect(ParticipationTrophy::where('event_id', $event->id)->exists())->toBeFalse();
});

it('gates the list on event.recordResults, route and component', function () {
    $event = trophyEvent();

    $this->get(route('event.trophies', $event->slug))->assertRedirect(route('login'));
    $this->actingAs(trophyStaff())->get(route('event.trophies', $event->slug))->assertForbidden();
    Livewire::actingAs(trophyStaff())->test(TrophyPage::class, ['eventId' => $event->id])->assertForbidden();
    $this->actingAs(trophyStaff('event.recordResults'))->get(route('event.trophies', $event->slug))->assertOk()->assertSee('Participation Trophies');
});
