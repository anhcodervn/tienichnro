<?php

use App\Features\Client\Wallet\Controllers\DepositPageController;
use App\Features\Client\Wallet\Controllers\DepositPaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/nap-tien', DepositPageController::class)
    ->middleware(['auth', 'site.active'])
    ->name('wallet.deposit.index');

Route::get('/nap-tien/{paymentTransaction:transaction_code}/thanh-toan', DepositPaymentController::class)
    ->middleware(['auth', 'site.active'])
    ->name('wallet.deposit.payment');
