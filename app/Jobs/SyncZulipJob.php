<?php

namespace App\Jobs;

use App\Services\Zulip\ZulipSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Runs the portal -> Zulip sync and records the result under the
 * zulip.last_sync cache key for the admin UI. Unique so overlapping runs
 * (daily schedule + manual button) don't stack.
 */
class SyncZulipJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 600;

    public function __construct()
    {
        // Dedicated queue so it doesn't need the sendportal workers.
        $this->onQueue('zulip');
    }

    public function uniqueId(): string
    {
        return 'zulip-sync';
    }

    public function handle(ZulipSyncService $sync): void
    {
        try {
            $summary = $sync->sync();
            $summary['ok'] = true;
        } catch (\Throwable $e) {
            Log::error('Zulip sync failed', ['exception' => $e]);
            $summary = ['ok' => false, 'errors' => [$e->getMessage()]];
        }

        $summary['finished_at'] = now()->toDateTimeString();

        // Storing the summary is best-effort — never let a cache issue mask a
        // sync that already ran (the API work happens above this line).
        try {
            Cache::put('zulip.last_sync', $summary, now()->addDays(30));
        } catch (\Throwable $e) {
            Log::warning('Could not store Zulip sync summary in cache: '.$e->getMessage());
        }

        Log::info('Zulip sync finished', $summary);
    }
}
