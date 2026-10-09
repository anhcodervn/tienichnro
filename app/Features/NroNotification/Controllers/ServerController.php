<?php

namespace App\Features\NroNotification\Controllers;

use App\Features\NroNotification\Requests\UpsertServerRequest;
use App\Http\Controllers\Controller;
use App\Models\NroServer;
use Illuminate\Http\JsonResponse;

class ServerController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => NroServer::query()->orderBy('sort_order')->orderBy('server_code')->get()]);
    }

    public function store(UpsertServerRequest $request): JsonResponse
    {
        return response()->json(['data' => NroServer::query()->create($request->validated())], 201);
    }

    public function update(UpsertServerRequest $request, NroServer $server): JsonResponse
    {
        $server->update($request->validated());

        return response()->json(['data' => $server->fresh()]);
    }
}
