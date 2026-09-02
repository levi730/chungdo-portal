<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Store menu
    |--------------------------------------------------------------------------
    |
    | Whether the Store item appears in the navigation. Turn it off to keep the
    | store out of sight while it is being set up, without removing any code.
    |
    | This hides the MENU ONLY. /store and /store/{slug} stay reachable by URL,
    | because they are public routes and someone may already have the link. Set
    | a product to Draft, or close its run, to actually take it off sale.
    |
    | The admin remains reachable at /admin/products for anyone holding
    | store.manage, so the store can still be worked on while hidden.
    |
    */

    'menu' => (bool) env('STORE_MENU', true),

];
