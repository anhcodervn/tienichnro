<?php

namespace App\Features\Admin\Topup\Controllers;

use App\Features\Admin\Topup\Requests\UpsertGlobalTopupPackageLevelPriceRequest;
use App\Features\Admin\Topup\Services\GlobalTopupPackageAdminService;
use App\Http\Controllers\Controller;
use App\Models\GlobalTopupPackage;
use App\Models\MemberLevel;
use App\Models\User;
use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalTopupPackageLevelPriceController extends Controller
{
    public function __construct(private readonly GlobalTopupPackageAdminService $service) {}

    public function update(
        UpsertGlobalTopupPackageLevelPriceRequest $request,
        GlobalTopupPackage $globalTopupPackage,
        MemberLevel $memberLevel,
    ): JsonResponse {
        $levelPrice = $this->service->upsertLevelPrice(
            $globalTopupPackage,
            $memberLevel,
            $request->validated(),
            $this->admin($request),
            $request,
        );

        return response()->json(ApiResponse::success('Đã lưu giá gói Global theo level.', [
            'level_price' => $levelPrice,
        ]));
    }

    public function destroy(Request $request, GlobalTopupPackage $globalTopupPackage, MemberLevel $memberLevel): JsonResponse
    {
        $this->service->deleteLevelPrice($globalTopupPackage, $memberLevel, $this->admin($request), $request);

        return response()->json(ApiResponse::success('Đã xóa giá gói Global theo level.'));
    }

    private function admin(Request $request): User
    {
        /** @var User $admin */
        $admin = $request->user();

        return $admin;
    }
}
