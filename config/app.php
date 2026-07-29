<?php

use Illuminate\Support\Facades\Facade;

return [

    'aliases' => Facade::defaultAliases()->merge([
        'Excel' => Maatwebsite\Excel\Facades\Excel::class,
        'QRCode' => LaravelQRCode\Facades\QRCode::class,
        'SnappyImage' => Barryvdh\Snappy\Facades\SnappyImage::class,
    ])->toArray(),

    'stripe_key' => env('STRIPE_KEY'),
    'timezone' => env('APP_TIMEZONE', 'America/Chicago'),

];
