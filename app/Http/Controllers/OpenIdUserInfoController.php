<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenIDConnect\ClaimExtractor;

/**
 * OpenID Connect userinfo endpoint.
 *
 * Laravel Passport does not ship a userinfo endpoint, so we provide one and
 * name the route `openid.userinfo` — the package's DiscoveryController detects
 * that name and advertises `userinfo_endpoint` in the discovery document.
 *
 * Returns the claims permitted by the scopes granted on the presented access
 * token, using the same IdentityEntity + ClaimExtractor as the id_token so the
 * two stay consistent. Requires a valid Passport bearer token (auth:api).
 */
class OpenIdUserInfoController extends Controller
{
    public function __invoke(Request $request, ClaimExtractor $extractor): JsonResponse
    {
        $user = $request->user();
        $subject = (string) $user->getAuthIdentifier();

        // Scopes granted on this specific access token.
        $scopes = $user->token()->scopes ?? [];

        $identity = app(config('openid.repositories.identity'))
            ->getByIdentifier($subject);

        $identityClaims = $identity->getClaims();
        $claims = $extractor->extract($scopes, $identityClaims);

        // Non-standard claims aren't part of any scope's claim set, so the
        // extractor drops them. Surface them when the `profile` scope was granted
        // — Zulip reads these via extra_attrs (belt_rank -> custom profile field,
        // zulip_groups -> user-group membership sync).
        if (in_array('profile', $scopes, true)) {
            foreach (['belt_rank', 'zulip_groups'] as $customClaim) {
                if (array_key_exists($customClaim, $identityClaims)) {
                    $claims[$customClaim] = $identityClaims[$customClaim];
                }
            }
        }

        // `sub` is always returned per the OIDC spec, regardless of scope.
        $claims['sub'] = $subject;

        return response()->json($claims);
    }
}
