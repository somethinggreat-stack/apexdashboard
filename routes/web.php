<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Client;
use App\Http\Controllers\IntakeController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\ServiceAreasController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Marketing Site
|--------------------------------------------------------------------------
*/

Route::get('/',         fn () => view('index'))->name('home');
Route::get('/about',    fn () => view('about'))->name('about');
Route::get('/contact',  fn () => view('contact'))->name('contact');
Route::get('/results',  fn () => view('results'))->name('results');
Route::get('/trial',    fn () => view('trial'))->name('trial');

Route::get('/service-areas',           [ServiceAreasController::class, 'index'])->name('service-areas.index');
Route::get('/service-areas/{slug}',    [ServiceAreasController::class, 'show'])->name('service-areas.show')
    ->where('slug', '[a-z\-]+');

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

/*
|--------------------------------------------------------------------------
| Public Lead Capture (rate-limited per IP)
|--------------------------------------------------------------------------
*/

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/leads',          [LeadController::class, 'storePopup'])->name('leads.store');
    Route::post('/contact-submit', [LeadController::class, 'storeContact'])->name('contact.submit');
});

/*
|--------------------------------------------------------------------------
| Public Client Intake (per-BO secret-token URLs)
|--------------------------------------------------------------------------
*/

Route::middleware('throttle:20,1')->group(function () {
    Route::get('/intake/{token}', [IntakeController::class, 'show'])->name('intake.show')->where('token', '[A-Za-z0-9]+');
    Route::middleware('throttle:5,1')->post('/intake/{token}', [IntakeController::class, 'store'])->name('intake.store')->where('token', '[A-Za-z0-9]+');
    Route::get('/intake/{token}/thank-you', [IntakeController::class, 'success'])->name('intake.success')->where('token', '[A-Za-z0-9]+');
});

// Server-to-server intake API (key-authenticated; CSRF-exempt via bootstrap/app.php)
Route::post('/api/intake', [IntakeController::class, 'apiStore'])->middleware('throttle:30,1')->name('api.intake');
// Same endpoint off the /api prefix. Some WAFs block multipart uploads under
// /api/* before PHP runs (HTTP 406); partners can post here instead. Identical
// controller, identical key auth — only the path differs.
Route::post('/partner-intake', [IntakeController::class, 'apiStore'])->middleware('throttle:30,1')->name('partner.intake');

