<?php

namespace App\Features\Admin\Seo\Controllers;

use App\Features\Admin\Seo\Requests\UpdateGameSeoRequest;
use App\Features\Admin\Seo\Requests\UpdateHomeSeoRequest;
use App\Features\Admin\Seo\Requests\UpsertSeoCategoryRequest;
use App\Features\Admin\Seo\Requests\UpsertSeoPostRequest;
use App\Features\Admin\Seo\Services\SeoService;
use App\Features\Topup\Services\HomeSeoService;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\SeoCategory;
use App\Models\SeoPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeoController extends Controller
{
    public function __construct(
        protected SeoService $seoService,
        protected HomeSeoService $homeSeoService,
    ) {}

    public function overview(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $this->seoService->overview(),
        ]);
    }

    public function home(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $this->homeSeoService->settings(),
        ]);
    }

    public function updateHome(UpdateHomeSeoRequest $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Cập nhật SEO trang chủ thành công.',
            'data' => $this->homeSeoService->update($request->validated()),
        ]);
    }

    public function games(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $this->seoService->gameSeoSettings(),
        ]);
    }

    public function updateGame(UpdateGameSeoRequest $request, Game $game): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Cập nhật SEO game thành công.',
            'data' => $this->seoService->updateGameSeoSettings($game, $request->validated()),
        ]);
    }

    public function categories(Request $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => [
                'categories' => $this->seoService->listCategories(
                    trim($request->string('search')->toString()),
                ),
            ],
        ]);
    }

    public function storeCategory(UpsertSeoCategoryRequest $request): JsonResponse
    {
        $category = $this->seoService->upsertCategory($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Tạo danh mục SEO thành công.',
            'data' => $category,
        ], 201);
    }

    public function showCategory(SeoCategory $seoCategory): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $seoCategory->loadCount('posts'),
        ]);
    }

    public function updateCategory(UpsertSeoCategoryRequest $request, SeoCategory $seoCategory): JsonResponse
    {
        $category = $this->seoService->upsertCategory($request->validated(), $seoCategory);

        return response()->json([
            'status' => true,
            'message' => 'Cập nhật danh mục SEO thành công.',
            'data' => $category,
        ]);
    }

    public function destroyCategory(SeoCategory $seoCategory): JsonResponse
    {
        $seoCategory->delete();

        return response()->json([
            'status' => true,
            'message' => 'Đã xóa danh mục SEO.',
        ]);
    }

    public function posts(Request $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => [
                'posts' => $this->seoService->listPosts([
                    'search' => $request->string('search')->toString(),
                    'status' => $request->string('status')->toString(),
                ]),
                'categories' => SeoCategory::query()
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ],
        ]);
    }

    public function postOptions(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $this->seoService->postOptions(),
        ]);
    }

    public function storePost(UpsertSeoPostRequest $request): JsonResponse
    {
        $post = $this->seoService->upsertPost($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Tạo bài viết SEO thành công.',
            'data' => $post,
        ], 201);
    }

    public function showPost(SeoPost $seoPost): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $seoPost->load(['category:id,name', 'service:id,name,slug,status']),
        ]);
    }

    public function updatePost(UpsertSeoPostRequest $request, SeoPost $seoPost): JsonResponse
    {
        $post = $this->seoService->upsertPost($request->validated(), $seoPost);

        return response()->json([
            'status' => true,
            'message' => 'Cập nhật bài viết SEO thành công.',
            'data' => $post,
        ]);
    }

    public function destroyPost(SeoPost $seoPost): JsonResponse
    {
        $seoPost->delete();

        return response()->json([
            'status' => true,
            'message' => 'Đã xóa bài viết SEO.',
        ]);
    }

    public function sitemaps(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => [
                'entries' => $this->seoService->sitemapSummary(),
            ],
        ]);
    }
}
