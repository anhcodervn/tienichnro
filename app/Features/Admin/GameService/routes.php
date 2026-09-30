<?php

use App\Features\Admin\GameService\Controllers\GameServiceAnnouncementController;
use App\Features\Admin\GameService\Controllers\GameServiceController;
use App\Features\Admin\GameService\Controllers\GameServiceGameController;
use App\Features\Admin\GameService\Controllers\GameServiceOrderChatController;
use App\Features\Admin\GameService\Controllers\GameServiceOrderController;
use App\Features\Admin\GameService\Controllers\GameServicePackageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin', 'platform.admin'])
    ->prefix('admin-api')
    ->name('admin.game-services.')
    ->group(function (): void {
        Route::get('game-service-announcements', [GameServiceAnnouncementController::class, 'index'])->name('announcements.index');
        Route::post('game-service-announcements', [GameServiceAnnouncementController::class, 'store'])->name('announcements.store');
        Route::put('game-service-announcements/{announcement}', [GameServiceAnnouncementController::class, 'update'])->name('announcements.update');
        Route::delete('game-service-announcements/{announcement}', [GameServiceAnnouncementController::class, 'destroy'])->name('announcements.destroy');
        Route::get('game-service-games', [GameServiceGameController::class, 'index'])->name('games.index');
        Route::patch('game-service-games/{game}', [GameServiceGameController::class, 'update'])->name('games.update');
        Route::apiResource('game-services', GameServiceController::class)->parameters(['game-services' => 'gameService']);
        Route::apiResource('game-service-packages', GameServicePackageController::class)->parameters(['game-service-packages' => 'gameServicePackage']);
        Route::get('game-service-order-chats/collaborators', [GameServiceOrderChatController::class, 'collaborators'])->name('chats.collaborators');
        Route::get('game-service-order-chats', [GameServiceOrderChatController::class, 'index'])->name('chats.index');
        Route::get('game-service-order-chats/{gameServiceOrder}', [GameServiceOrderChatController::class, 'show'])->name('chats.show');
        Route::post('game-service-order-chats/{gameServiceOrder}/messages', [GameServiceOrderChatController::class, 'store'])
            ->middleware('throttle:30,1')->name('chats.messages.store');
        Route::get('game-service-orders/review-count', [GameServiceOrderController::class, 'reviewCount'])
            ->name('orders.review-count');
        Route::get('game-service-orders/{gameServiceOrder}/payload', [GameServiceOrderController::class, 'payload'])
            ->middleware('game-service.secondary')->name('orders.payload');
        Route::get('game-service-orders/{gameServiceOrder}/progress', [GameServiceOrderController::class, 'progress'])
            ->name('orders.progress.index');
        Route::patch('game-service-orders/{gameServiceOrder}/approve-completion', [GameServiceOrderController::class, 'approveCompletion'])
            ->name('orders.approve-completion');
        Route::patch('game-service-orders/{gameServiceOrder}/refund', [GameServiceOrderController::class, 'refund'])
            ->name('orders.refund');
        Route::apiResource('game-service-orders', GameServiceOrderController::class)
            ->only(['index', 'show', 'update'])
            ->parameters(['game-service-orders' => 'gameServiceOrder']);
    });
