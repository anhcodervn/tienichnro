<?php

namespace App\Features\Admin\Topup\Controllers;

use App\Features\Admin\Topup\Requests\ListTopupProviderRequest;
use App\Features\Admin\Topup\Requests\StoreTopupProviderRequest;
use App\Features\Admin\Topup\Requests\UpdateTopupProviderRequest;
use App\Features\Admin\Topup\Resources\TopupProviderResource;
use App\Features\Admin\Topup\Services\TopupAdminService;
use App\Features\Topup\Exceptions\TopupProviderConnectionException;
use App\Features\Topup\Services\TopupProviderBalanceService;
use App\Features\Topup\Services\TopupProviderResolver;
use App\Http\Controllers\Controller;
use App\Models\TopupProvider;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TopupProviderController extends Controller
{
    public function __construct(
        private readonly TopupAdminService $service,
        private readonly TopupProviderBalanceService $balanceService,
    ) {}

    public function index(ListTopupProviderRequest $request): JsonResponse
    {
        return response()->json(['status' => true, 'data' => TopupProviderResource::collection($this->service->providers($request->validated()))->response()->getData(true)]);
    }

    public function store(StoreTopupProviderRequest $request): JsonResponse
    {
        $provider = $this->service->create(new TopupProvider, $request->validated(), $this->admin($request), $request);

        return response()->json(['status' => true, 'data' => TopupProviderResource::make($provider->loadCount('packages'))], 201);
    }

    public function show(TopupProvider $topupProvider): TopupProviderResource
    {
        return TopupProviderResource::make($topupProvider->loadCount('packages'));
    }

    public function update(UpdateTopupProviderRequest $request, TopupProvider $topupProvider): TopupProviderResource
    {
        $payload = $request->validated();

        if (isset($payload['connection_config'])) {
            $payload['connection_config'] = $topupProvider->mergeMaskedConnectionConfig($payload['connection_config']);
        }

        return TopupProviderResource::make(
            $this->service->update($topupProvider, $payload, $this->admin($request), $request)->loadCount('packages'),
        );
    }

    public function destroy(Request $request, TopupProvider $topupProvider): JsonResponse
    {
        if ($topupProvider->packages()->exists() || $topupProvider->globalPackages()->exists() || $topupProvider->orders()->exists()) {
            throw ValidationException::withMessages([
                'provider' => 'Không thể xóa provider đang được gán cho gói nạp hoặc đơn hàng.',
            ]);
        }

        $this->service->delete($topupProvider, $this->admin($request), $request);

        return response()->json(['status' => true, 'message' => 'Provider đã được xóa.']);
    }

    public function refreshBalances(Request $request): JsonResponse
    {
        $providerIds = collect($request->validate([
            'provider_ids' => ['required', 'array', 'max:100'],
            'provider_ids.*' => ['integer', 'distinct', 'exists:topup_providers,id'],
        ])['provider_ids']);

        $providers = TopupProvider::query()
            ->whereIn('id', $providerIds)
            ->whereIn('slug', TopupProviderResolver::BALANCE_PROVIDER_SLUGS)
            ->get();

        foreach ($providers as $provider) {
            try {
                $this->balanceService->forProvider($provider);
            } catch (TopupProviderConnectionException) {
                // Chẩn đoán an toàn đã được lưu để hiển thị trong bảng quản trị.
            }
        }

        return response()->json([
            'status' => true,
            'data' => TopupProviderResource::collection($providers->map->fresh()),
        ]);
    }

    private function admin(Request $request): User
    {
        /** @var User $admin */
        $admin = $request->user();

        return $admin;
    }
}
