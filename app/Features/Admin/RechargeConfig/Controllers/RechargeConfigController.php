<?php

namespace App\Features\Admin\RechargeConfig\Controllers;

use App\Features\Admin\RechargeConfig\Requests\UpdateRechargeConfigRequest;
use App\Features\Admin\RechargeConfig\Requests\VerifyRechargePartnerCredentialsRequest;
use App\Features\Recharge\Resources\RechargeConfigResource;
use App\Features\Recharge\Services\ApiBankVnPartnerService;
use App\Features\Recharge\Services\RechargeConfigService;
use App\Http\Controllers\Controller;
use App\Models\ConfigRecharge;
use App\Utils\ApiResponse;
use App\Utils\Site;
use Illuminate\Http\JsonResponse;

class RechargeConfigController extends Controller
{
    public function __construct(
        private readonly ApiBankVnPartnerService $apiBankVnPartnerService,
        private readonly RechargeConfigService $rechargeConfigService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(ApiResponse::success(data: [
            'configs' => $this->rechargeConfigService->all()
                ->map(fn (ConfigRecharge $config): array => (new RechargeConfigResource($config))->resolve())
                ->values()
                ->all(),
            'site' => [
                'is_main' => Site::isMain(),
                'callback_url' => $this->callbackUrl(),
                'allowed_providers' => Site::isChild() ? ['apibankvn_api'] : ['manual', 'apibankvn_api'],
            ],
        ]));
    }

    public function store(UpdateRechargeConfigRequest $request): JsonResponse
    {
        $payload = $this->normalizedPayload($request);
        $config = $this->rechargeConfigService->create($payload);

        return response()->json(ApiResponse::success(
            message: 'Tạo cấu hình nạp tiền thành công.',
            data: [
                'config' => (new RechargeConfigResource($config))->resolve(),
            ],
        ), 201);
    }

    public function update(UpdateRechargeConfigRequest $request, ConfigRecharge $configRecharge): JsonResponse
    {
        $payload = $this->normalizedPayload($request);
        $config = $this->rechargeConfigService->update($configRecharge, $payload);

        return response()->json(ApiResponse::success(
            message: 'Cập nhật cấu hình nạp tiền thành công.',
            data: [
                'config' => (new RechargeConfigResource($config))->resolve(),
            ],
        ));
    }

    public function toggle(ConfigRecharge $configRecharge): JsonResponse
    {
        $config = $this->rechargeConfigService->toggle($configRecharge);

        return response()->json(ApiResponse::success(
            message: $config->is_active ? 'Đã bật cấu hình nạp tiền.' : 'Đã tắt cấu hình nạp tiền.',
            data: [
                'config' => (new RechargeConfigResource($config))->resolve(),
            ],
        ));
    }

    public function destroy(ConfigRecharge $configRecharge): JsonResponse
    {
        $this->rechargeConfigService->delete($configRecharge);

        return response()->json(ApiResponse::success(
            message: 'Đã xóa cấu hình nạp tiền.',
            data: [],
        ));
    }

    public function verifyCredentials(VerifyRechargePartnerCredentialsRequest $request): JsonResponse
    {
        $result = $this->apiBankVnPartnerService->verifyCredentials(
            apiKey: (string) $request->validated('api_key'),
            apiSecret: (string) $request->validated('api_secret'),
            baseUrl: 'https://apibankvn.com',
        );

        return response()->json(ApiResponse::success(
            message: 'Xác thực API key thành công.',
            data: $result,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedPayload(UpdateRechargeConfigRequest $request): array
    {
        $payload = $request->validated();
        $payload['transfer_prefix'] = $this->rechargeConfigService->normalizePrefix((string) $payload['transfer_prefix']);
        $payload['api_base_url'] = $payload['provider'] === 'apibankvn_api'
            ? 'https://apibankvn.com'
            : ($payload['api_base_url'] ?? null);

        return $payload;
    }

    private function callbackUrl(): string
    {
        $domain = Site::domain();

        return $domain === null
            ? url('/api/recharge/callbacks/apibankvn')
            : "https://{$domain}/api/recharge/callbacks/apibankvn";
    }
}
