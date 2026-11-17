<?php

use App\Features\Client\GamePassword\Controllers\GamePasswordController;
use Illuminate\Support\Facades\Route;

Route::get('/doi-mat-khau-game-ngoc-rong-online', [GamePasswordController::class, 'index'])->middleware('cache.headers:no_store;private')->name('tools.game-password');
Route::middleware(['auth', 'cache.headers:no_store;private'])->group(function (): void {
    Route::post('/doi-mat-khau-game-ngoc-rong-online/captcha', [GamePasswordController::class, 'saveCaptcha'])->name('tools.game-password.captcha.save');
    Route::post('/doi-mat-khau-game-ngoc-rong-online/captcha/refresh', [GamePasswordController::class, 'refreshCaptcha'])->name('tools.game-password.captcha.refresh');
    Route::post('/doi-mat-khau-game-ngoc-rong-online', [GamePasswordController::class, 'store'])->name('tools.game-password.store');
    Route::post('/doi-mat-khau-game-ngoc-rong-online/{order}/retry', [GamePasswordController::class, 'retry'])->whereNumber('order')->name('tools.game-password.retry');
    Route::get('/doi-mat-khau-game-ngoc-rong-online/lich-su', [GamePasswordController::class, 'history'])->name('tools.game-password.history');
    Route::get('/doi-mat-khau-game-ngoc-rong-online/stream', [GamePasswordController::class, 'stream'])->name('tools.game-password.stream');
});
