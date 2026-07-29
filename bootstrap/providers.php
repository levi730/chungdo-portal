<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
    App\Providers\FortifyUIServiceProvider::class,
    // Replaces Laravel\Passport\PassportServiceProvider (excluded from
    // auto-discovery in composer.json) to add OpenID Connect support:
    // discovery, JWKS, and id_token issuance on top of Passport.
    OpenIDConnect\Laravel\PassportServiceProvider::class,
];
