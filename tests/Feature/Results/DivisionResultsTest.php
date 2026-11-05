<?php

use App\Livewire\Event\Results;
use App\Livewire\Event\Scoring;
use App\Models\DivisionPlacement;
use App\Models\DivisionResult;
use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\DivisionResults;
use App\Services\EventDivisionService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/** @return array{0: Event, 1: EventDivision, 2: array<int>} an event, a saved division, its registration ids */
function scoredEvent(int $competitors = 4, string $discipline = EventDivision::DISCIPLINE_SPARRING): array
{
    $event = Event::create(['name' => 'Results Test', 'slug' => 'results-test-'.uniqid(), 'cost' => 0, 'startdatetime' => now()]);
    $regs = [];
    for ($i = 0; $i < $competitors; $i++) {
        $user = User::factory()->create();
        $event->registrations()->attach($user->id, ['amount_due' => 0, 'amount_paid' => 0]);
        $regs[] = (int) EventRegistration::where('event_id', $event->id)->where('user_id', $user->id)->value('id');
    }

    (new EventDivisionService())->save($event, [['id' => null, 'label' => 'Boys Green', 'members' => $regs]], $discipline);

    return [$event, EventDivision::where('event_id', $event->id)->firstOrFail(), $regs];
}

function resultsStaff(string ...$permissions): User
{
    $user = User::factory()->create();
    $user->markEmailAsVerified();
    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

it('records places and closes the division', function () {
    [, $division, $r] = scoredEvent();

    $result = (new DivisionResults())->record($division, [$r[0] => 1, $r[1] => 2, $r[2] => 3, $r[3] => ''], resultsStaff());

    expect($result->isCompleted())->toBeTrue();
    expect($result->division_name)->toBe('Boys Green');
    expect($result->placements()->pluck('place')->all())->toBe([1, 2, 3]);
});

it('allows ties and numbering every competitor', function () {
    [, $division, $r] = scoredEvent(5);

    $result = (new DivisionResults())->record($division, [$r[0] => 1, $r[1] => 2, $r[2] => 3, $r[3] => 3, $r[4] => 5], resultsStaff());

    expect($result->placements()->pluck('place')->all())->toBe([1, 2, 3, 3, 5]);
});

it('records forms results from the forms membership', function () {
    [, $division, $r] = scoredEvent(3, EventDivision::DISCIPLINE_FORMS);

    $result = (new DivisionResults())->record($division, [$r[0] => 1], resultsStaff());

    expect($result->discipline)->toBe(EventDivision::DISCIPLINE_FORMS);
});

it('refuses a place past the division size, a stranger, and an empty sheet', function () {
    [, $division, $r] = scoredEvent(3);
    [, , $other] = scoredEvent(1);
    $svc = new DivisionResults();

    expect(fn () => $svc->record($division, [$r[0] => 4], resultsStaff()))->toThrow(InvalidArgumentException::class);
    expect(fn () => $svc->record($division, [$other[0] => 1], resultsStaff()))->toThrow(InvalidArgumentException::class);
    expect(fn () => $svc->record($division, [$r[0] => ''], resultsStaff()))->toThrow(InvalidArgumentException::class);
    expect(DivisionResult::where('event_division_id', $division->id)->exists())->toBeFalse();
});

it('will not overwrite a closed division until it is reopened', function () {
    [, $division, $r] = scoredEvent();
    $svc = new DivisionResults();
    $by = resultsStaff();

    $result = $svc->record($division, [$r[0] => 1], $by);
    expect(fn () => $svc->record($division, [$r[1] => 1], $by))->toThrow(InvalidArgumentException::class);

    $svc->reopen($result);
    expect(DivisionResult::completed()->whereKey($result->id)->exists())->toBeFalse();

    $svc->record($division, [$r[1] => 1], $by);
    expect(DivisionPlacement::where('division_result_id', $result->id)->count())->toBe(1);
    expect(DivisionResult::completed()->whereKey($result->id)->exists())->toBeTrue();
});

it('keeps a result after the competitor withdraws', function () {
    [$event, $division, $r] = scoredEvent();
    $result = (new DivisionResults())->record($division, [$r[0] => 1], resultsStaff());

    DB::table('event_registrations')->where('id', $r[0])->delete();

    expect($result->placements()->count())->toBe(1);
    // ...and the withdrawn competitor doesn't block the board.
    (new EventDivisionService())->save($event, [['id' => $division->id, 'label' => 'Boys Green', 'members' => [$r[1]]]]);
    expect(EventDivision::find($division->id))->not->toBeNull();
});

it('refuses a board save that would replace a scored division', function () {
    [$event, $division, $r] = scoredEvent();
    (new DivisionResults())->record($division, [$r[0] => 1], resultsStaff());

    // Natural, Auto-combine and Restore all produce id-less boards.
    expect(fn () => (new EventDivisionService())->save($event, [['id' => null, 'label' => 'Boys Green', 'members' => $r]]))
        ->toThrow(DomainException::class);

    expect(EventDivision::find($division->id))->not->toBeNull();
});

it('refuses moving a placed competitor but allows other edits', function () {
    [$event, $division, $r] = scoredEvent();
    (new DivisionResults())->record($division, [$r[0] => 1], resultsStaff());
    $svc = new EventDivisionService();

    expect(fn () => $svc->save($event, [
        ['id' => $division->id, 'label' => 'Boys Green', 'members' => [$r[1], $r[2], $r[3]]],
        ['id' => null, 'label' => 'New', 'members' => [$r[0]]],
    ]))->toThrow(DomainException::class);

    $svc->save($event, [['id' => $division->id, 'label' => 'Boys Green Belt', 'members' => [$r[0], $r[1]]]]);

    expect(DivisionResult::where('event_division_id', $division->id)->value('division_name'))->toBe('Boys Green Belt');
});

it('returns the refusal to the board as a 422', function () {
    [$event, $division, $r] = scoredEvent();
    (new DivisionResults())->record($division, [$r[0] => 1], resultsStaff());

    $this->actingAs(resultsStaff('event.reorganizeDivisions'))
        ->postJson(route('event.arrange-divisions.save', $event->slug), [
            'divisions' => [['id' => null, 'label' => 'Boys Green', 'members' => $r]],
        ])
        ->assertStatus(422)
        ->assertJson(['ok' => false]);
});

it('numbers competitors in tap order and closes gaps', function () {
    [$event, $division, $r] = scoredEvent();

    Livewire::actingAs(resultsStaff('event.recordResults'))
        ->test(Scoring::class, ['eventId' => $event->id])
        ->call('open', $division->id)
        ->call('mark', $r[2])
        ->call('mark', $r[0])
        ->call('mark', $r[3])
        ->assertSet('places', [$r[2] => 1, $r[0] => 2, $r[3] => 3])
        ->call('mark', $r[0])
        ->assertSet('places', [$r[2] => 1, $r[3] => 2]);
});

it('saves an edited tie through the screen', function () {
    [$event, $division, $r] = scoredEvent();

    Livewire::actingAs(resultsStaff('event.recordResults'))
        ->test(Scoring::class, ['eventId' => $event->id])
        ->call('open', $division->id)
        ->call('mark', $r[0])->call('mark', $r[1])->call('mark', $r[2])->call('mark', $r[3])
        ->set("places.{$r[3]}", '3')
        ->call('save')
        ->assertSet('divisionId', null);

    expect(DivisionResult::where('event_division_id', $division->id)->first()->placements()->pluck('place')->all())
        ->toBe([1, 2, 3, 3]);
});

it('gates scoring on event.recordResults, route and component', function () {
    [$event] = scoredEvent();
    $member = resultsStaff();

    $this->actingAs($member)->get(route('event.scoring', $event->slug))->assertForbidden();
    Livewire::actingAs($member)->test(Scoring::class, ['eventId' => $event->id])->assertForbidden();

    $this->actingAs(resultsStaff('event.recordResults'))->get(route('event.scoring', $event->slug))->assertOk();
});

it('keeps reopening and the public switch to event.manage', function () {
    [$event, $division, $r] = scoredEvent();
    (new DivisionResults())->record($division, [$r[0] => 1], resultsStaff());

    Livewire::actingAs(resultsStaff('event.recordResults'))
        ->test(Scoring::class, ['eventId' => $event->id])
        ->call('open', $division->id)
        ->call('reopen')
        ->assertForbidden();

    Livewire::actingAs(resultsStaff('event.recordResults', 'event.manage'))
        ->test(Scoring::class, ['eventId' => $event->id])
        ->call('togglePublic');

    expect($event->fresh()->results_public)->toBeTrue();
});

it('shows results to members and sends guests to sign in until made public', function () {
    [$event, $division, $r] = scoredEvent();
    (new DivisionResults())->record($division, [$r[0] => 1], resultsStaff());
    $name = DivisionPlacement::whereHas('result', fn ($q) => $q->where('event_id', $event->id))->value('name');

    $this->get(route('event.results', $event->slug))->assertRedirect(route('login'));
    $this->actingAs(resultsStaff())->get(route('event.results', $event->slug))->assertOk()->assertSee($name);

    auth()->logout();
    $event->update(['results_public' => true]);

    $this->get(route('event.results', $event->slug))->assertOk()->assertSee($name);
});

it('stops an open page when results stop being public', function () {
    [$event] = scoredEvent();
    $event->update(['results_public' => true]);

    $page = Livewire::test(Results::class, ['eventId' => $event->id])->assertOk();

    $event->update(['results_public' => false]);

    $page->call('$refresh')->assertForbidden();
});

it('leaves reopened divisions off the results page', function () {
    [$event, $division, $r] = scoredEvent();
    $svc = new DivisionResults();
    $result = $svc->record($division, [$r[0] => 1], resultsStaff());
    $svc->reopen($result);
    $event->update(['results_public' => true]);

    $this->get(route('event.results', $event->slug))->assertOk()->assertDontSee('Boys Green');
});
