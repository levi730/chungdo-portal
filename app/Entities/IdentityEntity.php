<?php

declare(strict_types=1);

namespace App\Entities;

use App\Models\User;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use OpenIDConnect\Claims\Traits\WithClaims;
use OpenIDConnect\Interfaces\IdentityEntityInterface;

/**
 * Supplies the OpenID Connect claims baked into the `id_token` (and returned
 * from the userinfo endpoint). The ClaimExtractor filters these down to the
 * claims permitted by the scopes granted on the token (openid/profile/email/…),
 * so it is safe to return the full set here.
 */
class IdentityEntity implements IdentityEntityInterface
{
    use EntityTrait;
    use WithClaims;

    protected User $user;

    /**
     * The identity repository resolves this entity from the token's subject id.
     *
     * @param mixed $identifier
     */
    public function setIdentifier($identifier): void
    {
        $this->identifier = $identifier;
        $this->user = User::findOrFail($identifier);
    }

    /**
     * Claims keyed by their standard OIDC name. Only those covered by a granted
     * scope end up in the token — e.g. `email`/`email_verified` require the
     * `email` scope, the `name`/`given_name`/… set requires `profile`.
     */
    public function getClaims(): array
    {
        $claims = [
            // email scope
            'email' => $this->user->email,
            'email_verified' => $this->user->email_verified_at !== null,

            // profile scope
            'name' => $this->user->full_name,
            'given_name' => $this->user->firstname,
            'family_name' => $this->user->lastname,
            'preferred_username' => $this->user->email,
            'gender' => $this->user->sex,
            'birthdate' => $this->user->dob?->format('Y-m-d'),
            'picture' => $this->user->avatar,
            'updated_at' => $this->user->updated_at?->getTimestamp(),

            // Non-standard claim: the student's belt rank name. Consumed by
            // Zulip's OIDC custom-profile-field sync (via extra_attrs). Not part
            // of any standard scope claim set, so the userinfo controller
            // surfaces it explicitly (the ClaimExtractor would otherwise drop it).
            'belt_rank' => $this->user->rank?->rank,

            // Non-standard claim: the Zulip user groups this user should belong
            // to, computed from portal logic. Zulip reconciles membership from
            // this array on each login (for groups it manages). Also surfaced by
            // the userinfo controller. An empty array is intentional — it tells
            // Zulip to remove the user from all managed groups.
            'zulip_groups' => app(\App\Services\ZulipGroupResolver::class)->for($this->user),
        ];

        // Omit empty claims rather than emitting `null` — some OIDC clients
        // reject or mishandle null-valued claims. Keep `false` (email_verified).
        return array_filter($claims, static fn ($value) => $value !== null);
    }
}
