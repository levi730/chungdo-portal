<?php

use App\EventAddons\DonationAddon;
use App\EventAddons\EventParticipationAddon;
use App\EventAddons\GuestsAddon;
use App\EventAddons\MealTicketAddon;
use App\EventAddons\PotluckAddon;
use App\EventAddons\RegistrationFeeAddon;
use App\EventAddons\TshirtAddon;
use App\EventAddons\VolunteerAddon;

return [

    /*
    |--------------------------------------------------------------------------
    | Event Add-on Handlers
    |--------------------------------------------------------------------------
    |
    | Each event add-on (registration fee, donation, potluck, meal ticket,
    | t-shirt, ...) is implemented as a handler class listed here. The order
    | of this list is the default display order in the admin UI and on the
    | registration form. Add a new add-on by writing a handler that implements
    | App\EventAddons\AddonHandler and adding it below.
    |
    */

    'handlers' => [
        RegistrationFeeAddon::class,
        EventParticipationAddon::class,
        MealTicketAddon::class,
        TshirtAddon::class,
        GuestsAddon::class,
        VolunteerAddon::class,
        PotluckAddon::class,
        DonationAddon::class,
    ],

];
