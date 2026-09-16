<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * The association's coordinator.
 *
 * Two halves, deliberately kept apart:
 *
 *  - WHO holds it is `portal.coordinator_user_id`, and only that. There is no
 *    row in model_has_roles, so no administrator — not even a super.admin — can
 *    hand the position to anyone through the portal. Moving it means editing
 *    config and deploying, which is the whole point.
 *  - WHAT it may do is the `coordinator` role's permission list in
 *    PermissionSeeder, so the allowlist stays in one reviewed place and the
 *    role row doubles as documentation of the position's reach.
 *
 * The role is therefore a permission bundle that is never assigned to anybody.
 * Do not "fix" that by assigning it — a Spatie assignment would reintroduce
 * exactly the UI-grantable state this design removes.
 *
 * Registered as a singleton (AppServiceProvider) because the Gate::before hook
 * consults it on every authorization check and the permission list is one query.
 */
class Coordinator
{
    public const ROLE = 'coordinator';

    private ?array $permissionNames = null;

    private bool $warnedMissingUser = false;

    /**
     * Is this user the configured coordinator?
     */
    public function holds(User $user): bool
    {
        $configured = $this->configuredUserId();

        return $configured !== null && $configured === (int) $user->getKey();
    }

    /**
     * The permissions the position carries.
     *
     * Read from config, NOT from the `coordinator` role's grants. That role is
     * kept empty on purpose: Spatie's own Gate::before hook honours a role's
     * permissions without consulting this class, so a role with real grants
     * would be conferrable by anyone who can write a model_has_roles row —
     * exactly the out-of-config path this design exists to close.
     */
    public function permissionNames(): array
    {
        return $this->permissionNames ??= array_values(
            (array) config('portal.coordinator_permissions', [])
        );
    }

    /**
     * The configured id, or null when unset, blank or non-numeric.
     */
    public function configuredUserId(): ?int
    {
        $configured = config('portal.coordinator_user_id');

        if ($configured === null || $configured === '' || ! is_numeric($configured)) {
            return null;
        }

        return (int) $configured;
    }

    /**
     * The user holding the position, or null.
     *
     * Warns once per request when the id is set but matches nobody: the failure
     * is safe (nobody is coordinator) but silent, and a typo'd id would
     * otherwise look exactly like a deliberately vacant position.
     */
    public function user(): ?User
    {
        $id = $this->configuredUserId();

        if ($id === null) {
            return null;
        }

        $user = User::find($id);

        if (! $user && ! $this->warnedMissingUser) {
            $this->warnedMissingUser = true;
            Log::warning('portal.coordinator_user_id is set to '.$id.' but no such user exists; nobody holds the coordinator position.');
        }

        return $user;
    }
}
