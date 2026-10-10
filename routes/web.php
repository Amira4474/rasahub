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
