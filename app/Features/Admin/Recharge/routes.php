<?php

use App\Features\Admin\Recharge\Controllers\RechargeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin', 'cache.headers:no_store;private'])->prefix('admin-api/recharge')->name('admin.recharge.')->group(function (): void {
    Route::get('/configuration', [RechargeController::class, 'configuration'])->name('configuration');
    Route::put('/configuration', [RechargeController::class, 'configure'])->name('configure');
    Route::post('/banks', [RechargeController::class, 'banks'])->name('banks');
    Route::get('/history', [RechargeController::class, 'history'])->name('history');
    Route::post('/check', [RechargeController::class, 'check'])->name('check');
});
