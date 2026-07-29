# Zulip OIDC attribute sync (requires Zulip 13.0+)

Zulip can sync the belt-rank custom profile field and user-group memberships
directly from the OIDC login **starting in Zulip 13.0**. As of this writing the
latest Zulip release is **12.1**, so this is not usable yet — the portal drives
these via the Zulip REST API in the meantime (see `App\Services\Zulip\*`).

When you upgrade Zulip to 13.0+, add the config below and the login itself will
keep everything in sync (no API sync needed for what OIDC covers). The portal
already emits the `belt_rank` and `zulip_groups` claims from
`/api/oauth/userinfo`, so **no portal changes are required** — this is Zulip-side
config only.

## Coolify compose env (Zulip service)

```yaml
# Enable the OIDC backend alongside email/password
SETTING_AUTHENTICATION_BACKENDS: '("zproject.backends.EmailAuthBackend", "zproject.backends.GenericOpenIdConnectBackend")'

# IdP config — note extra_attrs lists the custom claims the portal sends
SETTING_SOCIAL_AUTH_OIDC_ENABLED_IDPS: '{"chungdo": {"oidc_url": "https://chungdo.org", "display_name": "Chung Do Portal", "client_id": "${ZULIP_OIDC_CLIENTID}", "secret": get_secret("social_auth_oidc_secret"), "auto_signup": True, "extra_attrs": ["belt_rank", "zulip_groups"]}}'

# Map the claims: belt_rank -> "Belt rank" custom profile field; zulip_groups -> managed groups
SETTING_SOCIAL_AUTH_SYNC_ATTRS_DICT: '{"": {"oidc": {"custom__Belt rank": "belt_rank", "groups": ["no-belt","white-belt","yellow-belt","green-belt","purple-belt","brown-belt","black-belt","black-belt-2nd","black-belt-3rd","black-belt-4th","black-belt-5th","black-belt-6th","all-black"]}}}'

SECRETS_social_auth_oidc_secret: '${ZULIP_OIDC_SECRET}'
```

## Before it works

1. **Zulip 13.0+** — this config is ignored on 12.x (OIDC `extra_attrs` support
   was added on the `main` branch, targeting 13.0).
2. Create the **"Belt rank"** custom profile field in Zulip if it doesn't exist.
   - Short-text field: accepts any value.
   - List/select field: incoming values must exactly match option labels.
3. The `groups` list only manages the groups listed there; add your **committee
   slugs** too. Regenerate the full list (belts + all-black + committees):
   ```bash
   php artisan tinker --execute='
   $belts = \App\Models\Rank::orderBy("id")->pluck("rank")->map(fn($r)=>\Illuminate\Support\Str::slug($r));
   $committees = \App\Models\Committee::whereNotNull("slug")->pluck("slug");
   echo json_encode($belts->push("all-black")->merge($committees)->unique()->values()->all(), JSON_UNESCAPED_SLASHES);'
   ```
4. Zulip re-syncs these on **every login**. Groups Zulip doesn't list are left
   untouched; listed groups are auto-created if missing.

## Notes

- The realm subdomain key is `""` for the single org on `chat.chungdo.org`.
- Group sync landed in Zulip 11.0 for SAML, 13.0 for OIDC. Custom-profile-field
  sync over OIDC is likewise 13.0+.
- Once on 13.0 with this config, the API-based sync becomes redundant for
  belt-rank + groups (you can keep it for a nightly reconcile / backfill if you
  like, or retire it).
