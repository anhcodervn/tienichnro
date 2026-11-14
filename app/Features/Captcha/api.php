<?php

use App\Features\Captcha\Controllers\AdminCaptchaController;
use App\Features\Captcha\Controllers\CaptchaController;
use App\Features\Captcha\Controllers\CaptchaProviderController;
use App\Features\Captcha\Middleware\AuthenticateCaptchaKey;
use Illuminate\Support\Facades\Route;

Route::post('/v1/giai-captcha-nro/solve', [CaptchaController::class, 'solve'])
    ->middleware([AuthenticateCaptchaKey::class, 'throttle:60,1'])->name('api.captcha.solve');

Route::middleware(['auth:sanctum', 'admin', 'throttle:120,1'])->prefix('admin-api/captcha')->name('admin.captcha.')->group(function (): void {
    Route::get('/providers', [CaptchaProviderController::class, 'index'])->name('providers.index');
    Route::post('/providers', [CaptchaProviderController::class, 'store'])->name('providers.store');
    Route::put('/providers/{provider}', [CaptchaProviderController::class, 'update'])->whereNumber('provider')->name('providers.update');
    Route::delete('/providers/{provider}', [CaptchaProviderController::class, 'destroy'])->whereNumber('provider')->name('providers.destroy');
    Route::get('/wallets', [AdminCaptchaController::class, 'wallets'])->name('wallets');
    Route::get('/transactions', [AdminCaptchaController::class, 'transactions'])->name('transactions');
    Route::post('/wallets/{user}/adjust', [AdminCaptchaController::class, 'adjust'])->whereNumber('user')->name('adjust');
    Route::get('/rates', [AdminCaptchaController::class, 'rates'])->name('rates');
    Route::put('/rates', [AdminCaptchaController::class, 'saveRates'])->name('rates.save');
    Route::get('/solves', [AdminCaptchaController::class, 'solves'])->name('solves');
    Route::get('/solves/{solve}', [AdminCaptchaController::class, 'show'])->whereNumber('solve')->name('show');
    Route::get('/solves/{solve}/image', [AdminCaptchaController::class, 'image'])->whereNumber('solve')->name('image');
});
