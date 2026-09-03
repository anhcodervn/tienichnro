<?php

use App\Features\Admin\Notifications\Controllers\NotificationsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin', 'platform.admin'])
    ->prefix('admin-api/notifications')
    ->name('admin.notifications.')
    ->controller(NotificationsController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('{notification}', 'show')->name('show');
        Route::patch('{notification}', 'update')->name('update');
        Route::delete('{notification}', 'destroy')->name('destroy');
    });
