<?php

use App\Features\Client\Potential\Controllers\PotentialController;
use Illuminate\Support\Facades\Route;

Route::get('/tinh-tiem-nang', [PotentialController::class, 'index'])->name('tools.potential');
Route::post('/tinh-tiem-nang', [PotentialController::class, 'calculate'])->middleware('throttle:30,1')->name('tools.potential.calculate');
