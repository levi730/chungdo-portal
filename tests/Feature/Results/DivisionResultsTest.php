<?php

use App\Livewire\Event\Results;
use App\Livewire\Event\Scoring;
use App\Models\DivisionPlacement;
use App\Models\DivisionResult;
use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventDivisionVersion;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\DivisionResults;
use App\Services\EventDivisionService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/** @return array{0: Event, 1: EventDivisionVersion, 2: array<int>} an event, its published version, its registration ids */
function scoredEvent(int $competitors = 4, string $discipline = EventDivision::DISCIPLINE_SPARRING): array
{
    $event = Event::create(['name' => 'Results Test', 'slug' => 'results-test-'.uniqid(), 'cost' => 0, 'startdatetime' => now()]);
    $regs = [];
    for ($i = 0; $i < $competitors; $i++) {
        $user = User::factory()->create();
        $event->registrations()->attach($user->id, ['amount_due' => 0, 'amount_paid' => 0]);
        $regs[] = (int) EventRegistration::where('event_id', $event->id)->where('user_id', $user->id)->value('id');
    }

    $svc = new EventDivisionService();
    $version = EventDivisionVersion::find($svc->save($event, [['id' => null, 'label' => 'Boys Green', 'members' => $regs]], $discipline));
    $svc->publish($event, $version);

    return [$event, $version, $regs];
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
    [, $version, $r] = scoredEvent();

    $result = (new DivisionResults())->record($version, 0, [$r[0] => 1, $r[1] => 2, $r[2] => 3, $r[3] => ''], resultsStaff());

    expect($result->isCompleted())->toBeTrue();
    expect($result->division_name)->toBe('Boys Green');
    expect($result->placements()->pluck('place')->all())->toBe([1, 2, 3]);
});

it('allows ties and numbering every competitor', function () {
    [, $version, $r] = scoredEvent(5);

    $result = (new DivisionResults())->record($version, 0, [$r[0] => 1, $r[1] => 2, $r[2] => 3, $r[3] => 3, $r[4] => 5], resultsStaff());

    expect($result->placements()->pluck('place')->all())->toBe([1, 2, 3, 3, 5]);
});

it('scores forms from the published forms version', function () {
    [, $version, $r] = scoredEvent(3, EventDivision::DISCIPLINE_FORMS);

    $result = (new DivisionResults())->record($version, 0, [$r[0] => 1], resultsStaff());

    expect($result->discipline)->toBe(EventDivision::DISCIPLINE_FORMS);
});

it('scores the published version, not a later save of the board', function () {
    [$event, $version, $r] = scoredEvent();
    $div = EventDivision::where('event_id', $event->id)->first();

    // A later save, unpublished, drops two people and renames the division.
    (new EventDivisionService())->save($event, [['id' => $div->id, 'label' => 'Changed', 'members' => [$r[0], $r[1]]]]);

    $svc = new DivisionResults();
    expect($svc->members($version, 0)->keys()->sort()->values()->all())->toBe($r);
    expect($svc->publishedVersions($event)['sparring']->id)->toBe($version->id);
    expect($svc->record($version, 0, [$r[3] => 1], resultsStaff())->division_name)->toBe('Boys Green');
});

it('refuses a place past the division size, a stranger, a bad division and an empty sheet', function () {
    [, $version, $r] = scoredEvent(3);
    [, , $other] = scoredEvent(1);
    $svc = new DivisionResults();

    expect(fn () => $svc->record($version, 0, [$r[0] => 4], resultsStaff()))->toThrow(InvalidArgumentException::class);
    expect(fn () => $svc->record($version, 0, [$other[0] => 1], resultsStaff()))->toThrow(InvalidArgumentException::class);
    expect(fn () => $svc->record($version, 9, [$r[0] => 1], resultsStaff()))->toThrow(InvalidArgumentException::class);
    expect(fn () => $svc->record($version, 0, [$r[0] => ''], resultsStaff()))->toThrow(InvalidArgumentException::class);
    expect(DivisionResult::where('event_division_version_id', $version->id)->exists())->toBeFalse();
});

it('refuses scoring a version that is no longer published', function () {
    [$event, $old, $r] = scoredEvent();
    $div = EventDivision::where('event_id', $event->id)->first();
    $svc = new EventDivisionService();

    $svc->publish($event, EventDivisionVersion::find($svc->save($event, [['id' => $div->id, 'label' => 'New', 'members' => $r]])));

    expect(fn () => (new DivisionResults())->record($old, 0, [$r[0] => 1], resultsStaff()))
        ->toThrow(InvalidArgumentException::class, 'different version');
});

it('will not overwrite a closed division until it is reopened', function () {
    [, $version, $r] = scoredEvent();
    $svc = new DivisionResults();
    $by = resultsStaff();

    $result = $svc->record($version, 0, [$r[0] => 1], $by);
    expect(fn () => $svc->record($version, 0, [$r[1] => 1], $by))->toThrow(InvalidArgumentException::class);

    $svc->reopen($result);
    expect(DivisionResult::completed()->whereKey($result->id)->exists())->toBeFalse();

    $svc->record($version, 0, [$r[1] => 1], $by);
    expect(DivisionPlacement::where('division_result_id', $result->id)->count())->toBe(1);
    expect(DivisionResult::completed()->whereKey($result->id)->exists())->toBeTrue();
});

