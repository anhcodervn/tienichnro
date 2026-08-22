<?php

namespace App\Http\Controllers;

use App\Models\SeoCategory;
use App\Models\SeoPost;
use App\Support\EditorContentRenderer;
use App\Support\SettingStore;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PublicSeoPageController extends Controller
{
    public function __construct(
        protected EditorContentRenderer $contentRenderer,
    ) {}

    public function index(Request $request, SettingStore $settingStore): View
    {
        $search = trim($request->string('q')->toString());
        $categorySlug = trim($request->string('category')->toString());

        $baseQuery = SeoPost::query()
            ->with(['category:id,name,slug'])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
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
            ->get();

        $featuredPost = $posts->first();
        $latestPosts = $posts->skip($featuredPost ? 1 : 0)->take(6)->values();
        $sidebarPosts = $posts->take(3)->values();

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
        $pageTitle = 'Tin tức và hướng dẫn nạp game Teamobi';
        $pageDescription = 'Hướng dẫn chọn gói Carot, thanh toán an toàn và xử lý các tình huống thường gặp khi nạp game Teamobi.';

        return view('pages.seo.index', [
            'systemSettings' => $systemSettings,
            'pageTitle' => $pageTitle,
            'pageDescription' => $pageDescription,
            'pageMetaTitle' => $search !== ''
                ? "Tìm kiếm: {$search} | {$pageTitle}"
                : $pageTitle.' | '.($systemSettings['site_name'] ?: config('app.name', 'Nạp Carot')),
            'pageMetaDescription' => $pageDescription,
            'pageMetaUrl' => $request->url().($request->getQueryString() ? '?'.$request->getQueryString() : ''),
            'featuredPost' => $featuredPost ? $this->transformPost($featuredPost) : null,
            'latestPosts' => $latestPosts->map(fn (SeoPost $post) => $this->transformPost($post)),
            'sidebarPosts' => $sidebarPosts->map(fn (SeoPost $post) => $this->transformPost($post)),
            'categories' => $categories,
            'activeCategorySlug' => $categorySlug,
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

    public function show(string $slug, Request $request, SettingStore $settingStore): View
    {
        $post = SeoPost::query()
            ->with('category:id,name,slug')
            ->where('slug', $slug)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->firstOrFail();

        $relatedPosts = SeoPost::query()
            ->with('category:id,name,slug')
            ->where('id', '!=', $post->id)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
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
        $coverImage = $this->contentRenderer->firstImage($content);
        $headingIndex = $this->contentRenderer->headingIndex($content);

        return view('pages.seo.show', [
            'systemSettings' => $systemSettings,
            'post' => $post,
            'contentHtml' => $contentHtml,
            'coverImage' => $coverImage,
            'readingMinutes' => $this->contentRenderer->estimateReadingMinutes($content),
            'headingIndex' => $headingIndex,
            'relatedPosts' => $relatedPosts->map(fn (SeoPost $item) => $this->transformPost($item)),
            'sidebarCategories' => $sidebarCategories,
            'pageMetaTitle' => $post->seo_title ?: $post->title.' | '.($systemSettings['site_name'] ?: config('app.name', 'Nạp Carot')),
            'pageMetaDescription' => $post->seo_description ?: ($post->excerpt ?: $this->contentRenderer->extractText($content)),
            'pageMetaCanonical' => $post->canonical_url ?: $request->url(),
            'pageMetaUrl' => $request->url(),
            'pageMetaImage' => $coverImage,
        ]);
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
            'cover_image' => $this->contentRenderer->firstImage($content),
            'focus_keyword' => $post->focus_keyword,
            'category_name' => $post->category?->name,
            'category_slug' => $post->category?->slug,
            'published_at' => $publishedAt,
            'published_label' => $publishedAt?->format('d/m/Y'),
            'reading_minutes' => $this->contentRenderer->estimateReadingMinutes($content),
            'url' => route('seo.show', $post->slug),
        ];
    }
}
