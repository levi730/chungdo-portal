<?php

return [

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],
    // Stripe. The portal transacts on more than one Stripe account: each event
    // names the account its registration money lands in (events.stripe_account),
    // defaulting to the association. The top-level secret/webhook_secret below
    // are the association's and remain because Laravel Cashier reads
    // STRIPE_SECRET / STRIPE_KEY from the environment directly.
    //
    // Add an account by adding a key here; the event form picks up the new
    // option automatically. An account whose secret is unset is treated as not
    // configured and is hidden from the form.
    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),

        'default_account' => env('STRIPE_DEFAULT_ACCOUNT', 'association'),

        'accounts' => [
            'association' => [
                'label' => 'Association Account',
                'key' => env('STRIPE_KEY'),
                'secret' => env('STRIPE_SECRET'),
                'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            ],
            'main_school' => [
                'label' => 'Main School Account',
                'key' => env('STRIPE_MAIN_SCHOOL_KEY'),
                'secret' => env('STRIPE_MAIN_SCHOOL_SECRET'),
                'webhook_secret' => env('STRIPE_MAIN_SCHOOL_WEBHOOK_SECRET'),
            ],
        ],
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
