<?php

use App\Features\Admin\Affiliate\Controllers\AffiliateAnnouncementController;
use App\Features\Admin\Affiliate\Controllers\AffiliateCommissionController;
use App\Features\Admin\Affiliate\Controllers\AffiliateController;
use App\Features\Admin\Affiliate\Controllers\AffiliatePartnerController;
use App\Features\Admin\Affiliate\Controllers\AffiliateProgramController;
use App\Features\Admin\Affiliate\Controllers\AffiliateWithdrawalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin-api/affiliate')->name('admin.affiliate.')->group(function (): void {
    Route::get('/', [AffiliateController::class, 'index'])->name('index');
    Route::get('/announcements', [AffiliateAnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('/announcements', [AffiliateAnnouncementController::class, 'store'])->name('announcements.store');
    Route::put('/announcements/{announcement}', [AffiliateAnnouncementController::class, 'update'])->whereNumber('announcement')->name('announcements.update');
    Route::delete('/announcements/{announcement}', [AffiliateAnnouncementController::class, 'destroy'])->whereNumber('announcement')->name('announcements.destroy');
    Route::get('/configuration', [AffiliateProgramController::class, 'show'])->name('configuration.show');
    Route::put('/configuration', [AffiliateProgramController::class, 'update'])->name('configuration.update');
    Route::put('/global-rates/{globalTopupPackage}', [AffiliateProgramController::class, 'updateGlobalRate'])->name('global-rates.update');
    Route::put('/rates/{topupPackage}', [AffiliateProgramController::class, 'updateRate'])->name('rates.update');
    Route::delete('/rates/{topupPackage}', [AffiliateProgramController::class, 'resetRate'])->name('rates.reset');
    Route::get('/partners', [AffiliatePartnerController::class, 'index'])->name('partners.index');
    Route::patch('/partners/{profile}', [AffiliatePartnerController::class, 'update'])->whereNumber('profile')->name('partners.update');
    Route::post('/partners/{profile}/orders', [AffiliatePartnerController::class, 'assignOrder'])->whereNumber('profile')->name('partners.orders.store');
    Route::get('/commissions', [AffiliateCommissionController::class, 'index'])->name('commissions.index');
    Route::patch('/commissions/{commission}', [AffiliateCommissionController::class, 'update'])->whereNumber('commission')->name('commissions.update');
    Route::get('/withdrawals', [AffiliateWithdrawalController::class, 'index'])->name('withdrawals.index');
    Route::get('/withdrawals/{withdrawal}', [AffiliateWithdrawalController::class, 'show'])->whereNumber('withdrawal')->name('withdrawals.show');
    Route::patch('/withdrawals/{withdrawal}', [AffiliateWithdrawalController::class, 'update'])->whereNumber('withdrawal')->name('withdrawals.update');
});
