<?php

namespace App\Features\Client\Topup\Controllers;

use App\Features\Client\Topup\Services\TurnstileService;
use App\Features\Topup\Services\GameRewardService;
use App\Features\Topup\Services\TopupPackagePricingService;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class TopupController extends Controller
{
    public function index(): View
    {
        $games = Game::query()->active()->orderBy('sort_order')->get(['id', 'name', 'slug', 'short_name', 'image', 'description']);

        return view('client.topup.index', compact('games'));
    }

    public function show(
        Request $request,
        Game $game,
        TurnstileService $turnstileService,
        TopupPackagePricingService $topupPackagePricingService,
        GameRewardService $gameRewardService,
    ): View {
        abort_unless($game->status === 'active', 404);

        $game->load([
            'servers' => fn ($query) => $query
                ->select(['id', 'game_id', 'name', 'status', 'sort_order'])
                ->active(),
            'packages' => fn ($query) => $query
                ->select([
                    'id', 'game_id', 'global_topup_package_id', 'name', 'denomination', 'carot_amount',
                    'reward_x2_amount', 'reward_x3_amount', 'first_topup_reward_amount',
                    'provider_price', 'price', 'original_price', 'discount_percent', 'bonus_text', 'min_quantity', 'max_quantity',
                    'status', 'sort_order', 'metadata',
                ])
                ->active(),
        ]);

        /** @var User|null $user */
        $user = $request->user();
        $walletBalance = (int) ($user?->wallet()->value('balance') ?? 0);
        $gameRewardService->applyToPackages($game->packages);
        $topupPackagePricingService->apply($game->packages, $user);
        $game->setRelation(
            'packages',
            $game->packages->filter(fn ($package) => $package->is_price_available)->values(),
        );

        return view('client.topup.game', [
            'game' => $game,
            'walletBalance' => $walletBalance,
            'turnstileEnabled' => $turnstileService->isEnabled(),
            'turnstileSiteKey' => $turnstileService->siteKey(),
        ]);
    }
}
