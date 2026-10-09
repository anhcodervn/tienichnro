<?php

namespace App\Features\NroNotification\Controllers;

use App\Features\NroNotification\Requests\UpsertBossRequest;
use App\Http\Controllers\Controller;
use App\Models\Boss;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class BossController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Boss::query()->orderBy('sort_order')->orderBy('name')->orderBy('id')->get()]);
    }

    public function store(UpsertBossRequest $request): JsonResponse
    {
        return response()->json(['data' => Boss::query()->create($request->validated())], 201);
    }

    public function update(UpsertBossRequest $request, Boss $boss): JsonResponse
    {
        $boss->update($request->validated());

        return response()->json(['data' => $boss->fresh()]);
    }

    public function destroy(Boss $boss): Response
    {
        $boss->delete();

        return response()->noContent();
    }
}
