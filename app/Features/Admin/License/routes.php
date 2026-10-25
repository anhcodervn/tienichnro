<?php

use App\Features\Admin\License\Controllers\ClientGameController;
use App\Features\Admin\License\Controllers\LicenseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin', 'throttle:120,1'])->prefix('admin-api/license/client-game')->name('admin-api.license.client-game.')->group(function (): void {
    Route::get('/', [ClientGameController::class, 'index'])->name('index');
    Route::get('{kind}/download', [ClientGameController::class, 'download'])->whereIn('kind', ['original', 'update'])->name('download');
    Route::post('{kind}', [ClientGameController::class, 'store'])->whereIn('kind', ['original', 'update'])->name('store');
});

Route::middleware(['auth:sanctum', 'admin', 'throttle:120,1'])->prefix('admin-api/license')->name('admin-api.license.')->controller(LicenseController::class)->group(function (): void {
    Route::get('products', 'products')->name('products.index');
    Route::post('products', 'storeProduct')->name('products.store');
    Route::patch('products/{product}', 'updateProduct')->name('products.update');
    Route::delete('products/{product}', 'destroyProduct')->name('products.destroy');
    Route::get('plans', 'plans')->name('plans.index');
    Route::post('plans', 'storePlan')->name('plans.store');
    Route::patch('plans/{plan}', 'updatePlan')->name('plans.update');
    Route::delete('plans/{plan}', 'destroyPlan')->name('plans.destroy');
    Route::get('keys', 'index')->name('keys.index');
    Route::post('keys', 'store')->name('keys.store');
    Route::get('keys/{license}', 'show')->name('keys.show');
    Route::patch('keys/{license}', 'update')->name('keys.update');
    Route::delete('keys/{license}', 'destroy')->name('keys.destroy');
});
