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

// Nightly 10 PM ET: message each business owner the clients we worked today
// (rounds started). Timezone handles EST/EDT automatically.
Schedule::command('messages:daily-digest')->timezone('America/New_York')->dailyAt('22:00');
