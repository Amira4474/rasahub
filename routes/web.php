<?php

use App\Http\Controllers\ExitSuperImposeController;
use App\Http\Controllers\Pos\PosHomeController;
use App\Http\Controllers\Pos\PosLoginController;
use App\Http\Controllers\Registration\TenantRegistrationController;
use App\Http\Middleware\EnsurePosAccess;
use Illuminate\Support\Facades\Route;

Route::view('/', 'marketing.home')->name('home');

/*
| There are three separate ways in, each with its own sign-in page:
|   /platform  platform users        (Filament panel)
|   /admin     a business's managers (Filament panel)
|   /pos       staff on shift        (below)
*/

// A new tenant registers with the link from an invitation; there is no open
// sign-up. The token is part of the address, so it is kept out of the logs
// of other sites by the page's no-referrer policy.
Route::prefix('register/{token}')->name('register.')->middleware('throttle:10,1')->group(function () {
    Route::get('/', [TenantRegistrationController::class, 'show'])->name('show');
    Route::post('/', [TenantRegistrationController::class, 'store'])->name('store');
});

Route::post('/super-impose/exit', ExitSuperImposeController::class)->name('super-impose.exit');

Route::prefix('pos')->name('pos.')->group(function () {
    Route::get('login', [PosLoginController::class, 'showCompany'])->name('login');
    Route::get('login/credentials', [PosLoginController::class, 'showCredentials'])->name('login.credentials');

    Route::middleware('throttle:10,1')->group(function () {
        Route::post('login', [PosLoginController::class, 'storeCompany'])->name('login.company');
        Route::post('login/credentials', [PosLoginController::class, 'authenticate'])->name('login.authenticate');
    });

    Route::post('logout', [PosLoginController::class, 'logout'])->name('logout');

    Route::get('/', PosHomeController::class)->middleware(EnsurePosAccess::class)->name('home');
});
