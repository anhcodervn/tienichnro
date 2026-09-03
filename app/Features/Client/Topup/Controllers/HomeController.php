<?php

namespace App\Features\Client\Topup\Controllers;

use App\Features\Client\Topup\Services\TurnstileService;
use App\Features\Topup\Services\GameRewardService;
use App\Features\Topup\Services\TopupPackagePricingService;
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
        TopupPackagePricingService $topupPackagePricingService,
        GameRewardService $gameRewardService,
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
                        'id', 'game_id', 'global_topup_package_id', 'name', 'denomination', 'carot_amount',
                        'reward_x2_amount', 'reward_x3_amount', 'first_topup_reward_amount', 'provider_price', 'price', 'original_price', 'metadata',
                        'discount_percent', 'bonus_text', 'min_quantity', 'max_quantity',
                        'status', 'sort_order',
                    ])
                    ->active(),
                'globalPackageSettings' => fn ($query) => $query
                    ->select(['id', 'game_id', 'denomination', 'receives'])
                    ->orderBy('denomination'),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        /** @var User|null $user */
        $user = $request->user();
        $walletBalance = (int) ($user?->wallet()->value('balance') ?? 0);
        $gameRewardService->applyToPackages(
            $games->flatMap(fn (Game $game) => $game->packages),
            $games->flatMap(fn (Game $game) => $game->globalPackageSettings),
        );
        $topupPackagePricingService->apply($games->flatMap(fn (Game $game) => $game->packages), $user);
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
            'home_popup_title' => 'Thông báo',
            'home_popup_content' => [],
            'home_popup_is_published' => false,
            'home_popup_display_mode' => 'modal',
            'home_popup_allow_dismiss' => false,
            'home_popup_dismiss_hours' => 24,
        ]);
        $homeNoticeContent = is_array($systemSettings['home_notice_content'])
            ? $systemSettings['home_notice_content']
            : [];

        return view('client.home.index', [
            'games' => $games,
            'walletBalance' => $walletBalance,
            'systemSettings' => $systemSettings,
            'homeNoticeTitle' => (string) $systemSettings['home_notice_title'],
            'homeNoticeHtml' => $contentRenderer->renderNodes($homeNoticeContent),
            'homeNoticeIsPublished' => (bool) $systemSettings['home_notice_is_published'],
            ...$this->homePopupData($systemSettings, $contentRenderer),
            'turnstileEnabled' => $turnstileService->isEnabled(),
            'turnstileSiteKey' => $turnstileService->siteKey(),
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
