<?php

namespace App\Features\Client\Topup\Services;

use App\Enums\OrderStatus;
use App\Features\Topup\Services\GameRewardService;
use App\Features\Topup\Services\TopupPackagePricingService;
use App\Models\Game;
use App\Models\Order;
use App\Models\SeoPost;
use App\Models\User;
use App\Support\EditorContentRenderer;
use App\Support\SettingStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class GameLandingService
{
    public function __construct(
        private readonly SettingStore $settingStore,
        private readonly EditorContentRenderer $contentRenderer,
        private readonly TopupPackagePricingService $topupPackagePricingService,
        private readonly GameRewardService $gameRewardService,
    ) {}

    /** @return array<string, mixed> */
    public function data(Game $game, ?User $user): array
    {
        $this->loadSaleData($game, $user);
        $landing = $this->seoLanding($game);
        $settings = $this->settings();
        $seoSetting = $game->seoSetting;
        $legacyHasSeo = filled($game->seo_title) || filled($game->seo_description) || filled($game->content);
        $usesCustomSeo = $seoSetting?->is_published ?? $legacyHasSeo;
        $defaultCanonicalUrl = $this->canonicalUrl($game, (string) $settings['site_domain']);
        $canonicalUrl = $usesCustomSeo && $this->isAbsoluteHttpUrl($seoSetting?->canonical_url)
            ? (string) $seoSetting->canonical_url
            : $defaultCanonicalUrl;
        $pageTitle = $usesCustomSeo
            ? ($seoSetting?->meta_title ?: $game->seo_title ?: ($landing['title'] ?? 'Nạp game '.$game->name.' nhanh chóng'))
            : ($landing['title'] ?? 'Nạp game '.$game->name.' nhanh chóng');
        $pageDescription = $usesCustomSeo
            ? ($seoSetting?->meta_description ?: $game->seo_description ?: ($landing['description'] ?? $game->description))
            : ($landing['description'] ?? $game->description);
        $pageImage = $usesCustomSeo && filled($seoSetting?->og_image)
            ? (string) $seoSetting->og_image
            : (string) $settings['og_image'];
        $seoFaqs = $this->normalizeFaqs($usesCustomSeo ? $seoSetting?->faqs : []);
        $seoContentHtml = $this->seoContentHtml($game, $landing, $seoSetting?->content, $usesCustomSeo);
        $gameSchemas = $this->schemas(
            $game,
            $canonicalUrl,
            (string) $pageTitle,
            (string) $pageDescription,
            $pageImage,
            $seoFaqs,
            ! $usesCustomSeo || ($seoSetting?->breadcrumb_schema ?? true),
            ! $usesCustomSeo || ($seoSetting?->webpage_schema ?? true),
        );

        return [
            'game' => $game,
            'walletBalance' => (int) ($user?->wallet()->value('balance') ?? 0),
            'recentPendingOrders' => $this->recentPendingOrders($game),
            'relatedSeoPosts' => $this->relatedSeoPosts($game, $landing),
            'systemSettings' => $settings,
            'homeNoticeTitle' => (string) $settings['home_notice_title'],
            'homeNoticeHtml' => $this->contentRenderer->renderNodes(
                is_array($settings['home_notice_content']) ? $settings['home_notice_content'] : [],
            ),
            'homeNoticeIsPublished' => (bool) $settings['home_notice_is_published'],
            'pageTitle' => $pageTitle,
            'pageDescription' => $pageDescription,
            'pageKeywords' => $usesCustomSeo ? (string) ($seoSetting?->meta_keywords ?? '') : '',
            'pageH1' => $usesCustomSeo ? ($seoSetting?->h1 ?: 'Nạp game '.$game->name) : 'Nạp game '.$game->name,
            'seoArticleTitle' => $usesCustomSeo ? ($seoSetting?->article_title ?: 'Hướng dẫn nạp '.$game->name) : 'Hướng dẫn nạp '.$game->name,
            'seoContentHtml' => $seoContentHtml,
            'seoFaqs' => $seoFaqs,
            'pageImage' => $pageImage,
            'pageImageAlt' => $usesCustomSeo ? ($seoSetting?->og_image_alt ?: $game->name) : $game->name,
            'pageRobots' => $usesCustomSeo ? (string) ($seoSetting?->robots ?? 'index,follow') : 'index,follow',
            'canonicalUrl' => $canonicalUrl,
            'gameSchemas' => $gameSchemas,
        ];
    }

    private function loadSaleData(Game $game, ?User $user): void
    {
        $game->load([
            'servers' => fn ($query) => $query
                ->select(['id', 'game_id', 'name', 'status', 'sort_order'])
                ->active(),
            'packages' => fn ($query) => $query
                ->select([
                    'id', 'game_id', 'global_topup_package_id', 'name', 'denomination', 'carot_amount',
                    'reward_x2_amount', 'reward_x3_amount', 'first_topup_reward_amount',
                    'provider_price', 'price', 'original_price', 'discount_percent', 'bonus_text',
                    'status', 'sort_order', 'metadata',
                ])
                ->active(),
            'globalPackageSettings' => fn ($query) => $query
                ->select(['id', 'game_id', 'denomination', 'receives'])
                ->orderBy('denomination'),
            'seoSetting',
        ]);

        $this->gameRewardService->applyToPackages($game->packages, $game->globalPackageSettings);
        $this->topupPackagePricingService->apply($game->packages, $user);
        $game->setRelation(
            'packages',
            $game->packages->filter(fn ($package) => $package->is_price_available)->values(),
        );
    }

    /** @return Collection<int, array{package_name: string, quantity: int, created_at: mixed}> */
    private function recentPendingOrders(Game $game): Collection
    {
        return Order::query()
            ->select(['id', 'game_id', 'package_name', 'quantity', 'order_status', 'created_at'])
            ->whereBelongsTo($game)
            ->where('order_status', OrderStatus::Pending->value)
            ->latest('created_at')
            ->orderByDesc('id')
            ->limit(3)
            ->get()
            ->map(fn (Order $order): array => [
                'package_name' => (string) ($order->package_name ?: 'Gói nạp'),
                'quantity' => (int) $order->quantity,
                'created_at' => $order->created_at,
            ]);
    }

    /**
     * @param  array<string, mixed>  $landing
     * @return Collection<int, array{title: string, excerpt: string|null, url: string}>
     */
    private function relatedSeoPosts(Game $game, array $landing): Collection
    {
        $articleSlugs = collect($landing['article_slugs'] ?? [])->filter()->values();

        return SeoPost::query()
            ->select(['id', 'seo_category_id', 'service_id', 'title', 'slug', 'excerpt', 'published_at'])
            ->with('category:id,name,slug,is_active')
            ->where(function (Builder $query) use ($articleSlugs, $game): void {
                $query->where('service_id', $game->id)
                    ->when($articleSlugs->isNotEmpty(), fn (Builder $builder) => $builder->orWhereIn('slug', $articleSlugs));
            })
            ->where('status', 'published')
            ->where('robots', 'index,follow')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereHas('category', fn (Builder $query) => $query->where('is_active', true))
            ->latest('published_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn (SeoPost $post): array => [
                'title' => $post->title,
                'excerpt' => $post->excerpt,
                'url' => route('seo.show', [
                    'categorySlug' => $post->category->slug,
                    'postSlug' => $post->slug,
                ]),
            ]);
    }

    /** @return array<string, mixed> */
    private function seoLanding(Game $game): array
    {
        $landing = collect(config('seo.landings', []))->first(
            fn (mixed $item): bool => is_array($item) && in_array($game->slug, $item['game_slugs'] ?? [], true),
        );

        return is_array($landing) ? $landing : [];
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        return $this->settingStore->getMany([
            'site_name' => config('app.name', 'Nạp Carot'),
            'site_domain' => '',
            'site_description' => '',
            'support_email' => '',
            'hotline' => '',
            'light_logo' => '',
            'favicon' => '',
            'meta_title' => '',
            'meta_description' => '',
            'og_image' => '',
            'home_notice_title' => 'Thông báo quan trọng',
            'home_notice_content' => [],
            'home_notice_is_published' => true,
        ]);
    }

    private function canonicalUrl(Game $game, string $configuredSiteDomain): string
    {
        $path = '/nap-game-'.$game->slug;

        return filter_var(trim($configuredSiteDomain), FILTER_VALIDATE_URL)
            ? rtrim($configuredSiteDomain, '/').$path
            : route('topup.game', ['game' => $game]);
    }

    /** @return array<string, mixed> */
    private function breadcrumbSchema(Game $game, string $canonicalUrl): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Trang chủ', 'item' => route('home')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Nạp '.$game->name, 'item' => $canonicalUrl],
            ],
        ];
    }

    /** @param array<string, mixed> $landing */
    private function seoContentHtml(Game $game, array $landing, mixed $content, bool $usesCustomSeo): HtmlString
    {
        if ($usesCustomSeo && is_array($content)) {
            return $this->contentRenderer->renderNodes($content);
        }

        if ($usesCustomSeo && filled($game->content)) {
            return new HtmlString('<p>'.nl2br(e((string) $game->content)).'</p>');
        }

        $fallback = trim((string) ($landing['intro'] ?? $game->description ?? ''));

        return new HtmlString($fallback !== '' ? '<p>'.nl2br(e($fallback)).'</p>' : '');
    }

    /** @return Collection<int, array{question: string, answer: string}> */
    private function normalizeFaqs(mixed $faqs): Collection
    {
        return collect(is_array($faqs) ? $faqs : [])
            ->filter(fn (mixed $faq): bool => is_array($faq) && filled($faq['question'] ?? null) && filled($faq['answer'] ?? null))
            ->map(fn (array $faq): array => [
                'question' => trim((string) $faq['question']),
                'answer' => trim((string) $faq['answer']),
            ])
            ->values();
    }

    /**
     * @param  Collection<int, array{question: string, answer: string}>  $faqs
     * @return array<int, array<string, mixed>>
     */
    private function schemas(
        Game $game,
        string $canonicalUrl,
        string $title,
        string $description,
        string $image,
        Collection $faqs,
        bool $includeBreadcrumb,
        bool $includeWebPage,
    ): array {
        $schemas = [];

        if ($includeBreadcrumb) {
            $schemas[] = $this->breadcrumbSchema($game, $canonicalUrl);
        }

        if ($includeWebPage) {
            $webPage = [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => $title,
                'description' => $description,
                'url' => $canonicalUrl,
            ];
            if ($image !== '') {
                $webPage['primaryImageOfPage'] = [
                    '@type' => 'ImageObject',
                    'url' => $this->absoluteUrl($image),
                ];
            }
            $schemas[] = $webPage;
        }

        if ($faqs->isNotEmpty()) {
            $schemas[] = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $faqs->map(fn (array $faq): array => [
                    '@type' => 'Question',
                    'name' => $faq['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
                ])->all(),
            ];
        }

        return $schemas;
    }

    private function isAbsoluteHttpUrl(mixed $value): bool
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    private function absoluteUrl(string $value): string
    {
        return $this->isAbsoluteHttpUrl($value) ? $value : url($value);
    }
}
