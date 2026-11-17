<?php

use App\Features\Admin\GameImage\Controllers\GameImageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin-api/game-image')
    ->name('admin.game-image.')
    ->group(function (): void {
        Route::get('/configuration', [GameImageController::class, 'configuration'])->name('configuration');
        Route::put('/configuration', [GameImageController::class, 'configure'])->name('configure');
        Route::get('/history', [GameImageController::class, 'history'])->name('history');
        Route::patch('/accounts/{account}', [GameImageController::class, 'update'])->whereNumber('account')->name('update');
        Route::delete('/accounts/{account}', [GameImageController::class, 'destroy'])->whereNumber('account')->name('destroy');
        Route::patch('/accounts/{account}/status', [GameImageController::class, 'status'])->whereNumber('account')->name('status');
        Route::post('/accounts/{account}/capture', [App\Features\Client\GameImage\Controllers\GameImageController::class, 'capture'])->whereNumber('account')->name('capture');
    });
