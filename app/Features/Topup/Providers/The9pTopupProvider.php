<?php

namespace App\Features\Topup\Providers;

use App\Features\Topup\Contracts\TopupProviderBalanceInterface;
use App\Features\Topup\Contracts\TopupProviderInterface;
use App\Features\Topup\DTOs\TopupProviderBalanceDto;
use App\Features\Topup\DTOs\TopupProviderResultDto;
use App\Features\Topup\Enums\TopupProviderStatus;
use App\Models\GameServer;
use App\Models\Order;
use App\Models\OrderRecipient;
use App\Models\TopupPackage;
use App\Models\TopupProvider;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class The9pTopupProvider implements TopupProviderBalanceInterface, TopupProviderInterface
{
    private const DEFAULT_ENDPOINT = 'https://the9p.com/api/rechargews';

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

        if (blank($package->provider_service_code)) {
            $errors[] = 'mã dịch vụ của gói';
        }

        if (blank($server->code)) {
            $errors[] = 'mã máy chủ';
        }

        if (filter_var($config['base_url'], FILTER_VALIDATE_URL) === false || parse_url($config['base_url'], PHP_URL_SCHEME) !== 'https') {
            $errors[] = 'base_url HTTPS';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'package_id' => 'Gói nạp hiện chưa sẵn sàng. Vui lòng chọn gói khác hoặc liên hệ hỗ trợ.',
            ]);
        }
    }

    public function submit(Order $order, OrderRecipient $recipient, ?TopupProvider $provider, string $requestId): TopupProviderResultDto
    {
        if (! $provider instanceof TopupProvider) {
            throw ValidationException::withMessages(['package_id' => 'Gói nạp hiện chưa sẵn sàng. Vui lòng liên hệ hỗ trợ.']);
        }

        $config = $this->configuration($provider);
        $serviceCode = (string) data_get($order->metadata, 'provider.service_code');
        $serverCode = (string) $order->server?->code;
        $username = trim((string) ($recipient->recipient_data['username'] ?? $recipient->recipient_data['game_account'] ?? ''));

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

    public function balance(TopupProvider $provider): TopupProviderBalanceDto
    {
        $config = $this->configuration($provider);

        if (
            $config['partner_id'] === ''
            || $config['partner_key'] === ''
            || filter_var($config['base_url'], FILTER_VALIDATE_URL) === false
            || parse_url($config['base_url'], PHP_URL_SCHEME) !== 'https'
        ) {
            throw new \RuntimeException('Cấu hình kết nối The9p chưa đầy đủ.');
        }

        $command = 'getbalance';
        $response = Http::acceptJson()
            ->asJson()
            ->connectTimeout($config['connect_timeout'])
            ->timeout($config['timeout'])
            ->post($config['base_url'], [
                'command' => $command,
                'partner_id' => $config['partner_id'],
                'sign' => md5($config['partner_key'].$config['partner_id'].$command),
            ])
            ->throw();

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
            throw new \UnexpectedValueException('Phản hồi số dư The9p không hợp lệ.');
        }

        return new TopupProviderBalanceDto(
            balance: (int) round((float) $rawBalance),
            currency: $currency,
        );
    }

    /** @param array{base_url:string,partner_id:string,partner_key:string,connect_timeout:int,timeout:int,max_status_checks:int} $config */
    private function request(array $config, array $payload, ?string $fallbackReference): TopupProviderResultDto
    {
        $response = Http::acceptJson()
            ->asJson()
            ->connectTimeout($config['connect_timeout'])
            ->timeout($config['timeout'])
            ->post($config['base_url'], $payload);

        if ($response->status() === 429 || $response->serverError()) {
            $response->throw();
        }

        if ($response->clientError()) {
            return new TopupProviderResultDto(
                TopupProviderStatus::Failed,
                $fallbackReference,
                'Yêu cầu nạp chưa được hệ thống xử lý chấp nhận.',
                ['http_status' => $response->status()],
            );
        }

        return $this->resultFromResponse($response, $fallbackReference);
    }

    private function resultFromResponse(Response $response, ?string $fallbackReference): TopupProviderResultDto
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        $transactionStatus = strtolower(trim((string) ($data['status'] ?? '')));
        $envelopeStatus = strtolower(trim((string) ($body['status'] ?? '')));
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

        return new TopupProviderResultDto(
            status: $status,
            reference: $reference,
            message: $message,
            response: [
                'http_status' => $response->status(),
                'provider_status' => $transactionStatus !== '' ? $transactionStatus : $envelopeStatus,
                'provider_code' => is_scalar($body['code'] ?? null) ? $body['code'] : null,
            ],
        );
    }

    /** @return array{base_url:string,partner_id:string,partner_key:string,connect_timeout:int,timeout:int,max_status_checks:int} */
    public function configuration(TopupProvider $provider): array
    {
        $config = $provider->connection_config ?? [];

        return [
            'base_url' => rtrim((string) ($config['base_url'] ?? self::DEFAULT_ENDPOINT), '/'),
            'partner_id' => trim((string) ($config['partner_id'] ?? $config['api_key'] ?? '')),
            'partner_key' => trim((string) ($config['partner_key'] ?? $config['api_secret'] ?? '')),
            'connect_timeout' => min(max((int) ($config['connect_timeout'] ?? 5), 1), 15),
            'timeout' => min(max((int) ($config['timeout'] ?? 20), 5), 45),
            'max_status_checks' => min(max((int) ($config['max_status_checks'] ?? 20), 1), 100),
        ];
    }

    private function signature(string $partnerKey, string $partnerId, string $command, string $requestId): string
    {
        return md5($partnerKey.$partnerId.$command.$requestId);
    }
}
