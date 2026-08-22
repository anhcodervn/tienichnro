<?php

namespace App\Features\Admin\Topup\Controllers;

use App\Features\Admin\Topup\Requests\ListTopupProviderRequest;
use App\Features\Admin\Topup\Requests\StoreTopupProviderRequest;
use App\Features\Admin\Topup\Requests\UpdateTopupProviderRequest;
use App\Features\Admin\Topup\Resources\TopupProviderResource;
use App\Features\Admin\Topup\Services\TopupAdminService;
use App\Http\Controllers\Controller;
use App\Models\TopupProvider;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TopupProviderController extends Controller
{
    public function __construct(private readonly TopupAdminService $service) {}

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
        if ($topupProvider->packages()->exists() || $topupProvider->orders()->exists()) {
            throw ValidationException::withMessages([
                'provider' => 'Không thể xóa provider đang được gán cho gói nạp hoặc đơn hàng.',
            ]);
        }

        $this->service->delete($topupProvider, $this->admin($request), $request);

        return response()->json(['status' => true, 'message' => 'Provider đã được xóa.']);
    }

    private function admin(Request $request): User
    {
        /** @var User $admin */
        $admin = $request->user();

        return $admin;
    }
}
