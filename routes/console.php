<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Pause clients with 14+ days of no activity — runs every morning at 7am server time.
Schedule::command('clients:auto-pause')->dailyAt('07:00');
