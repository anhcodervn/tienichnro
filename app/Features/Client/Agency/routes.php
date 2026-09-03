<?php

use App\Features\Client\Agency\Controllers\AgencyPageController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')
    ->prefix('dai-ly')
    ->name('client.agency.')
    ->controller(AgencyPageController::class)
    ->group(function (): void {
        Route::get('/tao-website', 'website')->name('website');
        Route::get('/ket-noi-api', 'api')->name('api');
    });
