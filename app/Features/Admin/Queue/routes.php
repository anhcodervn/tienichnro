<?php

use App\Features\Admin\Queue\Controllers\QueueController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin', 'platform.admin'])
    ->prefix('admin-api/queues')
    ->name('admin.queues.')
    ->controller(QueueController::class)
    ->group(function (): void {
        Route::get('overview', 'overview')->name('overview');
        Route::get('logs', 'logs')->name('logs');
        Route::post('logs/{queueLog}/replay', 'replayLog')->name('logs.replay');
        Route::get('failed-jobs', 'failedJobs')->name('failed-jobs');
        Route::post('failed-jobs/{uuid}/retry', 'retryFailedJob')->whereUuid('uuid')->name('failed-jobs.retry');
        Route::delete('failed-jobs/{uuid}', 'deleteFailedJob')->whereUuid('uuid')->name('failed-jobs.delete');
    });
