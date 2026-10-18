<?php

use App\Features\NroGameData\Controllers\GameDataController;
use App\Features\NroGameData\Services\GameDataCatalogService;
use Illuminate\Support\Facades\Route;

Route::prefix('admin-api/nro/game-data')->name('admin.nro.game-data.')
    ->middleware(['auth:sanctum', 'admin', 'platform.admin'])->group(function (): void {
        Route::get('/syncs/{sync}', [GameDataController::class, 'showSync'])->whereNumber('sync')->name('syncs.show');
        Route::get('/{dataset}', [GameDataController::class, 'index'])->whereIn('dataset', array_keys(GameDataCatalogService::DATASETS))->name('index');
        Route::post('/{dataset}', [GameDataController::class, 'store'])->whereIn('dataset', array_keys(GameDataCatalogService::DATASETS))->name('store');
        Route::post('/{dataset}/compare', [GameDataController::class, 'compare'])->whereIn('dataset', array_keys(GameDataCatalogService::DATASETS))->name('compare');
    });
