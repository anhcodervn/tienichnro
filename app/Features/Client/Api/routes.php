<?php

use App\Features\Client\Api\Controllers\BalanceController;
use App\Features\Client\Api\Controllers\TopupTaskController;
use App\Http\Middleware\AuthenticateApiCredentials;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {
        Route::get('/balance', BalanceController::class)
            ->middleware([AuthenticateApiCredentials::class.':balance:read', 'site.active', 'throttle:60,1'])
            ->name('balance');

        Route::post('/tasks', [TopupTaskController::class, 'store'])
            ->middleware([AuthenticateApiCredentials::class.':tasks:create', 'site.active', 'throttle:10,1'])
            ->name('tasks.store');
        Route::get('/tasks/{task}', [TopupTaskController::class, 'show'])
            ->whereAlphaNumeric('task')
            ->middleware([AuthenticateApiCredentials::class.':tasks:read', 'site.active', 'throttle:60,1'])
            ->name('tasks.show');
    });
