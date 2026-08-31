<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Computes the set of Zulip user-group names a portal user should belong to.
 *
 * The result is sent to Zulip in the `zulip_groups` OIDC claim (see
 * {@see \App\Entities\IdentityEntity}). Zulip reconciles membership on each
 * login: a user is added to every managed group present in the claim and
 * removed from every managed group that is absent.
 *
 * Important: Zulip only touches groups you also declare as managed in
 * SOCIAL_AUTH_SYNC_ATTRS_DICT ["...", "oidc", "groups"]. Returning a group here
 * has no effect until it is listed there, and groups Zulip doesn't manage are
 * left untouched. Group names are auto-created in Zulip if they don't exist.
 *
 * Add new group rules by writing another private method and merging it in
 * {@see self::for()}.
 */
class ZulipGroupResolver
{
    /**
     * The full universe of group names this resolver can ever emit — every belt
     * rank slug, "all-black", and every committee slug. Used to reconcile Zulip
     * group membership (so groups that should now be empty are emptied) and to
     * build the Zulip 13 managed `groups` list.
     */
    public function managedGroups(): array
    {
        $beltGroups = \App\Models\Rank::orderBy('id')
            ->pluck('rank')
            ->map(fn ($rank) => Str::slug($rank));

        $committeeGroups = \App\Models\Committee::whereNotNull('slug')->pluck('slug');

        return $beltGroups
            ->push('all-black')
            ->merge($committeeGroups)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Committee slugs only — the subset of managedGroups() that gets a Zulip
     * channel. Belt-rank groups are used for mentions and permissions, not
     * rooms.
     */
    public function committeeSlugs(): array
    {
        return \App\Models\Committee::whereNotNull('slug')
            ->pluck('slug')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function for(User $user): array
    {
        $groups = [
            ...$this->beltRankGroups($user),
            ...$this->blackBeltGroups($user),
            ...$this->committeeGroups($user),
            // Future rules go here, e.g. $this->schoolGroups($user),
            // $this->roleGroups($user), $this->instructorGroups($user), ...
        ];

        // De-duplicate and drop empties; re-index for a clean JSON array.
        return array_values(array_unique(array_filter($groups)));
    }

    /**
     * One group per belt rank, e.g. "Black Belt (4th)" -> "black-belt-4th".
     */
    private function beltRankGroups(User $user): array
    {
        $rank = $user->rank?->rank;

        return $rank ? [Str::slug($rank)] : [];
    }

    /**
     * "all-black" for any black belt — Black Belt (1st) and up have rank id >= 1;
     * colored (gup) ranks are negative. Null-guarded so rankless users (and the
     * null-coerces-to-0 comparison) don't slip in.
     */
    private function blackBeltGroups(User $user): array
    {
        $rankId = $user->rank?->id;

        return $rankId !== null && $rankId >= 1 ? ['all-black'] : [];
    }

    /**
     * One group per committee the user belongs to, using the committee's slug
     * as the Zulip group name. Committees without a slug are skipped.
     */
    private function committeeGroups(User $user): array
    {
        return $user->committees()
            ->whereNotNull('slug')
            ->pluck('slug')
            ->all();
    }
}
