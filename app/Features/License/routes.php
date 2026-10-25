<?php

use App\Features\License\Controllers\ClientGameDownloadController;
use App\Features\License\Controllers\LicenseController;
use App\Http\Middleware\LicenseApiGuard;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/licenses')->name('license.')->middleware(['auth:sanctum', 'throttle:60,1', 'cache.headers:no_store;private'])->group(function (): void {
    Route::get('client-game', [ClientGameDownloadController::class, 'index'])->name('client-game.index');
    Route::get('client-game/{kind}/download', [ClientGameDownloadController::class, 'download'])->whereIn('kind', ['original', 'update'])->name('client-game.download');
});

Route::prefix('v1/licenses')->name('license.')->middleware(LicenseApiGuard::class)->controller(LicenseController::class)->group(function (): void {
    Route::post('challenge', 'challenge')->name('challenge');
    Route::post('activate', 'activate')->name('activate');
    Route::post('heartbeat', 'heartbeat')->name('heartbeat');
    Route::get('status', 'status')->name('status');
    Route::post('deactivate', 'deactivate')->name('deactivate');
    Route::post('transfer', 'transfer')->middleware('auth:sanctum')->name('transfer');
});
