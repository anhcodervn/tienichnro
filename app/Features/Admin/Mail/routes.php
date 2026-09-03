<?php

use App\Features\Admin\Mail\Controllers\MailController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin', 'platform.admin'])
    ->prefix('admin-api/mail')
    ->name('admin.mail.')
    ->controller(MailController::class)
    ->group(function (): void {
        Route::get('users', 'users')->name('users');
        Route::post('send', 'send')->name('send');
    });
