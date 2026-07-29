<?php

namespace App\Console\Commands;

use App\Jobs\SyncZulipJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Runs the portal -> Zulip sync synchronously (no queue worker needed) and
 * prints the summary. Used for manual/CLI runs and the nightly schedule.
 */
class ZulipSync extends Command
{
    protected $signature = 'zulip:sync';

    protected $description = 'Sync flagged users (belt rank + group memberships) to Zulip via the API';

    public function handle(): int
    {
        $this->info('Running Zulip sync…');

        // dispatchSync runs the job in-process (records the last-run summary).
        SyncZulipJob::dispatchSync();

        $s = Cache::get('zulip.last_sync', []);

        if (! ($s['ok'] ?? false)) {
            $this->error('Sync failed: '.implode('; ', $s['errors'] ?? ['unknown error']));

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Done. Eligible: %d, created: %d, belt ranks set: %d, groups changed: %d.',
            $s['eligible'] ?? 0,
            count($s['created'] ?? []),
            $s['belt_rank_updated'] ?? 0,
            count($s['groups'] ?? []),
        ));

        foreach ($s['unmatched'] ?? [] as $email) {
            $this->warn("Unmatched (not in Zulip yet): {$email}");
        }
        foreach ($s['errors'] ?? [] as $error) {
            $this->warn($error);
        }

        return self::SUCCESS;
    }
}
