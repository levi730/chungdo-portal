<?php

use App\Http\Controllers\OpenIdUserInfoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Sendportal\Base\Facades\Sendportal;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// OpenID Connect userinfo endpoint. Named `openid.userinfo` so the package's
// discovery document advertises it. Authenticated with a Passport access token.
Route::get('/oauth/userinfo', OpenIdUserInfoController::class)
    ->middleware('auth:api')
    ->name('openid.userinfo');

Route::post('/stripe/webhook', [\App\Http\Controllers\StripeWebhookController::class, 'handle'])->name('stripe-webhook');

Sendportal::publicApiRoutes();