/*
|--------------------------------------------------------------------------
| VA Admin (admin guard)
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->name('admin.')->group(function () {

    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [Admin\AuthController::class, 'showLoginForm'])->name('login');
        Route::post('login', [Admin\AuthController::class, 'login']);
    });

    Route::middleware(['auth:admin', \App\Http\Middleware\LogActivity::class])->group(function () {
        Route::match(['get', 'post'], 'logout', [Admin\AuthController::class, 'logout'])->name('logout');

        // Self-service profile — any authenticated admin (super, VA, leads agent)
        // edits their own name, email and password.
        Route::get('profile', [Admin\ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [Admin\ProfileController::class, 'update'])->name('profile.update');

        // ---- Super-admin only: website leads + user management ----
        Route::middleware('admin.super')->group(function () {
            // Main super-admin dashboard — cross-business-owner overview
            Route::get('dashboard', [Admin\DashboardController::class, 'index'])->name('dashboard');
            // Universal: clear incomplete logs across ALL owners (never the closeout steps).
            Route::post('clear-incomplete-all', [Admin\EndUserController::class, 'clearIncompleteAll'])
                ->name('end-users.clear-incomplete-all');

            // Leads (from public website forms)
            Route::get('leads', [LeadController::class, 'dashboard'])->name('leads.index');
            Route::delete('leads/{lead}', [LeadController::class, 'destroy'])->name('leads.destroy');

            // User management + activity log
            Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
            Route::post('users', [Admin\UserController::class, 'store'])->name('users.store');
            Route::put('users/{id}/password', [Admin\UserController::class, 'resetPassword'])->name('users.password');
            Route::delete('users/{id}', [Admin\UserController::class, 'destroy'])->name('users.destroy');

            // Referral commissions — each referrer earns per real client payment of their referred BOs
            Route::get('commissions', [Admin\CommissionController::class, 'index'])->name('commissions.index');
            Route::get('commissions/{id}', [Admin\CommissionController::class, 'show'])->whereNumber('id')->name('commissions.show');
            Route::post('commissions/{id}/payout', [Admin\CommissionController::class, 'storePayout'])->whereNumber('id')->name('commissions.payout.store');
            Route::delete('commissions/payout/{id}', [Admin\CommissionController::class, 'destroyPayout'])->whereNumber('id')->name('commissions.payout.destroy');

            // Recycle Bin — deleted business owners + clients, recoverable for 10 days. Super admin only.
            Route::middleware('admin.super')->group(function () {
                Route::get('recycle-bin', [Admin\RecycleBinController::class, 'index'])->name('recycle-bin.index');
                Route::delete('recycle-bin/empty', [Admin\RecycleBinController::class, 'emptyAll'])->name('recycle-bin.empty');
                Route::post('recycle-bin/business-owner/{id}/restore', [Admin\RecycleBinController::class, 'restoreClient'])->whereNumber('id')->name('recycle-bin.client.restore');
                Route::post('recycle-bin/client/{id}/restore', [Admin\RecycleBinController::class, 'restoreEndUser'])->whereNumber('id')->name('recycle-bin.end-user.restore');
                Route::delete('recycle-bin/business-owner/{id}', [Admin\RecycleBinController::class, 'forceClient'])->whereNumber('id')->name('recycle-bin.client.force');
                Route::delete('recycle-bin/client/{id}', [Admin\RecycleBinController::class, 'forceEndUser'])->whereNumber('id')->name('recycle-bin.end-user.force');
            });

            // Extra projects — funnels, customer support, ads
            Route::get('extra/{type}', [Admin\ExtraProjectController::class, 'index'])
                ->whereIn('type', ['funnel', 'support', 'ads'])->name('extra.index');
            Route::post('extra/{type}', [Admin\ExtraProjectController::class, 'store'])
                ->whereIn('type', ['funnel', 'support', 'ads'])->name('extra.store');
            Route::put('extra/{id}', [Admin\ExtraProjectController::class, 'update'])
                ->whereNumber('id')->name('extra.update');
            Route::delete('extra/{id}', [Admin\ExtraProjectController::class, 'destroy'])
                ->whereNumber('id')->name('extra.destroy');
        });

        // ---- Sales leads pipeline: super admin OR a leads agent ----
        Route::middleware('admin.leads')->group(function () {
            // Prospects — manual sales pipeline of prospective business owners
            Route::get('prospects/lost', [Admin\ProspectController::class, 'lost'])->name('prospects.lost');
            Route::get('prospects/interested', [Admin\ProspectController::class, 'interested'])->name('prospects.interested');
            Route::resource('prospects', Admin\ProspectController::class)
                ->only(['index', 'store', 'update', 'destroy']);
            Route::post('prospects/{prospect}/lost', [Admin\ProspectController::class, 'markLost'])->name('prospects.mark-lost');
            Route::post('prospects/{prospect}/interested', [Admin\ProspectController::class, 'markInterested'])->name('prospects.mark-interested');
            Route::post('prospects/{prospect}/reactivate', [Admin\ProspectController::class, 'reactivate'])->name('prospects.reactivate');

            // Prospect leads — simple list of leads (name, verified WhatsApp, socials)
            Route::resource('prospect-leads', Admin\ProspectLeadController::class)
                ->only(['index', 'store', 'update', 'destroy']);
            Route::post('prospect-leads/{prospect_lead}/move', [Admin\ProspectLeadController::class, 'move'])
                ->name('prospect-leads.move');
            Route::post('prospect-leads/{prospect_lead}/toggle-hot', [Admin\ProspectLeadController::class, 'toggleHot'])
                ->name('prospect-leads.toggle-hot');
        });

        // ---- Business-owner / client workflow: super admin OR a VA (leads agents blocked) ----
        Route::middleware('admin.clients')->group(function () {

        // Business-owner picker — accessible without a selection
        Route::get('select-business-owner', [Admin\ClientSelectorController::class, 'index'])
            ->name('client-selector.index');
        // Universal client search — its own sidebar page (VAs), plus the JSON
        // endpoint the page calls to render results live.
        Route::get('universal-search', [Admin\ClientSelectorController::class, 'universalSearch'])
            ->name('universal-search');
        // Daily Task — last-12h work per business owner (super admin + VAs)
        Route::get('daily-task', [Admin\DailyTaskController::class, 'index'])->name('daily-task');
        // CFPB Logins — last-12h CFPB logins entered per business owner
        Route::get('cfpb-logins', [Admin\CfpbLoginController::class, 'index'])->name('cfpb-logins');
        Route::get('select-business-owner/search', [Admin\ClientSelectorController::class, 'search'])
            ->name('client-selector.search');
        Route::post('select-business-owner/{id}', [Admin\ClientSelectorController::class, 'select'])
            ->name('client-selector.select');
        Route::post('switch-business-owner', [Admin\ClientSelectorController::class, 'clear'])
            ->name('client-selector.clear');

        // Business-owner CRUD — super admin only (add/remove business owners)
        Route::resource('clients', Admin\ClientController::class)->except(['show'])->middleware('admin.super');

        // Everything below is scoped to the currently selected business owner
        Route::middleware('client.selected')->group(function () {
            Route::get('today-queue', [Admin\AllClientsController::class, 'todayQueue'])->name('today-queue');

            // Payments — super admin only
            Route::middleware('admin.super')->group(function () {
                Route::get('payments', [Admin\PaymentController::class, 'index'])->name('payments.index');
                Route::put('payments/config', [Admin\PaymentController::class, 'updateConfig'])->name('payments.config');
                // Per-round payments
                Route::post('payments', [Admin\PaymentController::class, 'storePayment'])->name('payments.store');
                Route::post('payments/bulk', [Admin\PaymentController::class, 'bulkStorePayment'])->name('payments.bulk');
                Route::post('payments/pay-all-unpaid', [Admin\PaymentController::class, 'payAllUnpaid'])->name('payments.pay-all');
                Route::put('payments/client-rate/{id}', [Admin\PaymentController::class, 'updateEndUserFee'])->name('payments.client-rate');
                Route::put('payments/round-rate/{id}', [Admin\PaymentController::class, 'updateRoundFee'])->name('payments.round-rate');
                Route::post('payments/invoice', [Admin\PaymentController::class, 'generateInvoice'])->name('payments.invoice.generate');
                Route::get('payments/invoice/{id}', [Admin\PaymentController::class, 'showInvoice'])->name('payments.invoice.show');
                Route::put('payments/{id}', [Admin\PaymentController::class, 'updatePayment'])->name('payments.update');
                Route::delete('payments/{id}', [Admin\PaymentController::class, 'destroyPayment'])->name('payments.destroy');
                // Hourly — manual hours per period
                Route::post('payments/period-hours', [Admin\PaymentController::class, 'storePeriodHours'])->name('payments.period.hours');
                // Hourly time entries (legacy per-day logging)
                Route::post('payments/time', [Admin\PaymentController::class, 'storeTime'])->name('payments.time.store');
                Route::delete('payments/time/{id}', [Admin\PaymentController::class, 'destroyTime'])->name('payments.time.destroy');
                // Hourly payouts
                Route::post('payments/payout', [Admin\PaymentController::class, 'storePayout'])->name('payments.payout.store');
                Route::delete('payments/payout/{id}', [Admin\PaymentController::class, 'destroyPayout'])->name('payments.payout.destroy');
            });

            Route::get('messages', [Admin\MessageController::class, 'index'])->name('messages.index');
            Route::post('messages', [Admin\MessageController::class, 'store'])->name('messages.store');
            Route::post('messages/{id}/pin', [Admin\MessageController::class, 'togglePin'])->name('messages.pin');
            Route::post('messages/{id}/star', [Admin\MessageController::class, 'toggleStar'])->name('messages.star');
            Route::post('messages/{id}/note', [Admin\MessageController::class, 'saveNote'])->name('messages.note');

            // Tasks View — the selected BO's own 30-day work log (rounds started
            // per day), the internal twin of the owner's Tasks View but WITH the
            // VA name on each entry (super-admin/VA side only).
            Route::get('tasks', [Admin\TaskController::class, 'index'])->name('tasks');

            // New Clients — intake-form submissions pending review for this BO
            Route::get('new-clients', [Admin\EndUserController::class, 'newClients'])->name('new-clients');
            Route::post('new-clients/{id}/approve', [Admin\EndUserController::class, 'approveIntake'])->name('new-clients.approve');
            // Intake link + API key management — super admin only (VAs must not see/change it)
            Route::post('new-clients/regenerate-link', [Admin\EndUserController::class, 'regenerateIntake'])->middleware('admin.super')->name('new-clients.regenerate');
            Route::post('new-clients/api-key', [Admin\EndUserController::class, 'regenerateApiKey'])->middleware('admin.super')->name('new-clients.api-key');
            Route::get('errors', [Admin\EndUserController::class, 'errors'])->name('errors');
            Route::get('errors-resolved-new-clients', [Admin\EndUserController::class, 'errorsResolvedNewClients'])->name('errors-resolved-new');
            // The main Clients list (1st round done; remaining rounds worked here)
            Route::get('client-list', [Admin\EndUserController::class, 'activeClients'])->name('client-list');
            // Bulk credential exports (CSV) — super admin only, never VAs
            Route::get('client-list/cfpb-export', [Admin\EndUserController::class, 'exportCfpb'])
                ->middleware('admin.super')->name('client-list.cfpb-export');
            Route::get('client-list/credit-monitoring-export', [Admin\EndUserController::class, 'exportCreditMonitoring'])
                ->middleware('admin.super')->name('client-list.credit-monitoring-export');
            // Super-admin only: log the missing weekly steps for every flagged
            // client — but NEVER the closeout steps (pull report / record deletions).
            Route::post('clear-incomplete', [Admin\EndUserController::class, 'clearIncomplete'])
                ->middleware('admin.super')->name('end-users.clear-incomplete');
            Route::post('end-users/{id}/to-done', [Admin\EndUserController::class, 'moveToDone'])->name('end-users.to-done');
            Route::post('end-users/{id}/to-errors', [Admin\EndUserController::class, 'moveToErrors'])->name('end-users.to-errors');
            Route::post('end-users/{id}/to-new-clients', [Admin\EndUserController::class, 'moveToNewClients'])->name('end-users.to-new-clients');
            // Hold / Pause — park a client out of the normal buckets, or resume them
            Route::get('hold', [Admin\EndUserController::class, 'holdList'])->name('hold');
            Route::post('end-users/{id}/hold', [Admin\EndUserController::class, 'hold'])->name('end-users.hold');
            Route::post('end-users/{id}/resume', [Admin\EndUserController::class, 'resume'])->name('end-users.resume');
            // Round Errors — 2nd/3rd round import problems, moved from the Clients list
            Route::get('round-errors', [Admin\EndUserController::class, 'roundErrors'])->name('round-errors');
            Route::get('errors-resolved-by-client', [Admin\EndUserController::class, 'errorsResolvedByClient'])->name('errors-resolved');
            Route::post('end-users/{id}/to-round-error', [Admin\EndUserController::class, 'moveToRoundError'])->name('end-users.to-round-error');
            Route::post('end-users/{id}/resolve-round-error', [Admin\EndUserController::class, 'resolveRoundError'])->name('end-users.resolve-round-error');

            Route::get('end-users', [Admin\EndUserController::class, 'index'])->name('end-users.index');
            // Live duplicate check (email / SSN) for the Add Client form (distinct
            // path so it never collides with end-users/{id}).
            Route::get('end-users-dup-check', [Admin\EndUserController::class, 'checkDuplicate'])->name('end-users.dup-check');
            // Add Client — full page (must be declared before end-users/{id}).
            Route::get('end-users/create', [Admin\EndUserController::class, 'create'])->name('end-users.create');
            Route::post('end-users', [Admin\EndUserController::class, 'store'])->name('end-users.store');
            Route::get('end-users/{id}', [Admin\EndUserController::class, 'show'])->whereNumber('id')->name('end-users.show');
            Route::get('end-users/{id}/status-report', [Admin\EndUserController::class, 'statusReport'])->name('end-users.status-report');
            Route::put('end-users/{id}', [Admin\EndUserController::class, 'update'])->name('end-users.update');
            Route::delete('end-users/{id}', [Admin\EndUserController::class, 'destroy'])->name('end-users.destroy');

            // Negative items (results tracking) + round approval — enabled owners only
            Route::post('negative-items', [Admin\NegativeItemController::class, 'store'])->name('negative-items.store');
            Route::put('negative-items/{id}', [Admin\NegativeItemController::class, 'update'])->whereNumber('id')->name('negative-items.update');
            Route::post('negative-items/{id}/resolve', [Admin\NegativeItemController::class, 'resolve'])->whereNumber('id')->name('negative-items.resolve');
            Route::post('negative-items/{id}/reopen', [Admin\NegativeItemController::class, 'reopen'])->whereNumber('id')->name('negative-items.reopen');
            Route::delete('negative-items/{id}', [Admin\NegativeItemController::class, 'destroy'])->whereNumber('id')->name('negative-items.destroy');
            Route::post('end-users/{id}/request-approval', [Admin\EndUserController::class, 'requestRoundApproval'])->whereNumber('id')->name('end-users.request-approval');
            Route::post('end-users/{id}/approve-round', [Admin\EndUserController::class, 'approveRound'])->whereNumber('id')->name('end-users.approve-round');
            Route::post('end-users/{id}/clear-approval', [Admin\EndUserController::class, 'clearRoundApproval'])->whereNumber('id')->name('end-users.clear-approval');

            // Results reports (results-tracking owner — Clinecea): EOD + monthly.
            // Inside client.selected so the selected owner stays active in the nav.
            Route::get('results/eod', [Admin\ResultsController::class, 'eod'])->name('results.eod');
            Route::get('results/monthly', [Admin\ResultsController::class, 'monthly'])->name('results.monthly');

            Route::post('process-steps', [Admin\ProcessStepController::class, 'store'])->name('process-steps.store');
            Route::put('process-steps/{id}', [Admin\ProcessStepController::class, 'update'])->name('process-steps.update');
            Route::delete('process-steps/{id}', [Admin\ProcessStepController::class, 'destroy'])->name('process-steps.destroy');

            Route::delete('end-users/{endUser}/identity/{type}', [Admin\EndUserController::class, 'destroyIdentity'])
                ->whereIn('type', ['photo_id', 'proof_of_address', 'ssn_picture', 'collage'])
                ->name('end-users.identity.destroy');

            Route::post('documents', [Admin\DocumentController::class, 'store'])->name('documents.store');
            Route::post('documents/bulk', [Admin\DocumentController::class, 'bulkStore'])->name('documents.bulk');
            Route::delete('documents/{id}', [Admin\DocumentController::class, 'destroy'])->name('documents.destroy');

            Route::post('scores', [Admin\ScoreController::class, 'store'])->name('scores.store');

            Route::post('notes', [Admin\NoteController::class, 'store'])->name('notes.store');
            Route::delete('notes/{id}', [Admin\NoteController::class, 'destroy'])->name('notes.destroy');

            Route::get('files/documents/{id}', [Admin\FileController::class, 'document'])->name('files.document');
            Route::get('files/identity/{endUser}/{type}', [Admin\FileController::class, 'identity'])
                ->whereIn('type', ['photo_id', 'proof_of_address', 'ssn_picture', 'collage'])
                ->name('files.identity');
        });
        }); // end admin.clients group
    });
});

/*
|--------------------------------------------------------------------------
| Business Owner (client guard)
|--------------------------------------------------------------------------
*/

