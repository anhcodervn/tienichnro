<?php

namespace App\Features\NroGameData\Controllers;

use App\Features\NroGameData\Requests\PublicIndexGameDataRequest;
use App\Features\NroGameData\Services\GameDataReadCacheService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PublicGameDataController extends Controller
{
    public function index(PublicIndexGameDataRequest $request, string $dataset, GameDataReadCacheService $cache): JsonResponse
    {
        return response()->json($cache->index($dataset, $request->validated()));
    }
}
