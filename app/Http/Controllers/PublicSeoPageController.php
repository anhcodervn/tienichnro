<?php

namespace App\Http\Controllers;

use App\Models\SeoCategory;
use App\Models\SeoPost;
use App\Support\EditorContentRenderer;
use App\Support\SettingStore;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PublicSeoPageController extends Controller
{
    public function __construct(
        protected EditorContentRenderer $contentRenderer,
    ) {}

    public function index(Request $request, SettingStore $settingStore): View
    {
        return $this->renderIndex($request, $settingStore);
    }

    public function category(string $slug, Request $request, SettingStore $settingStore): View|RedirectResponse
    {
        $category = SeoCategory::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if ($category instanceof SeoCategory) {
            return $this->renderCategory($category, $request, $settingStore);
        }

        return $this->legacyShow($slug, $request, $settingStore);
    }

    protected function renderCategory(SeoCategory $category, Request $request, SettingStore $settingStore): View
    {
        $search = trim($request->string('q')->toString());
        $posts = $this->publishedPosts()
            ->with('category:id,name,slug,is_active')
            ->where('seo_category_id', $category->id)
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%")
                        ->orWhere('focus_keyword', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (SeoPost $post): array => $this->transformPost($post));

        $categories = SeoCategory::query()
            ->where('is_active', true)
            ->withCount([
                'posts' => fn (Builder $query) => $query
                    ->where('status', 'published')
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now()),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $systemSettings = $this->systemSettings($settingStore);
        $siteName = $systemSettings['site_name'] ?: config('app.name', 'Nạp Carot');
        $pageTitle = $category->seo_title ?: $category->name;
        $pageDescription = $category->seo_description ?: "Tổng hợp bài viết mới nhất trong danh mục {$category->name}.";
        $categoryUrl = route('seo.category', $category->slug);
        $canonicalUrl = $posts->currentPage() > 1
            ? $categoryUrl.'?page='.$posts->currentPage()
            : $categoryUrl;

        return view('pages.seo.category', [
            'systemSettings' => $systemSettings,
            'category' => $category,
            'categories' => $categories,
            'posts' => $posts,
            'search' => $search,
            'pageTitle' => $pageTitle,
            'pageDescription' => $pageDescription,
            'pageMetaTitle' => $search !== '' ? "Tìm kiếm: {$search} | {$pageTitle}" : $pageTitle.' | '.$siteName,
            'pageMetaDescription' => $pageDescription,
            'pageMetaCanonical' => $search !== '' ? $categoryUrl : $canonicalUrl,
            'pageMetaRobots' => $search !== '' ? 'noindex,follow' : ($category->robots ?: 'index,follow'),
        ]);
    }

    public function legacyShow(string $slug, Request $request, SettingStore $settingStore): View|RedirectResponse
    {
        $post = $this->publishedPosts()
            ->with('category:id,name,slug,is_active')
            ->where('slug', $slug)
            ->firstOrFail();

        if ($post->category?->is_active) {
            return redirect()->to($this->postUrl($post), 301);
        }

        return $this->renderPost($post, $request, $settingStore);
    }

    public function show(string $categorySlug, string $postSlug, Request $request, SettingStore $settingStore): View
    {
        $post = $this->publishedPosts()
            ->with('category:id,name,slug,is_active')
            ->where('slug', $postSlug)
            ->whereHas('category', function (Builder $query) use ($categorySlug): void {
                $query->where('slug', $categorySlug)->where('is_active', true);
            })
            ->firstOrFail();

        return $this->renderPost($post, $request, $settingStore);
    }

    protected function renderIndex(Request $request, SettingStore $settingStore, ?SeoCategory $activeCategory = null): View
    {
        $search = trim($request->string('q')->toString());
        $categorySlug = $activeCategory?->slug ?: trim($request->string('category')->toString());

        $baseQuery = $this->publishedPosts()
            ->with(['category:id,name,slug,is_active'])
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%")
                        ->orWhere('focus_keyword', 'like', "%{$search}%");
                });
            })
            ->when($categorySlug !== '', function (Builder $builder) use ($categorySlug): void {
                $builder->whereHas('category', function (Builder $query) use ($categorySlug): void {
                    $query
                        ->where('slug', $categorySlug)
                        ->where('is_active', true);
                });
            });

        $posts = (clone $baseQuery)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->take(7)
            ->get();

        $featuredPost = $posts->first();
        $latestPosts = $posts->skip($featuredPost ? 1 : 0)->take(6)->values();

        $categories = SeoCategory::query()
            ->where('is_active', true)
            ->withCount([
                'posts' => fn (Builder $query) => $query
                    ->where('status', 'published')
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now()),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $systemSettings = $this->systemSettings($settingStore);
        $defaultTitle = 'Tin tức và hướng dẫn nạp game Teamobi';
        $defaultDescription = 'Hướng dẫn chọn gói Carot, thanh toán an toàn và xử lý các tình huống thường gặp khi nạp game Teamobi.';
        $pageTitle = $activeCategory?->seo_title ?: ($activeCategory?->name ?: $defaultTitle);
        $pageDescription = $activeCategory?->seo_description ?: $defaultDescription;
        $pageUrl = $activeCategory
            ? route('seo.category', $activeCategory->slug)
            : route('seo.index');

        return view('pages.seo.index', [
            'systemSettings' => $systemSettings,
            'pageTitle' => $pageTitle,
            'pageDescription' => $pageDescription,
            'pageMetaTitle' => $search !== ''
                ? "Tìm kiếm: {$search} | {$pageTitle}"
                : $pageTitle.' | '.($systemSettings['site_name'] ?: config('app.name', 'Nạp Carot')),
            'pageMetaDescription' => $pageDescription,
            'pageMetaUrl' => $pageUrl,
            'pageMetaRobots' => $search !== '' ? 'noindex,follow' : ($activeCategory?->robots ?: 'index,follow'),
            'featuredPost' => $featuredPost ? $this->transformPost($featuredPost) : null,
            'latestPosts' => $latestPosts->map(fn (SeoPost $post) => $this->transformPost($post)),
            'categories' => $categories,
            'activeCategorySlug' => $categorySlug,
            'activeCategory' => $activeCategory,
            'search' => $search,
            'popularTags' => $posts
                ->pluck('focus_keyword')
                ->filter()
                ->map(fn (string $tag) => trim($tag))
                ->unique()
                ->take(8)
                ->values(),
        ]);
    }

    protected function renderPost(SeoPost $post, Request $request, SettingStore $settingStore): View
    {
        $relatedPosts = $this->publishedPosts()
            ->with('category:id,name,slug,is_active')
            ->where('id', '!=', $post->id)
            ->when($post->seo_category_id, fn (Builder $builder) => $builder->where('seo_category_id', $post->seo_category_id))
            ->orderByDesc('published_at')
            ->take(3)
            ->get();

        $sidebarCategories = SeoCategory::query()
            ->where('is_active', true)
            ->withCount([
                'posts' => fn (Builder $query) => $query
                    ->where('status', 'published')
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now()),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $systemSettings = $this->systemSettings($settingStore);
        $content = is_array($post->content) ? $post->content : [];
        $contentHtml = $this->contentRenderer->renderNodes($content);
        $coverImage = $post->cover_image ?: $this->contentRenderer->firstImage($content);
        $headingIndex = $this->contentRenderer->headingIndex($content);
        $postUrl = $this->postUrl($post);
        $siteName = $systemSettings['site_name'] ?: config('app.name', 'Nạp Carot');
        $absoluteCoverImage = $coverImage && ! Str::startsWith($coverImage, ['http://', 'https://']) ? url($coverImage) : $coverImage;
        $canonicalUrl = $this->canonicalUrl($post, $request, $postUrl);

        return view('pages.seo.show', [
            'systemSettings' => $systemSettings,
            'post' => $post,
            'contentHtml' => $contentHtml,
            'coverImage' => $coverImage,
            'readingMinutes' => $this->contentRenderer->estimateReadingMinutes($content),
            'headingIndex' => $headingIndex,
            'relatedPosts' => $relatedPosts->map(fn (SeoPost $item) => $this->transformPost($item)),
            'sidebarCategories' => $sidebarCategories,
            'pageMetaTitle' => $post->seo_title ?: $post->title.' | '.$siteName,
            'pageMetaDescription' => $post->seo_description ?: ($post->excerpt ?: $this->contentRenderer->extractText($content)),
            'pageMetaCanonical' => $canonicalUrl,
            'pageMetaUrl' => $postUrl,
            'pageMetaImage' => $coverImage,
            'articleSchema' => $post->article_schema ? array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $post->title,
                'description' => $post->seo_description ?: $post->excerpt,
                'image' => $absoluteCoverImage,
                'datePublished' => $post->published_at?->toAtomString(),
                'dateModified' => $post->updated_at?->toAtomString(),
                'mainEntityOfPage' => $canonicalUrl,
                'publisher' => ['@type' => 'Organization', 'name' => $siteName],
            ]) : null,
            'breadcrumbSchema' => $post->breadcrumb_schema ? [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => collect([
                    ['name' => 'Trang chủ', 'url' => route('home')],
                    ['name' => 'Tin tức', 'url' => route('seo.index')],
                    $post->category ? ['name' => $post->category->name, 'url' => route('seo.category', $post->category->slug)] : null,
                    ['name' => $post->title, 'url' => $canonicalUrl],
                ])->filter()->values()->map(fn (array $item, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $item['name'],
                    'item' => $item['url'],
                ])->all(),
            ] : null,
        ]);
    }

    protected function publishedPosts(): Builder
    {
        return SeoPost::query()
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('seo_category_id')
                    ->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('is_active', true));
            });
    }

    protected function postUrl(SeoPost $post): string
    {
        if ($post->category?->is_active && $post->category->slug) {
            return route('seo.show', [
                'categorySlug' => $post->category->slug,
                'postSlug' => $post->slug,
            ]);
        }

        return route('seo.legacy.show', $post->slug);
    }

    protected function canonicalUrl(SeoPost $post, Request $request, string $postUrl): string
    {
        $canonicalUrl = trim((string) $post->canonical_url);

        if ($canonicalUrl === '') {
            return $postUrl;
        }

        $canonicalHost = parse_url($canonicalUrl, PHP_URL_HOST);
        $canonicalPath = parse_url($canonicalUrl, PHP_URL_PATH);
        $legacyPaths = ['/tin-tuc/'.$post->slug, '/bai-viet/'.$post->slug];

        return $canonicalHost === $request->getHost() && in_array($canonicalPath, $legacyPaths, true)
            ? $postUrl
            : $canonicalUrl;
    }

    protected function systemSettings(SettingStore $settingStore): array
    {
        return $settingStore->getMany([
            'site_name' => config('app.name', 'Nạp Carot'),
            'site_domain' => '',
            'site_description' => '',
            'support_email' => '',
            'hotline' => '',
            'address' => '',
            'facebook' => '',
            'zalo' => '',
            'youtube' => '',
            'meta_title' => '',
            'meta_description' => '',
            'light_logo' => '',
            'dark_logo' => '',
            'favicon' => '',
            'og_image' => '',
            'gtm_id' => '',
            'meta_pixel_id' => '',
        ]);
    }

    protected function transformPost(SeoPost $post): array
    {
        $content = is_array($post->content) ? $post->content : [];
        $publishedAt = $post->published_at instanceof Carbon ? $post->published_at : null;

        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt ?: $this->contentRenderer->extractText($content),
            'cover_image' => $post->cover_image ?: $this->contentRenderer->firstImage($content),
            'focus_keyword' => $post->focus_keyword,
            'category_name' => $post->category?->name,
            'category_slug' => $post->category?->slug,
            'published_at' => $publishedAt,
            'published_label' => $publishedAt?->format('d/m/Y'),
            'reading_minutes' => $this->contentRenderer->estimateReadingMinutes($content),
            'url' => $this->postUrl($post),
        ];
    }
}
