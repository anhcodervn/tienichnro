<?php

namespace App\Features\Client\Api\Controllers;

use App\Features\Client\Api\Resources\CatalogGameResource;
use App\Features\MemberLevel\Services\MemberLevelPriceService;
use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function __invoke(Request $request, MemberLevelPriceService $memberLevelPriceService): JsonResponse
    {
        $games = Game::query()
            ->active()
            ->select(['id', 'name', 'slug', 'short_name', 'checkout_fields', 'package_mode', 'sort_order'])
            ->with([
                'servers' => fn (HasMany $query): HasMany => $query
                    ->active()
                    ->select(['id', 'game_id', 'name', 'sort_order']),
                'packages' => fn (HasMany $query): HasMany => $query
                    ->active()
                    ->whereNotNull('denomination')
                    ->select([
                        'id', 'game_id', 'global_topup_package_id', 'name', 'denomination', 'provider_price', 'price',
                        'original_price', 'min_quantity', 'max_quantity', 'sort_order',
                    ]),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $memberLevelStatus = $memberLevelPriceService->apply(
            $games->flatMap(fn (Game $game) => $game->packages),
            $request->user(),
        );
        $games->each(fn (Game $game) => $game->setRelation(
            'packages',
            $game->packages->filter(fn ($package) => $package->is_price_available)->values(),
        ));

        return response()->json([
            'status' => true,
            'data' => CatalogGameResource::collection($games)->resolve(),
            'member_level' => $memberLevelStatus,
        ]);
    }
}
