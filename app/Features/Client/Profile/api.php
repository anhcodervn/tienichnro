<?php

use App\Features\Client\Profile\Controllers\AccountLicenseController;
use App\Features\Client\Profile\Controllers\AccountToolController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:60,1', 'cache.headers:no_store;private'])
    ->prefix('account/licenses')->name('api.account.licenses.')->controller(AccountLicenseController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/{license}', 'show')->whereNumber('license')->name('show');
    });

Route::middleware(['auth:sanctum', 'throttle:60,1', 'cache.headers:no_store;private'])
    ->prefix('account/tools')->name('api.account.tools.')->controller(AccountToolController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/{product}/download/{kind}', 'download')->whereNumber('product')->whereIn('kind', ['tool', 'update'])->name('download');
    });
