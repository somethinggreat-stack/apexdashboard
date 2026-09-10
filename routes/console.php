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

// The GoHighLevel pull is deliberately NOT scheduled. A VA runs it from the
// Sync now button on the GHL Clients screen when they are ready to work the
// queue, so clients arrive when somebody is there to review them rather than
// piling up unattended. The ghl:sync-intake command still exists and can be
// run by hand; end_users.ghl_submission_id keeps every run idempotent.
