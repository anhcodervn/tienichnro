<?php

use App\Features\Client\GameService\Controllers\Account\GameServiceOrderController as AccountGameServiceOrderController;
use App\Features\Client\GameService\Controllers\GameServiceController;
use App\Features\Client\GameService\Controllers\GameServiceDetailController;
use App\Features\Client\GameService\Controllers\GameServiceOrderChatController;
use App\Features\Client\GameService\Controllers\GameServiceOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/tai-khoan/dich-vu-game', AccountGameServiceOrderController::class)
    ->middleware(['site.active', 'auth'])
    ->name('account.game-service-orders.index');

Route::middleware(['site.active', 'auth'])->group(function (): void {
    Route::delete('/tai-khoan/dich-vu-game/{gameServiceOrder}', [AccountGameServiceOrderController::class, 'destroy'])
        ->name('account.game-service-orders.cancel');
    Route::get('/tai-khoan/dich-vu-game/{gameServiceOrder}/chat', [GameServiceOrderChatController::class, 'page'])
        ->name('account.game-service-orders.chat');
    Route::get('/api/client/game-service-orders/{gameServiceOrder}/messages', [GameServiceOrderChatController::class, 'show'])
        ->name('client.game-service-orders.messages.index');
    Route::post('/api/client/game-service-orders/{gameServiceOrder}/messages', [GameServiceOrderChatController::class, 'store'])
        ->middleware('throttle:30,1')->name('client.game-service-orders.messages.store');
});

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
