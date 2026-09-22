<?php

namespace App\Support;

use App\Models\Game;
use App\Models\SeoCategory;
use App\Models\SeoPost;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SitemapUrlService
{
    public function __construct(
        protected SettingStore $settingStore,
    ) {}

    /**
     * @return Collection<int, array{loc: string, lastmod: mixed}>
     */
    public function urls(): Collection
    {
        return $this->pageUrls()
            ->concat($this->gameUrls())
            ->concat($this->categoryUrls())
            ->concat($this->articleUrls())
            ->unique('loc')
            ->values();
    }

    /** @return Collection<int, array{loc: string, lastmod: mixed}> */
    public function pageUrls(): Collection
    {
        $gameLandings = config('seo.home_game_landings', []);
        $landingUrls = collect(array_keys(config('seo.landings', [])))
            ->reject(fn (string $slug): bool => in_array($slug, $gameLandings, true))
            ->map(fn (string $slug): array => [
                'loc' => route('seo.landing', ['landingSlug' => $slug]),
                'lastmod' => null,
            ]);

        return $this->staticUrls()
            ->concat($this->contentPageUrls())
            ->concat($landingUrls)
            ->unique('loc')
            ->values();
    }

    /** @return Collection<int, array{loc: string, lastmod: mixed}> */
    public function gameUrls(): Collection
    {
        return Game::query()
            ->active()
            ->with('seoSetting')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'slug', 'updated_at'])
            ->map(function (Game $game): ?array {
                $setting = $game->seoSetting;
                $localUrl = route('topup.game', ['game' => $game]);

                if ($setting?->is_published && $setting->robots !== 'index,follow') {
                    return null;
                }

                if ($setting?->is_published && filled($setting->canonical_url)
                    && rtrim((string) $setting->canonical_url, '/') !== rtrim($localUrl, '/')) {
                    return null;
                }

                return [
                    'loc' => $localUrl,
                    'lastmod' => $setting?->updated_at ?? $game->updated_at,
                ];
            })
            ->filter()
            ->values();
    }

    /** @return Collection<int, array{loc: string, lastmod: mixed}> */
    public function categoryUrls(): Collection
    {
        return SeoCategory::query()
            ->where('is_active', true)
            ->where('robots', 'index,follow')
            ->get(['slug', 'updated_at'])
            ->map(fn (SeoCategory $category): array => [
                'loc' => route('seo.category', $category->slug),
                'lastmod' => $category->updated_at,
            ]);
    }

    /**
     * @return Collection<int, array{loc: string, lastmod: null}>
     */
    private function staticUrls(): Collection
    {
        return collect([
            ['loc' => route('home'), 'lastmod' => null],
            ['loc' => route('pricing'), 'lastmod' => null],
            ['loc' => route('seo.index'), 'lastmod' => null],
        ]);
    }

    /**
     * @return Collection<int, array{loc: string, lastmod: null}>
     */
    private function contentPageUrls(): Collection
    {
        $pages = [
            'content.about' => 'about_page_is_published',
            'content.contact' => 'contact_page_is_published',
            'content.guide' => 'guide_page_is_published',
            'content.terms' => 'terms_page_is_published',
            'content.privacy' => 'privacy_page_is_published',
            'content.refund' => 'refund_policy_is_published',
            'content.payment' => 'payment_policy_is_published',
            'content.faq' => 'faq_page_is_published',
        ];
        $published = $this->settingStore->getMany(array_fill_keys(array_values($pages), true));

        return collect($pages)
            ->filter(fn (string $settingKey): bool => (bool) ($published[$settingKey] ?? true))
            ->keys()
            ->map(fn (string $routeName): array => ['loc' => route($routeName), 'lastmod' => null])
            ->values();
    }

    /**
     * @return Collection<int, array{loc: string, lastmod: mixed}>
     */
    public function articleUrls(): Collection
    {
        return SeoPost::query()
            ->with('category:id,slug,is_active,robots')
            ->where('status', 'published')
            ->where('robots', 'index,follow')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('seo_category_id')
                    ->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery
                        ->where('is_active', true)
                        ->where('robots', 'index,follow'));
            })
            ->get(['id', 'seo_category_id', 'slug', 'canonical_url', 'updated_at'])
            ->map(function (SeoPost $post): ?array {
                $localUrl = $post->category?->is_active
                    ? route('seo.show', ['categorySlug' => $post->category->slug, 'postSlug' => $post->slug])
                    : route('seo.legacy.show', $post->slug);

                if (! $this->isCanonicalLocalUrl($post, $localUrl)) {
                    return null;
                }

                return ['loc' => $localUrl, 'lastmod' => $post->updated_at];
            })
            ->filter()
            ->values();
    }

    private function isCanonicalLocalUrl(SeoPost $post, string $localUrl): bool
    {
        $canonicalUrl = trim((string) $post->canonical_url);

        if ($canonicalUrl === '') {
            return true;
        }

        $canonicalHost = parse_url($canonicalUrl, PHP_URL_HOST);
        $canonicalPath = parse_url($canonicalUrl, PHP_URL_PATH);
        $legacyPaths = ['/tin-tuc/'.$post->slug, '/bai-viet/'.$post->slug];

        if ($canonicalHost === request()->getHost() && in_array($canonicalPath, $legacyPaths, true)) {
            return true;
        }

        return rtrim($canonicalUrl, '/') === rtrim($localUrl, '/');
    }
}