it('keeps a result after the competitor withdraws, and drops them from the division', function () {
    [, $version, $r] = scoredEvent();
    $svc = new DivisionResults();
    $result = $svc->record($version, 0, [$r[0] => 1], resultsStaff());

    DB::table('event_registrations')->where('id', $r[0])->delete();

    expect($result->placements()->count())->toBe(1);
    expect($svc->members($version, 0)->has($r[0]))->toBeFalse();
});

it('leaves scoring alone when the board is saved', function () {
    [$event, $version, $r] = scoredEvent();
    (new DivisionResults())->record($version, 0, [$r[0] => 1], resultsStaff());

    // Natural, Auto-combine and Restore all save boards with no ids.
    (new EventDivisionService())->save($event, [['id' => null, 'label' => 'Rebuilt', 'members' => $r]]);

    expect(DivisionResult::where('event_id', $event->id)->count())->toBe(1);
});

it('removes scoring when a different version is published', function () {
    [$event, $version, $r] = scoredEvent();
    (new DivisionResults())->record($version, 0, [$r[0] => 1], resultsStaff());
    $svc = new EventDivisionService();
    $next = EventDivisionVersion::find($svc->save($event, [['id' => null, 'label' => 'Rebuilt', 'members' => $r]]));

    $svc->publish($event, $next);

    expect(DivisionResult::where('event_id', $event->id)->count())->toBe(0);
    expect(DivisionPlacement::count())->toBe(0);
});

it('keeps scoring when the same version is republished, and for the other discipline', function () {
    [$event, $sparring, $r] = scoredEvent();
    $svc = new EventDivisionService();
    $forms = EventDivisionVersion::find($svc->save($event, [['id' => null, 'label' => 'Forms', 'members' => $r]], 'forms'));
    $svc->publish($event, $forms);
    (new DivisionResults())->record($sparring, 0, [$r[0] => 1], resultsStaff());

    $svc->publish($event, $sparring);
    expect($svc->scoredCount($event, 'sparring'))->toBe(1);

    $svc->publish($event, EventDivisionVersion::find($svc->save($event, [['id' => null, 'label' => 'Forms 2', 'members' => $r]], 'forms')));
    expect($svc->scoredCount($event, 'sparring'))->toBe(1);
});

it('asks before publishing over saved scoring', function () {
    [$event, $version, $r] = scoredEvent();
    (new DivisionResults())->record($version, 0, [$r[0] => 1], resultsStaff());
    $svc = new EventDivisionService();
    $next = EventDivisionVersion::find($svc->save($event, [['id' => null, 'label' => 'Rebuilt', 'members' => $r]]));
    $url = route('event.arrange-divisions.publish', [$event->slug, $next->id]);
    $admin = resultsStaff('event.reorganizeDivisions');

    $this->actingAs($admin)->postJson($url)->assertStatus(409)->assertJson(['ok' => false, 'scored' => 1]);
    expect($event->fresh()->published_version_id)->toBe($version->id);
    expect($svc->scoredCount($event, 'sparring'))->toBe(1);

    $this->actingAs($admin)->postJson($url, ['discard_scoring' => true])->assertOk();
    expect($event->fresh()->published_version_id)->toBe($next->id);
    expect($svc->scoredCount($event, 'sparring'))->toBe(0);
});

it('does not ask when nothing has been scored, or when republishing', function () {
    [$event, $version, $r] = scoredEvent();
    $svc = new EventDivisionService();
    $admin = resultsStaff('event.reorganizeDivisions');

    $next = EventDivisionVersion::find($svc->save($event, [['id' => null, 'label' => 'Rebuilt', 'members' => $r]]));
    $this->actingAs($admin)->postJson(route('event.arrange-divisions.publish', [$event->slug, $next->id]))->assertOk();

    (new DivisionResults())->record($next, 0, [$r[0] => 1], resultsStaff());
    $this->actingAs($admin)->postJson(route('event.arrange-divisions.publish', [$event->slug, $next->id]))->assertOk();
    expect($svc->scoredCount($event, 'sparring'))->toBe(1);
});

it('asks before unpublishing over saved scoring', function () {
    [$event, $version, $r] = scoredEvent();
    (new DivisionResults())->record($version, 0, [$r[0] => 1], resultsStaff());
    $url = route('event.arrange-divisions.unpublish', $event->slug);
    $admin = resultsStaff('event.reorganizeDivisions');

    $this->actingAs($admin)->postJson($url)->assertStatus(409);
    expect($event->fresh()->published_version_id)->toBe($version->id);

    $this->actingAs($admin)->postJson($url, ['discard_scoring' => true])->assertOk();
    expect($event->fresh()->published_version_id)->toBeNull();
    expect(DivisionResult::where('event_id', $event->id)->count())->toBe(0);
});

