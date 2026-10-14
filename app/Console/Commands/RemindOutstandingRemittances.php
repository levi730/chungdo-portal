<?php

namespace App\Console\Commands;

use App\Services\CashRegistration\RemittanceNotifier;
use Illuminate\Console\Command;

/**
 * The cash/check flow's reconcile sweep. There is no Stripe to ask, so the
 * backstop is people: once an event is a few days past, anything still
 * outstanding is mailed to whoever can mark it received. An envelope nobody
 * turned in surfaces within the week rather than at year-end.
 */
class RemindOutstandingRemittances extends Command
{
    protected $signature = 'cash:remind-outstanding
                            {--days=3 : Days after the event before a remittance counts as overdue}
                            {--dry-run : List what is overdue without sending anything}';

    protected $description = 'Email payment receivers about cash/check remittances still outstanding after their event';

    public function handle(RemittanceNotifier $notifier): int
    {
        $days = (int) $this->option('days');
        $overdue = $notifier->overdue($days);

        foreach ($overdue as $r) {
            $this->line(sprintf('%s  %s  $%s  %s', $r->reference, $r->event->name, $r->amount_due, $r->responsible->full_name));
        }

        if ($this->option('dry-run')) {
            $this->info($overdue->count().' overdue; would mail '.$notifier->receivers()->count().' receiver(s).');

            return self::SUCCESS;
        }

        $notifier->remindOverdue($days);
        $this->info($overdue->count().' overdue remittance(s) reported.');

        return self::SUCCESS;
    }
}
