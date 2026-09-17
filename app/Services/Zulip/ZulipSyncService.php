<?php

namespace App\Services\Zulip;

use App\Models\User;
use App\Services\ZulipGroupResolver;
use Illuminate\Support\Facades\Log;
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
        // There is one Zulip. A real sync run from a developer machine would
        // reconcile PRODUCTION against whatever rows happen to be in the local
        // database — stripping real people out of real groups and unsubscribing
        // them from real channels, because the portal is canonical and this
        // reconciles by removal. So outside production a write run is demoted
        // to a dry run rather than refused outright: the report is still
        // useful, and nothing can be lost by clicking the admin button or
        // forgetting --dry-run.
        $writesBlocked = false;

        if (! $dryRun && ! $this->writesAllowed()) {
            $dryRun = true;
            $writesBlocked = true;

            Log::warning('Zulip sync: writes are not allowed in this environment; ran as a dry run instead.', [
                'environment' => app()->environment(),
            ]);
        }

        $summary = [
            'dry_run' => $dryRun,
            'writes_blocked' => $writesBlocked,
            'eligible' => 0,
            'belt_rank_updated' => 0,
            'belt_rank_changes' => [],
            'unmatched' => [],
            'groups' => [],
            'channels' => [],
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
        $protected = [];    // zulip user ids never unsubscribed from a channel

        foreach ($this->zulip->getUsers(withProfileFields: (bool) $beltField) as $u) {
            $email = strtolower($u['delivery_email'] ?? $u['email'] ?? '');

            if ($email === '') {
                continue;
            }

            $id = (int) $u['user_id'];
            $byEmail[$email] = $id;
            $emailById[$id] = $email;

            // Organization admins/owners and bots are never unsubscribed from a
            // committee channel. They are often in a room to administer it
            // rather than because they sit on the committee, and the sync bot
            // must not lock itself out.
            if (($u['is_owner'] ?? false) || ($u['is_admin'] ?? false) || ($u['is_bot'] ?? false)) {
                $protected[$id] = $id;
            }

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

        // 3) Reconcile the committee channels those groups gate.
        $this->reconcileChannels($eligible, $byEmail, $emailById, $protected, $dryRun, $summary);

        return $summary;
    }

    /**
     * Decide what to do about a committee slug with no Zulip user group yet,
     * and say so in the summary. Returns whether the channel step should carry
     * on with this slug.
     *
     * Three different situations look identical here:
     *
     *  - Nobody is in the committee. The group step skips creating an empty
     *    group, and an empty channel is not wanted either. Not an error.
     *  - This is a dry run and the group step planned to create the group. It
     *    will exist by the time a real run reaches this point, because groups
     *    are reconciled before channels. Reporting "no matching group" here
     *    would be a scary false error on exactly the run people use to check a
     *    new committee before it goes live.
     *  - A real run got here with members and no group, which means the group
     *    step failed. That one is worth an error.
     */
    private function groupWillExist(string $slug, array $members, bool $dryRun, array &$summary): bool
    {
        if (empty($members)) {
            return false;
        }

        if ($dryRun && ($summary['groups'][$slug]['create'] ?? false)) {
            return true;
        }

        $summary['errors'][] = "Channel {$slug}: no matching Zulip user group, skipped.";

        return false;
    }

    /**
     * May this environment write to Zulip?
     *
     * Unset config means "production only", which is the safe default for a
     * setting whose whole job is to stop an accidental run. Setting
     * ZULIP_SYNC_ALLOW_WRITES explicitly overrides it either way — true to sync
     * from somewhere other than production, false to make even production
     * read-only while investigating.
     */
    private function writesAllowed(): bool
    {
        $configured = config('services.zulip.allow_writes');

        if ($configured !== null) {
            return (bool) $configured;
        }

        return app()->environment('production');
    }

    /**
     * Each committee has a private Zulip channel named after its slug, gated by
     * the committee's user group. This creates a missing channel, points the
     * channel's permission settings at the group, and reconciles subscribers to
     * the group's membership.
     *
     * Zulip has no auto-subscribe-on-group-join: subscribing a group is not a
     * thing the API supports (principals takes user ids only), so membership
     * has to be reconciled here the same way group membership is.
     *
     * Admins, owners and bots are added like anyone else but never removed.
     */
    private function reconcileChannels($eligible, array $byEmail, array $emailById, array $protected, bool $dryRun, array &$summary): void
    {
        $slugs = $this->groups->committeeSlugs();

        if (empty($slugs)) {
            return;
        }

        try {
            $groupIds = collect($this->zulip->getUserGroups())->pluck('id', 'name');
            $streams = collect($this->zulip->getStreams())->keyBy('name');
        } catch (Throwable $e) {
            $summary['errors'][] = "Fetching Zulip channels: {$e->getMessage()}";

            return;
        }

        // Desired membership per committee slug, from the portal.
        $desired = [];
        foreach ($eligible as $user) {
            $email = strtolower($user->email);

            if (! isset($byEmail[$email])) {
                continue; // already recorded as unmatched by the group step
            }

            foreach ($this->groups->for($user) as $slug) {
                if (in_array($slug, $slugs, true)) {
                    $desired[$slug][$byEmail[$email]] = $byEmail[$email];
                }
            }
        }

        $folderId = config('services.zulip.committee_folder_id');
        $folderId = $folderId === null ? null : (int) $folderId;

        foreach ($slugs as $slug) {
            $members = array_values($desired[$slug] ?? []);
            $groupId = $groupIds->get($slug);

            if (! $groupId && ! $this->groupWillExist($slug, $members, $dryRun, $summary)) {
                continue;
            }

            // On a dry run a group the group step only *planned* to create has
            // no id yet, so there is nothing to point the channel's permissions
            // at. Nothing is written on a dry run anyway — the plan below still
            // reports the channel and its members.
            $settings = $groupId ? [
                'can_subscribe_group' => (int) $groupId,
                'can_add_subscribers_group' => (int) $groupId,
                'can_send_message_group' => (int) $groupId,
            ] : [];

            try {
                $stream = $streams->get($slug);

                if (! $stream) {
                    if (! config('services.zulip.create_committee_channels', true)) {
                        continue;
                    }

                    if (! $dryRun) {
                        $this->zulip->createStream(
                            $slug,
                            "Managed by the Chung Do Portal.",
                            $members,
                            $settings,
                            $folderId,
                        );
                    }

                    $summary['channels'][$slug] = [
                        'create' => true,
                        'add' => $this->names($members, $emailById),
                        'remove' => [],
                    ];

                    continue;
                }

                $streamId = (int) $stream['stream_id'];
                $current = $this->zulip->getSubscribers($streamId);

                $add = array_values(array_diff($members, $current));
                // Never unsubscribe an admin, owner or bot.
                $remove = array_values(array_diff($current, $members, $protected));

                if (! $dryRun) {
                    $this->zulip->setStreamGroupSettings($streamId, $settings);
                    $this->zulip->subscribe($slug, $add);
                    $this->zulip->unsubscribe($slug, $remove);
                }

                if ($add || $remove) {
                    $summary['channels'][$slug] = [
                        'create' => false,
                        'add' => $this->names($add, $emailById),
                        'remove' => $this->names($remove, $emailById),
                    ];
                }
            } catch (Throwable $e) {
                $summary['errors'][] = "Channel {$slug}: {$e->getMessage()}";
            }
        }
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
