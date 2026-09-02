<?php

use App\Features\Admin\Topup\Controllers\GameController;
use App\Features\Admin\Topup\Controllers\GameServerController;
use App\Features\Admin\Topup\Controllers\GlobalTopupPackageController;
use App\Features\Admin\Topup\Controllers\GlobalTopupPackageLevelPriceController;
use App\Features\Admin\Topup\Controllers\GlobalTopupRewardController;
use App\Features\Admin\Topup\Controllers\OrderController;
use App\Features\Admin\Topup\Controllers\TopupPackageController;
use App\Features\Admin\Topup\Controllers\TopupProviderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin-api')->name('admin.topup.')->group(function (): void {
    Route::apiResource('games', GameController::class);
    Route::apiResource('game-servers', GameServerController::class)->parameters(['game-servers' => 'gameServer']);
    Route::apiResource('topup-packages', TopupPackageController::class)->parameters(['topup-packages' => 'topupPackage']);
    Route::apiResource('global-topup-packages', GlobalTopupPackageController::class)
        ->except(['show'])
        ->parameters(['global-topup-packages' => 'globalTopupPackage']);
    Route::put('global-topup-packages/{globalTopupPackage}/levels/{memberLevel}', [GlobalTopupPackageLevelPriceController::class, 'update'])->name('global-topup-package-level-prices.update');
    Route::delete('global-topup-packages/{globalTopupPackage}/levels/{memberLevel}', [GlobalTopupPackageLevelPriceController::class, 'destroy'])->name('global-topup-package-level-prices.destroy');
    Route::get('global-topup-rewards', [GlobalTopupRewardController::class, 'index'])->name('global-topup-rewards.index');
    Route::put('global-topup-rewards/{game}', [GlobalTopupRewardController::class, 'update'])->name('global-topup-rewards.update');
    Route::post('topup-providers/refresh-balances', [TopupProviderController::class, 'refreshBalances'])->name('topup-providers.refresh-balances');
    Route::apiResource('topup-providers', TopupProviderController::class)->parameters(['topup-providers' => 'topupProvider']);
    Route::apiResource('orders', OrderController::class)->only(['index', 'show', 'update']);
});
