<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Keep the activity log to a rolling 7-day window (clears entries older than 7 days).
Schedule::command('activity:prune')->dailyAt('03:00');

// Empty the Recycle Bin of anything past its 10-day retention (rows + files).
Schedule::command('recyclebin:purge')->dailyAt('03:15');

// Pull Benny's GoHighLevel onboarding submissions into New Clients. A pull, not
// a webhook, so a failed run simply retries five minutes later instead of
// dropping the client. No-ops when the GHL_* config is absent.
Schedule::command('ghl:sync-intake')->everyFiveMinutes()->withoutOverlapping();
