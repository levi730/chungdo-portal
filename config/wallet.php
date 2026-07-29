<?php

/*
|--------------------------------------------------------------------------
| Mobile Wallet Passes
|--------------------------------------------------------------------------
|
| Configuration for the event check-in passes added to Apple Wallet (.pkpass)
| and Google Wallet. Both carry the same QR payload the check-in scanner reads:
| {"registrants":[<event_registrations.id>, ...]}.
|
| A platform's "Add to Wallet" button only appears when that platform is
| fully configured (see AppleWalletPass::isConfigured() / GoogleWalletPass).
|
*/

return [

    'apple' => [
        // Pass Type ID certificate (.p12) exported from the Apple Developer
        // portal, containing the cert + private key. Regenerate when it expires.
        // Resolved base64 first (best for containerized deploys — keeps the key
        // out of git; macOS: base64 -i cert.p12 | tr -d '\n'), then a file path.
        'certificate_base64' => env('WALLET_APPLE_CERT_BASE64'),
        'certificate_path' => env('WALLET_APPLE_CERT_PATH', base_path('resources/certs/ChungDoEventPassCert.p12')),
        'certificate_password' => env('WALLET_APPLE_CERT_PASSWORD'),

        // Apple WWDR intermediate certificate (PEM). The bundled one is G4,
        // valid through 2030.
        'wwdr_path' => env('WALLET_APPLE_WWDR_PATH', base_path('resources/certs/AppleWWDRCA.pem')),

        'pass_type_identifier' => env('WALLET_APPLE_PASS_TYPE_ID', 'pass.org.chungdo.eventpass'),
        'team_identifier' => env('WALLET_APPLE_TEAM_ID', 'BYZXLD7X8S'),

        'organization_name' => env('WALLET_ORG_NAME', 'Chung Do Association'),
        'foreground_color' => 'rgb(255, 255, 255)',
        'background_color' => 'rgb(197, 31, 31)',

        // Pass image assets (icon/logo/thumbnail).
        'assets_path' => base_path('resources/certs'),
    ],

    'google' => [
        // Wallet Issuer ID from the Google Pay & Wallet Console.
        'issuer_id' => env('WALLET_GOOGLE_ISSUER_ID'),

        // The Google Cloud service-account key (with Wallet Object Issuer access)
        // used to RS256-sign the "Save to Google Wallet" JWT. Resolved in this
        // order — the base64 form is best for containerized deploys (Coolify,
        // etc.) where there's no persistent file to mount:
        //   1. WALLET_GOOGLE_SERVICE_ACCOUNT_BASE64  base64 of the JSON key
        //   2. WALLET_GOOGLE_SERVICE_ACCOUNT_JSON     raw JSON string
        //   3. WALLET_GOOGLE_SERVICE_ACCOUNT          path to the JSON key file
        'service_account_base64' => env('WALLET_GOOGLE_SERVICE_ACCOUNT_BASE64'),
        'service_account_json' => env('WALLET_GOOGLE_SERVICE_ACCOUNT_JSON'),
        'service_account_path' => env('WALLET_GOOGLE_SERVICE_ACCOUNT'),

        // Suffix for the EventTicket class id (issuerId.classSuffix). One class
        // per event is derived from this (see GoogleWalletPass).
        'class_suffix' => env('WALLET_GOOGLE_CLASS_SUFFIX', 'chungdo_event'),

        'organization_name' => env('WALLET_ORG_NAME', 'Chung Do Association'),
    ],

];
