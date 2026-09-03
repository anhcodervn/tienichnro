<?php

use App\Features\Admin\User\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin'])
    ->prefix('admin-api/users')
    ->name('admin.users.')
    ->controller(UserController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('{user}/prices', 'prices')->name('prices.index');
        Route::put('{user}/global-price', 'updateGlobalPrice')->name('global-price.update');
        Route::delete('{user}/global-price', 'deleteGlobalPrice')->name('global-price.destroy');
        Route::put('{user}/prices/{topupPackage}', 'updatePrice')->name('prices.update');
        Route::delete('{user}/prices/{topupPackage}', 'deletePrice')->name('prices.destroy');
        Route::get('{user}', 'show')->name('show');
        Route::patch('{user}/status', 'updateStatus')->name('status.update');
        Route::post('{user}/reset-password', 'resetPassword')->name('password.reset');
        Route::post('{user}/wallet-adjust', 'walletAdjust')->name('wallet.adjust');
        Route::get('{user}/wallet-transactions', 'walletTransactions')->name('wallet-transactions.index');
        Route::get('{user}/logs', 'logs')->name('logs.index');
    });
