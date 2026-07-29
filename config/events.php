<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Refund request notifications
    |--------------------------------------------------------------------------
    |
    | Email addresses notified whenever a registrant submits an add-on refund
    | request (which needs admin approval). Set EVENT_REFUND_NOTIFY_EMAILS in
    | .env as a comma-separated list.
    |
    */

    'refund_notification_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('EVENT_REFUND_NOTIFY_EMAILS', 'admin@example.com'))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Typst binary
    |--------------------------------------------------------------------------
    |
    | Path to the `typst` executable used to render tournament registration
    | cards. Override with TYPST_BIN when it isn't on PATH.
    |
    */

    'typst_bin' => env('TYPST_BIN', 'typst'),

];
