<?php

use App\Features\Client\GameService\Controllers\GameServiceController;
use App\Features\Client\GameService\Controllers\GameServiceDetailController;
use App\Features\Client\GameService\Controllers\GameServiceOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/dich-vu-game-{game:slug}/{gameService:slug}', GameServiceDetailController::class)
    ->middleware('site.active')
    ->where([
        'game' => '[a-z0-9]+(?:-[a-z0-9]+)*',
        'gameService' => '[a-z0-9]+(?:-[a-z0-9]+)*',
    ])
    ->scopeBindings()
    ->name('game-services.service');

Route::post('/dich-vu-game-{game:slug}/{gameService:slug}/dat-don', [GameServiceOrderController::class, 'store'])
    ->middleware(['site.active', 'throttle:10,1'])
    ->where([
        'game' => '[a-z0-9]+(?:-[a-z0-9]+)*',
        'gameService' => '[a-z0-9]+(?:-[a-z0-9]+)*',
    ])
    ->scopeBindings()
    ->name('game-services.orders.store');

Route::get('/dich-vu-game-{game:slug}', GameServiceController::class)
    ->middleware('site.active')
    ->where('game', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('game-services.show');
