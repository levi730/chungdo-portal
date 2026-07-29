<?php

namespace App\Services\Zulip;

use App\Models\User;
use App\Services\ZulipGroupResolver;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pushes the portal's data to Zulip via the REST API for users flagged with
 * sync_to_zulip: creates missing accounts, sets the belt-rank custom profile
 * field, and reconciles managed user-group memberships. Returns a summary.
 *
 * This is the stopgap until Zulip 13.0's native OIDC attribute sync ships
 * (see docs/zulip-13-oidc-sync.md).
 */
class ZulipSyncService
{
    public function __construct(
        private ZulipClient $zulip,
        private ZulipGroupResolver $groups,
    ) {}

    public function sync(): array
    {
        $summary = [
            'eligible' => 0,
            'created' => [],
            'belt_rank_updated' => 0,
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

        // email (lower) => zulip user_id
        $byEmail = [];
        foreach ($this->zulip->getUsers() as $u) {
            $email = strtolower($u['delivery_email'] ?? $u['email'] ?? '');
            if ($email !== '') {
                $byEmail[$email] = (int) $u['user_id'];
            }
        }

        $beltFieldId = $this->beltRankFieldId();

        // 1) Ensure users exist, then set belt rank. Build email->id as we go.
        foreach ($eligible as $user) {
            $email = strtolower($user->email);

            try {
                if (! isset($byEmail[$email])) {
                    $byEmail[$email] = $this->zulip->createUser(
                        $user->email,
                        $user->full_name,
                        Str::password(40),
                    );
                    $summary['created'][] = $user->email;
                }

                if ($beltFieldId && $user->rank?->rank) {
                    $this->zulip->setUserProfileField($byEmail[$email], $beltFieldId, $user->rank->rank);
                    $summary['belt_rank_updated']++;
                }
            } catch (Throwable $e) {
                $summary['errors'][] = "{$user->email}: {$e->getMessage()}";
            }
        }

        // 2) Reconcile group memberships for the managed group universe.
        $this->reconcileGroups($eligible, $byEmail, $summary);

        return $summary;
    }

    private function reconcileGroups($eligible, array $byEmail, array &$summary): void
    {
        // Desired: group slug => set of zulip user ids (flagged users only).
        $desired = [];
        foreach ($this->groups->managedGroups() as $slug) {
            $desired[$slug] = [];
        }
        foreach ($eligible as $user) {
            $email = strtolower($user->email);
            if (! isset($byEmail[$email])) {
                $summary['unmatched'][] = $user->email; // couldn't create/find in Zulip
                continue;
            }
            foreach ($this->groups->for($user) as $slug) {
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
                    $this->zulip->createUserGroup($slug, 'Managed by the Chung Do Portal.', $memberIds);
                    $summary['groups'][$slug] = ['added' => count($memberIds), 'removed' => 0];

                    continue;
                }

                $current = array_map('intval', $group['members'] ?? []);
                $add = array_values(array_diff($memberIds, $current));
                $remove = array_values(array_diff($current, $memberIds));

                $this->zulip->updateUserGroupMembers((int) $group['id'], $add, $remove);

                if ($add || $remove) {
                    $summary['groups'][$slug] = ['added' => count($add), 'removed' => count($remove)];
                }
            } catch (Throwable $e) {
                $summary['errors'][] = "Group {$slug}: {$e->getMessage()}";
            }
        }
    }

    private function beltRankFieldId(): ?int
    {
        $name = config('services.zulip.belt_rank_field', 'Belt rank');

        try {
            foreach ($this->zulip->getProfileFields() as $field) {
                if (($field['name'] ?? null) === $name) {
                    return (int) $field['id'];
                }
            }
        } catch (Throwable $e) {
            // fall through; belt rank just won't be synced this run
        }

        return null;
    }
}
