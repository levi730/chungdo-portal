<?php

namespace App\Services\Zulip;

use App\Models\User;
use App\Services\ZulipGroupResolver;
use Throwable;

/**
 * Pushes the portal's data to Zulip via the REST API for users flagged with
 * sync_to_zulip: sets the belt-rank custom profile field and reconciles managed
 * user-group memberships. Returns a summary.
 *
 * The portal is the canonical source for managed group membership. Every run
 * reconciles each managed group (belt slugs, "all-black", committee slugs) to
 * exactly the set the portal computes, so members Zulip has that the portal does
 * not are removed. Groups outside that managed universe are never touched.
 *
 * Only users who already exist in Zulip are touched. Accounts are never
 * pre-created here — a Zulip account is provisioned when the user first logs in
 * via SSO; users not yet in Zulip are reported as unmatched and picked up on a
 * later run once they have logged in.
 *
 * Pass $dryRun to plan a run without changing anything: every read still
 * happens, no write endpoint is called, and the summary lists the exact changes
 * a real run would make.
 *
 * This is the stopgap until Zulip 13.0's native OIDC attribute sync ships
 * (see docs/zulip-13-oidc-sync.md).
 */
class ZulipSyncService
{
    /** Zulip custom-profile-field type for "list of options" (SELECT). */
    private const ZULIP_FIELD_SELECT = 3;

    public function __construct(
        private ZulipClient $zulip,
        private ZulipGroupResolver $groups,
    ) {}

    public function sync(bool $dryRun = false): array
    {
        $summary = [
            'dry_run' => $dryRun,
            'eligible' => 0,
            'belt_rank_updated' => 0,
            'belt_rank_changes' => [],
            'unmatched' => [],
            'groups' => [],
            'errors' => [],
        ];

        $eligible = User::where('sync_to_zulip', true)
            ->where('email', '!=', '')
            ->whereNotNull('email')
            ->with(['rank', 'committees'])
            ->get();
        $summary['eligible'] = $eligible->count();

        $beltField = $this->beltRankField();

        // Zulip's side of the picture. The reverse map lets us name the people in
        // group adds/removes; the current belt values let us skip no-op writes
        // (and keep a dry run's report to real changes).
        $byEmail = [];      // email (lower) => zulip user id
        $emailById = [];    // zulip user id  => email
        $currentBelt = [];  // zulip user id  => current belt-rank field value

        foreach ($this->zulip->getUsers(withProfileFields: (bool) $beltField) as $u) {
            $email = strtolower($u['delivery_email'] ?? $u['email'] ?? '');

            if ($email === '') {
                continue;
            }

            $id = (int) $u['user_id'];
            $byEmail[$email] = $id;
            $emailById[$id] = $email;

            if ($beltField) {
                $currentBelt[$id] = $u['profile_data'][(string) $beltField['id']]['value'] ?? null;
            }
        }

        // 1) Set belt rank for users who already exist in Zulip. We never
        // pre-create accounts here: a Zulip account is provisioned when the user
        // first logs in via SSO. Users not yet in Zulip are left for the group
        // reconciliation step to record as unmatched.
        foreach ($eligible as $user) {
            $email = strtolower($user->email);

            if (! isset($byEmail[$email]) || ! $beltField || ! $user->rank?->rank) {
                continue;
            }

            try {
                $rank = $user->rank->rank;
                $value = $this->beltRankValue($beltField, $rank);

                if ($value === null) {
                    $summary['errors'][] = "{$user->email}: no Zulip 'Belt rank' choice matches '{$rank}'.";

                    continue;
                }

                $id = $byEmail[$email];

                // Already correct in Zulip — nothing to write.
                if ((string) ($currentBelt[$id] ?? '') === (string) $value) {
                    continue;
                }

                if (! $dryRun) {
                    $this->zulip->setUserProfileField($id, (int) $beltField['id'], $value);
                }

                $summary['belt_rank_changes'][] = "{$user->email}: {$rank}";
                $summary['belt_rank_updated']++;
            } catch (Throwable $e) {
                $summary['errors'][] = "{$user->email}: {$e->getMessage()}";
            }
        }

        // 2) Reconcile group memberships for the managed group universe.
        $this->reconcileGroups($eligible, $byEmail, $emailById, $dryRun, $summary);

        return $summary;
    }

