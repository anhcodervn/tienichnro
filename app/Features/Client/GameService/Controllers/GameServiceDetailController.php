<?php

namespace App\Features\Client\GameService\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameService;
use App\Support\EditorContentRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\View\View;

class GameServiceDetailController extends Controller
{
    public function __invoke(Game $game, GameService $gameService, EditorContentRenderer $contentRenderer): View
    {
        abort_unless($game->status === 'active' && $game->game_services_enabled && $gameService->status === 'active', 404);

        $gameService->load([
            'servers' => fn ($query) => $query->active()->select(['game_servers.id', 'game_id', 'name', 'code']),
            'packages' => fn (Builder|HasMany $query) => $query
                ->active()
                ->whereHas('prices', fn (Builder $priceQuery) => $priceQuery->active())
                ->select(['id', 'game_service_id', 'name', 'code', 'description'])
                ->with(['prices' => fn (Builder|HasMany $priceQuery) => $priceQuery
                    ->active()
                    ->select(['id', 'game_service_package_id', 'price', 'quantity_enabled', 'min_quantity', 'max_quantity'])]),
        ]);

        $recentOrders = $gameService->orders()
            ->select(['id', 'game_service_id', 'package_name', 'server_name', 'quantity', 'status', 'created_at'])
            ->latest()
            ->limit(5)
            ->get();
        $seoFaqs = collect($gameService->faqs ?? [])
            ->filter(fn (mixed $faq): bool => is_array($faq) && filled($faq['question'] ?? null) && filled($faq['answer'] ?? null))
            ->map(fn (array $faq): array => ['question' => trim((string) $faq['question']), 'answer' => trim((string) $faq['answer'])])
            ->values();

        return view('client.game-services.service', [
            'game' => $game,
            'gameService' => $gameService,
            'recentOrders' => $recentOrders,
            'seoContentHtml' => $contentRenderer->renderNodes($gameService->seo_content ?? []),
            'seoFaqs' => $seoFaqs,
        ]);
    }
}
