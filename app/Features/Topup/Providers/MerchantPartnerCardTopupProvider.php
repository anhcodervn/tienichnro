<?php

namespace App\Features\Topup\Providers;

use App\Features\Topup\Contracts\TopupProviderBalanceInterface;
use App\Features\Topup\Contracts\TopupProviderCatalogInterface;
use App\Features\Topup\Contracts\TopupProviderInterface;
use App\Features\Topup\DTOs\TopupProviderBalanceDto;
use App\Features\Topup\DTOs\TopupProviderProductDto;
use App\Features\Topup\DTOs\TopupProviderResultDto;
use App\Features\Topup\Enums\TopupProviderStatus;
use App\Features\Topup\Exceptions\TopupProviderConnectionException;
use App\Features\Topup\Services\TopupProviderHttpClientFactory;
use App\Models\GameServer;
use App\Models\Order;
use App\Models\OrderRecipient;
use App\Models\TopupPackage;
use App\Models\TopupProvider;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use JsonException;
use Throwable;

class MerchantPartnerCardTopupProvider implements TopupProviderBalanceInterface, TopupProviderCatalogInterface, TopupProviderInterface
{
    private const REQUEST_HEADERS = [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];

    public function __construct(private readonly TopupProviderHttpClientFactory $httpClientFactory) {}

