<?php

/*
 * Fold a duplicate account into the one being kept.
 *
 *   php oneoff/merge_users.php <keep_id> <drop_id> [--take-login] [--take=dob,...] [--connection=prod_ro] [--execute]
 *
 * Dry run unless --execute. Every column in the schema that can hold a user id
 * is discovered from information_schema, and each is either MOVED to the kept
 * account, DELETED with the dropped one, or the script REFUSES — a column it
 * does not know about, or one it cannot merge safely (roles, guardianships,
 * dependents, committees), stops it rather than being silently orphaned.
 *
 * --take-login  the kept account takes the dropped account's email, verified
 *               time and password, so the person signs in the way they now do.
 * --take=a,b    copy other users columns from the dropped account (e.g. dob).
 * --connection  run the plan against another connection; prod_ro for a dry
 *               run from a dev machine. It cannot --execute there.
 *
 * Take a dump of the tables it lists before running with --execute.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$args = array_values(array_filter(array_slice($argv, 1), fn ($a) => ! str_starts_with($a, '--')));
$opts = collect(array_slice($argv, 1))->filter(fn ($a) => str_starts_with($a, '--'))
    ->mapWithKeys(function ($a) {
        [$k, $v] = array_pad(explode('=', substr($a, 2), 2), 2, true);

        return [$k => $v];
    });

if (count($args) !== 2) {
    fwrite(STDERR, "usage: php oneoff/merge_users.php <keep_id> <drop_id> [--take-login] [--take=dob] [--connection=name] [--execute]\n");
    exit(1);
}

[$keepId, $dropId] = array_map('intval', $args);
$execute = (bool) $opts->get('execute', false);
$db = DB::connection($opts->get('connection', config('database.default')));

if ($execute && str_ends_with($db->getName(), '_ro')) {
    fwrite(STDERR, "Refusing to --execute on a read-only connection ({$db->getName()}).\n");
    exit(1);
}

$keep = $db->table('users')->find($keepId);
$drop = $db->table('users')->find($dropId);
if (! $keep || ! $drop || $keepId === $dropId) {
    fwrite(STDERR, "Need two different existing users.\n");
    exit(1);
}

// Columns whose rows follow the person to the kept account.
$move = [
    'event_registrations.user_id',
    'event_registrations.registering_user_id',
    'event_registrations_deleted.user_id',
    'event_registrations_deleted.registering_user_id',
    'pending_event_registrations.registering_user_id',
    'payments.user_id',
    'product_orders.user_id',
    'project_united_transactions.user_id',
    'refunds.refunded_to_user_id',
    'user_notes.user_id',
    'stripe_customers.user_id',
    'alternate_contacts.user_id',
];

// Rows that mean nothing once the account is gone.
$delete = [
    'sessions.user_id',
    'oauth_access_tokens.user_id',
    'oauth_auth_codes.user_id',
    'account_link_requests.requester_user_id',
    'account_link_requests.recipient_user_id',
];

$schema = $db->getDatabaseName();
$cols = collect($db->select(
    "SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = ? AND (COLUMN_NAME LIKE '%user_id' OR (COLUMN_NAME = 'model_id' AND TABLE_NAME LIKE 'model_has_%') OR COLUMN_NAME = 'tokenable_id')",
    [$schema]
))->map(fn ($x) => "$x->t.$x->c");

$plan = ['move' => [], 'delete' => []];
$refuse = [];

foreach ($cols as $col) {
    [$t, $c] = explode('.', $col);
    $q = $db->table($t)->where($c, $dropId);
    if ($c === 'model_id' || $c === 'tokenable_id') {
        $q->where(str_replace('_id', '_type', $c), 'like', '%User');
    }
    $n = $q->count();
    if (! $n) {
        continue;
    }
    if (in_array($col, $move)) {
        $plan['move'][$col] = $n;
    } elseif (in_array($col, $delete)) {
        $plan['delete'][$col] = $n;
    } else {
        $refuse[] = "$col has $n row(s) for the dropped account — merge that by hand";
    }
}

// pending_event_registrations.payload also names registrant ids. A fulfilled row is
// never fulfilled again, so a stale id there is history; an open one is not.
$open = $db->table('pending_event_registrations')->where('registering_user_id', $dropId)
    ->where('status', '!=', 'fulfilled')->count();
if ($open) {
    $refuse[] = "the dropped account has $open unfulfilled pending registration(s) — let them settle first";
}

// Same event on both accounts: moving would double-register the person.
$clash = $db->table('event_registrations')->where('user_id', $dropId)
    ->whereIn('event_id', $db->table('event_registrations')->where('user_id', $keepId)->select('event_id'))
    ->pluck('event_id');
if ($clash->isNotEmpty()) {
    $refuse[] = 'both accounts are registered for event(s) '.$clash->implode(', ');
}

// Stripe customer ids are per account; a second customer on the same account cannot be merged.
$stripeClash = $db->table('stripe_customers')->where('user_id', $dropId)
    ->whereIn('account', $db->table('stripe_customers')->where('user_id', $keepId)->select('account'))
    ->pluck('account');
if ($stripeClash->isNotEmpty()) {
    $refuse[] = 'both accounts have a Stripe customer on '.$stripeClash->implode(', ');
}
if ($drop->stripe_id && $keep->stripe_id) {
    $refuse[] = 'both accounts have users.stripe_id (association Stripe customer)';
}

// users columns the kept account takes from the dropped one.
$take = $opts->has('take') ? explode(',', $opts['take']) : [];
if ($opts->get('take-login')) {
    array_push($take, 'email', 'email_verified_at', 'password');
}
if ($drop->stripe_id && ! $keep->stripe_id) {
    $take = array_merge($take, ['stripe_id', 'pm_type', 'pm_last_four']);
}
$take = array_values(array_unique($take));
$unknown = array_diff($take, array_keys((array) $keep));
if ($unknown) {
    $refuse[] = 'unknown users column(s): '.implode(', ', $unknown);
}

echo "Keep #$keep->id  $keep->firstname $keep->lastname <$keep->email>\n";
echo "Drop #$drop->id  $drop->firstname $drop->lastname <$drop->email>\n";
echo "Connection: {$db->getName()} ($schema)\n\n";
foreach ($plan['move'] as $col => $n) {
    echo "  move    $col: $n\n";
}
foreach ($plan['delete'] as $col => $n) {
    echo "  delete  $col: $n\n";
}
foreach ($take as $c) {
    $show = $c === 'password' ? '(hash)' : var_export($drop->$c ?? null, true);
    echo "  take    users.$c: ".($c === 'password' ? '(hash)' : var_export($keep->$c, true))." -> $show\n";
}
echo "  delete  users #$dropId\n\n";

if ($refuse) {
    echo "REFUSING:\n  - ".implode("\n  - ", $refuse)."\n";
    exit(1);
}

$tables = collect(array_keys($plan['move'] + $plan['delete']))->map(fn ($c) => explode('.', $c)[0])
    ->push('users')->unique()->implode(' ');
echo "Back up first:\n  mysqldump $schema $tables > merge_{$keepId}_{$dropId}_".date('Ymd_His').".sql\n\n";

if (! $execute) {
    echo "Dry run — nothing written. Re-run with --execute.\n";
    exit(0);
}

$db->transaction(function () use ($db, $plan, $take, $keepId, $dropId, $drop) {
    foreach (array_keys($plan['delete']) as $col) {
        [$t, $c] = explode('.', $col);
        $db->table($t)->where($c, $dropId)->delete();
    }
    foreach (array_keys($plan['move']) as $col) {
        [$t, $c] = explode('.', $col);
        $db->table($t)->where($c, $dropId)->update([$c => $keepId]);
    }
    if ($take) {
        // email and stripe_id are unique: free them on the dropped row before the kept row takes them.
        $db->table('users')->where('id', $dropId)->update(array_intersect_key(
            ['email' => "merged-$dropId@invalid", 'stripe_id' => null],
            array_flip($take)
        ));
        $db->table('users')->where('id', $keepId)->update(
            collect($take)->mapWithKeys(fn ($c) => [$c => $drop->$c])->all() + ['updated_at' => now()]
        );
    }
    $db->table('users')->where('id', $dropId)->delete();
});

echo "Merged #$dropId into #$keepId.\n";
