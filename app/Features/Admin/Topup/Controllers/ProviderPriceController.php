<?php

namespace App\Features\Admin\Topup\Controllers;

use App\Features\Admin\Topup\Requests\ListProviderPriceRequest;
use App\Features\Admin\Topup\Requests\SelectProviderPriceRequest;
use App\Features\Admin\Topup\Requests\UpdateProviderPriceRequest;
use App\Features\Admin\Topup\Requests\UpdateProviderQuoteRequest;
use App\Features\Admin\Topup\Services\ProviderPriceAdminService;
use App\Features\Admin\Topup\Services\ProviderProductCatalogSyncService;
use App\Http\Controllers\Controller;
use App\Models\TopupProvider;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderPriceController extends Controller
{
    public function __construct(
        private readonly ProviderPriceAdminService $service,
        private readonly ProviderProductCatalogSyncService $catalogSyncService,
    ) {}

    public function index(ListProviderPriceRequest $request): JsonResponse
    {
        return response()->json(['status' => true, 'data' => $this->service->catalog($request->validated())]);
    }

    public function refresh(ListProviderPriceRequest $request): JsonResponse
    {
        $results = $this->catalogSyncService->refresh();

        return response()->json([
            'status' => true,
            'message' => collect($results)->contains('status', 'success')
                ? 'Đã cập nhật bảng giá provider.'
                : 'Không provider nào cập nhật giá thành công.',
            'data' => [
                ...$this->service->catalog($request->validated()),
                'sync_results' => $results,
            ],
        ]);
    }

    public function update(UpdateProviderPriceRequest $request, string $scope, int $id): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Đã cập nhật giá bán.',
            'data' => $this->service->updateSalePrice($scope, $id, $request->integer('price'), $this->admin($request), $request),
        ]);
    }

    public function updateQuote(
        UpdateProviderQuoteRequest $request,
        string $scope,
        int $id,
        TopupProvider $topupProvider,
    ): JsonResponse {
        $providerPrice = $request->validated('provider_price');

        return response()->json([
            'status' => true,
            'message' => $providerPrice === null ? 'Đã xóa báo giá provider.' : 'Đã lưu báo giá provider.',
            'data' => $this->service->updateQuote(
                $scope,
                $id,
                $topupProvider,
                is_numeric($providerPrice) ? (int) $providerPrice : null,
                $this->admin($request),
                $request,
            ),
        ]);
    }

    public function selectProvider(
        SelectProviderPriceRequest $request,
        string $scope,
        int $id,
        TopupProvider $topupProvider,
    ): JsonResponse {
        return response()->json([
            'status' => true,
            'message' => 'Đã kết nối provider cho gói nạp.',
            'data' => $this->service->selectProvider(
                $scope,
                $id,
                $topupProvider,
                $request->integer('provider_price'),
                $request->integer('price'),
                $this->admin($request),
                $request,
            ),
        ]);
    }

    private function admin(Request $request): User
    {
        /** @var User $admin */
        $admin = $request->user();

        return $admin;
    }
}
