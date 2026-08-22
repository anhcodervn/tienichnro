<?php

namespace App\Features\Recharge\Services;

use App\Exceptions\ApiException;
use App\Models\ConfigRecharge;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class ApiBankVnPartnerService
{
    private const DEFAULT_BASE_URL = 'https://apibankvn.com';

    public function __construct(private readonly BankTransferContentService $bankTransferContentService) {}

    /**
     * @return array<string, mixed>
     */
    public function createRechargeOrder(
        ConfigRecharge $config,
        float $amount,
        string $clientOrderCode,
        ?string $transferContent = null,
    ): array {
        $payload = [
            'bank_id' => (int) $config->api_bank_id,
            'amount' => (int) round($amount),
            'client_order_code' => $clientOrderCode,
        ];

        if (filled($transferContent)) {
            $payload['transfer_prefix'] = $this->bankTransferContentService->normalizePrefix((string) $config->transfer_prefix);
            $payload['transfer_content'] = trim((string) $transferContent);
        }

        $response = $this->request($config)->post('/recharge-orders', $payload);

        return $this->extractOrderData($response->json());
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchRechargeOrder(ConfigRecharge $config, string $orderCode): array
    {
        $response = $this->request($config)->get('/recharge-orders/'.$orderCode);

        return $this->extractOrderData($response->json());
    }

    /**
     * @return array{
     *     user: array<string, mixed>,
     *     permissions: array<int, mixed>,
     *     endpoints: array<int, string>,
     *     bank_accounts: array<int, array<string, mixed>>
     * }
     */
    public function verifyCredentials(string $apiKey, string $apiSecret, ?string $baseUrl = null): array
    {
        $request = $this->requestWithCredentials($apiKey, $apiSecret, $baseUrl);

        try {
            $profilePayload = $request->get('/')->throw()->json();
            $banksPayload = $request->get('/list-bank-accounts')->throw()->json();
        } catch (RequestException $exception) {
            $payload = $exception->response?->json();
            $message = is_array($payload) ? (string) ($payload['message'] ?? '') : '';

            throw new ApiException($message !== '' ? $message : 'Không thể xác thực với apibankvn.com.', $exception->response?->status() ?? 422);
        }

        if (! is_array($profilePayload) || ! ($profilePayload['status'] ?? false)) {
            throw new ApiException((string) ($profilePayload['message'] ?? 'Không thể xác thực với apibankvn.com.'), 422);
        }

        if (! is_array($banksPayload) || ! ($banksPayload['status'] ?? false)) {
            throw new ApiException((string) ($banksPayload['message'] ?? 'Không thể lấy danh sách tài khoản ngân hàng.'), 422);
        }

        return [
            'user' => is_array($profilePayload['data']['user'] ?? null) ? $profilePayload['data']['user'] : [],
            'permissions' => is_array($profilePayload['data']['permissions'] ?? null) ? $profilePayload['data']['permissions'] : [],
            'endpoints' => array_values(array_filter(
                is_array($profilePayload['data']['endpoints'] ?? null) ? $profilePayload['data']['endpoints'] : [],
                static fn (mixed $endpoint): bool => is_string($endpoint)
            )),
            'bank_accounts' => array_values(array_filter(
                is_array($banksPayload['data']['bank_accounts'] ?? null) ? $banksPayload['data']['bank_accounts'] : [],
                static fn (mixed $bankAccount): bool => is_array($bankAccount)
            )),
        ];
    }

    public function isConfigured(ConfigRecharge $config): bool
    {
        return $config->provider === 'apibankvn_api'
            && filled($config->api_key)
            && filled($config->api_secret)
            && (int) $config->api_bank_id > 0;
    }

    private function request(ConfigRecharge $config): PendingRequest
    {
        if (! $this->isConfigured($config)) {
            throw new ApiException('Cấu hình tích hợp apibankvn.com chưa đầy đủ.', 422);
        }

        return $this->requestWithCredentials(
            apiKey: (string) $config->api_key,
            apiSecret: (string) $config->api_secret,
            baseUrl: $config->api_base_url,
        );
    }

    private function requestWithCredentials(string $apiKey, string $apiSecret, ?string $baseUrl = null): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(15)
            ->retry(2, 300)
            ->baseUrl(rtrim($baseUrl ?: self::DEFAULT_BASE_URL, '/').'/api/v1')
            ->withHeaders([
                'X-API-KEY' => trim($apiKey),
                'X-API-SECRET' => trim($apiSecret),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractOrderData(mixed $payload): array
    {
        if (! is_array($payload)) {
            throw new ApiException('API apibankvn.com trả về dữ liệu không hợp lệ.', 502);
        }

        $status = (bool) ($payload['status'] ?? false);
        $message = (string) ($payload['message'] ?? 'Không thể kết nối tới apibankvn.com.');
        $order = $payload['data']['order'] ?? null;

        if (! $status || ! is_array($order)) {
            throw new ApiException($message, 422);
        }

        return $order;
    }
}
