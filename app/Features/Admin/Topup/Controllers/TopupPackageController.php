<?php

namespace App\Features\Admin\Topup\Controllers;

use App\Features\Admin\Topup\Requests\ListTopupPackageRequest;
use App\Features\Admin\Topup\Requests\StoreTopupPackageRequest;
use App\Features\Admin\Topup\Requests\UpdateTopupPackageRequest;
use App\Features\Admin\Topup\Resources\TopupPackageResource;
use App\Features\Admin\Topup\Services\TopupAdminService;
use App\Http\Controllers\Controller;
use App\Models\TopupPackage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TopupPackageController extends Controller
{
    public function __construct(private readonly TopupAdminService $service) {}

    public function index(ListTopupPackageRequest $request): JsonResponse
    {
        return response()->json(['status' => true, 'data' => TopupPackageResource::collection($this->service->packages($request->validated()))->response()->getData(true)]);
    }

    public function store(StoreTopupPackageRequest $request): JsonResponse
    {
        $package = $this->service->create(new TopupPackage, $request->validated(), $this->admin($request), $request);

        return response()->json(['status' => true, 'data' => TopupPackageResource::make($package->load(['game:id,name', 'server:id,name', 'provider:id,name,slug']))], 201);
    }

    public function show(TopupPackage $topupPackage): TopupPackageResource
    {
        return TopupPackageResource::make($topupPackage->load(['game:id,name', 'server:id,name', 'provider:id,name,slug']));
    }

    public function update(UpdateTopupPackageRequest $request, TopupPackage $topupPackage): TopupPackageResource
    {
        return TopupPackageResource::make($this->service->update($topupPackage, $request->validated(), $this->admin($request), $request)->load(['game:id,name', 'server:id,name', 'provider:id,name,slug']));
    }

    public function destroy(Request $request, TopupPackage $topupPackage): JsonResponse
    {
        $this->service->deleteCatalogModel($topupPackage, $this->admin($request), $request);

        return response()->json(['status' => true, 'message' => 'Gói nạp đã được xóa.']);
    }

    private function admin(Request $request): User
    {
        /** @var User $admin */
        $admin = $request->user();

        return $admin;
    }
}
