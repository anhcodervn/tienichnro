<?php

use App\Features\Admin\Seo\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin', 'platform.admin'])
    ->prefix('admin-api/seo')
    ->name('admin-api.seo.')
    ->group(function (): void {
        Route::get('/overview', [SeoController::class, 'overview'])->name('overview');

        Route::get('/categories', [SeoController::class, 'categories'])->name('categories.index');
        Route::post('/categories', [SeoController::class, 'storeCategory'])->name('categories.store');
        Route::get('/categories/{seoCategory}', [SeoController::class, 'showCategory'])->name('categories.show');
        Route::patch('/categories/{seoCategory}', [SeoController::class, 'updateCategory'])->name('categories.update');
        Route::delete('/categories/{seoCategory}', [SeoController::class, 'destroyCategory'])->name('categories.destroy');

        Route::get('/posts', [SeoController::class, 'posts'])->name('posts.index');
        Route::get('/post-options', [SeoController::class, 'postOptions'])->name('posts.options');
        Route::post('/posts', [SeoController::class, 'storePost'])->name('posts.store');
        Route::get('/posts/{seoPost}', [SeoController::class, 'showPost'])->name('posts.show');
        Route::patch('/posts/{seoPost}', [SeoController::class, 'updatePost'])->name('posts.update');
        Route::delete('/posts/{seoPost}', [SeoController::class, 'destroyPost'])->name('posts.destroy');

        Route::get('/sitemaps', [SeoController::class, 'sitemaps'])->name('sitemaps.index');
    });
