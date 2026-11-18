<?php

use App\Features\Admin\ManagedServer\Controllers\ManagedServerController;
use App\Models\ManagedServer;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin', 'cache.headers:no_store;private'])->prefix('admin-api/managed-servers')
    ->name('admin.managed-servers.')
    ->group(function (): void {
        Route::get('/napcarot/configuration', [ManagedServerController::class, 'configuration'])->name('configuration');
        Route::put('/napcarot/configuration', [ManagedServerController::class, 'configure'])->name('configure');
        Route::post('/napcarot/sync', [ManagedServerController::class, 'sync'])->name('sync');
        Route::get('/{type}', [ManagedServerController::class, 'index'])->whereIn('type', ManagedServer::TYPES)->name('index');
        Route::post('/{type}', [ManagedServerController::class, 'store'])->whereIn('type', ManagedServer::TYPES)->name('store');
        Route::put('/{type}/{server}', [ManagedServerController::class, 'update'])->whereIn('type', ManagedServer::TYPES)->whereNumber('server')->name('update');
        Route::delete('/{type}/{server}', [ManagedServerController::class, 'destroy'])->whereIn('type', ManagedServer::TYPES)->whereNumber('server')->name('destroy');
    });
