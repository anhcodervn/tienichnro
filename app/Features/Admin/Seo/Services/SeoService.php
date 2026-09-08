<?php

namespace App\Features\Admin\Seo\Services;

use App\Models\Game;
use App\Models\SeoCategory;
use App\Models\SeoPost;
use App\Support\SitemapUrlService;
use Illuminate\Support\Collection;

class SeoService
{
    public function __construct(
        protected SitemapUrlService $sitemapUrlService,
    ) {}

    public function overview(): array
    {
        $categoryCount = SeoCategory::query()->count();
        $indexedCategoryCount = SeoCategory::query()
            ->where('is_active', true)
            ->where('robots', 'index,follow')
            ->count();
        $postCount = SeoPost::query()->count();
        $publishedPostCount = SeoPost::query()->where('status', 'published')->count();
        $canonicalCount = SeoPost::query()
            ->whereNotNull('canonical_url')
            ->where('canonical_url', '!=', '')
            ->count();
        $schemaReadyCount = SeoPost::query()
            ->where('article_schema', true)
            ->where('breadcrumb_schema', true)
            ->count();

        return [
            'summary' => [
                'total_categories' => $categoryCount,
                'indexed_categories' => $indexedCategoryCount,
                'total_posts' => $postCount,
                'published_posts' => $publishedPostCount,
                'sitemap_files' => 5,
                'technical_score' => $postCount > 0
                    ? (int) round((($canonicalCount + $schemaReadyCount) / max(1, $postCount * 2)) * 100)
                    : 100,
            ],
            'sitemaps' => $this->sitemapSummary(),
        ];
    }

    public function listCategories(string $search = ''): Collection
    {
        return SeoCategory::query()
            ->withCount('posts')
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->where(function ($query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('seo_title', 'like', "%{$search}%");
                });
            })
            ->orderBy('sort_order')
            ->orderByDesc('updated_at')
            ->get();
    }

    public function upsertCategory(array $payload, ?SeoCategory $category = null): SeoCategory
    {
        $category ??= new SeoCategory;

        $category->fill([
            'name' => $payload['name'],
            'slug' => $payload['slug'],
            'seo_title' => $payload['seo_title'] ?? null,
            'seo_description' => $payload['seo_description'] ?? null,
            'robots' => $payload['robots'],
            'is_active' => $payload['is_active'] ?? true,
            'sort_order' => $payload['sort_order'] ?? 0,
        ]);

        $category->save();

        return $category->fresh()->loadCount('posts');
    }

    public function listPosts(array $filters = []): Collection
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        return SeoPost::query()
            ->with(['category:id,name', 'service:id,name,slug,status'])
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->where(function ($query) use ($search): void {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('seo_title', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($builder) => $builder->where('status', $status))
            ->orderByDesc('updated_at')
            ->get();
    }

    public function upsertPost(array $payload, ?SeoPost $post = null): SeoPost
    {
        $post ??= new SeoPost;

        $post->fill([
            'seo_category_id' => $payload['seo_category_id'] ?? null,
            'type' => $payload['type'],
            'service_id' => $payload['type'] === 'price' ? $payload['service_id'] : null,
            'title' => $payload['title'],
            'slug' => $payload['slug'],
            'excerpt' => $payload['excerpt'] ?? null,
            'content' => $payload['content'] ?? [],
            'faq' => $payload['faq'] ?? [],
            'cover_image' => $payload['cover_image'] ?? null,
            'seo_title' => $payload['seo_title'] ?? null,
            'seo_description' => $payload['seo_description'] ?? null,
            'canonical_url' => $payload['canonical_url'] ?? null,
            'robots' => $payload['robots'],
            'focus_keyword' => $payload['focus_keyword'] ?? null,
            'cover_alt' => $payload['cover_alt'] ?? null,
            'article_schema' => $payload['article_schema'] ?? true,
            'breadcrumb_schema' => $payload['breadcrumb_schema'] ?? true,
            'status' => $payload['status'],
            'published_at' => $payload['status'] === 'published'
                ? ($payload['published_at'] ?? now())
                : ($payload['published_at'] ?? null),
            'scheduled_at' => $payload['status'] === 'scheduled'
                ? ($payload['scheduled_at'] ?? null)
                : null,
        ]);

        $post->save();

        return $post->fresh()->load(['category:id,name', 'service:id,name,slug,status']);
    }

    public function postOptions(): array
    {
        return [
            'categories' => SeoCategory::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'services' => Game::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'status']),
        ];
    }

    public function sitemapSummary(): array
    {
        return [
            [
                'title' => 'Sitemap index',
                'path' => '/sitemap.xml',
                'description' => 'Tệp index để submit Google Search Console, liên kết tới bốn sitemap nội dung.',
                'included_count' => '4 sitemap',
            ],
            [
                'title' => 'Trang public',
                'path' => '/sitemap-pages.xml',
                'description' => 'Trang tĩnh và money page public có canonical riêng.',
                'included_count' => $this->sitemapUrlService->pageUrls()->count().' URL',
            ],
            [
                'title' => 'Bài viết',
                'path' => '/sitemap-articles.xml',
                'description' => 'Chỉ chứa bài published, index/follow và đúng canonical.',
                'included_count' => $this->sitemapUrlService->articleUrls()->count().' URL',
            ],
            [
                'title' => 'Danh mục',
                'path' => '/sitemap-categories.xml',
                'description' => 'Chỉ chứa danh mục đang hoạt động và index/follow.',
                'included_count' => $this->sitemapUrlService->categoryUrls()->count().' URL',
            ],
            [
                'title' => 'Game landing',
                'path' => '/sitemap-games.xml',
                'description' => 'Các money page nạp game Teamobi có canonical riêng.',
                'included_count' => $this->sitemapUrlService->gameUrls()->count().' URL',
            ],
        ];
    }
}
