<?php

namespace App\Http\Controllers;

use App\Features\Topup\Services\TopupPackagePricingService;
use App\Models\Game;
use App\Models\SeoPost;
use App\Models\User;
use App\Support\SettingStore;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SeoLandingPageController extends Controller
{
    public function __invoke(
        Request $request,
        string $landingSlug,
        SettingStore $settingStore,
        TopupPackagePricingService $topupPackagePricingService,
    ): View {
        /** @var array<string, mixed>|null $landing */
        $landing = config("seo.landings.{$landingSlug}");
        abort_unless(is_array($landing), 404);

        $gameSlugs = collect($landing['game_slugs'] ?? [])->filter()->values();
        $games = Game::query()
            ->select(['id', 'name', 'slug', 'short_name', 'description', 'status', 'sort_order'])
            ->active()
            ->when($gameSlugs->isNotEmpty(), fn (Builder $query) => $query->whereIn('slug', $gameSlugs))
            ->with(['packages' => fn ($query) => $query
                ->select([
                    'id', 'game_id', 'name', 'denomination', 'carot_amount', 'provider_price',
                    'price', 'original_price', 'discount_percent', 'bonus_text', 'min_quantity',
                    'max_quantity', 'status', 'sort_order',
                ])
                ->active()
                ->orderBy('sort_order')
                ->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        /** @var User|null $user */
        $user = $request->user();
        $topupPackagePricingService->apply($games->flatMap(fn (Game $game) => $game->packages), $user);
        $games->each(fn (Game $game) => $game->setRelation(
            'packages',
            $game->packages->filter(fn ($package) => $package->is_price_available)->values(),
        ));

        $relatedPosts = SeoPost::query()
            ->with('category:id,name,slug,is_active')
            ->whereIn('slug', $landing['article_slugs'] ?? [])
            ->where('status', 'published')
            ->where('robots', 'index,follow')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereHas('category', fn (Builder $query) => $query->where('is_active', true))
            ->orderByDesc('published_at')
            ->get()
            ->map(fn (SeoPost $post): array => [
                'title' => $post->title,
                'excerpt' => $post->excerpt,
                'url' => route('seo.show', [
                    'categorySlug' => $post->category->slug,
                    'postSlug' => $post->slug,
                ]),
            ]);

        $systemSettings = $settingStore->getMany([
            'site_name' => config('app.name', 'Nạp Carot'),
            'site_domain' => '',
            'site_description' => '',
            'support_email' => '',
            'hotline' => '',
            'light_logo' => '',
            'favicon' => '',
            'meta_title' => '',
            'meta_description' => '',
        ]);
        $configuredSiteDomain = trim((string) $systemSettings['site_domain']);
        $canonicalUrl = filter_var($configuredSiteDomain, FILTER_VALIDATE_URL)
            ? rtrim($configuredSiteDomain, '/').'/'.$landingSlug
            : route('seo.landing', ['landingSlug' => $landingSlug]);
        $gameLandings = collect(config('seo.home_game_landings', []))
            ->map(fn (string $slug): array => [
                'name' => config("seo.landings.{$slug}.name", $slug),
                'url' => route('seo.landing', ['landingSlug' => $slug]),
            ]);

        return view('pages.seo.landing', [
            'systemSettings' => $systemSettings,
            'landing' => $landing,
            'landingSlug' => $landingSlug,
            'games' => $games,
            'relatedPosts' => $relatedPosts,
            'gameLandings' => $gameLandings,
            'canonicalUrl' => $canonicalUrl,
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
                        'name' => $landing['name'],
                        'item' => $canonicalUrl,
                    ],
                ],
            ],
        ]);
    }
}
