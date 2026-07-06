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

        // ---- Super-admin only: website leads + user management ----
        Route::middleware('admin.super')->group(function () {
            // Leads (from public website forms)
            Route::get('leads', [LeadController::class, 'dashboard'])->name('leads.index');
            Route::delete('leads/{lead}', [LeadController::class, 'destroy'])->name('leads.destroy');

            // User management + activity log
            Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
            Route::post('users', [Admin\UserController::class, 'store'])->name('users.store');
            Route::put('users/{id}/password', [Admin\UserController::class, 'resetPassword'])->name('users.password');
            Route::delete('users/{id}', [Admin\UserController::class, 'destroy'])->name('users.destroy');
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
        Route::post('select-business-owner/{id}', [Admin\ClientSelectorController::class, 'select'])
            ->name('client-selector.select');
        Route::post('switch-business-owner', [Admin\ClientSelectorController::class, 'clear'])
            ->name('client-selector.clear');

        // Business-owner CRUD — super admin only (add/remove business owners)
        Route::resource('clients', Admin\ClientController::class)->except(['show'])->middleware('admin.super');

        // Everything below is scoped to the currently selected business owner
        Route::middleware('client.selected')->group(function () {
            // Dashboard removed — anyone landing here goes straight to the clients list.
            Route::get('dashboard', fn () => redirect()->route('admin.end-users.index'))->name('dashboard');
            Route::get('today-queue', [Admin\AllClientsController::class, 'todayQueue'])->name('today-queue');

            // Payments — super admin only
            Route::middleware('admin.super')->group(function () {
                Route::get('payments', [Admin\PaymentController::class, 'index'])->name('payments.index');
                Route::put('payments/config', [Admin\PaymentController::class, 'updateConfig'])->name('payments.config');
                // Per-round payments
                Route::post('payments', [Admin\PaymentController::class, 'storePayment'])->name('payments.store');
                Route::post('payments/bulk', [Admin\PaymentController::class, 'bulkStorePayment'])->name('payments.bulk');
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

            // New Clients — intake-form submissions pending review for this BO
            Route::get('new-clients', [Admin\EndUserController::class, 'newClients'])->name('new-clients');
            Route::post('new-clients/{id}/approve', [Admin\EndUserController::class, 'approveIntake'])->name('new-clients.approve');
            Route::post('new-clients/regenerate-link', [Admin\EndUserController::class, 'regenerateIntake'])->name('new-clients.regenerate');
            Route::get('errors', [Admin\EndUserController::class, 'errors'])->name('errors');
            Route::post('end-users/{id}/to-errors', [Admin\EndUserController::class, 'moveToErrors'])->name('end-users.to-errors');

            Route::get('end-users', [Admin\EndUserController::class, 'index'])->name('end-users.index');
            Route::post('end-users', [Admin\EndUserController::class, 'store'])->name('end-users.store');
            Route::get('end-users/{id}', [Admin\EndUserController::class, 'show'])->name('end-users.show');
            Route::get('end-users/{id}/status-report', [Admin\EndUserController::class, 'statusReport'])->name('end-users.status-report');
            Route::put('end-users/{id}', [Admin\EndUserController::class, 'update'])->name('end-users.update');
            Route::delete('end-users/{id}', [Admin\EndUserController::class, 'destroy'])->name('end-users.destroy');

            Route::post('process-steps', [Admin\ProcessStepController::class, 'store'])->name('process-steps.store');
            Route::put('process-steps/{id}', [Admin\ProcessStepController::class, 'update'])->name('process-steps.update');
            Route::delete('process-steps/{id}', [Admin\ProcessStepController::class, 'destroy'])->name('process-steps.destroy');

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

    Route::middleware('auth:client')->group(function () {
        Route::match(['get', 'post'], 'logout', [Client\AuthController::class, 'logout'])->name('logout');
        Route::get('dashboard', [Client\DashboardController::class, 'index'])->name('dashboard');

        Route::get('messages', [Client\MessageController::class, 'index'])->name('messages.index');
        Route::post('messages', [Client\MessageController::class, 'store'])->name('messages.store');

        Route::get('billing', [Client\BillingController::class, 'index'])->name('billing.index');
        Route::get('billing/invoice/{id}', [Client\BillingController::class, 'showInvoice'])->name('billing.invoice.show');

        // New Clients — intake submissions pending VA review (BOs can view only)
        Route::get('new-clients', [Client\EndUserController::class, 'newClients'])->name('new-clients');

        Route::get('end-users', [Client\EndUserController::class, 'index'])->name('end-users.index');
        Route::get('end-users/create', [Client\EndUserController::class, 'create'])->name('end-users.create');
        Route::post('end-users', [Client\EndUserController::class, 'store'])->name('end-users.store');
        Route::get('end-users/{id}', [Client\EndUserController::class, 'show'])->name('end-users.show');
        Route::get('end-users/{id}/status-report', [Client\EndUserController::class, 'statusReport'])->name('end-users.status-report');

        Route::get('files/documents/{id}', [Client\FileController::class, 'document'])->name('files.document');
        Route::get('files/identity/{endUser}/{type}', [Client\FileController::class, 'identity'])
            ->whereIn('type', ['photo_id', 'proof_of_address', 'ssn_picture', 'collage'])
            ->name('files.identity');
    });
});
