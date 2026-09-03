<?php

use App\Features\Admin\RechargeConfig\Controllers\RechargeBonusTierController;
use App\Features\Admin\RechargeConfig\Controllers\RechargeConfigController;
use Illuminate\Support\Facades\Route;

Route::middleware(['tenancy.active', 'auth:sanctum', 'admin'])
    ->prefix('admin-api/recharge-config')
    ->name('admin.recharge-config.')
    ->controller(RechargeConfigController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::post('/verify-credentials', 'verifyCredentials')->name('verify-credentials');
        Route::patch('/{configRecharge}', 'update')->name('update');
        Route::patch('/{configRecharge}/toggle', 'toggle')->name('toggle');
        Route::delete('/{configRecharge}', 'destroy')->name('destroy');
    });

Route::middleware(['auth:sanctum', 'admin', 'platform.admin'])
    ->prefix('admin-api/recharge-bonus-tiers')
    ->name('admin.recharge-bonus-tiers.')
    ->controller(RechargeBonusTierController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('/{rechargeBonusTier}', 'update')->name('update');
        Route::delete('/{rechargeBonusTier}', 'destroy')->name('destroy');
    });
