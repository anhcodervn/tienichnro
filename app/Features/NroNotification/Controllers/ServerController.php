<?php

namespace App\Features\NroNotification\Controllers;

use App\Features\NroNotification\Requests\ServerHeartbeatRequest;
use App\Features\NroNotification\Requests\UpsertServerRequest;
use App\Features\NroNotification\Services\ServerHeartbeatService;
use App\Http\Controllers\Controller;
use App\Models\NroServer;
use Illuminate\Http\JsonResponse;

class ServerController extends Controller
{
    public function internalIndex(): JsonResponse
    {
        return response()->json(NroServer::query()->where('is_active', true)
            ->orderBy('sort_order')->orderBy('server_code')->get(['id', 'name', 'code', 'server_code', 'connection', 'last_ping_at']));
    }

    public function ping(ServerHeartbeatRequest $request, ServerHeartbeatService $service): JsonResponse
    {
        $server = $service->ping((int) $request->validated('server_code'));

        return response()->json([
            'server_code' => $server->server_code, 'connection' => $server->connection,
            'last_ping_at' => $server->last_ping_at,
            'ping_interval_seconds' => max(1, (int) config('services.nro_bot.heartbeat_interval_seconds', 30)),
            'offline_after_seconds' => NroServer::heartbeatTimeoutSeconds(),
        ]);
    }

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
