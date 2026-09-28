<?php

use App\Features\Admin\Upload\Controllers\ImageUploadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin', 'throttle:30,1'])
    ->prefix('uploads')
    ->name('admin.uploads.')
    ->controller(ImageUploadController::class)
    ->group(function (): void {
        Route::post('/image', 'store')->name('image.store');
        Route::post('/image/import', 'import')->name('image.import');
    });
