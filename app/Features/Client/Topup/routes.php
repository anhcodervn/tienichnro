<?php

use App\Features\Client\Topup\Controllers\Account\OrderController as AccountOrderController;
use App\Features\Client\Topup\Controllers\CheckoutController;
use App\Features\Client\Topup\Controllers\HomeController;
use App\Features\Client\Topup\Controllers\OrderController;
use App\Features\Client\Topup\Controllers\TopupController;
use Illuminate\Support\Facades\Route;

Route::middleware('site.active')->group(function (): void {
    Route::get('/', HomeController::class)->name('home');
    Route::redirect('/nap-game', '/')->name('topup.index');
    Route::get('/nap-game/{game:slug}', [TopupController::class, 'show'])->name('topup.game');
    Route::get('/bang-gia', [TopupController::class, 'index'])->name('pricing');
    Route::post('/dat-hang', [CheckoutController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('checkout.store');

    Route::get('/tra-cuu-don-hang', [OrderController::class, 'lookup'])->name('orders.lookup');
    Route::post('/tra-cuu-don-hang', [OrderController::class, 'find'])
        ->middleware('throttle:6,1')
        ->name('orders.lookup.submit');
    Route::get('/don-hang/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/don-hang/{order}/thanh-toan', [OrderController::class, 'payment'])->name('orders.payment');
    Route::get('/don-hang/{order}/trang-thai', [OrderController::class, 'status'])
        ->middleware('throttle:20,1')
        ->name('orders.status');
});

Route::middleware('auth')
    ->prefix('tai-khoan')
    ->name('account.')
    ->group(function (): void {
        Route::get('/don-hang', [AccountOrderController::class, 'index'])->name('orders.index');
        Route::get('/don-hang/{order}', [AccountOrderController::class, 'show'])->name('orders.show');
    });
