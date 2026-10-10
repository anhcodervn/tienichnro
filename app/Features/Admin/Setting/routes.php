<?php

use App\Features\Admin\Setting\Controllers\ServiceCatalogController;
use App\Features\Admin\Setting\Controllers\ServiceManagementController;
use App\Features\Admin\Setting\Controllers\ServicePackageController;
use App\Features\Admin\Setting\Controllers\SettingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin'])
    ->prefix('admin-api/settings')
    ->name('admin-api.settings.')
    ->controller(SettingController::class)
    ->group(function (): void {
        Route::get('service-catalog', [ServiceCatalogController::class, 'index'])->name('service-catalog.index');
        Route::post('service-catalog', [ServiceCatalogController::class, 'store'])->name('service-catalog.store');
        Route::patch('service-catalog/{code}', [ServiceCatalogController::class, 'update'])->name('service-catalog.update');
        Route::delete('service-catalog/{code}', [ServiceCatalogController::class, 'destroy'])->name('service-catalog.destroy');
        Route::get('service-packages', [ServicePackageController::class, 'index'])->name('service-packages.index');
        Route::post('service-packages', [ServicePackageController::class, 'store'])->name('service-packages.store');
        Route::patch('service-packages/{package}', [ServicePackageController::class, 'update'])->whereNumber('package')->name('service-packages.update');
        Route::delete('service-packages/{package}', [ServicePackageController::class, 'destroy'])->whereNumber('package')->name('service-packages.destroy');
        Route::get('services', [ServiceManagementController::class, 'index'])->name('services.index');
        Route::post('services', [ServiceManagementController::class, 'store'])->name('services.store');
        Route::delete('services/{code}', [ServiceManagementController::class, 'destroy'])->name('services.destroy');
        Route::patch('services/{code}', [ServiceManagementController::class, 'update'])->name('services.update');
        Route::patch('system', 'updateSystem')->name('system.update');
        Route::patch('options', 'updateOptions')->name('options.update');
        Route::patch('{tab}', 'update')->name('update');
        Route::get('{tab}', 'show')->name('show');
    });
