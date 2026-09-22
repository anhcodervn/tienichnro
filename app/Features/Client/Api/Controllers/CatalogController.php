<?php

namespace App\Features\Client\Api\Controllers;

use App\Features\Client\Api\Resources\CatalogGameResource;
use App\Features\Topup\Services\TopupPackagePricingService;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function __invoke(Request $request, TopupPackagePricingService $topupPackagePricingService): JsonResponse
    {
        $games = Game::query()
            ->active()
            ->select(['id', 'name', 'slug', 'short_name', 'checkout_fields', 'package_mode', 'min_quantity', 'max_quantity', 'sort_order'])
            ->with([
                'servers' => fn (HasMany $query): HasMany => $query
                    ->active()
                    ->select(['id', 'game_id', 'name', 'sort_order']),
                'packages' => fn (HasMany $query): HasMany => $query
                    ->active()
                    ->whereNotNull('denomination')
                    ->select([
                        'id', 'game_id', 'global_topup_package_id', 'name', 'denomination', 'provider_price', 'price',
                        'original_price', 'sort_order',
                    ]),
            ])
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

        return response()->json([
            'status' => true,
            'data' => CatalogGameResource::collection($games)->resolve(),
        ]);
    }
}
