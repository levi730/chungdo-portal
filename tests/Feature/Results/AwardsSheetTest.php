<?php

use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventDivisionVersion;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\AwardsSheet;
use App\Services\DivisionResults;
use App\Services\EventDivisionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function awardsEvent(): Event
{
    return Event::create(['name' => 'Awards Test', 'slug' => 'awards-test-'.uniqid(), 'cost' => 0, 'startdatetime' => '2026-11-14 09:00:00']);
}

function awardsKid(Event $event, int $age = 30): int
{
    $user = User::factory()->create(['dob' => Carbon::parse('2026-11-14')->subYears($age)->subDay()->toDateString()]);
    $event->registrations()->attach($user->id, ['amount_due' => 0, 'amount_paid' => 0]);
    $id = (int) EventRegistration::where('event_id', $event->id)->where('user_id', $user->id)->value('id');
    DB::table('event_registrations')->where('id', $id)->update(['checkin' => now()]);

    return $id;
}

/** @param  array<string, array<int>>  $divisions  label => registration ids */
function awardsBoard(Event $event, array $divisions, string $discipline): EventDivisionVersion
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

function awardsStaff(string ...$permissions): User
{
    $user = User::factory()->create();
    $user->markEmailAsVerified();
    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

it('lists only 1st place in forms, ties included', function () {
    $event = awardsEvent();
    $kids = array_map(fn () => awardsKid($event), range(1, 3));
    $version = awardsBoard($event, ['Forms A' => $kids], EventDivision::DISCIPLINE_FORMS);
    (new DivisionResults())->record($version, 0, [$kids[0] => 1, $kids[1] => 1, $kids[2] => 3], User::factory()->create());

    $forms = app(AwardsSheet::class)->build($event)['forms'];

    expect($forms)->toHaveCount(1);
    expect($forms[0]['division'])->toBe('Forms A');
    expect($forms[0]['winners']->pluck('place')->all())->toBe([1, 1]);
});

it('stops sparring at the winning places, or the last recorded', function () {
    $event = awardsEvent();
    $big = array_map(fn () => awardsKid($event), range(1, 5));
    $small = array_map(fn () => awardsKid($event), range(1, 3));
    $version = awardsBoard($event, ['Big' => $big, 'Small' => $small], EventDivision::DISCIPLINE_SPARRING);
    $svc = new DivisionResults();
    $svc->record($version, 0, [$big[0] => 1, $big[1] => 2, $big[2] => 3, $big[3] => 4, $big[4] => 5], User::factory()->create());
    $svc->record($version, 1, [$small[0] => 1, $small[1] => 2], User::factory()->create());

    $sparring = app(AwardsSheet::class)->build($event)['sparring'];

    expect($sparring[0]['placements']->pluck('place')->all())->toBe([1, 2, 3]);
    expect($sparring[1]['placements']->pluck('place')->all())->toBe([1, 2]);

    $event->update(['winning_places' => 4]);
    expect(app(AwardsSheet::class)->build($event->fresh())['sparring'][0]['placements']->pluck('place')->all())->toBe([1, 2, 3, 4]);
});

it('shows the finalists of a division waiting on finals', function () {
    $event = awardsEvent();
    $kids = array_map(fn () => awardsKid($event), range(1, 4));
    $version = awardsBoard($event, ['Finals Div' => $kids], EventDivision::DISCIPLINE_SPARRING);
    (new DivisionResults())->record($version, 0, [$kids[2] => 3], User::factory()->create(), [$kids[0], $kids[1]]);

    $division = app(AwardsSheet::class)->build($event)['sparring'][0];

    expect($division['awaiting_finals'])->toBeTrue();
    expect($division['placements']->pluck('place')->all())->toBe([null, null, 3]);
});

it('names divisions not saved yet instead of leaving them out', function () {
    $event = awardsEvent();
    [$a, $b] = [awardsKid($event), awardsKid($event)];
    $sparring = awardsBoard($event, ['Saved' => [$a], 'Pending' => [$b]], EventDivision::DISCIPLINE_SPARRING);
    $forms = awardsBoard($event, ['Form Pending' => [$a]], EventDivision::DISCIPLINE_FORMS);
    $svc = new DivisionResults();
    $reopened = $svc->record($sparring, 0, [$a => 1], User::factory()->create());

    expect(app(AwardsSheet::class)->build($event)['unsaved'])->toBe(['Sparring: Pending', 'Forms: Form Pending']);

    $svc->reopen($reopened);
    expect(app(AwardsSheet::class)->build($event)['unsaved'])->toContain('Sparring: Saved');
});

it('includes the participation trophies', function () {
    $event = awardsEvent();
    [$won, $owed] = [awardsKid($event, 7), awardsKid($event, 7)];
    $version = awardsBoard($event, ['Mini' => [$won, $owed]], EventDivision::DISCIPLINE_SPARRING);
    (new DivisionResults())->record($version, 0, [$won => 1], User::factory()->create());

    $sheet = app(AwardsSheet::class)->build($event);

    expect($sheet['trophies']->pluck('registration_id')->all())->toBe([$owed]);
    expect($sheet['trophies_waiting'])->toBe(0);
});

it('renders the sheet behind event.recordResults', function () {
    $event = awardsEvent();
    $kids = array_map(fn () => awardsKid($event, 7), range(1, 4));
    $sparring = awardsBoard($event, ['Finals Div' => $kids], EventDivision::DISCIPLINE_SPARRING);
    $forms = awardsBoard($event, ['Forms A' => $kids], EventDivision::DISCIPLINE_FORMS);
    $svc = new DivisionResults();
    $svc->record($sparring, 0, [$kids[2] => 3], User::factory()->create(), [$kids[0], $kids[1]]);
    $svc->record($forms, 0, [$kids[0] => 1], User::factory()->create());

    $this->get(route('event.awards', $event->slug))->assertRedirect(route('login'));
    $this->actingAs(awardsStaff())->get(route('event.awards', $event->slug))->assertForbidden();
    $this->actingAs(awardsStaff('event.recordResults'))->get(route('event.awards', $event->slug))
        ->assertOk()
        ->assertSeeInOrder(['Forms: 1st place', 'Forms A', 'Sparring', 'Finals Div', 'waiting on finals', 'Participation trophies (1)']);
});

it('groups the results links in a menu for scorers and keeps a plain link for everyone else', function () {
    $event = awardsEvent();

    $this->actingAs(awardsStaff('event.recordResults'));
    $menu = view('partials.event.subnav', ['event' => $event])->render();

    expect($menu)->toContain('id="resultsDropdown"')
        ->toContain('Results Page')
        ->toContain(route('event.scoring', $event->slug))
        ->toContain(route('event.trophies', $event->slug))
        ->toContain(route('event.awards', $event->slug));

    $this->actingAs(awardsStaff('event.checkIn'));
    $menu = view('partials.event.subnav', ['event' => $event])->render();

    expect($menu)->not->toContain('resultsDropdown')
        ->not->toContain(route('event.scoring', $event->slug))
        ->toContain(route('event.results', $event->slug));
});
