<?php

use App\Features\Client\Profile\Controllers\PasswordUpdateController;
use App\Features\Client\Profile\Controllers\ProfilePageController;
use App\Features\Client\Profile\Controllers\ProfileUpdateController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('tai-khoan')->name('account.profile.')->group(function (): void {
    Route::get('/thong-tin', ProfilePageController::class)->defaults('tab', 'profile')->name('edit');
    Route::patch('/thong-tin', ProfileUpdateController::class)->name('update');

    Route::get('/doi-mat-khau', ProfilePageController::class)->defaults('tab', 'password')->name('password');
    Route::put('/doi-mat-khau', PasswordUpdateController::class)->middleware('throttle:5,1')->name('password.update');

    Route::get('/lich-su', ProfilePageController::class)->defaults('tab', 'logs')->name('logs');
});
