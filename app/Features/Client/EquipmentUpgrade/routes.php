<?php

use App\Features\Client\EquipmentUpgrade\Controllers\EquipmentUpgradeController;
use Illuminate\Support\Facades\Route;

Route::get('/tinh-chi-so-trang-bi-nang-cap', [EquipmentUpgradeController::class, 'index'])->name('tools.equipment-upgrade');
Route::post('/tinh-chi-so-trang-bi-nang-cap', [EquipmentUpgradeController::class, 'calculate'])
    ->middleware('throttle:30,1')->name('tools.equipment-upgrade.calculate');
