<?php

use App\Features\Admin\GameService\Controllers\GameServiceController;
use App\Features\Admin\GameService\Controllers\GameServiceGameController;
use App\Features\Admin\GameService\Controllers\GameServiceOrderController;
use App\Features\Admin\GameService\Controllers\GameServicePackageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin', 'platform.admin'])
    ->prefix('admin-api')
    ->name('admin.game-services.')
    ->group(function (): void {
        Route::get('game-service-games', [GameServiceGameController::class, 'index'])->name('games.index');
        Route::patch('game-service-games/{game}', [GameServiceGameController::class, 'update'])->name('games.update');
        Route::apiResource('game-services', GameServiceController::class)->parameters(['game-services' => 'gameService']);
        Route::apiResource('game-service-packages', GameServicePackageController::class)->parameters(['game-service-packages' => 'gameServicePackage']);
        Route::apiResource('game-service-orders', GameServiceOrderController::class)
            ->only(['index', 'show', 'update'])
            ->parameters(['game-service-orders' => 'gameServiceOrder']);
    });
