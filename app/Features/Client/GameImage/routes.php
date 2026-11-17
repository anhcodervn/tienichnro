<?php

use App\Features\Client\GameImage\Controllers\GameImageCaptureLogController;
use App\Features\Client\GameImage\Controllers\GameImageController;
use App\Features\Client\GameImage\Controllers\GameImageShareController;
use Illuminate\Support\Facades\Route;

Route::get('/ghep-anh-game', [GameImageController::class, 'index'])->middleware('cache.headers:no_store;private')->name('tools.game-image');
Route::get('/ghep-anh-game/chia-se/{account}/{token}', [GameImageShareController::class, 'show'])
    ->whereNumber('account')->where('token', '[A-Za-z0-9]{40}')->middleware(['signed', 'throttle:60,1', 'cache.headers:no_store;private'])->name('tools.game-image.shared');
Route::get('/ghep-anh-game/chia-se/{account}/{token}/anh/{path}', [GameImageShareController::class, 'source'])
    ->whereNumber('account')->where('token', '[A-Za-z0-9]{40}')->where('path', '.+')
    ->middleware(['signed', 'throttle:120,1', 'cache.headers:no_store;private'])->name('tools.game-image.shared.source');

Route::middleware(['auth', 'cache.headers:no_store;private'])->group(function (): void {
    Route::get('/ghep-anh-game/{account}/logs', [GameImageCaptureLogController::class, 'index'])->whereNumber('account')->name('tools.game-image.logs');
    Route::get('/ghep-anh-game/trang-thai/stream', [GameImageController::class, 'stream'])->name('tools.game-image.stream');
    Route::post('/ghep-anh-game/{account}/chup-lai', [GameImageController::class, 'recapture'])->whereNumber('account')->name('tools.game-image.recapture');
    Route::post('/ghep-anh-game', [GameImageController::class, 'store'])->name('tools.game-image.store');
    Route::patch('/ghep-anh-game/{account}/thong-tin', [GameImageController::class, 'details'])->whereNumber('account')->name('tools.game-image.details');
    Route::post('/ghep-anh-game/{account}/quan-ly', [GameImageController::class, 'control'])->whereNumber('account')->name('tools.game-image.control');
    Route::post('/ghep-anh-game/{account}/du-lieu', [GameImageController::class, 'capture'])->whereNumber('account')->name('tools.game-image.capture');
    Route::get('/ghep-anh-game/{account}/du-lieu.zip', [GameImageController::class, 'downloadData'])->whereNumber('account')->name('tools.game-image.download-data');
    Route::get('/ghep-anh-game/{account}/anh-ghep', [GameImageController::class, 'preview'])->whereNumber('account')->name('tools.game-image.preview');
    Route::get('/ghep-anh-game/{account}/anh-nguon/{path}', [GameImageController::class, 'source'])->whereNumber('account')->where('path', '.+')->name('tools.game-image.source');
    Route::get('/ghep-anh-game/{account}/dieu-chinh', [GameImageController::class, 'edit'])->whereNumber('account')->name('tools.game-image.edit');
    Route::patch('/ghep-anh-game/{account}', [GameImageController::class, 'update'])->whereNumber('account')->name('tools.game-image.update');
    Route::post('/ghep-anh-game/{account}/chia-se', [GameImageShareController::class, 'store'])->whereNumber('account')->name('tools.game-image.share');
    Route::delete('/ghep-anh-game/{account}/chia-se', [GameImageShareController::class, 'destroy'])->whereNumber('account')->name('tools.game-image.share.revoke');
});
