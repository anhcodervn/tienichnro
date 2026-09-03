<?php

namespace App\Features\Admin\RechargeConfig\Controllers;

use App\Features\Admin\RechargeConfig\Requests\StoreRechargeBonusTierRequest;
use App\Features\Admin\RechargeConfig\Requests\UpdateRechargeBonusTierRequest;
use App\Features\Recharge\Services\RechargeBonusService;
use App\Http\Controllers\Controller;
use App\Models\RechargeBonusTier;
use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;

class RechargeBonusTierController extends Controller
{
    public function __construct(private readonly RechargeBonusService $rechargeBonusService) {}

    public function index(): JsonResponse
    {
        return response()->json(ApiResponse::success(data: [
            'tiers' => $this->rechargeBonusService->all()
                ->map(fn (RechargeBonusTier $tier): array => $this->rechargeBonusService->serialize($tier))
                ->values()
                ->all(),
        ]));
    }

    public function store(StoreRechargeBonusTierRequest $request): JsonResponse
    {
        $tier = $this->rechargeBonusService->create($request->validated());

        return response()->json(ApiResponse::success(
            message: 'Đã thêm mốc khuyến mãi nạp tiền.',
            data: ['tier' => $this->rechargeBonusService->serialize($tier)],
        ), 201);
    }

    public function update(UpdateRechargeBonusTierRequest $request, RechargeBonusTier $rechargeBonusTier): JsonResponse
    {
        $tier = $this->rechargeBonusService->update($rechargeBonusTier, $request->validated());

        return response()->json(ApiResponse::success(
            message: 'Đã cập nhật mốc khuyến mãi nạp tiền.',
            data: ['tier' => $this->rechargeBonusService->serialize($tier)],
        ));
    }

    public function destroy(RechargeBonusTier $rechargeBonusTier): JsonResponse
    {
        $this->rechargeBonusService->delete($rechargeBonusTier);

        return response()->json(ApiResponse::success(message: 'Đã xóa mốc khuyến mãi nạp tiền.'));
    }
}
