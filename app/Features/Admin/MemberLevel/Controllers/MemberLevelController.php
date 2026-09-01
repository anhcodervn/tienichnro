<?php

namespace App\Features\Admin\MemberLevel\Controllers;

use App\Features\Admin\MemberLevel\Requests\AssignUserLevelRequest;
use App\Features\Admin\MemberLevel\Requests\StoreMemberLevelRequest;
use App\Features\Admin\MemberLevel\Requests\UpdateMemberLevelRequest;
use App\Features\Admin\MemberLevel\Requests\UpsertPackagePriceRequest;
use App\Features\Admin\MemberLevel\Services\MemberLevelAdminService;
use App\Http\Controllers\Controller;
use App\Models\MemberLevel;
use App\Models\TopupPackage;
use App\Models\User;
use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberLevelController extends Controller
{
    public function __construct(private readonly MemberLevelAdminService $service) {}

    public function index(): JsonResponse
    {
        return response()->json(ApiResponse::success(data: $this->service->catalog()));
    }

    public function store(StoreMemberLevelRequest $request): JsonResponse
    {
        return response()->json(ApiResponse::success('Đã tạo level.', [
            'level' => $this->service->create($request->validated(), $this->admin($request), $request),
        ]), 201);
    }

    public function update(UpdateMemberLevelRequest $request, MemberLevel $memberLevel): JsonResponse
    {
        return response()->json(ApiResponse::success('Đã cập nhật level.', [
            'level' => $this->service->update($memberLevel, $request->validated(), $this->admin($request), $request),
        ]));
    }

    public function destroy(Request $request, MemberLevel $memberLevel): JsonResponse
    {
        return response()->json(ApiResponse::success('Đã tạm tắt level.', [
            'level' => $this->service->disable($memberLevel, $this->admin($request), $request),
        ]));
    }

    public function upsertPackagePrice(
        UpsertPackagePriceRequest $request,
        MemberLevel $memberLevel,
        TopupPackage $topupPackage,
    ): JsonResponse {
        return response()->json(ApiResponse::success('Đã lưu giá theo level.', [
            'package_price' => $this->service->upsertPackagePrice(
                $memberLevel,
                $topupPackage,
                $request->validated(),
                $this->admin($request),
                $request,
            ),
        ]));
    }

    public function destroyPackagePrice(Request $request, MemberLevel $memberLevel, TopupPackage $topupPackage): JsonResponse
    {
        $this->service->destroyPackagePrice($memberLevel, $topupPackage, $this->admin($request), $request);

        return response()->json(ApiResponse::success('Đã xóa giá riêng của gói.'));
    }

    public function assignUser(AssignUserLevelRequest $request, User $user): JsonResponse
    {
        return response()->json(ApiResponse::success('Đã cập nhật level thủ công.', [
            'member_level' => $this->service->assignUser($user, $request->validated(), $this->admin($request)),
        ]));
    }

    private function admin(Request $request): User
    {
        /** @var User $admin */
        $admin = $request->user();

        return $admin;
    }
}
