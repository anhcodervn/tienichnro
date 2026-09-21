<?php

use App\Features\Topup\Controllers\ProviderPriceCronController;
use App\Features\Topup\Controllers\The9pBalanceCronController;
use Illuminate\Support\Facades\Route;

Route::get('/cron/the9p/balance', The9pBalanceCronController::class)
    ->middleware('throttle:6,1')
    ->name('api.cron.the9p.balance');

Route::post('/cron/provider-prices', ProviderPriceCronController::class)
    ->middleware('throttle:6,1')
    ->name('api.cron.provider-prices');
