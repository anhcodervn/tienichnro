<?php

namespace App\Features\Admin\Topup\Controllers;

use App\Features\Admin\Topup\Requests\ListGameServerRequest;
use App\Features\Admin\Topup\Requests\StoreGameServerRequest;
use App\Features\Admin\Topup\Requests\UpdateGameServerRequest;
use App\Features\Admin\Topup\Resources\GameServerResource;
use App\Features\Admin\Topup\Services\TopupAdminService;
use App\Http\Controllers\Controller;
use App\Models\GameServer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameServerController extends Controller
{
    public function __construct(private readonly TopupAdminService $service) {}

    public function index(ListGameServerRequest $request): JsonResponse
    {
        return response()->json(['status' => true, 'data' => GameServerResource::collection($this->service->servers($request->validated()))->response()->getData(true)]);
    }

    public function store(StoreGameServerRequest $request): JsonResponse
    {
        $server = $this->service->create(new GameServer, $request->validated(), $this->admin($request), $request);

        return response()->json(['status' => true, 'data' => GameServerResource::make($server->load('game:id,name'))], 201);
    }

    public function show(GameServer $gameServer): GameServerResource
    {
        return GameServerResource::make($gameServer->load('game:id,name'));
    }

    public function update(UpdateGameServerRequest $request, GameServer $gameServer): GameServerResource
    {
        return GameServerResource::make($this->service->update($gameServer, $request->validated(), $this->admin($request), $request)->load('game:id,name'));
    }

    public function destroy(Request $request, GameServer $gameServer): JsonResponse
    {
        $this->service->deleteCatalogModel($gameServer, $this->admin($request), $request);

        return response()->json(['status' => true, 'message' => 'Máy chủ đã được xóa.']);
    }

    private function admin(Request $request): User
    {
        /** @var User $admin */
        $admin = $request->user();

        return $admin;
    }
}
