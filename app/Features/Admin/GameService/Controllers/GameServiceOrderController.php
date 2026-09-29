<?php

namespace App\Features\Admin\GameService\Controllers;

use App\Features\Admin\GameService\Requests\ListGameServiceOrderRequest;
use App\Features\Admin\GameService\Requests\UpdateGameServiceOrderRequest;
use App\Features\Admin\GameService\Resources\GameServiceOrderResource;
use App\Features\Admin\GameService\Services\GameServiceAdminService;
use App\Http\Controllers\Controller;
use App\Models\GameServiceOrder;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameServiceOrderController extends Controller
{
    public function __construct(private readonly GameServiceAdminService $service) {}

    public function index(ListGameServiceOrderRequest $request): JsonResponse
    {
        $orders = GameServiceOrderResource::collection($this->service->orders($request->validated()))->response()->getData(true);

        return response()->json(['status' => true, 'data' => $orders]);
    }

    public function show(GameServiceOrder $gameServiceOrder): GameServiceOrderResource
    {
        return GameServiceOrderResource::make($gameServiceOrder);
    }

    public function update(UpdateGameServiceOrderRequest $request, GameServiceOrder $gameServiceOrder): GameServiceOrderResource
    {
        return GameServiceOrderResource::make($this->service->updateOrder($gameServiceOrder, $request->validated(), $this->admin($request), $request));
    }

    private function admin(Request $request): User
    {
        /** @var User $admin */
        $admin = $request->user();

        return $admin;
    }
}
