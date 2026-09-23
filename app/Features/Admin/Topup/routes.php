<?php

use App\Features\Admin\Topup\Controllers\GameController;
use App\Features\Admin\Topup\Controllers\GameImageController;
use App\Features\Admin\Topup\Controllers\GameServerController;
use App\Features\Admin\Topup\Controllers\GlobalTopupPackageController;
use App\Features\Admin\Topup\Controllers\GlobalTopupRewardController;
use App\Features\Admin\Topup\Controllers\OrderController;
use App\Features\Admin\Topup\Controllers\ProviderPriceController;
use App\Features\Admin\Topup\Controllers\TopupPackageController;
use App\Features\Admin\Topup\Controllers\TopupProviderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin-api')->name('admin.topup.')->group(function (): void {
    Route::middleware('platform.admin')->group(function (): void {
        Route::post('games/image', [GameImageController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('games.image.store');
        Route::apiResource('games', GameController::class);
        Route::apiResource('game-servers', GameServerController::class)->parameters(['game-servers' => 'gameServer']);
        Route::apiResource('topup-packages', TopupPackageController::class)->parameters(['topup-packages' => 'topupPackage']);
        Route::apiResource('global-topup-packages', GlobalTopupPackageController::class)
            ->except(['show'])
            ->parameters(['global-topup-packages' => 'globalTopupPackage']);
        Route::get('global-topup-rewards', [GlobalTopupRewardController::class, 'index'])->name('global-topup-rewards.index');
        Route::put('global-topup-rewards/{game}', [GlobalTopupRewardController::class, 'update'])->name('global-topup-rewards.update');
        Route::post('topup-providers/refresh-balances', [TopupProviderController::class, 'refreshBalances'])->name('topup-providers.refresh-balances');
        Route::post('topup-providers/{topupProvider}/services', [TopupProviderController::class, 'services'])
            ->middleware('throttle:10,1')
            ->name('topup-providers.services');
        Route::apiResource('topup-providers', TopupProviderController::class)->parameters(['topup-providers' => 'topupProvider']);
        Route::get('provider-prices', [ProviderPriceController::class, 'index'])->name('provider-prices.index');
        Route::post('provider-prices/refresh', [ProviderPriceController::class, 'refresh'])->name('provider-prices.refresh');
        Route::put('provider-prices/{scope}/{id}', [ProviderPriceController::class, 'update'])
            ->whereIn('scope', ['package', 'global'])
            ->whereNumber('id')
            ->name('provider-prices.update');
        Route::put('provider-prices/{scope}/{id}/providers/{topupProvider}', [ProviderPriceController::class, 'updateQuote'])
            ->whereIn('scope', ['package', 'global'])
            ->whereNumber('id')
            ->name('provider-prices.quotes.update');
        Route::put('provider-prices/{scope}/{id}/providers/{topupProvider}/select', [ProviderPriceController::class, 'selectProvider'])
            ->whereIn('scope', ['package', 'global'])
            ->whereNumber('id')
            ->name('provider-prices.providers.select');
    });
    Route::apiResource('orders', OrderController::class)->only(['index', 'show']);
    Route::match(['put', 'patch'], 'orders/{order}', [OrderController::class, 'update'])
        ->middleware('platform.admin')
        ->name('orders.update');
});
