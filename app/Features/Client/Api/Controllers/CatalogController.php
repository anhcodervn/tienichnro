<?php

namespace App\Features\Client\Api\Controllers;

use App\Features\Client\Api\Resources\CatalogGameResource;
use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;

class CatalogController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $games = Game::query()
            ->active()
            ->select(['id', 'name', 'slug', 'short_name', 'checkout_fields', 'sort_order'])
            ->with([
                'servers' => fn (HasMany $query): HasMany => $query
                    ->active()
                    ->select(['id', 'game_id', 'name', 'sort_order']),
                'packages' => fn (HasMany $query): HasMany => $query
                    ->active()
                    ->whereNotNull('denomination')
                    ->where(function ($query): void {
                        $query->whereNull('game_server_id')
                            ->orWhereHas('server', fn ($serverQuery) => $serverQuery->active());
                    })
                    ->select([
                        'id', 'game_id', 'game_server_id', 'name', 'denomination', 'price',
                        'original_price', 'min_quantity', 'max_quantity', 'sort_order',
                    ]),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'status' => true,
            'data' => CatalogGameResource::collection($games)->resolve(),
        ]);
    }
}
