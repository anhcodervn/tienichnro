<?php

namespace App\Features\Admin\Topup\Controllers;

use App\Features\Admin\Topup\Requests\StoreGlobalTopupPackageRequest;
use App\Features\Admin\Topup\Requests\UpdateGlobalTopupPackageRequest;
use App\Features\Admin\Topup\Resources\GlobalTopupPackageResource;
use App\Features\Admin\Topup\Services\GlobalTopupPackageAdminService;
use App\Http\Controllers\Controller;
use App\Models\GlobalTopupPackage;
use App\Models\User;
use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalTopupPackageController extends Controller
{
    public function __construct(private readonly GlobalTopupPackageAdminService $service) {}

    public function index(): JsonResponse
    {
        $catalog = $this->service->catalog();

        return response()->json(ApiResponse::success(data: [
            'global_packages' => GlobalTopupPackageResource::collection($catalog['global_packages'])->resolve(),
            'providers' => $catalog['providers'],
        ]));
    }

    public function store(StoreGlobalTopupPackageRequest $request): JsonResponse
    {
        $globalPackage = $this->service->create($request->validated(), $this->admin($request), $request);

        return response()->json(ApiResponse::success('Đã tạo gói nạp Global.', [
            'global_package' => GlobalTopupPackageResource::make($globalPackage),
        ]), 201);
    }

    public function update(UpdateGlobalTopupPackageRequest $request, GlobalTopupPackage $globalTopupPackage): JsonResponse
    {
        $globalPackage = $this->service->update($globalTopupPackage, $request->validated(), $this->admin($request), $request);

        return response()->json(ApiResponse::success('Đã cập nhật gói nạp Global.', [
            'global_package' => GlobalTopupPackageResource::make($globalPackage),
        ]));
    }

    public function destroy(Request $request, GlobalTopupPackage $globalTopupPackage): JsonResponse
    {
        $this->service->delete($globalTopupPackage, $this->admin($request), $request);

        return response()->json(ApiResponse::success('Đã xóa gói nạp Global.'));
    }

    private function admin(Request $request): User
    {
        /** @var User $admin */
        $admin = $request->user();

        return $admin;
    }
}
