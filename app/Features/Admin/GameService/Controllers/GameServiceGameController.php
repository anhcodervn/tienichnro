<?php

namespace App\Features\Admin\GameService\Controllers;

use App\Features\Admin\GameService\Requests\ListGameServiceCatalogRequest;
use App\Features\Admin\GameService\Requests\UpdateGameServiceGameRequest;
use App\Features\Admin\GameService\Resources\GameServiceGameResource;
use App\Features\Admin\GameService\Services\GameServiceAdminService;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameServiceGameController extends Controller
{
    public function __construct(private readonly GameServiceAdminService $service) {}

    public function index(ListGameServiceCatalogRequest $request): JsonResponse
    {
        $games = GameServiceGameResource::collection($this->service->games($request->validated()))->response()->getData(true);

        return response()->json(['status' => true, 'data' => $games]);
    }

    public function update(UpdateGameServiceGameRequest $request, Game $game): GameServiceGameResource
    {
        return GameServiceGameResource::make(
            $this->service->updateGame($game, $request->validated(), $this->admin($request), $request)->loadCount(['servers', 'gameServices']),
        );
    }

    private function admin(Request $request): User
    {
        /** @var User $admin */
        $admin = $request->user();

        return $admin;
    }
}
