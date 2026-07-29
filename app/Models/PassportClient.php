<?php

namespace App\Models;

use Laravel\Passport\Client as PassportBaseClient;

class PassportClient extends PassportBaseClient
{
    /**
     * Trusted confidential (server-side) clients skip the consent screen.
     *
     * This is required for OIDC. The `nonce` a client sends arrives on the
     * initial GET /oauth/authorize; Passport only completes the authorization
     * within that same request when it's auto-approved. When a consent screen is
     * shown, approval happens on a later POST that no longer carries the nonce,
     * so the id_token is issued without it and clients (e.g. Zulip) reject the
     * login with "Incorrect id_token: nonce". Auto-approving trusted clients
     * keeps the nonce intact (and removes needless consent friction for our own
     * first-party SSO apps).
     */
    public function skipsAuthorization()
    {
        return $this->confidential();
    }
}
