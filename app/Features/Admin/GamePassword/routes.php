<?php

use App\Features\Admin\GamePassword\Controllers\GamePasswordController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin', 'cache.headers:no_store;private'])->prefix('admin-api/game-password')->name('admin.game-password.')->group(function (): void {
    Route::get('/configuration', [GamePasswordController::class, 'configuration'])->name('configuration');
    Route::put('/configuration', [GamePasswordController::class, 'configure'])->name('configure');
    Route::get('/history', [GamePasswordController::class, 'history'])->name('history');
    Route::get('/stream', [GamePasswordController::class, 'stream'])->name('stream');
    Route::delete('/history', [GamePasswordController::class, 'clear'])->name('clear');
    Route::patch('/orders/{order}/resolve', [GamePasswordController::class, 'resolve'])->whereNumber('order')->name('resolve');
    Route::post('/orders/{order}/retry', [GamePasswordController::class, 'retry'])->whereNumber('order')->name('retry');
});
