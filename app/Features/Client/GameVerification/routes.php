<?php

use App\Features\Client\GameVerification\Controllers\GameVerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/xac-minh-tai-khoan-game-ngoc-rong-online', [GameVerificationController::class, 'index'])->middleware('cache.headers:no_store;private')->name('tools.game-verification');
Route::middleware(['auth', 'cache.headers:no_store;private'])->group(function (): void {
    Route::post('/xac-minh-tai-khoan-game-ngoc-rong-online/export', [GameVerificationController::class, 'export'])->name('tools.game-verification.export');
    Route::post('/xac-minh-tai-khoan-game-ngoc-rong-online', [GameVerificationController::class, 'store'])->name('tools.game-verification.store');
    Route::post('/xac-minh-tai-khoan-game-ngoc-rong-online/{order}/retry', [GameVerificationController::class, 'retry'])->whereNumber('order')->name('tools.game-verification.retry');
    Route::get('/xac-minh-tai-khoan-game-ngoc-rong-online/lich-su', [GameVerificationController::class, 'history'])->name('tools.game-verification.history');
    Route::get('/xac-minh-tai-khoan-game-ngoc-rong-online/stream', [GameVerificationController::class, 'stream'])->name('tools.game-verification.stream');
});
