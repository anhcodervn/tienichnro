<?php

use App\Features\Admin\Reporting\Controllers\ReportingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin'])
    ->prefix('admin-api/reports')
    ->name('admin.reporting.')
    ->group(function (): void {
        Route::get('/topup', [ReportingController::class, 'topup'])->name('topup');
        Route::get('/game-services', [ReportingController::class, 'gameServices'])
            ->middleware('platform.admin')
            ->name('game-services');
    });
