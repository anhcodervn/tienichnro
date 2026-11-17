<?php

use App\Features\Client\GameImage\Controllers\GameImageCaptureLogController;
use App\Features\Client\GameImage\Controllers\GameImageToolController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin', 'cache.headers:no_store;private'])
    ->prefix('v1/ghep-anh')->name('api.game-image.')->group(function (): void {
        Route::post('/accounts/{account}/logs', [GameImageCaptureLogController::class, 'store'])->whereNumber('account')->name('logs.store');
        Route::get('/accounts/{account}/logs', [GameImageCaptureLogController::class, 'index'])->whereNumber('account')->name('logs.index');
        Route::get('/accounts/pending', [GameImageToolController::class, 'index'])->name('pending');
        Route::get('/accounts/{account}', [GameImageToolController::class, 'show'])->whereNumber('account')->name('show');
        Route::post('/accounts/{account}/claim', [GameImageToolController::class, 'claim'])->whereNumber('account')->name('claim');
        Route::patch('/accounts/{account}/status', [GameImageToolController::class, 'status'])->whereNumber('account')->name('status');
        Route::post('/accounts/{account}/capture', [GameImageToolController::class, 'upload'])->whereNumber('account')->name('upload');
    });
