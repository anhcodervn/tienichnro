<?php

use App\Features\Recharge\Controllers\RechargeController;
use Illuminate\Support\Facades\Route;

Route::post('/recharge/cron/check', [RechargeController::class, 'cron'])->middleware('cache.headers:no_store;private')->name('recharge.cron');
Route::post('/recharge/callbacks/apibankvn', [RechargeController::class, 'callback'])->middleware('cache.headers:no_store;private')->name('recharge.callback.apibankvn');
