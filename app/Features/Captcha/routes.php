<?php

use App\Features\Captcha\Controllers\CaptchaController;
use Illuminate\Support\Facades\Route;

Route::get('/giai-captcha-nro', [CaptchaController::class, 'index'])->name('tools.captcha');
Route::middleware(['auth', 'throttle:60,1'])->prefix('giai-captcha-nro')->name('tools.captcha.')->group(function (): void {
    Route::post('/solve', [CaptchaController::class, 'solve'])->name('solve');
    Route::post('/purchase', [CaptchaController::class, 'purchase'])->name('purchase');
    Route::post('/api-token', [CaptchaController::class, 'key'])->middleware('throttle:10,1')->name('key');
    Route::delete('/api-token', [CaptchaController::class, 'revoke'])->name('revoke');
    Route::get('/history', [CaptchaController::class, 'history'])->name('history');
});
