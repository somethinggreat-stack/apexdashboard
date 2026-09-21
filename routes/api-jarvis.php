<?php

use App\Http\Controllers\Api\JarvisController;
use App\Http\Controllers\Api\JarvisWriteController;
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
        // The screen Umair actually works from, mirrored exactly.
        Route::get('needs-attention', [JarvisController::class, 'needsAttention'])->name('needs-attention');

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

/*
| WRITES — slice 1: buckets and holds. Every one is a proxy to a single-purpose
| dashboard action that cannot touch a personal field.
|
| Off unless JARVIS_WRITES_ENABLED=true: the routes exist, but Mutation::run()
| refuses with 503, so the kill switch works without a deploy. Their own, much
| lower rate limit — a stuck loop shouldn't get 60 mutations a minute.
*/
Route::middleware(['jarvis.token', 'throttle:jarvis-write'])
    ->prefix('jarvis/clients')
    ->name('jarvis.write.')
    ->group(function () {
        Route::post('{id}/to-done', [JarvisWriteController::class, 'toDone'])->whereNumber('id')->name('to-done');
        Route::post('{id}/to-errors', [JarvisWriteController::class, 'toErrors'])->whereNumber('id')->name('to-errors');
        Route::post('{id}/to-round-error', [JarvisWriteController::class, 'toRoundError'])->whereNumber('id')->name('to-round-error');
        Route::post('{id}/resolve-round-error', [JarvisWriteController::class, 'resolveRoundError'])->whereNumber('id')->name('resolve-round-error');
        Route::post('{id}/to-new-clients', [JarvisWriteController::class, 'toNewClients'])->whereNumber('id')->name('to-new-clients');
        Route::post('{id}/hold', [JarvisWriteController::class, 'hold'])->whereNumber('id')->name('hold');
        Route::post('{id}/resume', [JarvisWriteController::class, 'resume'])->whereNumber('id')->name('resume');
        // Clinecea only — results_tracking. Refused plainly for any other owner.
        Route::post('{id}/request-approval', [JarvisWriteController::class, 'requestApproval'])->whereNumber('id')->name('request-approval');
        Route::post('{id}/approve-round', [JarvisWriteController::class, 'approveRound'])->whereNumber('id')->name('approve-round');
        Route::post('{id}/clear-approval', [JarvisWriteController::class, 'clearApproval'])->whereNumber('id')->name('clear-approval');
    });
