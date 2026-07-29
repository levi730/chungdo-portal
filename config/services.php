<?php

return [

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],
    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    // Zulip REST API (bot account with organization-owner rights). Used by the
    // portal -> Zulip sync (App\Services\Zulip). Leave unset to disable the sync.
    'zulip' => [
        'site' => env('ZULIP_SITE'),           // e.g. https://chat.chungdo.org
        'bot_email' => env('ZULIP_BOT_EMAIL'), // the sync bot's email
        'bot_api_key' => env('ZULIP_BOT_API_KEY'),
        // Name of the Zulip custom profile field that receives the belt rank.
        'belt_rank_field' => env('ZULIP_BELT_RANK_FIELD', 'Belt rank'),
    ],

];
