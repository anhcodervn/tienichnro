<?php

namespace App\Features\Topup\Exceptions;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use RuntimeException;
use Throwable;

class TopupProviderConnectionException extends RuntimeException
{
    /** @param array<string, mixed> $debugContext */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        ?Throwable $previous = null,
        public readonly array $debugContext = [],
    ) {
        parent::__construct($message, 0, $previous);
    }

    /** @param array<string, mixed> $debugContext */
    public static function fromThrowable(Throwable $exception, array $debugContext = []): self
    {
        if ($exception instanceof self) {
            return $debugContext === [] || $exception->debugContext !== []
                ? $exception
                : new self($exception->errorCode, $exception->getMessage(), $exception, $debugContext);
        }

        if ($exception instanceof RequestException) {
            $status = $exception->response->status();
            $diagnostic = match (true) {
                in_array($status, [401, 403], true) => new self('authentication_failed', 'Provider từ chối xác thực. Kiểm tra API key, secret hoặc IP whitelist.', $exception),
                $status === 429 => new self('rate_limited', 'Provider đang giới hạn tần suất yêu cầu. Vui lòng thử lại sau.', $exception),
                $status >= 500 => new self('provider_unavailable', "Provider đang lỗi hoặc bảo trì (HTTP {$status}).", $exception),
                default => new self('request_rejected', "Provider từ chối yêu cầu (HTTP {$status}). Kiểm tra cấu hình và dữ liệu gửi đi.", $exception),
            };

            return self::withDebugContext($diagnostic, $exception, $debugContext);
        }

        if ($exception instanceof DecryptException) {
            return self::withDebugContext(new self(
                'configuration_decryption_failed',
                'Không giải mã được cấu hình provider. Kiểm tra APP_KEY production hoặc lưu lại credential provider.',
                $exception,
            ), $exception, $debugContext);
        }

        if ($exception instanceof ConnectionException) {
            $message = strtolower($exception->getMessage());
            $diagnostic = match (true) {
                str_contains($message, 'could not resolve'), str_contains($message, 'resolve host') => new self('dns_failed', 'Server không phân giải được tên miền provider. Kiểm tra DNS/outbound trên máy chủ.', $exception),
                str_contains($message, 'timed out'), str_contains($message, 'timeout') => new self('connection_timeout', 'Kết nối provider bị hết thời gian chờ. Kiểm tra outbound, firewall hoặc IP whitelist.', $exception),
                str_contains($message, 'ssl'), str_contains($message, 'certificate') => new self('tls_failed', 'Kết nối HTTPS tới provider không xác thực được chứng chỉ TLS.', $exception),
                default => new self('connection_failed', 'Máy chủ không kết nối được provider. Kiểm tra outbound, firewall và IP whitelist.', $exception),
            };

            return self::withDebugContext($diagnostic, $exception, $debugContext);
        }

        return self::withDebugContext(
            new self('unexpected_error', 'Không thể kiểm tra provider do lỗi hệ thống không xác định.', $exception),
            $exception,
            $debugContext,
        );
    }

    /** @param array<string, mixed> $debugContext */
    private static function withDebugContext(self $diagnostic, Throwable $exception, array $debugContext): self
    {
        return $debugContext === []
            ? $diagnostic
            : new self($diagnostic->errorCode, $diagnostic->getMessage(), $exception, $debugContext);
    }
}
