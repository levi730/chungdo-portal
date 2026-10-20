<?php

namespace App\Services\Users;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Everything in the database that points at a user, so an account is only
 * deleted when nothing real would be orphaned.
 *
 * Columns are discovered from information_schema the way
 * oneoff/merge_users.php does it, so a table added later is counted without
 * anyone remembering to list it here. Rows that mean nothing once the account
 * is gone (sessions, tokens) are reported separately as disposable.
 */
class AccountFootprint
{
    /** Rows that go with the account rather than standing in the way of deleting it. */
    public const DISPOSABLE = [
        'sessions.user_id',
        'oauth_access_tokens.user_id',
        'oauth_auth_codes.user_id',
        'personal_access_tokens.tokenable_id',
        'account_link_requests.requester_user_id',
        'account_link_requests.recipient_user_id',
    ];

    /** User references whose names don't end in user_id. */
    private const EXTRA = ['user_notes.added_by'];

    private ?array $columns = null;

    public function __construct(private ?Connection $db = null)
    {
        $this->db ??= DB::connection();
    }

    /**
     * @return array{blocking: array<string,int>, disposable: array<string,int>}
     */
    public function for(int $userId): array
    {
        $found = ['blocking' => [], 'disposable' => []];

        foreach ($this->columns() as $col) {
            [$t, $c] = explode('.', $col);
            $q = $this->db->table($t)->where($c, $userId);
            if ($c === 'model_id' || $c === 'tokenable_id') {
                $q->where(str_replace('_id', '_type', $c), 'like', '%User');
            }
            if ($n = $q->count()) {
                $found[in_array($col, self::DISPOSABLE, true) ? 'disposable' : 'blocking'][$col] = $n;
            }
        }

        // The association's Stripe customer lives on the row itself.
        if ($this->db->table('users')->where('id', $userId)->whereNotNull('stripe_id')->exists()) {
            $found['blocking']['users.stripe_id'] = 1;
        }

        return $found;
    }

    public function isDeletable(int $userId): bool
    {
        return $this->for($userId)['blocking'] === [];
    }

    /** Remove the disposable rows, then the account, in one transaction. */
    public function delete(int $userId): void
    {
        $this->db->transaction(function () use ($userId) {
            foreach (array_keys($this->for($userId)['disposable']) as $col) {
                [$t, $c] = explode('.', $col);
                $this->db->table($t)->where($c, $userId)->delete();
            }

            // Through the model so UserObserver drops the Sendportal subscriber.
            \App\Models\User::on($this->db->getName())->findOrFail($userId)->delete();
        });
    }

    private function columns(): array
    {
        return $this->columns ??= collect($this->db->select(
            "SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND (COLUMN_NAME LIKE '%user_id'
                OR (COLUMN_NAME = 'model_id' AND (TABLE_NAME LIKE 'model_has_%' OR TABLE_NAME = 'media'))
                OR COLUMN_NAME = 'tokenable_id')",
            [$this->db->getDatabaseName()]
        ))->map(fn ($x) => "$x->t.$x->c")
            ->merge(self::EXTRA)
            ->unique()->values()->all();
    }
}
