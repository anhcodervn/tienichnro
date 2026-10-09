<?php

use App\Features\Admin\User\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin'])
    ->prefix('admin-api/users')
    ->name('admin.users.')
    ->controller(UserController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('{user}', 'show')->name('show');
        Route::patch('{user}/status', 'updateStatus')->name('status.update');
        Route::post('{user}/reset-password', 'resetPassword')->name('password.reset');
        Route::get('{user}/logs', 'logs')->name('logs.index');
    });
