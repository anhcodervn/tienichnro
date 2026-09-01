<?php

use App\Features\Admin\MemberLevel\Controllers\MemberLevelController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin'])
    ->prefix('admin-api/member-levels')
    ->name('admin.member_levels.')
    ->group(function (): void {
        Route::get('/', [MemberLevelController::class, 'index'])->name('index');
        Route::post('/', [MemberLevelController::class, 'store'])->name('store');
        Route::put('{memberLevel}', [MemberLevelController::class, 'update'])->name('update');
        Route::delete('{memberLevel}', [MemberLevelController::class, 'destroy'])->name('destroy');
        Route::put('{memberLevel}/packages/{topupPackage}', [MemberLevelController::class, 'upsertPackagePrice'])->name('package_prices.upsert');
        Route::delete('{memberLevel}/packages/{topupPackage}', [MemberLevelController::class, 'destroyPackagePrice'])->name('package_prices.destroy');
        Route::put('users/{user}/assignment', [MemberLevelController::class, 'assignUser'])->name('users.assign');
    });
