<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * Which roles a given actor is allowed to hand out.
 *
 * Two roles are withheld, for different reasons:
 *
 *  - `super.admin` is the technical tier: it passes every ability through the
 *    Gate::before hook in AppServiceProvider, future ones included. Without
 *    this guard, granting user administration to a non-technical role (the
 *    coordinator) would be a straight escalation path — the roles form lists
 *    every role, so anyone who could edit a user could tick super.admin for
 *    themselves. Only a super.admin may grant or revoke it.
 *  - `coordinator` is never assignable by anyone, through any surface. The
 *    position is held by whoever `portal.coordinator_user_id` names and by no
 *    other mechanism ({@see Coordinator}). The role is kept permanently empty,
 *    so ticking it would confer nothing — a silently inert grant is worse than
 *    an honest refusal, and offering it would imply a second coordinator is a
 *    thing an administrator can create.
 *
 * Neither may be conferred by a committee.
 */
class RoleAssignment
{
    public const TECHNICAL = 'super.admin';

    /** Roles no surface may ever hand out, whoever is asking. */
    public const NEVER_ASSIGNABLE = [Coordinator::ROLE];

    /**
     * Role names $actor may add to, or remove from, a user account.
     */
    public static function assignableBy(User $actor): Collection
    {
        return self::base()
            ->reject(fn ($name) => $name === self::TECHNICAL && ! $actor->hasRole(self::TECHNICAL))
            ->values();
    }

    /**
     * Role names a committee may confer on its members.
     *
     * Everything except the technical tier and the coordinator.
     */
    public static function conferrableByCommittee(): Collection
    {
        return self::base()
            ->reject(fn ($name) => $name === self::TECHNICAL)
            ->values();
    }

    private static function base(): Collection
    {
        return Role::orderBy('name')
            ->pluck('name')
            ->reject(fn ($name) => in_array($name, self::NEVER_ASSIGNABLE, true));
    }
}
