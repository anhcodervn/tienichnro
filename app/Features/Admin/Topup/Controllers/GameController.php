<?php

namespace App\Features\Admin\Topup\Controllers;

use App\Features\Admin\Topup\Requests\ListGameRequest;
use App\Features\Admin\Topup\Requests\StoreGameRequest;
use App\Features\Admin\Topup\Requests\UpdateGameRequest;
use App\Features\Admin\Topup\Resources\GameResource;
use App\Features\Admin\Topup\Services\TopupAdminService;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function __construct(private readonly TopupAdminService $service) {}

    public function index(ListGameRequest $request): JsonResponse
    {
        return response()->json(['status' => true, 'data' => GameResource::collection($this->service->games($request->validated()))->response()->getData(true)]);
    }

    public function store(StoreGameRequest $request): JsonResponse
    {
        $game = $this->service->create(new Game, $request->validated(), $this->admin($request), $request);

        return response()->json(['status' => true, 'data' => GameResource::make($game)], 201);
    }

    public function show(Game $game): GameResource
    {
        return GameResource::make($game->loadCount(['servers', 'packages']));
    }

    public function update(UpdateGameRequest $request, Game $game): GameResource
    {
        return GameResource::make(
            $this->service->update($game, $request->validated(), $this->admin($request), $request)
        );
    }

    public function destroy(Request $request, Game $game): JsonResponse
    {
        $this->service->deleteCatalogModel($game, $this->admin($request), $request);

        return response()->json(['status' => true, 'message' => 'Game đã được xóa.']);
    }

    private function admin(Request $request): User
    {
        /** @var User $admin */
        $admin = $request->user();

        return $admin;
    }
}
