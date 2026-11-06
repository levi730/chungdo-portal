<?php

use App\Models\DivisionResult;
use App\Models\Event;
use App\Models\EventDivisionVersion;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\DivisionResults;
use App\Services\EventDivisionService;
use App\Services\Zulip\PostResultToZulip;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

const ZULIP_API = 'https://zulip.test/api/v1';

/** Results on, sync bot deliberately different, and no request may reach anywhere real. */
function zulipResultsOn(): void
{
    config([
        'services.zulip.site' => 'https://sync.test',
        'services.zulip.bot_email' => 'sync-bot@zulip.test',
        'services.zulip.bot_api_key' => 'sync-key',
        'services.zulip.results' => [
            'site' => 'https://zulip.test',
            'bot_email' => 'results-bot@zulip.test',
            'bot_api_key' => 'results-key',
            'channel' => 'results-test',
        ],
    ]);
}

function zulipAccepts(int $messageId = 900): void
{
    Http::preventStrayRequests();
    Http::fake([
        ZULIP_API.'/messages' => Http::response(['result' => 'success', 'id' => $messageId]),
        ZULIP_API.'/messages/*' => Http::response(['result' => 'success']),
    ]);
}

function zulipRefusesEdits(int $messageId = 901): void
{
    Http::preventStrayRequests();
    Http::fake([
        ZULIP_API.'/messages' => Http::response(['result' => 'success', 'id' => $messageId]),
        ZULIP_API.'/messages/*' => Http::response(['result' => 'error', 'msg' => 'This API is not available to incoming webhook bots.'], 400),
    ]);
}

/** @return array{0: Event, 1: EventDivisionVersion, 2: array<int>} */
function zulipDivisions(string $eventName = 'Gateway Championships'): array
{
    $event = Event::create(['name' => $eventName, 'slug' => 'zulip-test-'.uniqid(), 'cost' => 0, 'startdatetime' => now()]);
    $regs = [];
    for ($i = 0; $i < 6; $i++) {
        $user = User::factory()->create();
        $event->registrations()->attach($user->id, ['amount_due' => 0, 'amount_paid' => 0]);
        $regs[] = (int) EventRegistration::where('event_id', $event->id)->where('user_id', $user->id)->value('id');
    }

    $svc = new EventDivisionService();
    $version = EventDivisionVersion::find($svc->save($event, [
        ['id' => null, 'label' => 'Div A', 'members' => array_slice($regs, 0, 3)],
        ['id' => null, 'label' => 'Div B', 'members' => array_slice($regs, 3, 3)],
    ]));
    $svc->publish($event, $version);

    return [$event, $version, $regs];
}

function zulipPosts(): int
{
    return Http::recorded(fn (Request $r) => $r->method() === 'POST')->count();
}

function zulipEdits(): int
{
    return Http::recorded(fn (Request $r) => $r->method() === 'PATCH')->count();
}

it('sends nothing until a results channel is set', function () {
    Http::preventStrayRequests();
    Http::fake();
    [, $version, $r] = zulipDivisions();

    (new DivisionResults())->record($version, 0, [$r[0] => 1], User::factory()->create());

    Http::assertNothingSent();
});

it('stays off when the channel is set but the bot is not', function () {
    Http::preventStrayRequests();
    Http::fake();
    config(['services.zulip.results.channel' => 'results-test']);
    [, $version, $r] = zulipDivisions();

    (new DivisionResults())->record($version, 0, [$r[0] => 1], User::factory()->create());

    Http::assertNothingSent();
});

it('posts a saved division to the channel and keeps the message id', function () {
    zulipResultsOn();
    zulipAccepts();
    [$event, $version, $r] = zulipDivisions();
    $winner = EventRegistration::find($r[0])->user;

    $result = (new DivisionResults())->record($version, 0, [$r[0] => 1], User::factory()->create());

    Http::assertSent(fn (Request $q) => $q->url() === ZULIP_API.'/messages'
        && $q['type'] === 'channel'
        && $q['to'] === 'results-test'
        && $q['topic'] === 'Gateway Championships - Sparring'
        && str_contains($q['content'], '**Div A**')
        && str_contains($q['content'], '**1** '.$winner->full_name)
        && str_contains($q['content'], route('event.results', $event->slug)));

    expect($result->fresh()->zulip_message_id)->toBe(900);
});

it('posts with the results bot, never the sync bot', function () {
    zulipResultsOn();
    zulipAccepts();
    [, $version, $r] = zulipDivisions();

    (new DivisionResults())->record($version, 0, [$r[0] => 1], User::factory()->create());

    Http::assertSent(fn (Request $q) => $q->hasHeader('Authorization', 'Basic '.base64_encode('results-bot@zulip.test:results-key')));
    Http::assertNotSent(fn (Request $q) => str_contains($q->url(), 'sync.test'));
});

it('writes tied places literally rather than as a list Zulip would renumber', function () {
    zulipResultsOn();
    zulipAccepts();
    [, $version, $r] = zulipDivisions();

    (new DivisionResults())->record($version, 0, [$r[0] => 3, $r[1] => 3], User::factory()->create());

    Http::assertSent(fn (Request $q) => substr_count($q['content'], '**3** ') === 2 && ! str_contains($q['content'], '3. '));
});

