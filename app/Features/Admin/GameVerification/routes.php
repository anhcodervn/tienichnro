<?php

use App\Features\Admin\GameVerification\Controllers\GameVerificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin', 'cache.headers:no_store;private'])->prefix('admin-api/game-verification')->name('admin.game-verification.')->group(function (): void {
    Route::get('/configuration', [GameVerificationController::class, 'configuration'])->name('configuration');
    Route::put('/configuration', [GameVerificationController::class, 'configure'])->name('configure');
    Route::get('/history', [GameVerificationController::class, 'history'])->name('history');
    Route::get('/stream', [GameVerificationController::class, 'stream'])->name('stream');
    Route::delete('/history', [GameVerificationController::class, 'clear'])->name('clear');
    Route::post('/orders/{order}/retry', [GameVerificationController::class, 'retry'])->whereNumber('order')->name('retry');
});
