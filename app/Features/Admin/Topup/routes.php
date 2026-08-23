<?php

use App\Features\Admin\Topup\Controllers\GameController;
use App\Features\Admin\Topup\Controllers\GameServerController;
use App\Features\Admin\Topup\Controllers\OrderController;
use App\Features\Admin\Topup\Controllers\TopupPackageController;
use App\Features\Admin\Topup\Controllers\TopupProviderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin-api')->name('admin.topup.')->group(function (): void {
    Route::apiResource('games', GameController::class);
    Route::apiResource('game-servers', GameServerController::class)->parameters(['game-servers' => 'gameServer']);
    Route::apiResource('topup-packages', TopupPackageController::class)->parameters(['topup-packages' => 'topupPackage']);
    Route::post('topup-providers/refresh-balances', [TopupProviderController::class, 'refreshBalances'])->name('topup-providers.refresh-balances');
    Route::apiResource('topup-providers', TopupProviderController::class)->parameters(['topup-providers' => 'topupProvider']);
    Route::apiResource('orders', OrderController::class)->only(['index', 'show', 'update']);
});
