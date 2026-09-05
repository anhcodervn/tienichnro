<?php

use App\Features\Client\Affiliate\Controllers\AffiliateController;
use App\Features\Client\Affiliate\Controllers\AffiliateHomeController;
use App\Features\Client\Affiliate\Controllers\AffiliateWalletController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:60,1'])->prefix('client/affiliate')->name('client.affiliate.')->group(function (): void {
    Route::get('/home', [AffiliateHomeController::class, 'index'])->name('home');
    Route::get('/', [AffiliateController::class, 'index'])->name('index');
    Route::put('/payout-account', [AffiliateController::class, 'updatePayout'])->name('payout.update');
    Route::post('/convert', [AffiliateWalletController::class, 'convert'])->middleware('throttle:10,1')->name('convert');
    Route::post('/withdrawals', [AffiliateWalletController::class, 'withdraw'])->middleware('throttle:5,1')->name('withdrawals.store');
});
