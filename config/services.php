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

        // Committee channels: private Zulip channels named after the committee
        // slug. The folder is the "COMMITTEES" channel folder they are filed
        // under; leave null to file them nowhere.
        'committee_folder_id' => env('ZULIP_COMMITTEE_FOLDER_ID'),
        // Create a channel for a committee that doesn't have one yet.
        'create_committee_channels' => (bool) env('ZULIP_CREATE_COMMITTEE_CHANNELS', true),
    ],

    // Google Static Maps, used only to snapshot an event's location once so the
    // dashboard can show a map picture without embedding a live Google map in
    // every card. The image is fetched server-side and cached on our disk, so
    // one event costs exactly one API call for as long as its map_url is
    // unchanged — see App\Services\EventMapSnapshot.
    //
    // Leave unset and the cards fall back to the venue address; nothing breaks.
    // The key is used server-side, so restrict it by IP (not HTTP referrer) and
    // set a quota cap in the Google Cloud console.
    'google_maps' => [
        'key' => env('GOOGLE_MAPS_KEY'),
        'zoom' => (int) env('GOOGLE_MAPS_ZOOM', 15),
        'size' => env('GOOGLE_MAPS_SIZE', '640x360'),
        'scale' => (int) env('GOOGLE_MAPS_SCALE', 2),
    ],

];
