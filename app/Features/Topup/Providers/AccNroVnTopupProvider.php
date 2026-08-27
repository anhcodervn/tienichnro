<?php

namespace App\Features\Topup\Providers;

use App\Features\Topup\Contracts\TopupProviderBalanceInterface;
use App\Features\Topup\Contracts\TopupProviderInterface;
use App\Features\Topup\DTOs\TopupProviderBalanceDto;
use App\Features\Topup\DTOs\TopupProviderResultDto;
use App\Features\Topup\Enums\TopupProviderStatus;
use App\Features\Topup\Exceptions\TopupProviderConnectionException;
use App\Models\GameServer;
use App\Models\Order;
use App\Models\OrderRecipient;
use App\Models\TopupPackage;
use App\Models\TopupProvider;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use JsonException;
use Throwable;

class AccNroVnTopupProvider implements TopupProviderBalanceInterface, TopupProviderInterface
{
    private const DEFAULT_BASE_URL = 'https://accnro.vn/api/v1/partner/recharge';

    private const REQUEST_HEADERS = [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];

    public function assertConfigured(TopupProvider $provider, TopupPackage $package, GameServer $server): void
    {
        $config = $this->configuration($provider);
        $game = trim((string) $package->provider_service_code);
        $configuredGame = $package->game()->first();
        $checkoutFieldKeys = collect($configuredGame?->checkoutFields() ?? [])->pluck('key');
        $hasInvalidConfiguration = $config['partner_id'] === ''
            || $config['secret_key'] === ''
            || $game === ''
            || ! $checkoutFieldKeys->contains('account')
            || filter_var($config['base_url'], FILTER_VALIDATE_URL) === false
            || parse_url($config['base_url'], PHP_URL_SCHEME) !== 'https';

        if ($hasInvalidConfiguration) {
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
    ): TopupProviderResultDto {
        if (! $provider instanceof TopupProvider) {
            throw ValidationException::withMessages([
                'package_id' => 'Gói nạp hiện chưa sẵn sàng. Vui lòng liên hệ hỗ trợ.',
            ]);
        }

        $config = $this->configuration($provider);
        $game = trim((string) data_get($order->metadata, 'provider.service_code'));
        $providerFields = $this->providerFields($recipient);
        $server = $this->serverCode($order);
        $account = $providerFields['account'] ?? '';
        unset($providerFields['account']);

        if ($game === '' || $account === '') {
            throw ValidationException::withMessages([
                'package_id' => 'Thông tin tài khoản hoặc mã game chưa phù hợp với gói nạp.',
            ]);
        }

        $payload = [
            'partner_id' => $config['partner_id'],
            'secret_key' => $config['secret_key'],
            'request_id' => $requestId,
            'game' => $game,
            ...($server !== '' ? ['server' => $server] : []),
            'account' => $account,
            'price' => (int) ($order->denomination ?? 0),
            'amount' => 1,
            ...($providerFields !== [] ? ['extra' => $providerFields] : []),
        ];

        $exchange = $this->request($config, 'create', $payload);

        return $this->resultFromResponse($exchange['response'], null, $exchange['request']);
    }

    public function status(
        Order $order,
        OrderRecipient $recipient,
        TopupProvider $provider,
        string $requestId,
        string $reference,
    ): TopupProviderResultDto {
        $config = $this->configuration($provider);
        $payload = [
            'partner_id' => $config['partner_id'],
            'secret_key' => $config['secret_key'],
            'request_id' => $requestId,
        ];
        $exchange = $this->request($config, 'query', $payload);

        return $this->resultFromResponse($exchange['response'], $reference, $exchange['request']);
    }

    public function supportsStatusChecks(): bool
    {
        return true;
    }

    public function balance(TopupProvider $provider): TopupProviderBalanceDto
    {
        $config = $this->configuration($provider);
        $this->assertConnectionConfigured($config);
        $payload = [
            'partner_id' => $config['partner_id'],
            'secret_key' => $config['secret_key'],
        ];
        $response = $this->request($config, 'balance', $payload)['response'];

        if ($response->clientError()) {
            try {
                $response->throw();
            } catch (Throwable $exception) {
                throw TopupProviderConnectionException::fromThrowable($exception);
            }
        }

        $body = $response->json();
        $data = is_array($body) && is_array($body['data'] ?? null) ? $body['data'] : [];
        $rawBalance = $data['balance'] ?? null;

        if (($body['success'] ?? null) !== true || ! is_numeric($rawBalance) || (float) $rawBalance < 0) {
            throw new TopupProviderConnectionException(
                'invalid_response',
                'Provider trả trạng thái lỗi hoặc dữ liệu số dư không đúng định dạng.',
            );
        }

        return new TopupProviderBalanceDto(
            balance: (int) round((float) $rawBalance),
            currency: 'VND',
        );
    }

    /** @return array{base_url:string,partner_id:string,secret_key:string,connect_timeout:int,timeout:int,max_status_checks:int} */
    public function configuration(TopupProvider $provider): array
    {
        $config = $provider->connection_config ?? [];
        $explicitPartnerId = $this->firstConfiguredString($config, ['partner_id', 'api_key']);
        $partnerId = $explicitPartnerId !== ''
            ? $explicitPartnerId
            : $this->firstConfiguredString($config, ['partner_key']);
        $secretKey = $this->firstConfiguredString($config, ['secret_key', 'serect_key', 'api_secret']);

        if ($secretKey === '' && $explicitPartnerId !== '') {
            $secretKey = $this->firstConfiguredString($config, ['partner_key']);
        }

        return [
            'base_url' => $this->normalizedBaseUrl((string) ($config['base_url'] ?? self::DEFAULT_BASE_URL)),
            'partner_id' => $partnerId,
            'secret_key' => $secretKey,
            'connect_timeout' => min(max((int) ($config['connect_timeout'] ?? 5), 1), 15),
            'timeout' => min(max((int) ($config['timeout'] ?? 20), 5), 45),
            'max_status_checks' => min(max((int) ($config['max_status_checks'] ?? 20), 1), 100),
        ];
    }

    /**
     * @param  array{base_url:string,partner_id:string,secret_key:string,connect_timeout:int,timeout:int,max_status_checks:int}  $config
     * @param  array<string, mixed>  $payload
     * @return array{response: Response, request: array<string, mixed>}
     */
    private function request(array $config, string $operation, array $payload): array
    {
        $this->assertConnectionConfigured($config);

        $requestSnapshot = [
            'method' => 'POST',
            'url' => $config['base_url'].'/'.$operation,
            'headers' => self::REQUEST_HEADERS,
            'payload' => $payload,
            'raw_body' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout($config['connect_timeout'])
                ->timeout($config['timeout'])
                ->beforeSending(function (ClientRequest $request) use (&$requestSnapshot): void {
                    $requestSnapshot = [
                        'method' => $request->method(),
                        'url' => $request->url(),
                        'headers' => $request->headers(),
                        'payload' => $request->data(),
                        'raw_body' => $request->body(),
                    ];
                })
                ->post(
                    $config['base_url'].'/'.$operation,
                    $payload,
                );

            if ($response->status() === 429 || $response->serverError()) {
                $response->throw();
            }

            return ['response' => $response, 'request' => $requestSnapshot];
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
        $providerStatus = strtolower(trim((string) ($data['status'] ?? '')));
        $providerCode = strtoupper(trim((string) ($data['status_code'] ?? '')));
        $status = match (true) {
            in_array($providerStatus, ['success', 'completed', 'complete', 'done'], true),
            $providerCode === 'SUCCESS' => TopupProviderStatus::Completed,
            in_array($providerStatus, ['failed', 'fail', 'error', 'cancelled', 'canceled'], true),
            in_array($providerCode, ['FAILED', 'REJECTED', 'CANCELLED', 'CANCELED'], true) => TopupProviderStatus::Failed,
            $providerStatus === 'processing', $providerCode === 'PROCESSING' => TopupProviderStatus::Processing,
            default => TopupProviderStatus::Pending,
        };

        if (($body['success'] ?? null) !== true && $response->clientError()) {
            $status = TopupProviderStatus::Failed;
        }

        $reference = filled($data['order_id'] ?? null)
            ? (string) $data['order_id']
            : $fallbackReference;
        $message = filled($data['message'] ?? null)
            ? (string) $data['message']
            : (filled($body['message'] ?? null) ? (string) $body['message'] : null);

        if ($response->clientError()) {
            $message = match ($response->status()) {
                401 => 'Provider từ chối xác thực; kiểm tra partner_id, secret_key hoặc trạng thái đại lý.',
                403 => 'Provider từ chối địa chỉ IP hiện tại; kiểm tra IP allowlist.',
                404 => 'Provider không tìm thấy đơn nạp cần tra cứu.',
                default => $message ?: 'Provider từ chối yêu cầu nạp (HTTP '.$response->status().').',
            };
        }

        return new TopupProviderResultDto(
            status: $status,
            reference: $reference,
            message: $message !== null ? mb_substr(strip_tags($message), 0, 500) : null,
            response: [
                ...$this->httpResponseSnapshot($response),
                'provider_status' => $providerStatus !== '' ? $providerStatus : null,
                'provider_code' => $providerCode !== '' ? $providerCode : null,
                'envelope_status' => ($body['success'] ?? null) === true ? 'success' : 'failed',
                'provider_topup_id' => is_scalar($data['topup_id'] ?? null) && filled($data['topup_id'])
                    ? (string) $data['topup_id']
                    : null,
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

    /** @param array{base_url:string,partner_id:string,secret_key:string,connect_timeout:int,timeout:int,max_status_checks:int} $config */
    private function assertConnectionConfigured(array $config): void
    {
        if (
            $config['partner_id'] === ''
            || $config['secret_key'] === ''
            || filter_var($config['base_url'], FILTER_VALIDATE_URL) === false
            || parse_url($config['base_url'], PHP_URL_SCHEME) !== 'https'
        ) {
            throw new TopupProviderConnectionException(
                'invalid_configuration',
                'Cấu hình AccNRO chưa đầy đủ hoặc base URL không dùng HTTPS.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<int, string>  $keys
     */
    private function firstConfiguredString(array $config, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($config[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function normalizedBaseUrl(string $baseUrl): string
    {
        $baseUrl = rtrim(trim($baseUrl), '/');

        return preg_replace('#/(create|query|balance|catalog)$#i', '', $baseUrl) ?? $baseUrl;
    }

    private function serverCode(Order $order): string
    {
        $serverCode = trim((string) $order->server?->code);

        if ($serverCode !== '' || $order->game_server_id === null) {
            return $serverCode;
        }

        return trim((string) $order->server()->value('code'));
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
}