it('numbers competitors in tap order and closes gaps', function () {
    [$event, $version, $r] = scoredEvent();

    Livewire::actingAs(resultsStaff('event.recordResults'))
        ->test(Scoring::class, ['eventId' => $event->id])
        ->call('open', $version->id, 0)
        ->call('mark', $r[2])
        ->call('mark', $r[0])
        ->call('mark', $r[3])
        ->assertSet('places', [$r[2] => 1, $r[0] => 2, $r[3] => 3])
        ->call('mark', $r[0])
        ->assertSet('places', [$r[2] => 1, $r[3] => 2]);
});

it('saves an edited tie through the screen', function () {
    [$event, $version, $r] = scoredEvent();

    Livewire::actingAs(resultsStaff('event.recordResults'))
        ->test(Scoring::class, ['eventId' => $event->id])
        ->call('open', $version->id, 0)
        ->call('mark', $r[0])->call('mark', $r[1])->call('mark', $r[2])->call('mark', $r[3])
        ->set("places.{$r[3]}", '3')
        ->call('save')
        ->assertSet('versionId', null);

    expect(DivisionResult::where('event_division_version_id', $version->id)->first()->placements()->pluck('place')->all())
        ->toBe([1, 2, 3, 3]);
});

it('shows a scorer the error when the version was replaced under them', function () {
    [$event, $version, $r] = scoredEvent();
    $svc = new EventDivisionService();

    $page = Livewire::actingAs(resultsStaff('event.recordResults'))
        ->test(Scoring::class, ['eventId' => $event->id])
        ->call('open', $version->id, 0)
        ->call('mark', $r[0]);

    $svc->publish($event, EventDivisionVersion::find($svc->save($event, [['id' => null, 'label' => 'Rebuilt', 'members' => $r]])));

    $page->call('save')->assertSet('error', fn ($e) => str_contains($e, 'different version'));
    expect(DivisionResult::where('event_id', $event->id)->count())->toBe(0);
});

it('will not open a division of an unpublished version', function () {
    [$event, $version, $r] = scoredEvent();
    $svc = new EventDivisionService();
    $svc->publish($event, EventDivisionVersion::find($svc->save($event, [['id' => null, 'label' => 'Rebuilt', 'members' => $r]])));

    Livewire::actingAs(resultsStaff('event.recordResults'))
        ->test(Scoring::class, ['eventId' => $event->id])
        ->call('open', $version->id, 0)
        ->assertNotFound();
});

it('tells the scorer when nothing is published', function () {
    [$event] = scoredEvent();
    (new EventDivisionService())->unpublish($event);

    Livewire::actingAs(resultsStaff('event.recordResults'))
        ->test(Scoring::class, ['eventId' => $event->id])
        ->assertSee('Publish a version on Arrange Divisions first');
});

it('gates scoring on event.recordResults, route and component', function () {
    [$event] = scoredEvent();
    $member = resultsStaff();

    $this->actingAs($member)->get(route('event.scoring', $event->slug))->assertForbidden();
    Livewire::actingAs($member)->test(Scoring::class, ['eventId' => $event->id])->assertForbidden();

    $this->actingAs(resultsStaff('event.recordResults'))->get(route('event.scoring', $event->slug))->assertOk();
});

it('keeps reopening to event.manage', function () {
    [$event, $version, $r] = scoredEvent();
    (new DivisionResults())->record($version, 0, [$r[0] => 1], resultsStaff());

    Livewire::actingAs(resultsStaff('event.recordResults'))
        ->test(Scoring::class, ['eventId' => $event->id])
        ->call('open', $version->id, 0)
        ->call('reopen')
        ->assertForbidden();
});

it('shows results to verified members and nobody else', function () {
    [$event, $version, $r] = scoredEvent();
    (new DivisionResults())->record($version, 0, [$r[0] => 1], resultsStaff());
    $name = DivisionPlacement::whereHas('result', fn ($q) => $q->where('event_id', $event->id))->value('name');
    $url = route('event.results', $event->slug);

    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create())->get($url)->assertRedirect(route('verification.notice'));
    $this->actingAs(resultsStaff())->get($url)->assertOk()->assertSee($name);
});

it('checks sign-in on the polling component too', function () {
    [$event] = scoredEvent();

    Livewire::test(Results::class, ['eventId' => $event->id])->assertForbidden();
});

it('leaves reopened divisions off the results page', function () {
    [$event, $version, $r] = scoredEvent();
    $svc = new DivisionResults();
    $svc->reopen($svc->record($version, 0, [$r[0] => 1], resultsStaff()));

    $this->actingAs(resultsStaff())->get(route('event.results', $event->slug))->assertOk()->assertDontSee('Boys Green');
});

it('links members to results once there are some', function () {
    [$event, $version, $r] = scoredEvent();
    $member = resultsStaff();
    $url = route('event.results', $event->slug);

    expect($event->hasResults())->toBeFalse();
    $this->actingAs($member)->get(route('event.register', $event->slug))->assertDontSee($url);

    (new DivisionResults())->record($version, 0, [$r[0] => 1], resultsStaff());

    expect($event->fresh()->hasResults())->toBeTrue();
    $this->actingAs($member)->get(route('event.register', $event->slug))->assertSee($url);
});
