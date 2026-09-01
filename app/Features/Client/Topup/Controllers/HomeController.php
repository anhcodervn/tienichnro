<?php

namespace App\Features\Client\Topup\Controllers;

use App\Features\Client\Topup\Services\TurnstileService;
use App\Features\MemberLevel\Services\MemberLevelPriceService;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\User;
use App\Support\EditorContentRenderer;
use App\Support\SettingStore;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(
        Request $request,
        SettingStore $settingStore,
        EditorContentRenderer $contentRenderer,
        TurnstileService $turnstileService,
        MemberLevelPriceService $memberLevelPriceService,
    ): View {
        $games = Game::query()
            ->select([
                'id', 'name', 'slug', 'short_name', 'reward_label', 'description', 'checkout_fields',
                'package_mode', 'status', 'sort_order',
            ])
            ->active()
            ->with([
                'servers' => fn ($query) => $query
                    ->select(['id', 'game_id', 'name', 'status', 'sort_order'])
                    ->active(),
                'packages' => fn ($query) => $query
                    ->select([
                        'id', 'game_id', 'game_server_id', 'global_topup_package_id', 'name', 'denomination', 'carot_amount',
                        'reward_x2_amount', 'reward_x3_amount', 'first_topup_reward_amount', 'provider_price', 'price', 'original_price',
                        'discount_percent', 'bonus_text', 'min_quantity', 'max_quantity',
                        'status', 'sort_order',
                    ])
                    ->active(),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        /** @var User|null $user */
        $user = $request->user();
        $walletBalance = (int) ($user?->wallet()->value('balance') ?? 0);
        $memberLevelStatus = $memberLevelPriceService->apply(
            $games->flatMap(fn (Game $game) => $game->packages),
            $user,
        );
        $games->each(fn (Game $game) => $game->setRelation(
            'packages',
            $game->packages->filter(fn ($package) => $package->is_price_available)->values(),
        ));

        $systemSettings = $settingStore->getMany([
            'site_name' => config('app.name', 'Nạp Carot'),
            'site_description' => 'Nạp Carot game Teamobi nhanh chóng và minh bạch.',
            'support_email' => '', 'hotline' => '', 'light_logo' => '', 'favicon' => '',
            'meta_title' => '', 'meta_description' => '',
            'home_notice_title' => 'Thông báo quan trọng',
            'home_notice_content' => [],
            'home_notice_is_published' => true,
        ]);
        $homeNoticeContent = is_array($systemSettings['home_notice_content'])
            ? $systemSettings['home_notice_content']
            : [];

        return view('client.home.index', [
            'games' => $games,
            'walletBalance' => $walletBalance,
            'memberLevelStatus' => $memberLevelStatus,
            'systemSettings' => $systemSettings,
            'homeNoticeTitle' => (string) $systemSettings['home_notice_title'],
            'homeNoticeHtml' => $contentRenderer->renderNodes($homeNoticeContent),
            'homeNoticeIsPublished' => (bool) $systemSettings['home_notice_is_published'],
            'turnstileEnabled' => $turnstileService->isEnabled(),
            'turnstileSiteKey' => $turnstileService->siteKey(),
        ]);
    }
}
