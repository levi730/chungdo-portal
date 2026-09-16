<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * Which roles a given actor is allowed to hand out.
 *
 * `super.admin` is the technical tier: it passes every ability through the
 * Gate::before hook in AppServiceProvider, future ones included. Handing out
 * user administration to a non-technical role (the coordinator) without this
 * guard would be a straight escalation path — the roles form lists every role,
 * so anyone who can edit a user could tick super.admin for themselves.
 *
 * The rule is therefore: only a super.admin may grant or revoke super.admin,
 * and no committee may confer it at all.
 */
class RoleAssignment
{
    public const TECHNICAL = 'super.admin';

    /**
     * Role names $actor may add to, or remove from, a user account.
     */
    public static function assignableBy(User $actor): Collection
    {
        return Role::orderBy('name')
            ->pluck('name')
            ->reject(fn ($name) => $name === self::TECHNICAL && ! $actor->hasRole(self::TECHNICAL))
            ->values();
    }

    /**
     * Role names a committee may confer on its members.
     *
     * Everything except the technical tier. The coordinator role is allowed
     * here — odd, since it describes a single position, but it is a visible
     * checkbox an administrator has to tick, and it cannot reach further than
     * the administrator ticking it already can.
     */
    public static function conferrableByCommittee(): Collection
    {
        return Role::orderBy('name')
            ->pluck('name')
            ->reject(fn ($name) => $name === self::TECHNICAL)
            ->values();
    }
}
