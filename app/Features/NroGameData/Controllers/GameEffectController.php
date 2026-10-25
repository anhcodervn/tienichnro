<?php

namespace App\Features\NroGameData\Controllers;

use App\Features\NroGameData\Requests\StoreGameEffectRequest;
use App\Features\NroGameData\Services\GameEffectService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class GameEffectController extends Controller
{
    public function store(StoreGameEffectRequest $request, int $effect, GameEffectService $effects): JsonResponse
    {
        return response()->json(['data' => $effects->install($effect, $request->file('sheet'), $request->file('animation'),
            $request->file('binary'), (int) $request->validated('tick_rate', 30))], 201);
    }
}
