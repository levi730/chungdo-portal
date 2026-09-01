<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Nightly portal -> Zulip sync (only when the Zulip API is configured). Runs
// synchronously in the scheduler, so it needs no queue worker.
if (config('services.zulip.site')) {
    Schedule::command('zulip:sync')->dailyAt('03:00')->withoutOverlapping();
}

// Store orders that reached Stripe but never completed. This is the only
// backstop that doesn't depend on a webhook being delivered — Stripe disables
// endpoints after sustained failures. See docs/payment-flow-pattern.md.
Schedule::command('store:reconcile-orders')->everyFifteenMinutes()->withoutOverlapping();
