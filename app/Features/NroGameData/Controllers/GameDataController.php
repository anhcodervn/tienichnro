<?php

namespace App\Features\NroGameData\Controllers;

use App\Features\NroGameData\Requests\IndexGameDataRequest;
use App\Features\NroGameData\Requests\StoreGameDataRequest;
use App\Features\NroGameData\Services\GameDataCatalogService;
use App\Features\NroGameData\Services\GameDataSyncService;
use App\Http\Controllers\Controller;
use App\Models\NroGameDataSync;
use Illuminate\Http\JsonResponse;

class GameDataController extends Controller
{
    public function store(StoreGameDataRequest $request, string $dataset, GameDataSyncService $service): JsonResponse
    {
        return response()->json(['data' => $service->enqueue($dataset, $request->validated())], 202);
    }

    public function compare(StoreGameDataRequest $request, string $dataset, GameDataCatalogService $catalog): JsonResponse
    {
        return response()->json(['data' => $catalog->compare($dataset, $request->validated('data'))]);
    }

    public function index(IndexGameDataRequest $request, string $dataset, GameDataCatalogService $catalog): JsonResponse
    {
        $input = $request->validated();
        $limit = (int) ($input['limit'] ?? 200);
        $query = $catalog->query($dataset)->where('id', '>', $input['after_id'] ?? 0)->orderBy('id');
        $identity = $catalog->identityColumns($dataset);
        if (isset($input['ids'])) {
            $query->whereIn($identity[array_key_last($identity)], $input['ids']);
        }
        if (isset($input['class_id'])) {
            $query->where('class_id', $input['class_id']);
        }
        if (($input['view'] ?? 'records') === 'hashes') {
            $query->select(['id', ...$identity, 'content_hash', 'updated_at']);
        }
        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit)->values();

        return response()->json(['data' => $rows, 'meta' => [
            'limit' => $limit, 'has_more' => $hasMore, 'next_after_id' => $hasMore ? $rows->last()->id : null,
        ]]);
    }

    public function showSync(NroGameDataSync $sync): JsonResponse
    {
        return response()->json(['data' => $sync]);
    }
}