Route::prefix('business-owner')->name('client.')->group(function () {

    Route::middleware('guest:client')->group(function () {
        Route::get('login', [Client\AuthController::class, 'showLoginForm'])->name('login');
        Route::post('login', [Client\AuthController::class, 'login']);
    });

    Route::middleware(['auth:client', 'client.active'])->group(function () {
        Route::match(['get', 'post'], 'logout', [Client\AuthController::class, 'logout'])->name('logout');
        Route::get('dashboard', [Client\DashboardController::class, 'index'])->name('dashboard');

        // New Leads — the business owner's own lead tracker (not visible to admins/VAs).
        Route::get('leads', [Client\LeadController::class, 'index'])->name('leads.index');
        Route::post('leads', [Client\LeadController::class, 'store'])->name('leads.store');
        Route::get('leads/{id}', [Client\LeadController::class, 'show'])->name('leads.show')->whereNumber('id');
        Route::put('leads/{id}', [Client\LeadController::class, 'update'])->name('leads.update')->whereNumber('id');
        Route::delete('leads/{id}', [Client\LeadController::class, 'destroy'])->name('leads.destroy')->whereNumber('id');

        Route::get('messages', [Client\MessageController::class, 'index'])->name('messages.index');
        Route::post('messages', [Client\MessageController::class, 'store'])->name('messages.store');

        Route::get('billing', [Client\BillingController::class, 'index'])->name('billing.index');
        Route::get('billing/invoice/{id}', [Client\BillingController::class, 'showInvoice'])->name('billing.invoice.show');

        // Referral commission — visible only to the owner flagged is_commission_referrer (Chantal).
        Route::get('commissions', [Client\CommissionController::class, 'index'])->name('commissions.index');

        // Tasks View — the owner's own 30-day work log (rounds started per day),
        // mirroring the internal Daily Task signal. Read-only, no VA names.
        Route::get('tasks', [Client\TaskController::class, 'index'])->name('tasks');

        // New Clients — intake submissions pending VA review (BOs can view only)
        Route::get('new-clients', [Client\EndUserController::class, 'newClients'])->name('new-clients');

        // Errors — clients the VA pulled out to fix (BOs can view only)
        Route::get('errors', [Client\EndUserController::class, 'errors'])->name('errors');
        Route::put('errors/{id}/resolve', [Client\EndUserController::class, 'resolveNewError'])->name('errors.resolve')->whereNumber('id');
        Route::get('errors-resolved-new', [Client\EndUserController::class, 'errorsResolvedNew'])->name('errors-resolved-new');

        // In Progress — verified clients whose 1st round isn't done yet
        Route::get('end-users', [Client\EndUserController::class, 'index'])->name('end-users.index');

        // Done Clients — the main list; all rounds after the 1st are worked here
        Route::get('client-list', [Client\EndUserController::class, 'doneClients'])->name('client-list');

        // Hold / Pause — clients the team parked (BOs can view only)
        Route::get('hold', [Client\EndUserController::class, 'holdList'])->name('hold');

        // Round Errors — 2nd/3rd round problems the team is fixing (BOs can view only)
        Route::get('round-errors', [Client\EndUserController::class, 'roundErrors'])->name('round-errors');
        Route::put('round-errors/{id}/resolve', [Client\EndUserController::class, 'resolveRoundError'])->name('round-errors.resolve')->whereNumber('id');
        Route::get('errors-resolved', [Client\EndUserController::class, 'errorsResolved'])->name('errors-resolved');

        // Custom lists (Tycon Stan only) — an owner-side grouping of their clients.
        Route::get('lists/{list}', [Client\EndUserController::class, 'customList'])
            ->whereIn('list', array_keys(\App\Models\EndUser::CUSTOM_LISTS))->name('lists.show');
        Route::post('end-users/{id}/list', [Client\EndUserController::class, 'moveToList'])->name('end-users.list');

        Route::get('end-users/create', [Client\EndUserController::class, 'create'])->name('end-users.create');
        // Live duplicate check (email / SSN) for the BO's add-client form.
        Route::get('end-users-dup-check', [Client\EndUserController::class, 'checkDuplicate'])->name('end-users.dup-check');
        Route::post('end-users', [Client\EndUserController::class, 'store'])->name('end-users.store');
        Route::get('end-users/{id}', [Client\EndUserController::class, 'show'])->name('end-users.show');
        Route::get('end-users/{id}/status-report', [Client\EndUserController::class, 'statusReport'])->name('end-users.status-report');

        Route::get('files/documents/{id}', [Client\FileController::class, 'document'])->name('files.document');
        Route::get('files/identity/{endUser}/{type}', [Client\FileController::class, 'identity'])
            ->whereIn('type', ['photo_id', 'proof_of_address', 'ssn_picture', 'collage'])
            ->name('files.identity');
    });
});
