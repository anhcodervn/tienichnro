<?php

use App\Features\Tenant\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

Route::middleware(['tenancy.active', 'auth:sanctum', 'admin'])->prefix('admin-api')->name('admin.tenants.')->group(function (): void {
    Route::get('site', [TenantController::class, 'current'])->name('current');
    Route::get('site/prices', [TenantController::class, 'prices'])->name('prices');
    Route::put('site/prices/{topupPackage}', [TenantController::class, 'updatePrice'])->name('prices.update');

    Route::middleware('platform.admin')->group(function (): void {
        Route::get('tenants', [TenantController::class, 'index'])->name('index');
        Route::post('tenants', [TenantController::class, 'store'])->name('store');
        Route::get('tenants/{tenant}', [TenantController::class, 'show'])->name('show');
        Route::put('tenants/{tenant}', [TenantController::class, 'update'])->name('update');
    });
});
