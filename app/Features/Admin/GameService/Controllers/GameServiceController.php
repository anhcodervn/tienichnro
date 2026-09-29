<?php

namespace App\Features\Admin\GameService\Controllers;

use App\Features\Admin\GameService\Requests\ListGameServiceCatalogRequest;
use App\Features\Admin\GameService\Requests\StoreGameServiceRequest;
use App\Features\Admin\GameService\Requests\UpdateGameServiceRequest;
use App\Features\Admin\GameService\Resources\GameServiceResource;
use App\Features\Admin\GameService\Services\GameServiceAdminService;
use App\Http\Controllers\Controller;
use App\Models\GameService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameServiceController extends Controller
{
    public function __construct(private readonly GameServiceAdminService $service) {}

    public function index(ListGameServiceCatalogRequest $request): JsonResponse
    {
        $services = GameServiceResource::collection($this->service->services($request->validated()))->response()->getData(true);

        return response()->json(['status' => true, 'data' => $services]);
    }

    public function store(StoreGameServiceRequest $request): JsonResponse
    {
        $service = $this->service->createService($request->validated(), $this->admin($request), $request);

        return response()->json(['status' => true, 'data' => GameServiceResource::make($service)], 201);
    }

    public function show(GameService $gameService): GameServiceResource
    {
        return GameServiceResource::make($gameService->load(['game:id,name,slug,provider_service_code', 'servers:id,game_id,name,code'])->loadCount(['packages', 'orders']));
    }

    public function update(UpdateGameServiceRequest $request, GameService $gameService): GameServiceResource
    {
        return GameServiceResource::make($this->service->updateService($gameService, $request->validated(), $this->admin($request), $request));
    }

    public function destroy(Request $request, GameService $gameService): JsonResponse
    {
        $this->service->deleteService($gameService, $this->admin($request), $request);

        return response()->json(['status' => true, 'message' => 'Dịch vụ game đã được xóa.']);
    }

    private function admin(Request $request): User
    {
        /** @var User $admin */
        $admin = $request->user();

        return $admin;
    }
}
