<?php

use App\Features\Admin\Wallet\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin-api/wallets')
    ->name('admin.wallets.')
    ->group(function (): void {
        Route::get('/', [WalletController::class, 'index'])->name('index');
        Route::get('/{user}/transactions', [WalletController::class, 'transactions'])->whereNumber('user')->name('transactions');
        Route::post('/{user}/adjust', [WalletController::class, 'adjust'])->whereNumber('user')->name('adjust');
    });
