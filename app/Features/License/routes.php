<?php

use App\Features\License\Controllers\LicenseController;
use App\Http\Middleware\LicenseApiGuard;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/licenses')->name('license.')->middleware(LicenseApiGuard::class)->controller(LicenseController::class)->group(function (): void {
    Route::post('challenge', 'challenge')->name('challenge');
    Route::post('activate', 'activate')->name('activate');
    Route::post('heartbeat', 'heartbeat')->name('heartbeat');
    Route::get('status', 'status')->name('status');
    Route::post('deactivate', 'deactivate')->name('deactivate');
    Route::post('transfer', 'transfer')->middleware('auth:sanctum')->name('transfer');
});
