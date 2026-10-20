<?php

/*
 * Delete spam-bot accounts by id.
 *
 *   php oneoff/delete_bot_accounts.php [<id> ...] [--connection=prod_ro] [--execute]
 *
 * With no ids it uses the 25 found by the 2026-10-19 scan of the prod dump
 * (lastname = firstname + capitals, disposable domains, keyboard mash).
 *
 * Dry run unless --execute. Each account is checked with AccountFootprint:
 * anything pointing at it other than sessions/tokens — a registration,
 * payment, dependent, guardian, role, note, Stripe customer — and the script
 * REFUSES the whole run rather than deleting around it.
 *
 * --connection  check against another connection; prod_ro for a dry run from
 *               a dev machine. It cannot --execute there.
 *
 * Take a dump of users before running with --execute.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Users\AccountFootprint;
use Illuminate\Support\Facades\DB;

const SCAN_2026_10_19 = [
    530, 531, 651, 652, 653, 1647, 1678, 1679, 1680, 1681, 1682, 1683, 1684,
    1692, 1714, 1715, 1717, 1718, 1719, 1724, 1725, 1726, 1727, 1740, 1761,
];

$args = array_values(array_filter(array_slice($argv, 1), fn ($a) => ! str_starts_with($a, '--')));
$opts = collect(array_slice($argv, 1))->filter(fn ($a) => str_starts_with($a, '--'))
    ->mapWithKeys(function ($a) {
        [$k, $v] = array_pad(explode('=', substr($a, 2), 2), 2, true);

        return [$k => $v];
    });

$ids = $args ? array_map('intval', $args) : SCAN_2026_10_19;
$execute = (bool) $opts->get('execute', false);
$db = DB::connection($opts->get('connection', config('database.default')));

if ($execute && str_ends_with($db->getName(), '_ro')) {
    fwrite(STDERR, "Refusing to --execute on a read-only connection ({$db->getName()}).\n");
    exit(1);
}

$footprint = new AccountFootprint($db);
$users = $db->table('users')->whereIn('id', $ids)->get()->keyBy('id');
$refuse = [];

echo "Connection: {$db->getName()} ({$db->getDatabaseName()})\n\n";

foreach ($ids as $id) {
    $u = $users->get($id);
    if (! $u) {
        echo "  skip    #$id  (no such user)\n";

        continue;
    }

    $found = $footprint->for($id);
    echo sprintf("  %-7s #%d  %s | %s <%s>  verified=%s  created %s\n",
        $found['blocking'] ? 'KEEP' : 'delete', $id, $u->firstname, $u->lastname, $u->email,
        $u->email_verified_at ? 'yes' : 'no', substr($u->created_at, 0, 10));

    foreach ($found['disposable'] as $col => $n) {
        echo "            also delete $col: $n\n";
    }
    foreach ($found['blocking'] as $col => $n) {
        $refuse[] = "#$id has $n row(s) in $col";
    }
}

if ($refuse) {
    echo "\nREFUSING — these are not bare bot accounts, check them by hand:\n  - ".implode("\n  - ", $refuse)."\n";
    exit(1);
}

$toDelete = $users->keys()->all();
echo "\n".count($toDelete)." account(s) to delete.\n";
echo "Back up first:\n  mysqldump {$db->getDatabaseName()} users > delete_bots_".date('Ymd_His').".sql\n\n";

if (! $execute) {
    echo "Dry run — nothing written. Re-run with --execute.\n";
    exit(0);
}

foreach ($toDelete as $id) {
    $footprint->delete($id);
}

echo 'Deleted '.count($toDelete)." account(s).\n";
