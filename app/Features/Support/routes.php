<?php

use App\Features\Support\Controllers\AdminSupportController;
use App\Features\Support\Controllers\ClientSupportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('client/support')
    ->name('client.support.')
    ->controller(ClientSupportController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/unread', 'unread')->name('unread');
        Route::post('/messages', 'store')->middleware('throttle:support-user-message')->name('messages.store');
        Route::post('/read', 'markRead')->name('read');
    });

Route::middleware(['auth:sanctum', 'admin'])
    ->prefix('admin-api/support')
    ->name('admin.support.')
    ->controller(AdminSupportController::class)
    ->group(function (): void {
        Route::get('/conversations', 'index')->name('conversations.index');
        Route::post('/conversations', 'store')->middleware('throttle:20,1')->name('conversations.store');
        Route::get('/conversations/{conversation}', 'show')->whereNumber('conversation')->name('conversations.show');
        Route::post('/conversations/{conversation}/messages', 'reply')->whereNumber('conversation')->middleware('throttle:30,1')->name('messages.store');
        Route::post('/conversations/{conversation}/read', 'markRead')->whereNumber('conversation')->name('read');
        Route::get('/users', 'users')->name('users.index');
        Route::get('/unread', 'unread')->name('unread');
    });
