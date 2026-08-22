<?php

use App\Features\Client\Profile\Controllers\ApiTokenDestroyController;
use App\Features\Client\Profile\Controllers\ApiTokenStoreController;
use App\Features\Client\Profile\Controllers\PasswordUpdateController;
use App\Features\Client\Profile\Controllers\ProfilePageController;
use App\Features\Client\Profile\Controllers\ProfileUpdateController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('tai-khoan')->name('account.profile.')->group(function (): void {
    Route::get('/thong-tin', ProfilePageController::class)->defaults('tab', 'profile')->name('edit');
    Route::patch('/thong-tin', ProfileUpdateController::class)->name('update');

    Route::get('/doi-mat-khau', ProfilePageController::class)->defaults('tab', 'password')->name('password');
    Route::put('/doi-mat-khau', PasswordUpdateController::class)->middleware('throttle:5,1')->name('password.update');

    Route::get('/api-key', ProfilePageController::class)->defaults('tab', 'api')->name('api');
    Route::post('/api-key', ApiTokenStoreController::class)->middleware('throttle:10,1')->name('api.store');
    Route::delete('/api-key/{token}', ApiTokenDestroyController::class)->whereNumber('token')->name('api.destroy');

    Route::get('/lich-su', ProfilePageController::class)->defaults('tab', 'logs')->name('logs');
    Route::get('/dong-tien', ProfilePageController::class)->defaults('tab', 'wallet')->name('wallet');
});
