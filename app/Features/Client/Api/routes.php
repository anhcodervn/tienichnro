<?php

use App\Features\Client\Api\Controllers\BalanceController;
use App\Features\Client\Api\Controllers\CatalogController;
use App\Features\Client\Api\Controllers\TopupOrderController;
use App\Http\Middleware\AuthenticateApiCredentials;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {
        Route::get('/balance', BalanceController::class)
            ->middleware([AuthenticateApiCredentials::class.':balance:read', 'site.active', 'throttle:60,1'])
            ->name('balance');

        Route::get('/catalog', CatalogController::class)
            ->middleware([AuthenticateApiCredentials::class.':catalog:read', 'site.active', 'throttle:60,1'])
            ->name('catalog');

        Route::post('/orders', [TopupOrderController::class, 'store'])
            ->middleware([AuthenticateApiCredentials::class.':orders:create', 'site.active', 'topup.available', 'throttle:10,1'])
            ->name('orders.store');
        Route::get('/orders/{order}', [TopupOrderController::class, 'show'])
            ->whereAlphaNumeric('order')
            ->middleware([AuthenticateApiCredentials::class.':orders:read', 'site.active', 'throttle:60,1'])
            ->name('orders.show');
    });
