<?php

namespace App\Http\Controllers;

use App\Models\SeoCategory;
use App\Models\SeoPost;
use App\Models\SeoRedirect;
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
                    ->whereIn('type', ['knowledge', 'guide'])
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
            'breadcrumbSchema' => [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    [
                        '@type' => 'ListItem',
                        'position' => 1,
                        'name' => 'Trang chủ',
                        'item' => route('home'),
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 2,
                        'name' => 'Bài viết',
                        'item' => route('seo.index'),
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 3,
                        'name' => $category->name,
                        'item' => $canonicalUrl,
                    ],
                ],
            ],
        ]);
    }

    public function legacyShow(string $slug, Request $request, SettingStore $settingStore): View|RedirectResponse
    {
        $post = $this->publishedPosts()
            ->with('category:id,name,slug,is_active')
            ->where('slug', $slug)
            ->first();

        if (! $post instanceof SeoPost) {
            return $this->storedRedirect($request) ?? abort(404);
        }

        if ($post->category?->is_active) {
            return redirect()->to($this->postUrl($post), 301);
        }

        return $this->renderPost($post, $request, $settingStore);
    }

    public function show(string $categorySlug, string $postSlug, Request $request, SettingStore $settingStore): View|RedirectResponse
    {
        $post = $this->publishedPosts()
            ->with('category:id,name,slug,is_active')
            ->where('slug', $postSlug)
            ->whereHas('category', function (Builder $query) use ($categorySlug): void {
                $query->where('slug', $categorySlug)->where('is_active', true);
            })
            ->first();

        if (! $post instanceof SeoPost) {
            return $this->storedRedirect($request) ?? abort(404);
        }

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
            ->paginate(12)
            ->withQueryString();

        $featuredPost = $posts->getCollection()->first();
        $latestPosts = $posts->getCollection()->skip($featuredPost ? 1 : 0)->values();

        $categories = SeoCategory::query()
            ->where('is_active', true)
            ->withCount([
                'posts' => fn (Builder $query) => $query
                    ->whereIn('type', ['knowledge', 'guide'])
                    ->where('status', 'published')
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now()),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $systemSettings = $this->systemSettings($settingStore);
        $defaultTitle = 'Tin tức và hướng dẫn Ngọc Rồng Online';
        $defaultDescription = 'Tin tức, kinh nghiệm chơi, hướng dẫn nhiệm vụ và cập nhật game Ngọc Rồng Online.';
        $pageTitle = $activeCategory?->seo_title ?: ($activeCategory?->name ?: $defaultTitle);
        $pageDescription = $activeCategory?->seo_description ?: $defaultDescription;
        $pageUrl = $activeCategory ? route('seo.category', $activeCategory->slug) : route('seo.index');

        return view('pages.seo.index', [
            'systemSettings' => $systemSettings,
            'posts' => $posts,
            'pageTitle' => $pageTitle,
            'pageDescription' => $pageDescription,
            'pageMetaTitle' => $search !== ''
                ? "Tìm kiếm: {$search} | {$pageTitle}"
                : $pageTitle.' | '.($systemSettings['site_name'] ?: config('app.name', 'Tiện ích NRO')),
            'pageMetaDescription' => $pageDescription,
            'pageMetaUrl' => $pageUrl,
            'pageMetaCanonical' => $posts->currentPage() > 1 && $search === '' ? $pageUrl.'?page='.$posts->currentPage() : $pageUrl,
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
                    ->whereIn('type', ['knowledge', 'guide'])
                    ->where('status', 'published')
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now()),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $systemSettings = $this->systemSettings($settingStore);
        $content = $this->normalizeContentHeadings(is_array($post->content) ? $post->content : []);
        $contentHtml = $this->contentRenderer->renderNodes($content);
        $coverImage = $post->cover_image ?: $this->contentRenderer->firstImage($content);
        $headingIndex = $this->contentRenderer->headingIndex($content);
        $postUrl = $this->postUrl($post);
        $siteName = $systemSettings['site_name'] ?: config('app.name', 'Nạp Carot');
        $absoluteCoverImage = $coverImage && ! Str::startsWith($coverImage, ['http://', 'https://']) ? url($coverImage) : $coverImage;
        $canonicalUrl = $this->canonicalUrl($post, $request, $postUrl);
        $displayUpdatedAt = $post->updated_at;
        $pageSchema = $post->article_schema ? array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post->title,
            'description' => $post->seo_description ?: $post->excerpt,
            'image' => $absoluteCoverImage,
            'datePublished' => $post->published_at?->toAtomString(),
            'dateModified' => $displayUpdatedAt?->toAtomString(),
            'mainEntityOfPage' => $canonicalUrl,
            'publisher' => ['@type' => 'Organization', 'name' => $siteName],
        ]) : null;
        $faq = collect($post->faq ?? [])
            ->filter(fn (mixed $item): bool => is_array($item) && filled($item['question'] ?? null) && filled($item['answer'] ?? null))
            ->values();

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
            'pageOgType' => 'article',
            'pageSchema' => $pageSchema,
            'faq' => $faq,
            'faqSchema' => $faq->isNotEmpty() ? [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $faq->map(fn (array $item): array => [
                    '@type' => 'Question',
                    'name' => $item['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $item['answer'],
                    ],
                ])->all(),
            ] : null,
            'displayUpdatedAt' => $displayUpdatedAt,
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
            ->whereIn('type', ['knowledge', 'guide'])
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

    private function storedRedirect(Request $request): ?RedirectResponse
    {
        $path = '/'.ltrim($request->path(), '/');
        $redirect = SeoRedirect::query()->where('from_path', $path)->first();

        if (! $redirect instanceof SeoRedirect
            || ! Str::startsWith($redirect->to_path, '/')
            || Str::startsWith($redirect->to_path, '//')) {
            return null;
        }

        $statusCode = in_array($redirect->status_code, [301, 302, 307, 308], true)
            ? $redirect->status_code
            : 301;

        return redirect()->to(url($redirect->to_path), $statusCode);
    }

    /**
     * @param  array<int, mixed>  $nodes
     * @return array<int, mixed>
     */
    private function normalizeContentHeadings(array $nodes): array
    {
        return collect($nodes)
            ->map(function (mixed $node): mixed {
                if (! is_array($node)) {
                    return $node;
                }

                if (($node['type'] ?? null) === 'heading') {
                    $node['level'] = max(2, (int) ($node['level'] ?? 2));
                }

                if (($node['type'] ?? null) === 'container' && is_array($node['children'] ?? null)) {
                    $node['children'] = $this->normalizeContentHeadings($node['children']);
                }

                return $node;
            })
            ->all();
    }
}
