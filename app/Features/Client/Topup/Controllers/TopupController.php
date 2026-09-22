<?php

namespace App\Features\Client\Topup\Controllers;

use App\Features\Client\Topup\Services\GameLandingService;
use App\Features\Client\Topup\Services\TurnstileService;
use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
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
        GameLandingService $gameLandingService,
        TurnstileService $turnstileService,
    ): View {
        abort_unless($game->status === 'active', 404);

        return view('client.topup.game', [
            ...$gameLandingService->data($game, $request->user()),
            'turnstileEnabled' => $turnstileService->isEnabled(),
            'turnstileSiteKey' => $turnstileService->siteKey(),
        ]);
    }

    public function legacy(Game $game): RedirectResponse
    {
        return redirect()->route('topup.game', ['game' => $game], 301);
    }
}
