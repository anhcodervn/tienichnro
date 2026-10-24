<?php

use App\Features\Client\Profile\Controllers\DownloadLicenseProductController;
use App\Features\Client\Profile\Controllers\PasswordUpdateController;
use App\Features\Client\Profile\Controllers\ProfilePageController;
use App\Features\Client\Profile\Controllers\ProfileUpdateController;
use App\Features\Client\Profile\Controllers\RotateLicenseController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', fn () => redirect()->route('account.profile.dashboard'))
    ->middleware('auth')->name('dashboard');

Route::middleware('auth')->prefix('tai-khoan')->name('account.profile.')->group(function (): void {
    Route::get('/dashboard', ProfilePageController::class)->defaults('tab', 'dashboard')
        ->middleware('cache.headers:no_store;private')->name('dashboard');

    Route::post('/licenses/{license}/rotate', RotateLicenseController::class)
        ->whereNumber('license')->middleware('throttle:5,1')->name('licenses.rotate');

    Route::get('/licenses/{license}/download/{kind}', DownloadLicenseProductController::class)
        ->whereNumber('license')->whereIn('kind', ['tool', 'update'])->middleware('throttle:20,1')->name('licenses.download');

    Route::get('/thong-tin', ProfilePageController::class)->defaults('tab', 'profile')->name('edit');
    Route::patch('/thong-tin', ProfileUpdateController::class)->name('update');

    Route::get('/doi-mat-khau', ProfilePageController::class)->defaults('tab', 'password')->name('password');
    Route::put('/doi-mat-khau', PasswordUpdateController::class)->middleware('throttle:5,1')->name('password.update');

    Route::get('/lich-su', ProfilePageController::class)->defaults('tab', 'logs')->name('logs');
});
