<?php

namespace App\Features\Client\GameService\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\View\View;

class GameServiceController extends Controller
{
    public function __invoke(Game $game): View
    {
        abort_unless($game->status === 'active' && $game->game_services_enabled, 404);

        $game->load(['gameServices' => fn (Builder|HasMany $query) => $query
            ->active()
            ->select(['id', 'game_id', 'name', 'slug', 'background_image'])
            ->withCount(['packages as active_packages_count' => fn (Builder $packageQuery) => $packageQuery->active()])
            ->with(['packages' => fn (Builder|HasMany $packageQuery) => $packageQuery
                ->active()
                ->select(['id', 'game_service_id'])
                ->with(['prices' => fn (Builder|HasMany $priceQuery) => $priceQuery
                    ->active()
                    ->select(['id', 'game_service_package_id', 'price'])])])]);

        abort_if($game->gameServices->isEmpty(), 404);
        $this->attachPriceRanges($game);

        return view('client.game-services.show', ['game' => $game]);
    }

    private function attachPriceRanges(Game $game): void
    {
        $game->gameServices->each(function (GameService $service): void {
            $prices = $service->packages->pluck('prices')->flatten()->pluck('price');

            $service->setAttribute('minimum_price', $prices->isEmpty() ? null : (int) $prices->min());
            $service->setAttribute('maximum_price', $prices->isEmpty() ? null : (int) $prices->max());
            $service->unsetRelation('packages');
        });
    }
}
