<?php

use App\Features\Client\Wallet\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('client/wallet')
    ->name('client/wallet.')
    ->middleware('auth:sanctum')
    ->controller(WalletController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/deposit-requests', 'depositRequests')->name('deposit-requests.index');
        Route::post('/deposit-requests', 'storeDepositRequest')->middleware('throttle:6,1')->name('deposit-requests.store');
        Route::post('/deposit-requests/{paymentTransaction}/confirm', 'confirmDepositRequest')->middleware('throttle:10,1')->name('deposit-requests.confirm');
    });
