<?php

use App\Http\Controllers\Api\PrintGatewayController;
use Illuminate\Support\Facades\Route;

/*
| The print gateway: a small program at each branch that collects print
| jobs and sends them to the branch's printers. It signs each request with
| its branch's gateway key; there is no session.
*/
Route::prefix('gateway/v1')->middleware('throttle:240,1')->controller(PrintGatewayController::class)->group(function () {
    Route::get('ping', 'ping');
    Route::post('jobs/claim', 'claim');
    Route::post('jobs/{job}/printed', 'printed');
    Route::post('jobs/{job}/failed', 'failed');
});
