<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Services\Coordinator;
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
    /** @var array<string, string[]>|null composite slug => source committee slugs */
    private ?array $composites = null;

    /** @var string[]|null slugs of committees that actually have a roster */
    private ?array $realCommitteeSlugs = null;

    /**
     * Composite committee groups, from config, validated against the committees
     * that actually exist.
     *
     * A composite is a Zulip group whose members are everyone in the listed
     * committees rather than a roster of its own, so it cannot drift: the only
     * way in or out is through a source committee.
     *
     * Two rules are enforced here rather than trusted to config. A key that
     * collides with a real committee's slug is dropped, because that committee
     * has a roster and computing its membership instead would silently empty
     * it. Sources are intersected with real committee slugs, so a composite
     * naming another composite contributes nothing — composites are one level
     * deep by construction, which keeps {@see self::for()} a single pass.
     *
     * @return array<string, string[]>
     */
    public function composites(): array
    {
        if ($this->composites !== null) {
            return $this->composites;
        }

        $real = $this->realCommitteeSlugs();
        $composites = [];

        foreach ((array) config('services.zulip.committee_composites', []) as $slug => $sources) {
            $slug = (string) $slug;

            if ($slug === '' || in_array($slug, $real, true)) {
                continue;
            }

            $sources = array_values(array_intersect((array) $sources, $real));

            if ($sources) {
                $composites[$slug] = $sources;
            }
        }

        return $this->composites = $composites;
    }

    /** Slugs of committees with their own roster (no composites). */
    private function realCommitteeSlugs(): array
    {
        return $this->realCommitteeSlugs ??= \App\Models\Committee::whereNotNull('slug')
            ->pluck('slug')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

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

        return $beltGroups
            ->push('all-black')
            ->merge($this->committeeSlugs())
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Every group that gets a Zulip channel: each real committee, plus each
     * composite. Belt-rank groups are used for mentions and permissions, not
     * rooms, so they are not here.
     *
     * This is also what the coordinator rule hands back, which is why the
     * coordinator lands in composites without a rule of its own.
     */
    public function committeeSlugs(): array
    {
        return array_values(array_unique(array_merge(
            $this->realCommitteeSlugs(),
            array_keys($this->composites()),
        )));
    }

    public function for(User $user): array
    {
        // Resolved once and passed down: composites are derived from the same
        // membership, and this is called for every user on every sync.
        $committees = $this->committeeGroups($user);

        $groups = [
            ...$this->beltRankGroups($user),
            ...$this->blackBeltGroups($user),
            ...$committees,
            ...$this->compositeGroups($committees),
            ...$this->coordinatorGroups($user),
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

    /**
     * Every composite the user's committee membership puts them in: being in
     * any one source committee is enough.
     *
     * @param  string[]  $committeeSlugs the user's real committee slugs
     */
    private function compositeGroups(array $committeeSlugs): array
    {
        $groups = [];

        foreach ($this->composites() as $slug => $sources) {
            if (array_intersect($committeeSlugs, $sources)) {
                $groups[] = $slug;
            }
        }

        return $groups;
    }

    /**
     * The coordinator sits in every committee's group.
     *
     * This is a reach rule, not a membership one: the coordinator is not added
     * to `committee_user`, so they don't appear on any committee's published
     * roster and confer nothing on themselves through
     * {@see \App\Models\User::committeePermissionNames()}. This only puts them
     * in the rooms, which is the point: the position has to be able to read and
     * post anywhere the committees are talking.
     *
     * Who holds it comes from config, not from a role assignment
     * ({@see \App\Services\Coordinator}). Because the sync removes as well as
     * adds, changing that config and deploying moves the Zulip memberships with
     * it on the next sync — including removing the previous holder.
     */
    private function coordinatorGroups(User $user): array
    {
        return app(Coordinator::class)->holds($user) ? $this->committeeSlugs() : [];
    }
}
