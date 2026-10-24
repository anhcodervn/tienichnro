<?php

namespace App\Features\Admin\Seo\Services;

use App\Exceptions\ApiException;
use App\Models\SeoCategory;
use App\Models\SeoPost;
use App\Support\EditorContentRenderer;
use App\Support\SettingStore;
use App\Support\SitemapUrlService;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeoService
{
    public function __construct(
        protected SitemapUrlService $sitemapUrlService,
        protected SettingStore $settingStore,
        protected EditorContentRenderer $contentRenderer,
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
                'sitemap_files' => 4,
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

    public function deleteCategory(SeoCategory $category): int
    {
        return DB::transaction(function () use ($category): int {
            $detachedPostCount = $category->posts()->update(['seo_category_id' => null]);
            $category->delete();

            return $detachedPostCount;
        });
    }

    public function listPosts(array $filters = []): Collection
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        return SeoPost::query()
            ->with('category:id,name')
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
        return DB::transaction(function () use ($payload, $post): SeoPost {
            $post = $post?->exists
                ? SeoPost::query()->whereKey($post->id)->lockForUpdate()->firstOrFail()
                : new SeoPost;

            if (array_key_exists('expected_version', $payload) && $post->lock_version !== (int) $payload['expected_version']) {
                throw new ApiException('Bài viết đã được cập nhật ở phiên khác. Hãy tải lại trước khi lưu để tránh ghi đè dữ liệu.', 409);
            }

            if ($post->exists && $post->status === 'published' && (! $post->published_at || $post->published_at->lte(now()))) {
                $post->revisions()->create([
                    'snapshot' => $post->only($post->getFillable()),
                    'published_at' => $post->published_at,
                ]);
            }

            $post->fill(Arr::only($payload, $post->getFillable()));
            $content = is_array($post->content) ? $post->content : [];
            $post->title = Str::limit($this->contentRenderer->firstContentText($content, 'heading', 1)
                ?: ($payload['title'] ?? $post->seo_title ?: $post->title ?: 'Bài viết SEO'), 255, '');
            $post->excerpt = $this->contentRenderer->firstContentText($content, 'paragraph')
                ?? ($payload['excerpt'] ?? $post->seo_description ?? $post->excerpt);
            $post->status = 'published';
            $post->published_at = now();
            $post->scheduled_at = null;
            $post->lock_version++;
            $post->save();

            $retainedIds = $post->revisions()->latest('id')->limit(10)->pluck('id');
            $post->revisions()->whereNotIn('id', $retainedIds)->delete();

            return $post->fresh()->load('category:id,name');
        }, 3);
    }

    public function postOptions(): array
    {
        return [
            'categories' => SeoCategory::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'services' => [],
        ];
    }

    public function sitemapSummary(): array
    {
        return [
            [
                'title' => 'Sitemap index',
                'path' => '/sitemap.xml',
                'description' => 'Tệp index để submit Google Search Console, liên kết tới bốn sitemap nội dung.',
                'included_count' => '3 sitemap',
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
        ];
    }
}