    public function assertConfigured(TopupProvider $provider, TopupPackage $package, GameServer $server): void
    {
        $config = $this->configuration($provider);
        $errors = [];

        if ($config['partner_id'] === '') {
            $errors[] = 'partner_id';
        }

        if ($config['partner_key'] === '') {
            $errors[] = 'partner_key';
        }

        if (blank($package->providerServiceCode())) {
            $errors[] = 'mã dịch vụ của game';
        }

        if (blank($server->code)) {
            $errors[] = 'mã máy chủ';
        }

        if (filter_var($config['base_url'], FILTER_VALIDATE_URL) === false || parse_url($config['base_url'], PHP_URL_SCHEME) !== 'https') {
            $errors[] = 'base_url HTTPS';
        }

        if (! TopupProviderHttpClientFactory::isValidProxyUrl($config['proxy_url'])) {
            $errors[] = 'proxy';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'package_id' => 'Gói nạp hiện chưa sẵn sàng. Vui lòng chọn gói khác hoặc liên hệ hỗ trợ.',
            ]);
        }
    }

    public function submit(
        Order $order,
        OrderRecipient $recipient,
        ?TopupProvider $provider,
        string $requestId,
        ?int $quantity = null,
    ): TopupProviderResultDto {
        if (! $provider instanceof TopupProvider) {
            throw ValidationException::withMessages(['package_id' => 'Gói nạp hiện chưa sẵn sàng. Vui lòng liên hệ hỗ trợ.']);
        }

        $config = $this->configuration($provider);
        $serviceCode = (string) data_get($order->metadata, 'provider.service_code');
        $serverCode = (string) data_get($order->metadata, 'provider.server_code', $order->server?->code);
        $providerFields = $this->providerFields($recipient);
        unset($providerFields['server']);
        [$username, $primaryKey] = $this->primaryRecipientField($order, $providerFields, 'username');
        unset($providerFields['username'], $providerFields[$primaryKey]);

        if ($serverCode === '' || $username === '') {
            throw ValidationException::withMessages([
                'package_id' => 'Thông tin tài khoản hoặc máy chủ chưa phù hợp với gói nạp.',
            ]);
        }

        $payload = [
            'command' => 'topup',
            'partner_id' => $config['partner_id'],
            'request_id' => $requestId,
            'service_code' => $serviceCode,
            'amount' => (int) ($order->denomination ?? 0),
            'account_info' => [
                'server' => is_numeric($serverCode) ? (int) $serverCode : $serverCode,
                'username' => $username,
                ...$providerFields,
            ],
            'sign' => $this->signature($config['partner_key'], $config['partner_id'], 'topup', $requestId),
        ];

        return $this->request($config, $payload, null);
    }

    public function status(Order $order, OrderRecipient $recipient, TopupProvider $provider, string $requestId, string $reference): TopupProviderResultDto
    {
        $config = $this->configuration($provider);
        $payload = [
            'command' => 'getstatus',
            'partner_id' => $config['partner_id'],
            'request_id' => $requestId,
            'order_code' => $reference,
            'sign' => $this->signature($config['partner_key'], $config['partner_id'], 'getstatus', $requestId),
        ];

        return $this->request($config, $payload, $reference);
    }

    public function supportsStatusChecks(): bool
    {
        return true;
    }

    public function supportsBatchQuantity(): bool
    {
        return false;
    }

    public function balance(TopupProvider $provider): TopupProviderBalanceDto
    {
        $config = $this->configuration($provider);

        if (
            $config['partner_id'] === ''
            || $config['partner_key'] === ''
            || filter_var($config['base_url'], FILTER_VALIDATE_URL) === false
            || parse_url($config['base_url'], PHP_URL_SCHEME) !== 'https'
            || ! TopupProviderHttpClientFactory::isValidProxyUrl($config['proxy_url'])
        ) {
            throw new TopupProviderConnectionException('invalid_configuration', 'Cấu hình Merchant Partner Card chưa đầy đủ hoặc base URL không dùng HTTPS.');
        }

        $command = 'getbalance';
        try {
            $response = $this->httpClientFactory
                ->make($config['connect_timeout'], $config['timeout'], $config['proxy_url'])
                ->post($config['base_url'], [
                    'command' => $command,
                    'partner_id' => $config['partner_id'],
                    'sign' => md5($config['partner_key'].$config['partner_id'].$command),
                ])
                ->throw();
        } catch (Throwable $exception) {
            throw TopupProviderConnectionException::fromThrowable($exception);
        }

        $body = $response->json();
        $data = is_array($body) && is_array($body['data'] ?? null) ? $body['data'] : [];
        $rawBalance = $data['balance'] ?? null;
        $currency = strtoupper(trim((string) ($data['currency'] ?? 'VND')));

        if (
            strtolower(trim((string) ($body['status'] ?? ''))) !== 'success'
            || ! is_numeric($rawBalance)
            || (float) $rawBalance < 0
            || $currency === ''
            || strlen($currency) > 10
        ) {
            throw new TopupProviderConnectionException(
                'invalid_response',
                'Provider trả trạng thái lỗi hoặc dữ liệu số dư không đúng định dạng.',
            );
        }

        return new TopupProviderBalanceDto(
            balance: (int) round((float) $rawBalance),
            currency: $currency,
        );
    }

    /** @return Collection<int, TopupProviderProductDto> */
    public function products(TopupProvider $provider): Collection
    {
        $config = $this->configuration($provider);

        if (
            $config['partner_id'] === ''
            || $config['partner_key'] === ''
            || filter_var($config['base_url'], FILTER_VALIDATE_URL) === false
            || parse_url($config['base_url'], PHP_URL_SCHEME) !== 'https'
            || ! TopupProviderHttpClientFactory::isValidProxyUrl($config['proxy_url'])
        ) {
            throw new TopupProviderConnectionException('invalid_configuration', 'Cấu hình provider chưa đầy đủ hoặc base URL không dùng HTTPS.');
        }

        $command = 'productlist';

        try {
            $response = $this->httpClientFactory
                ->make($config['connect_timeout'], $config['timeout'], $config['proxy_url'])
                ->post($config['base_url'], [
                    'command' => $command,
                    'partner_id' => $config['partner_id'],
                    'sign' => md5($config['partner_key'].$config['partner_id'].$command),
                ])
                ->throw();
        } catch (Throwable $exception) {
            throw TopupProviderConnectionException::fromThrowable($exception);
        }

        $body = $response->json();
        $status = is_array($body) ? $body['status'] ?? null : null;
        $catalog = is_array($body) && is_array($body['data'] ?? null) ? $body['data'] : null;

        if (! in_array($status, ['success', 1, '1', true], true) || ! is_array($catalog)) {
            throw new TopupProviderConnectionException(
                'invalid_catalog_response',
                'Provider trả về danh sách sản phẩm không đúng định dạng.',
            );
        }

        return collect($catalog)
            ->filter(fn (mixed $product): bool => is_array($product))
            ->flatMap(function (array $product): array {
                $serviceCode = strtoupper(trim((string) ($product['service_code'] ?? $product['key'] ?? '')));
                $items = is_array($product['items'] ?? null) ? $product['items'] : [];

                return collect($items)
                    ->filter(fn (mixed $item): bool => is_array($item))
                    ->map(function (array $item) use ($serviceCode): ?TopupProviderProductDto {
                        $denomination = $item['value'] ?? $item['amount'] ?? $item['denomination'] ?? null;
                        $price = $item['price'] ?? $item['pay_amount'] ?? null;
                        $discount = $item['discount'] ?? 0;

                        if ($serviceCode === '' || ! is_numeric($denomination) || ! is_numeric($price) || ! is_numeric($discount)) {
                            return null;
                        }

                        $normalizedDenomination = (int) round((float) $denomination);
                        $normalizedBasePrice = (int) round((float) $price);
                        $normalizedDiscountRate = (float) $discount;
                        $normalizedPrice = (int) round($normalizedBasePrice * (100 - $normalizedDiscountRate) / 100);

                        if ($normalizedDenomination <= 0 || $normalizedBasePrice <= 0 || $normalizedDiscountRate < 0 || $normalizedDiscountRate >= 100 || $normalizedPrice <= 0) {
                            return null;
                        }

                        return new TopupProviderProductDto($serviceCode, $normalizedDenomination, $normalizedPrice);
                    })
                    ->filter()
                    ->values()
                    ->all();
            })
            ->values();
    }

    /** @param array{base_url:string,partner_id:string,partner_key:string,proxy_url:string,connect_timeout:int,timeout:int,max_status_checks:int} $config */
    private function request(array $config, array $payload, ?string $fallbackReference): TopupProviderResultDto
    {
        $requestSnapshot = [
            'method' => 'POST',
            'url' => $config['base_url'],
            'headers' => self::REQUEST_HEADERS,
            'payload' => $payload,
            'raw_body' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];

        try {
            $response = $this->httpClientFactory
                ->make($config['connect_timeout'], $config['timeout'], $config['proxy_url'])
                ->beforeSending(function (ClientRequest $request) use (&$requestSnapshot): void {
                    $requestSnapshot = [
                        'method' => $request->method(),
                        'url' => $request->url(),
                        'headers' => $request->headers(),
                        'payload' => $request->data(),
                        'raw_body' => $request->body(),
                    ];
                })
                ->post($config['base_url'], $payload);

            if ($response->status() === 429 || $response->serverError()) {
                $response->throw();
            }
        } catch (Throwable $exception) {
            $responseSnapshot = isset($response) && $response instanceof Response
                ? $this->httpResponseSnapshot($response)
                : [
                    'http_status' => null,
                    'reason' => null,
                    'effective_uri' => $requestSnapshot['url'],
                    'headers' => [],
                    'body' => null,
                    'raw_body' => null,
                    'transport_error' => [
                        'class' => $exception::class,
                        'message' => $exception->getMessage(),
                    ],
                ];

            throw TopupProviderConnectionException::fromThrowable($exception, [
                'request' => $requestSnapshot,
                'response' => $responseSnapshot,
            ]);
        }

        return $this->resultFromResponse($response, $fallbackReference, $requestSnapshot);
    }

    /** @param array<string, mixed> $requestSnapshot */
    private function resultFromResponse(
        Response $response,
        ?string $fallbackReference,
        array $requestSnapshot,
    ): TopupProviderResultDto {
        $providerResponse = $this->responseBody($response);
        $body = is_array($providerResponse) ? $providerResponse : [];
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        $transactionStatus = strtolower(trim((string) ($data['status'] ?? '')));
        $envelopeStatus = strtolower(trim((string) ($body['status'] ?? '')));
        $providerTopupId = is_scalar($data['topup_id'] ?? null) && filled($data['topup_id'])
            ? trim((string) $data['topup_id'])
            : null;
        $status = match ($transactionStatus) {
            'success', 'completed', 'complete', 'done', '1' => TopupProviderStatus::Completed,
            'failed', 'fail', 'error', 'cancelled', 'canceled', '-1' => TopupProviderStatus::Failed,
            'processing', 'running' => TopupProviderStatus::Processing,
            default => TopupProviderStatus::Pending,
        };

        if ($transactionStatus === '' && ! in_array($envelopeStatus, ['success', 'pending', 'processing'], true)) {
            $status = TopupProviderStatus::Failed;
        }

        $reference = filled($data['order_code'] ?? null)
            ? (string) $data['order_code']
            : $fallbackReference;
        $message = filled($data['message'] ?? null)
            ? (string) $data['message']
            : (filled($body['message'] ?? null) ? (string) $body['message'] : null);

        if ($response->clientError() && blank($message)) {
            $message = match ($response->status()) {
                401, 403 => 'Provider từ chối xác thực; kiểm tra credential hoặc IP whitelist.',
                422 => 'Provider từ chối dữ liệu đơn nạp.',
                default => 'Provider từ chối yêu cầu nạp (HTTP '.$response->status().').',
            };
        }

        $safeMessage = $message !== null ? mb_substr(strip_tags($message), 0, 500) : null;

        return new TopupProviderResultDto(
            status: $status,
            reference: $reference,
            message: $safeMessage,
            response: [
                ...$this->httpResponseSnapshot($response),
                'provider_status' => $transactionStatus !== '' ? $transactionStatus : null,
                'envelope_status' => $envelopeStatus !== '' ? $envelopeStatus : null,
                'provider_code' => is_scalar($body['code'] ?? null) ? $body['code'] : null,
                'provider_topup_id' => $providerTopupId,
            ],
            request: $requestSnapshot,
            providerResponse: $providerResponse,
        );
    }

    private function responseBody(Response $response): mixed
    {
        try {
            return json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $response->body();
        }
    }

    /** @return array<string, mixed> */
    private function httpResponseSnapshot(Response $response): array
    {
        return [
            'http_status' => $response->status(),
            'reason' => $response->reason(),
            'effective_uri' => (string) $response->effectiveUri(),
            'headers' => $response->headers(),
            'body' => $this->responseBody($response),
            'raw_body' => $response->body(),
        ];
    }

    /** @return array{base_url:string,partner_id:string,partner_key:string,proxy_url:string,connect_timeout:int,timeout:int,max_status_checks:int} */
    public function configuration(TopupProvider $provider): array
    {
        $config = $provider->connection_config ?? [];

        return [
            'base_url' => rtrim((string) ($config['base_url'] ?? ''), '/'),
            'partner_id' => trim((string) ($config['partner_id'] ?? $config['api_key'] ?? '')),
            'partner_key' => trim((string) ($config['partner_key'] ?? $config['api_secret'] ?? '')),
            'proxy_url' => TopupProviderHttpClientFactory::normalizeProxyUrl($config['proxy_url'] ?? $config['proxy'] ?? null),
            'connect_timeout' => min(max((int) ($config['connect_timeout'] ?? 5), 1), 15),
            'timeout' => min(max((int) ($config['timeout'] ?? 20), 5), 45),
            'max_status_checks' => min(max((int) ($config['max_status_checks'] ?? 20), 1), 100),
        ];
    }

    private function signature(string $partnerKey, string $partnerId, string $command, string $requestId): string
    {
        return md5($partnerKey.$partnerId.$command.$requestId);
    }

    /** @return array<string, string> */
    private function providerFields(OrderRecipient $recipient): array
    {
        $recipientData = is_array($recipient->recipient_data) ? $recipient->recipient_data : [];

        return collect($recipientData)
            ->mapWithKeys(fn (mixed $value, mixed $key): array => [(string) $key => trim((string) $value)])
            ->filter(fn (string $value): bool => $value !== '')
            ->all();
    }

    /**
     * @param  array<string, string>  $providerFields
     * @return array{0:string,1:string}
     */
    private function primaryRecipientField(Order $order, array $providerFields, string $providerKey): array
    {
        if (filled($providerFields[$providerKey] ?? null)) {
            return [$providerFields[$providerKey], $providerKey];
        }

        foreach ($order->checkout_fields_snapshot ?? [] as $field) {
            $key = is_array($field) ? (string) ($field['key'] ?? '') : '';

            if ($key !== '' && filled($providerFields[$key] ?? null)) {
                return [$providerFields[$key], $key];
            }
        }

        $key = (string) collect($providerFields)->search(fn (string $value): bool => $value !== '');

        return [$key !== '' ? $providerFields[$key] : '', $key];
    }
}
