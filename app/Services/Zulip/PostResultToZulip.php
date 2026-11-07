<?php

namespace App\Services\Zulip;

use App\Events\DivisionAwaitingFinals;
use App\Events\DivisionCleared;
use App\Events\DivisionCompleted;
use App\Events\DivisionReopened;
use App\Models\DivisionResult;
use App\Models\Event;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queued so a Zulip outage can't hold up the scorer. Registered explicitly in
 * AppServiceProvider and kept out of app/Listeners, because production caches
 * its bootstrap and a discovered listener would need that cache rebuilt.
 *
 * Each handler reads the result as it is now: a job can run after the result
 * has moved on, so a saved division that was reopened meanwhile posts nothing.
 */
class PostResultToZulip implements ShouldQueue
{
    /** The worker runs with --tries=1; this overrides it to ride out an outage. */
    public int $tries = 5;

    /** @var array<int, int> seconds */
    public array $backoff = [30, 120, 300, 600];

    public function __construct(private ResultsChannel $channel) {}

    /** The worker on kronos for this queue is chungdo-zulip-sync. */
    public function viaQueue(): string
    {
        return 'zulip';
    }

    public function shouldQueue(DivisionCompleted|DivisionAwaitingFinals|DivisionReopened|DivisionCleared $event): bool
    {
        if (! $this->channel->enabled()) {
            return false;
        }

        return ! $event instanceof DivisionCleared || $event->zulipMessageId !== null;
    }

    public function handle(DivisionCompleted|DivisionAwaitingFinals|DivisionReopened|DivisionCleared $event): void
    {
        if ($event instanceof DivisionCleared) {
            $this->channel->cleared(Event::findOrFail($event->eventId), $event->discipline, $event->divisionName, $event->zulipMessageId);

            return;
        }

        $result = DivisionResult::with(['event', 'placements'])->find($event->resultId);

        if ($event instanceof DivisionCompleted && $result?->isCompleted()) {
            $this->channel->completed($result);
        } elseif ($event instanceof DivisionAwaitingFinals && $result?->isAwaitingFinals()) {
            $this->channel->completed($result);
        } elseif ($event instanceof DivisionReopened && $result && ! $result->isCompleted()) {
            $this->channel->reopened($result);
        }
    }

    public function failed(DivisionCompleted|DivisionAwaitingFinals|DivisionReopened|DivisionCleared $event, Throwable $e): void
    {
        Log::error('Tournament results did not reach Zulip.', ['event' => $event::class, 'error' => $e->getMessage()]);
    }
}