it('can never mention anyone', function () {
    zulipResultsOn();
    zulipAccepts();
    [, $version, $r] = zulipDivisions();
    EventRegistration::find($r[0])->user->update(['firstname' => '@**all**', 'lastname' => '@_everyone']);

    (new DivisionResults())->record($version, 0, [$r[0] => 1], User::factory()->create());

    Http::assertSent(fn (Request $q) => ! str_contains($q['content'], '@'));
});

it('keeps a long event name inside the topic limit', function () {
    zulipResultsOn();
    zulipAccepts();
    [, $version, $r] = zulipDivisions(str_repeat('Championships ', 8));

    (new DivisionResults())->record($version, 0, [$r[0] => 1], User::factory()->create());

    Http::assertSent(fn (Request $q) => mb_strlen($q['topic']) <= 60 && str_ends_with($q['topic'], ' - Sparring'));
});

it('edits the original post for a correction', function () {
    zulipResultsOn();
    zulipAccepts();
    [, $version, $r] = zulipDivisions();
    $svc = new DivisionResults();
    $by = User::factory()->create();

    $svc->record($version, 0, [$r[0] => 1], $by);
    $svc->reopen(DivisionResult::where('event_division_version_id', $version->id)->first());
    $svc->record($version, 0, [$r[1] => 1], $by);

    expect(zulipPosts())->toBe(1);
    expect(zulipEdits())->toBe(2);
    Http::assertSent(fn (Request $q) => $q->method() === 'PATCH' && $q->url() === ZULIP_API.'/messages/900'
        && str_contains($q['content'], 'Being corrected'));
    $corrected = EventRegistration::find($r[1])->user->full_name;
    Http::assertSent(fn (Request $q) => $q->method() === 'PATCH' && str_contains($q['content'], $corrected));
});

it('posts a marked correction when Zulip refuses the edit', function () {
    zulipResultsOn();
    zulipAccepts(900);
    [, $version, $r] = zulipDivisions();
    $svc = new DivisionResults();
    $by = User::factory()->create();
    $result = $svc->record($version, 0, [$r[0] => 1], $by);

    Http::swap(new \Illuminate\Http\Client\Factory());
    zulipRefusesEdits(901);

    $svc->reopen($result->fresh());
    expect(zulipPosts())->toBe(0); // a refused "being corrected" edit posts nothing

    $svc->record($version, 0, [$r[1] => 1], $by);

    expect(zulipPosts())->toBe(1);
    Http::assertSent(fn (Request $q) => $q->method() === 'POST' && str_contains($q['content'], '**Correction**'));
    expect($result->fresh()->zulip_message_id)->toBe(901);
});

it('marks only the division that changed when a new version is published', function () {
    zulipResultsOn();
    zulipAccepts();
    [$event, $version, $r] = zulipDivisions();
    $results = new DivisionResults();
    $by = User::factory()->create();
    $results->record($version, 0, [$r[0] => 1], $by);
    DivisionResult::where('event_division_version_id', $version->id)->where('position', 0)->update(['zulip_message_id' => 900]);
    $results->record($version, 1, [$r[3] => 1], $by);
    DivisionResult::where('event_division_version_id', $version->id)->where('position', 1)->update(['zulip_message_id' => 950]);
    $svc = new EventDivisionService();

    $user = User::factory()->create();
    $event->registrations()->attach($user->id, ['amount_due' => 0, 'amount_paid' => 0]);
    $late = (int) EventRegistration::where('event_id', $event->id)->where('user_id', $user->id)->value('id');

    $next = EventDivisionVersion::find($svc->save($event, [
        ['id' => null, 'label' => 'Div A', 'members' => [$r[0], $r[1], $r[2], $late]],
        ['id' => null, 'label' => 'Div B', 'members' => [$r[3], $r[4], $r[5]]],
    ]));

    Http::swap(new \Illuminate\Http\Client\Factory());
    zulipAccepts();

    $svc->publish($event, $next);

    expect(Http::recorded())->toHaveCount(1);
    Http::assertSent(fn (Request $q) => $q->method() === 'PATCH' && $q->url() === ZULIP_API.'/messages/900'
        && str_contains($q['content'], 'cleared'));
});

it('says nothing about a cleared division that was never posted', function () {
    [$event, $version, $r] = zulipDivisions();
    (new DivisionResults())->record($version, 0, [$r[0] => 1], User::factory()->create());

    zulipResultsOn();
    zulipAccepts();

    (new EventDivisionService())->unpublish($event);

    Http::assertNothingSent();
});

it('queues on the zulip queue, so the scorer never waits on Zulip', function () {
    zulipResultsOn();
    Queue::fake();
    [, $version, $r] = zulipDivisions();

    (new DivisionResults())->record($version, 0, [$r[0] => 1], User::factory()->create());

    Queue::assertPushedOn('zulip', CallQueuedListener::class, fn ($job) => $job->class === PostResultToZulip::class);
});

it('posts nothing if a division was reopened before its job ran', function () {
    zulipResultsOn();
    Queue::fake();
    [, $version, $r] = zulipDivisions();
    $svc = new DivisionResults();
    $result = $svc->record($version, 0, [$r[0] => 1], User::factory()->create());
    $svc->reopen($result);

    zulipAccepts();
    app(PostResultToZulip::class)->handle(new \App\Events\DivisionCompleted($result->id));

    Http::assertNothingSent();
});
