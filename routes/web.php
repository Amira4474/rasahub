<?php

use App\Http\Controllers\ExitSuperImposeController;
use App\Http\Controllers\Pos\PosHomeController;
use App\Http\Controllers\Pos\PosLoginController;
use App\Http\Controllers\Pos\PosReceiptController;
use App\Http\Controllers\Pos\PosTerminalController;
use App\Http\Controllers\Registration\TenantRegistrationController;
use App\Http\Controllers\ReportExportController;
use App\Http\Middleware\EnsurePosAccess;
use App\Http\Middleware\EnsurePosTerminal;
use Illuminate\Support\Facades\Route;

Route::view('/', 'marketing.home')->name('home');

// Business settings became Restaurant settings in v0.2.0.
Route::redirect('/admin/business-settings', '/admin/restaurant-settings', 301);

/*
| There are three separate ways in, each with its own sign-in page:
|   /platform  platform users        (Filament panel)
|   /admin     a business's managers (Filament panel)
|   /pos       staff on shift        (below)
*/

// A new tenant registers in two steps: the token from an invitation, then
// the business details. Nothing is created without a valid token, and
// attempts are limited so that tokens cannot be guessed.
Route::prefix('register')->name('register.')->group(function () {
    Route::get('/', [TenantRegistrationController::class, 'showToken'])->name('show');
    Route::post('/', [TenantRegistrationController::class, 'storeToken'])->middleware('throttle:5,1')->name('token');
    Route::get('details', [TenantRegistrationController::class, 'showDetails'])->name('details');
    Route::post('details', [TenantRegistrationController::class, 'store'])->middleware('throttle:10,1')->name('store');
    Route::get('received', [TenantRegistrationController::class, 'received'])->name('received');
});

Route::post('/super-impose/exit', ExitSuperImposeController::class)->name('super-impose.exit');

// Reports as Excel or PDF, for the tenant admin. The controller checks who may.
Route::get('/admin/reports/{report}.{format}', ReportExportController::class)
    ->where(['report' => '[a-z]+', 'format' => 'xlsx|pdf'])
    ->name('reports.export');

Route::prefix('pos')->name('pos.')->group(function () {
    Route::get('login', [PosLoginController::class, 'showCompany'])->name('login');
    Route::get('login/credentials', [PosLoginController::class, 'showCredentials'])->name('login.credentials');

    Route::middleware('throttle:10,1')->group(function () {
        Route::post('login', [PosLoginController::class, 'storeCompany'])->name('login.company');
        Route::post('login/credentials', [PosLoginController::class, 'authenticate'])->name('login.authenticate');
    });

    Route::post('logout', [PosLoginController::class, 'logout'])->name('logout');

    // Printed from the POS, and from an order in the admin, including by a
    // platform user in Super Impose. The controller checks who may see it.
    Route::get('orders/{order}/receipt', PosReceiptController::class)->name('receipt');
    Route::get('refunds/{refund}/slip', [PosReceiptController::class, 'refund'])->name('refund-slip');
    Route::get('shifts/{shift}/report', [PosReceiptController::class, 'shift'])->name('shift-report');

    Route::middleware(EnsurePosAccess::class)->group(function () {
        Route::get('terminal', [PosTerminalController::class, 'show'])->name('terminal');
        Route::post('terminal', [PosTerminalController::class, 'store'])->name('terminal.store');

        Route::get('/', PosHomeController::class)->middleware(EnsurePosTerminal::class)->name('home');
    });
});
