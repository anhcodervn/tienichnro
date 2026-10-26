<?php

use App\Features\NroGameData\Controllers\GameDataController;
use App\Features\NroGameData\Controllers\GameEffectController;
use App\Features\NroGameData\Controllers\PublicGameDataController;
use App\Features\NroGameData\Services\GameDataCatalogService;
use Illuminate\Support\Facades\Route;

Route::get('nro/game-data/{dataset}', [PublicGameDataController::class, 'index'])
    ->whereIn('dataset', array_keys(GameDataCatalogService::DATASETS))
    ->middleware('throttle:120,1')->name('nro.game-data.api.index');

Route::prefix('admin-api/nro/game-data')->name('admin.nro.game-data.')
    ->middleware(['auth:sanctum', 'admin', 'platform.admin'])->group(function (): void {
        Route::post('/effects/{effect}', [GameEffectController::class, 'store'])->where('effect', '[0-9]{1,5}')->name('effects.store');
        Route::get('/syncs/{sync}', [GameDataController::class, 'showSync'])->whereNumber('sync')->name('syncs.show');
        Route::get('/{dataset}', [GameDataController::class, 'index'])->whereIn('dataset', array_keys(GameDataCatalogService::DATASETS))->name('index');
        Route::post('/{dataset}', [GameDataController::class, 'store'])->whereIn('dataset', array_keys(GameDataCatalogService::DATASETS))->name('store');
        Route::post('/{dataset}/compare', [GameDataController::class, 'compare'])->whereIn('dataset', array_keys(GameDataCatalogService::DATASETS))->name('compare');
    });
