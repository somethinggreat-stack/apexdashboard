<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Client;
use App\Http\Controllers\LeadController;
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
| VA Admin (admin guard)
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->name('admin.')->group(function () {

    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [Admin\AuthController::class, 'showLoginForm'])->name('login');
        Route::post('login', [Admin\AuthController::class, 'login']);
    });

    Route::middleware('auth:admin')->group(function () {
        Route::match(['get', 'post'], 'logout', [Admin\AuthController::class, 'logout'])->name('logout');

        // Leads (from public website forms) — accessible without a business owner being selected
        Route::get('leads', [LeadController::class, 'dashboard'])->name('leads.index');

        // Business-owner picker — accessible without a selection
        Route::get('select-business-owner', [Admin\ClientSelectorController::class, 'index'])
            ->name('client-selector.index');
        Route::post('select-business-owner/{id}', [Admin\ClientSelectorController::class, 'select'])
            ->name('client-selector.select');
        Route::post('switch-business-owner', [Admin\ClientSelectorController::class, 'clear'])
            ->name('client-selector.clear');

        // Business-owner CRUD — accessible without a selection so VAs can manage BOs
        Route::resource('clients', Admin\ClientController::class);

        // Everything below is scoped to the currently selected business owner
        Route::middleware('client.selected')->group(function () {
            Route::get('dashboard', [Admin\DashboardController::class, 'index'])->name('dashboard');

            Route::get('messages', [Admin\MessageController::class, 'index'])->name('messages.index');
            Route::post('messages', [Admin\MessageController::class, 'store'])->name('messages.store');

            Route::get('end-users', [Admin\EndUserController::class, 'index'])->name('end-users.index');
            Route::post('end-users', [Admin\EndUserController::class, 'store'])->name('end-users.store');
            Route::get('end-users/{id}', [Admin\EndUserController::class, 'show'])->name('end-users.show');
            Route::put('end-users/{id}', [Admin\EndUserController::class, 'update'])->name('end-users.update');

            Route::post('process-steps', [Admin\ProcessStepController::class, 'store'])->name('process-steps.store');
            Route::put('process-steps/{id}', [Admin\ProcessStepController::class, 'update'])->name('process-steps.update');
            Route::delete('process-steps/{id}', [Admin\ProcessStepController::class, 'destroy'])->name('process-steps.destroy');

            Route::post('documents', [Admin\DocumentController::class, 'store'])->name('documents.store');
            Route::delete('documents/{id}', [Admin\DocumentController::class, 'destroy'])->name('documents.destroy');

            Route::post('scores', [Admin\ScoreController::class, 'store'])->name('scores.store');

            Route::post('notes', [Admin\NoteController::class, 'store'])->name('notes.store');
            Route::delete('notes/{id}', [Admin\NoteController::class, 'destroy'])->name('notes.destroy');

            Route::get('files/documents/{id}', [Admin\FileController::class, 'document'])->name('files.document');
            Route::get('files/identity/{endUser}/{type}', [Admin\FileController::class, 'identity'])
                ->whereIn('type', ['photo_id', 'proof_of_address', 'ssn_picture'])
                ->name('files.identity');
        });
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

        Route::get('end-users', [Client\EndUserController::class, 'index'])->name('end-users.index');
        Route::post('end-users', [Client\EndUserController::class, 'store'])->name('end-users.store');
        Route::get('end-users/{id}', [Client\EndUserController::class, 'show'])->name('end-users.show');

        Route::get('files/documents/{id}', [Client\FileController::class, 'document'])->name('files.document');
        Route::get('files/identity/{endUser}/{type}', [Client\FileController::class, 'identity'])
            ->whereIn('type', ['photo_id', 'proof_of_address', 'ssn_picture'])
            ->name('files.identity');
    });
});
