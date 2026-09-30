<?php

use App\Features\Admin\User\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin'])
    ->prefix('admin-api/users')
    ->name('admin.users.')
    ->controller(UserController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('discounts', 'discounts')->name('discounts.index');
        Route::put('discounts/bulk', 'bulkSetDiscounts')->name('discounts.bulk');
        Route::get('{user}/prices', 'prices')->name('prices.index');
        Route::get('{user}/game-services', 'gameServices')->name('game-services.index');
        Route::put('{user}/game-services', 'syncGameServices')->name('game-services.update');
        Route::put('{user}/game-service-secondary-password', 'updateGameServiceSecondaryPassword')->name('game-service-secondary-password.update');
        Route::put('{user}/prices/quick-set', 'quickSetPrices')->name('prices.quick-set');
        Route::put('{user}/global-prices/{globalTopupPackage}', 'updateGlobalPrice')->name('global-prices.update');
        Route::delete('{user}/global-prices/{globalTopupPackage}', 'deleteGlobalPrice')->name('global-prices.destroy');
        Route::put('{user}/prices/{topupPackage}', 'updatePrice')->name('prices.update');
        Route::delete('{user}/prices/{topupPackage}', 'deletePrice')->name('prices.destroy');
        Route::get('{user}', 'show')->name('show');
        Route::patch('{user}/status', 'updateStatus')->name('status.update');
        Route::patch('{user}/role', 'updateRole')->name('role.update');
        Route::post('{user}/reset-password', 'resetPassword')->name('password.reset');
        Route::post('{user}/wallet-adjust', 'walletAdjust')->name('wallet.adjust');
        Route::get('{user}/wallet-transactions', 'walletTransactions')->name('wallet-transactions.index');
        Route::get('{user}/logs', 'logs')->name('logs.index');
    });
