<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Users\AccountFootprint;
use Illuminate\Console\Command;

/**
 * Sign-ups that never confirmed their email and never did anything. Most are
 * spam bots that got past the reCAPTCHA, but not all — some are real people
 * who never clicked the link — so this is run by hand, not scheduled. Always
 * --dry-run first and read the list. An account with anything pointing at it
 * (a registration, payment, dependent, guardian, role, note, Stripe customer)
 * is never touched.
 */
class PruneUnverifiedUsers extends Command
{
    protected $signature = 'users:prune-unverified
                            {--days=30 : How old an unverified sign-up must be before it is removed}
                            {--dry-run : List what would be removed without deleting anything}';

    protected $description = 'Delete unverified sign-ups older than --days that have no activity';

    public function handle(AccountFootprint $footprint): int
    {
        $days = (int) $this->option('days');

        $candidates = User::query()
            ->whereNull('email_verified_at')
            ->where('can_login', true)
            ->whereNull('responsible_user_id')
            ->where('created_at', '<', now()->subDays($days))
            ->orderBy('id')
            ->get(['id', 'firstname', 'lastname', 'email', 'created_at']);

        $removed = 0;
        foreach ($candidates as $user) {
            $found = $footprint->for($user->id);
            $label = sprintf('#%d  %s %s <%s>  %s', $user->id, $user->firstname, $user->lastname, $user->email, $user->created_at->toDateString());

            if ($found['blocking']) {
                // Imported parents who never signed in are the bulk of these; -v to see them.
                $this->line("keep    $label  (".implode(', ', array_keys($found['blocking'])).')', verbosity: 'v');

                continue;
            }

            $this->line("remove  $label");
            if (! $this->option('dry-run')) {
                $footprint->delete($user->id);
            }
            $removed++;
        }

        $this->info(($this->option('dry-run') ? 'Would remove ' : 'Removed ')."$removed unverified account(s).");

        return self::SUCCESS;
    }
}
