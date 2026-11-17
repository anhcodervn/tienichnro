<?php

use App\Features\Recharge\Controllers\RechargeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'cache.headers:no_store;private'])->prefix('tai-khoan/nap-tien')->name('account.recharge.')->group(function (): void {
    Route::post('/', [RechargeController::class, 'store'])->middleware('throttle:10,1')->name('store');
    Route::get('/{order}', [RechargeController::class, 'show'])->whereNumber('order')->name('show');
    Route::get('/{order}/trang-thai', [RechargeController::class, 'status'])->whereNumber('order')->name('status');
});
