<?php

use App\Http\Controllers\Api\JarvisController;
use Illuminate\Support\Facades\Route;

/*
| JARVIS — a read-only API for the owner's own assistant.
|
| NOTHING is registered unless JARVIS_API_TOKEN is configured. An un-configured
| deploy therefore has no JARVIS surface at all: not a route that 401s, not a
| route that exists. `php artisan route:list` shows nothing to probe.
|
| Every route in here is a GET, by rule and by test (JarvisApiTest asserts no
| registered api/jarvis route accepts anything but GET|HEAD).
*/

if (blank(config('jarvis.token'))) {
    return;
}

Route::middleware(['jarvis.token', 'throttle:jarvis'])
    ->prefix('jarvis')
    ->name('jarvis.')
    ->group(function () {
        // --- the morning briefing: these three alone answer "what do I need to know" ---
        Route::get('health', [JarvisController::class, 'health'])->name('health');
        Route::get('summary', [JarvisController::class, 'summary'])->name('summary');
        Route::get('alerts', [JarvisController::class, 'alerts'])->name('alerts');

        // --- the work ---
        Route::get('end-users', [JarvisController::class, 'endUsers'])->name('end-users');
        Route::get('end-users/{id}', [JarvisController::class, 'endUser'])
            ->whereNumber('id')->name('end-user');
        Route::get('rounds/due', [JarvisController::class, 'roundsDue'])->name('rounds.due');

        // --- money, people, pipeline ---
        Route::get('invoices/outstanding', [JarvisController::class, 'outstandingInvoices'])->name('invoices.outstanding');
        Route::get('business-owners', [JarvisController::class, 'businessOwners'])->name('business-owners');
        Route::get('team/workload', [JarvisController::class, 'teamWorkload'])->name('team.workload');
        Route::get('leads/recent', [JarvisController::class, 'recentLeads'])->name('leads.recent');
        Route::get('activity', [JarvisController::class, 'activity'])->name('activity');
    });
