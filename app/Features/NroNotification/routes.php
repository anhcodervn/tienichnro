<?php

use App\Features\NroNotification\Controllers\BossController;
use App\Features\NroNotification\Controllers\NotificationPruneController;
use App\Features\NroNotification\Controllers\NotificationTypeController;
use App\Features\NroNotification\Controllers\NotifyController;
use App\Features\NroNotification\Controllers\ServerController;
use App\Features\NroNotification\Controllers\ZaloReceiveNotificationController;
use App\Features\NroNotification\Middleware\LimitNotificationTraffic;
use Illuminate\Support\Facades\Route;

Route::post('nro/cron/prune-notifies', NotificationPruneController::class)
    ->middleware('throttle:6,1')->name('nro.notifies.prune');

Route::prefix('nro')->name('nro.')->middleware('web')->group(function (): void {
    Route::get('/notifies', [NotifyController::class, 'index'])->middleware(LimitNotificationTraffic::class)->name('notifies.index');
    Route::get('/notifies/stream', [NotifyController::class, 'stream'])->middleware(LimitNotificationTraffic::class.':stream')->name('notifies.stream');
    Route::get('/options', [NotifyController::class, 'options'])->middleware(LimitNotificationTraffic::class)->name('options');
});
Route::prefix('admin-api/nro')->name('admin.nro.')->middleware(['auth:sanctum', 'admin', 'platform.admin', 'throttle:120,1'])->group(function (): void {
    Route::get('/zalo-receivers', [ZaloReceiveNotificationController::class, 'index'])->name('zalo-receivers.index');
    Route::post('/zalo-receivers', [ZaloReceiveNotificationController::class, 'store'])->name('zalo-receivers.store');
    Route::patch('/zalo-receivers/{receiver}', [ZaloReceiveNotificationController::class, 'update'])->whereNumber('receiver')->name('zalo-receivers.update');
    Route::delete('/zalo-receivers/{receiver}', [ZaloReceiveNotificationController::class, 'destroy'])->whereNumber('receiver')->name('zalo-receivers.destroy');
    Route::get('/servers', [ServerController::class, 'index'])->name('servers.index');
    Route::post('/servers', [ServerController::class, 'store'])->name('servers.store');
    Route::patch('/servers/{server}', [ServerController::class, 'update'])->name('servers.update');
    Route::post('/notifies', [NotifyController::class, 'store'])->name('notifies.store');
    Route::get('/notifies', [NotifyController::class, 'index'])->name('notifies.index');
    Route::get('/notification-types', [NotificationTypeController::class, 'index'])->name('notification-types.index');
    Route::post('/notification-types', [NotificationTypeController::class, 'store'])->name('notification-types.store');
    Route::patch('/notification-types/{notificationType}', [NotificationTypeController::class, 'update'])->name('notification-types.update');
    Route::delete('/notification-types/{notificationType}', [NotificationTypeController::class, 'destroy'])->name('notification-types.destroy');
    Route::get('/bosses', [BossController::class, 'index'])->name('bosses.index');
    Route::post('/bosses', [BossController::class, 'store'])->name('bosses.store');
    Route::patch('/bosses/{boss}', [BossController::class, 'update'])->name('bosses.update');
    Route::delete('/bosses/{boss}', [BossController::class, 'destroy'])->name('bosses.destroy');
});
