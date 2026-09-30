<?php

use App\Features\Client\Affiliate\Controllers\AffiliateController;
use App\Features\Client\Affiliate\Controllers\AffiliateHomeController;
use App\Features\Client\Affiliate\Controllers\AffiliateWalletController;
use App\Features\Client\Affiliate\Controllers\CollaboratorDashboardController;
use App\Features\Client\Affiliate\Controllers\GameServiceOrderChatController;
use App\Features\Client\Affiliate\Controllers\SecondaryPasswordController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:60,1'])->prefix('client/affiliate')->name('client.affiliate.')->group(function (): void {
    Route::get('/home', [AffiliateHomeController::class, 'index'])->name('home');
    Route::get('/', [AffiliateController::class, 'index'])->name('index');
    Route::get('/rates', [AffiliateController::class, 'rates'])->name('rates.index');
    Route::put('/payout-account', [AffiliateController::class, 'updatePayout'])->name('payout.update');
    Route::post('/convert', [AffiliateWalletController::class, 'convert'])->middleware('throttle:10,1')->name('convert');
    Route::post('/withdrawals', [AffiliateWalletController::class, 'withdraw'])->middleware('throttle:5,1')->name('withdrawals.store');
    Route::post('/announcements/{affiliateAnnouncement}/read', [AffiliateHomeController::class, 'read'])->name('announcements.read');

    Route::middleware('role:admin,ctv')->group(function (): void {
        Route::get('/game-service-secondary-auth', [SecondaryPasswordController::class, 'show'])->name('game-service-secondary-auth.show');
        Route::post('/game-service-secondary-auth', [SecondaryPasswordController::class, 'store'])
            ->middleware('throttle:game-service-secondary-auth')->name('game-service-secondary-auth.store');
        Route::get('/game-service-orders', [GameServiceOrderChatController::class, 'index'])->name('game-service-orders.index');
        Route::get('/game-service-dashboard', [CollaboratorDashboardController::class, 'index'])->name('game-service-dashboard.index');
        Route::get('/game-service-announcements', [CollaboratorDashboardController::class, 'announcements'])->name('game-service-announcements.index');
        Route::post('/game-service-announcements/{affiliateAnnouncement}/read', [CollaboratorDashboardController::class, 'readAnnouncement'])
            ->name('game-service-announcements.read');
        Route::get('/game-service-finance', [CollaboratorDashboardController::class, 'finance'])->name('game-service-finance.index');
        Route::middleware('game-service.secondary')->group(function (): void {
            Route::get('/game-service-order-chats', [GameServiceOrderChatController::class, 'chats'])->name('game-service-order-chats.index');
            Route::get('/game-service-orders/{gameServiceOrder}/payload', [GameServiceOrderChatController::class, 'payload'])
                ->name('game-service-orders.payload');
            Route::get('/game-service-orders/{gameServiceOrder}/messages', [GameServiceOrderChatController::class, 'show'])->name('game-service-orders.messages.index');
            Route::post('/game-service-orders/{gameServiceOrder}/messages', [GameServiceOrderChatController::class, 'store'])
                ->middleware('throttle:game-service-chat-message')->name('game-service-orders.messages.store');
            Route::put('/game-service-payout-account', [CollaboratorDashboardController::class, 'updatePayout'])->name('game-service-payout.update');
            Route::post('/game-service-withdrawals', [CollaboratorDashboardController::class, 'withdraw'])
                ->middleware('throttle:game-service-withdrawal')->name('game-service-withdrawals.store');
            Route::post('/game-service-orders/{gameServiceOrder}/start', [CollaboratorDashboardController::class, 'start'])->name('game-service-orders.start');
            Route::post('/game-service-orders/{gameServiceOrder}/submit', [CollaboratorDashboardController::class, 'submit'])->name('game-service-orders.submit');
        });
    });
});
