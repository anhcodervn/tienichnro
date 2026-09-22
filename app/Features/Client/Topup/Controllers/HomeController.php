<?php

namespace App\Features\Client\Topup\Controllers;

use App\Features\Topup\Services\GameRewardService;
use App\Features\Topup\Services\HomeSeoService;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Order;
use App\Models\SeoPost;
use App\Models\User;
use App\Support\EditorContentRenderer;
use App\Support\SettingStore;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class HomeController extends Controller
{
    public function __invoke(
        SettingStore $settingStore,
        EditorContentRenderer $contentRenderer,
        GameRewardService $gameRewardService,
        HomeSeoService $homeSeoService,
    ): View {
        $games = Game::query()
            ->select([
                'id', 'name', 'slug', 'short_name', 'reward_label', 'image', 'status', 'sort_order',
            ])
            ->active()
            ->with([
                'packages' => fn ($query) => $query
                    ->select([
                        'id', 'game_id', 'name', 'denomination', 'carot_amount', 'reward_x2_amount',
                        'reward_x3_amount', 'first_topup_reward_amount', 'metadata', 'status', 'sort_order',
                    ])
                    ->active()
                    ->orderBy('sort_order')
                    ->orderBy('id'),
                'globalPackageSettings' => fn ($query) => $query
                    ->select(['id', 'game_id', 'denomination', 'receives'])
                    ->orderBy('denomination'),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $gameRewardService->applyToPackages(
            $games->flatMap(fn (Game $game) => $game->packages),
            $games->flatMap(fn (Game $game) => $game->globalPackageSettings),
        );
        $homeRewardGames = $games->map(function (Game $game): array {
            $packagesByDenomination = $game->packages
                ->filter(fn ($package): bool => $package->denomination !== null)
                ->keyBy(fn ($package): int => (int) $package->denomination);
            $settingsByDenomination = $game->globalPackageSettings
                ->keyBy(fn ($setting): int => (int) $setting->denomination);

            $rows = $packagesByDenomination->keys()
                ->merge($settingsByDenomination->keys())
                ->unique()
                ->sort()
                ->map(function (int $denomination) use ($game, $packagesByDenomination, $settingsByDenomination): array {
                    $package = $packagesByDenomination->get($denomination);
                    $receives = $package
                        ? $package->rewardItems($game->reward_label)
                        : array_values((array) $settingsByDenomination->get($denomination)?->receives);

                    return [
                        'denomination' => $denomination,
                        'receives' => collect($receives)->filter(fn ($receive): bool => is_array($receive))->values(),
                    ];
                })
                ->filter(fn (array $row): bool => $row['receives']->isNotEmpty())
                ->values();

            return [
                'id' => $game->id,
                'name' => $game->name ?: $game->short_name,
                'rows' => $rows,
            ];
        });

        $systemSettings = $settingStore->getMany([
            'site_name' => config('app.name', 'Nạp Carot'),
            'site_domain' => '',
            'site_description' => 'Nạp Carot game Teamobi nhanh chóng và minh bạch.',
            'support_email' => '', 'hotline' => '', 'light_logo' => '', 'favicon' => '',
            'meta_title' => '', 'meta_description' => '',
            'home_popup_title' => 'Thông báo',
            'home_popup_content' => [],
            'home_popup_is_published' => false,
            'home_popup_display_mode' => 'modal',
            'home_popup_allow_dismiss' => false,
            'home_popup_dismiss_hours' => 24,
        ]);
        $latestSeoPosts = SeoPost::query()
            ->select(['id', 'seo_category_id', 'title', 'slug', 'excerpt', 'published_at'])
            ->with('category:id,name,slug,is_active')
            ->where('status', 'published')
            ->where('robots', 'index,follow')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereHas('category', fn (Builder $query) => $query->where('is_active', true))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->take(3)
            ->get()
            ->map(fn (SeoPost $post): array => [
                'title' => $post->title,
                'excerpt' => $post->excerpt,
                'category' => $post->category->name,
                'url' => route('seo.show', [
                    'categorySlug' => $post->category->slug,
                    'postSlug' => $post->slug,
                ]),
            ]);
        $homeSeo = $homeSeoService->settings();
        $homeSeoIsPublished = $homeSeo['is_published'];
        $homeSeoContent = $homeSeoIsPublished && is_array($homeSeo['content']) ? $homeSeo['content'] : [];
        $homeSeoHtml = $contentRenderer->renderNodes($homeSeoContent);
        $homeFaqs = collect($homeSeoIsPublished ? $homeSeo['faqs'] : config('seo.faqs', []))
            ->filter(fn (mixed $faq): bool => is_array($faq)
                && filled($faq['question'] ?? null)
                && filled($faq['answer'] ?? null))
            ->map(fn (array $faq): array => [
                'question' => trim((string) $faq['question']),
                'answer' => trim((string) $faq['answer']),
            ])
            ->values();
        $configuredSiteDomain = trim((string) $systemSettings['site_domain']);
        $homeCanonicalUrl = filter_var($configuredSiteDomain, FILTER_VALIDATE_URL)
            ? rtrim($configuredSiteDomain, '/').'/'
            : rtrim(route('home'), '/').'/';
        $homeSchemas = [
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => config('seo.brand_name', 'NapCarot'),
                'url' => $homeCanonicalUrl,
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => config('seo.brand_name', 'NapCarot'),
                'url' => $homeCanonicalUrl,
            ],
        ];

        if ($homeFaqs->isNotEmpty()) {
            $homeSchemas[] = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $homeFaqs->map(fn (array $faq): array => [
                    '@type' => 'Question',
                    'name' => $faq['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $faq['answer'],
                    ],
                ])->all(),
            ];
        }

        if ($games->isNotEmpty()) {
            $homeSchemas[] = [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => 'Danh sách game hỗ trợ nạp Carot',
                'itemListElement' => $games->values()->map(function (Game $game, int $index): array {
                    $item = [
                        '@type' => 'ListItem',
                        'position' => $index + 1,
                        'name' => $game->name,
                        'url' => route('topup.game', ['game' => $game]),
                    ];

                    if (filled($game->image)) {
                        $item['image'] = filter_var($game->image, FILTER_VALIDATE_URL)
                            ? $game->image
                            : url($game->image);
                    }

                    return $item;
                })->all(),
            ];
        }

        return view('client.home.index', [
            'games' => $games,
            'homeRewardGames' => $homeRewardGames,
            'homeStatistics' => [
                'members' => User::query()->count(),
                'orders' => Order::query()->count(),
                'games' => $games->count(),
            ],
            'systemSettings' => $systemSettings,
            ...$this->homePopupData($systemSettings, $contentRenderer),
            'mainSeoLandings' => collect(['nap-carot', 'nap-game-teamobi'])->map(fn (string $slug): array => [
                'slug' => $slug,
                'name' => config("seo.landings.{$slug}.name", $slug),
                'url' => route('seo.landing', ['landingSlug' => $slug]),
            ]),
            'latestSeoPosts' => $latestSeoPosts,
            'homeFaqs' => $homeFaqs,
            'homeSeoMetaTitle' => $homeSeoIsPublished ? $homeSeo['meta_title'] : config('seo.homepage.title'),
            'homeSeoMetaDescription' => $homeSeoIsPublished ? $homeSeo['meta_description'] : config('seo.homepage.description'),
            'homeSeoH1' => $homeSeoIsPublished ? $homeSeo['h1'] : 'Nạp Carot Game Teamobi Nhanh Chóng, Giá Tốt',
            'homeSeoArticleTitle' => $homeSeoIsPublished ? $homeSeo['article_title'] : 'Nạp Carot game Teamobi: chọn đúng game, rõ giá trước khi thanh toán',
            'homeSeoHtml' => $homeSeoHtml,
            'homeSeoIsPublished' => $homeSeoIsPublished && $homeSeoHtml->isNotEmpty(),
            'homeCanonicalUrl' => $homeCanonicalUrl,
            'homeSchemas' => $homeSchemas,
        ]);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function homePopupData(array $settings, EditorContentRenderer $contentRenderer): array
    {
        $content = is_array($settings['home_popup_content']) ? $settings['home_popup_content'] : [];
        $title = (string) $settings['home_popup_title'];
        $displayMode = in_array($settings['home_popup_display_mode'], ['modal', 'popup'], true)
            ? (string) $settings['home_popup_display_mode']
            : 'modal';
        $dismissHours = max(1, min(8760, (int) $settings['home_popup_dismiss_hours']));
        $fingerprint = hash('sha256', (string) json_encode([
            'title' => $title,
            'content' => $content,
            'display_mode' => $displayMode,
            'allow_dismiss' => (bool) $settings['home_popup_allow_dismiss'],
            'dismiss_hours' => $dismissHours,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return [
            'homePopupTitle' => $title,
            'homePopupHtml' => $contentRenderer->renderNodes($content),
            'homePopupIsPublished' => (bool) $settings['home_popup_is_published'],
            'homePopupDisplayMode' => $displayMode,
            'homePopupAllowDismiss' => (bool) $settings['home_popup_allow_dismiss'],
            'homePopupDismissHours' => $dismissHours,
            'homePopupKey' => substr($fingerprint, 0, 24),
        ];
    }
}
