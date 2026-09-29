<?php

namespace App\Features\Admin\GameService\Controllers;

use App\Features\Admin\GameService\Requests\ListGameServiceCatalogRequest;
use App\Features\Admin\GameService\Requests\StoreGameServicePackageRequest;
use App\Features\Admin\GameService\Requests\UpdateGameServicePackageRequest;
use App\Features\Admin\GameService\Resources\GameServicePackageResource;
use App\Features\Admin\GameService\Services\GameServiceAdminService;
use App\Http\Controllers\Controller;
use App\Models\GameServicePackage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameServicePackageController extends Controller
{
    public function __construct(private readonly GameServiceAdminService $service) {}

    public function index(ListGameServiceCatalogRequest $request): JsonResponse
    {
        $packages = GameServicePackageResource::collection($this->service->packages($request->validated()))->response()->getData(true);

        return response()->json(['status' => true, 'data' => $packages]);
    }

    public function store(StoreGameServicePackageRequest $request): JsonResponse
    {
        $package = $this->service->createPackage($request->validated(), $this->admin($request), $request);

        return response()->json(['status' => true, 'data' => GameServicePackageResource::make($package)], 201);
    }

    public function show(GameServicePackage $gameServicePackage): GameServicePackageResource
    {
        return GameServicePackageResource::make($gameServicePackage->load(['service:id,game_id,name', 'service.game:id,name', 'prices'])->loadCount('orders'));
    }

    public function update(UpdateGameServicePackageRequest $request, GameServicePackage $gameServicePackage): GameServicePackageResource
    {
        return GameServicePackageResource::make($this->service->updatePackage($gameServicePackage, $request->validated(), $this->admin($request), $request));
    }

    public function destroy(Request $request, GameServicePackage $gameServicePackage): JsonResponse
    {
        $this->service->deletePackage($gameServicePackage, $this->admin($request), $request);

        return response()->json(['status' => true, 'message' => 'Gói dịch vụ đã được xóa.']);
    }

    private function admin(Request $request): User
    {
        /** @var User $admin */
        $admin = $request->user();

        return $admin;
    }
}