    private function reconcileGroups($eligible, array $byEmail, array $emailById, bool $dryRun, array &$summary): void
    {
        // Desired: group slug => set of zulip user ids (flagged users only).
        $desired = [];
        foreach ($this->groups->managedGroups() as $slug) {
            $desired[$slug] = [];
        }

        foreach ($eligible as $user) {
            $email = strtolower($user->email);

            if (! isset($byEmail[$email])) {
                $summary['unmatched'][] = $user->email; // not in Zulip yet (no SSO login)
                continue;
            }

            foreach ($this->groups->for($user) as $slug) {
                // Safety net: a slug the resolver emits but managedGroups() did
                // not list still gets its members, it just isn't emptied.
                $desired[$slug] ??= [];
                $desired[$slug][$byEmail[$email]] = $byEmail[$email];
            }
        }

        try {
            $existing = collect($this->zulip->getUserGroups())->keyBy('name');
        } catch (Throwable $e) {
            $summary['errors'][] = "Fetching Zulip groups: {$e->getMessage()}";

            return;
        }

        foreach ($desired as $slug => $memberIds) {
            $memberIds = array_values($memberIds);

            try {
                $group = $existing->get($slug);

                if (! $group) {
                    // Skip creating a group that would have no members anyway.
                    if (empty($memberIds)) {
                        continue;
                    }

                    if (! $dryRun) {
                        $this->zulip->createUserGroup($slug, 'Managed by the Chung Do Portal.', $memberIds);
                    }

                    $summary['groups'][$slug] = [
                        'create' => true,
                        'add' => $this->names($memberIds, $emailById),
                        'remove' => [],
                    ];

                    continue;
                }

                $current = array_map('intval', $group['members'] ?? []);
                $add = array_values(array_diff($memberIds, $current));
                $remove = array_values(array_diff($current, $memberIds));

                if (! $add && ! $remove) {
                    continue;
                }

                if (! $dryRun) {
                    $this->zulip->updateUserGroupMembers((int) $group['id'], $add, $remove);
                }

                $summary['groups'][$slug] = [
                    'create' => false,
                    'add' => $this->names($add, $emailById),
                    'remove' => $this->names($remove, $emailById),
                ];
            } catch (Throwable $e) {
                $summary['errors'][] = "Group {$slug}: {$e->getMessage()}";
            }
        }
    }

    /** Turn Zulip user ids into emails for the summary, falling back to the id. */
    private function names(array $ids, array $emailById): array
    {
        return array_values(array_map(fn ($id) => $emailById[$id] ?? "user #{$id}", $ids));
    }

    private function beltRankField(): ?array
    {
        $name = config('services.zulip.belt_rank_field', 'Belt rank');

        try {
            foreach ($this->zulip->getProfileFields() as $field) {
                if (($field['name'] ?? null) === $name) {
                    return $field;
                }
            }
        } catch (Throwable $e) {
            // fall through; belt rank just won't be synced this run
        }

        return null;
    }

    /**
     * Resolve the value to send for a user's belt rank. Zulip "list of options"
     * (SELECT) fields validate against each choice's internal key, not its
     * label, so translate the portal's rank label to that key. Other field
     * types (e.g. short text) take the label verbatim. Returns null when a
     * SELECT field has no choice whose text matches the rank.
     */
    private function beltRankValue(array $field, string $rank): ?string
    {
        if ((int) ($field['type'] ?? 0) !== self::ZULIP_FIELD_SELECT) {
            return $rank;
        }

        // field_data is a JSON map of key => ['text' => label, 'order' => n].
        $choices = json_decode($field['field_data'] ?? '', true) ?: [];
        foreach ($choices as $key => $choice) {
            if (($choice['text'] ?? null) === $rank) {
                return (string) $key;
            }
        }

        return null;
    }
}
