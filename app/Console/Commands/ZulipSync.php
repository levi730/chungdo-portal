<?php

namespace App\Console\Commands;

use App\Jobs\SyncZulipJob;
use App\Services\Zulip\ZulipSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Runs the portal -> Zulip sync synchronously (no queue worker needed) and
 * prints the summary. Used for manual/CLI runs and the nightly schedule.
 *
 * --dry-run plans the run without writing anything to Zulip and prints the
 * exact belt-rank changes and group adds/removes a real run would make. A dry
 * run deliberately does not touch the zulip.last_sync cache entry the admin
 * page reads, so it can never be mistaken for a real run.
 */
class ZulipSync extends Command
{
    protected $signature = 'zulip:sync {--dry-run : Report what would change without writing anything to Zulip}';

    protected $description = 'Sync flagged users (belt rank + group memberships) to Zulip via the API';

    public function handle(ZulipSyncService $sync): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — reading from Zulip, writing nothing.');

            try {
                $s = $sync->sync(dryRun: true);
                $s['ok'] = true;
            } catch (Throwable $e) {
                $s = ['ok' => false, 'errors' => [$e->getMessage()]];
            }
        } else {
            $this->info('Running Zulip sync…');

            // dispatchSync runs the job in-process (records the last-run summary).
            SyncZulipJob::dispatchSync();

            $s = Cache::get('zulip.last_sync', []);
        }

        if (! ($s['ok'] ?? false)) {
            $this->error('Sync failed: '.implode('; ', $s['errors'] ?? ['unknown error']));

            return self::FAILURE;
        }

        $this->newLine();
        $this->line(sprintf('Eligible users: %d', $s['eligible'] ?? 0));

        $this->line(sprintf(
            'Belt ranks %s: %d',
            $dryRun ? 'to change' : 'changed',
            $s['belt_rank_updated'] ?? 0,
        ));
        foreach ($s['belt_rank_changes'] ?? [] as $change) {
            $this->line("    {$change}");
        }

        $groups = $s['groups'] ?? [];
        $this->newLine();
        $this->line(sprintf('Groups %s: %d', $dryRun ? 'to change' : 'changed', count($groups)));

        foreach ($groups as $slug => $change) {
            $label = ($change['create'] ?? false) ? " (group {$this->wouldBe($dryRun)} created)" : '';
            $this->line("  {$slug}{$label}");

            foreach ($change['add'] ?? [] as $who) {
                $this->line("    <fg=green>+ {$who}</>");
            }
            foreach ($change['remove'] ?? [] as $who) {
                $this->line("    <fg=red>- {$who}</>");
            }
        }

        $channels = $s['channels'] ?? [];
        $this->newLine();
        $this->line(sprintf('Committee channels %s: %d', $dryRun ? 'to change' : 'changed', count($channels)));

        foreach ($channels as $slug => $change) {
            $label = ($change['create'] ?? false) ? " (channel {$this->wouldBe($dryRun)} created)" : '';
            $this->line("  {$slug}{$label}");

            foreach ($change['add'] ?? [] as $who) {
                $this->line("    <fg=green>+ {$who}</>");
            }
            foreach ($change['remove'] ?? [] as $who) {
                $this->line("    <fg=red>- {$who}</>");
            }
        }

        $removals = array_sum(array_map(fn ($c) => count($c['remove'] ?? []), $groups))
            + array_sum(array_map(fn ($c) => count($c['remove'] ?? []), $channels));
        if ($removals > 0) {
            $this->newLine();
            $this->warn(sprintf(
                '%d membership removal(s) %s — the portal is canonical, so anyone in a managed group '
                .'or committee channel that the portal does not place there %s removed. '
                .'Zulip admins, owners and bots are never removed.',
                $removals,
                $dryRun ? 'pending' : 'applied',
                $dryRun ? 'would be' : 'was',
            ));
        }

        $unmatched = $s['unmatched'] ?? [];
        if ($unmatched) {
            $this->newLine();
            $this->line(sprintf('Not in Zulip yet (no SSO login), skipped: %d', count($unmatched)));
            foreach ($unmatched as $email) {
                $this->line("    {$email}");
            }
        }

        $errors = $s['errors'] ?? [];
        if ($errors) {
            $this->newLine();
            $this->line(sprintf('Errors: %d', count($errors)));
            foreach ($errors as $error) {
                $this->warn("    {$error}");
            }
        }

        $this->newLine();
        $this->info($dryRun
            ? 'Dry run complete — nothing was written. Re-run without --dry-run to apply.'
            : 'Sync complete.');

        return self::SUCCESS;
    }

    private function wouldBe(bool $dryRun): string
    {
        return $dryRun ? 'would be' : 'was';
    }
}
