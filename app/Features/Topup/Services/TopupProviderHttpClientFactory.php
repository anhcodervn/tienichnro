<?php

namespace App\Features\Topup\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class TopupProviderHttpClientFactory
{
    public function make(int $connectTimeout, int $timeout, ?string $proxyUrl = null): PendingRequest
    {
        $request = Http::acceptJson()
            ->asJson()
            ->connectTimeout($connectTimeout)
            ->timeout($timeout);

        $normalizedProxyUrl = self::normalizeProxyUrl($proxyUrl);

        return $normalizedProxyUrl === ''
            ? $request
            : $request->withOptions(['proxy' => $normalizedProxyUrl]);
    }

    public static function normalizeProxyUrl(mixed $proxyUrl): string
    {
        return is_string($proxyUrl) ? trim($proxyUrl) : '';
    }

    public static function isValidProxyUrl(string $proxyUrl): bool
    {
        $proxyUrl = self::normalizeProxyUrl($proxyUrl);

        if ($proxyUrl === '') {
            return true;
        }

        if (mb_strlen($proxyUrl) > 1000 || preg_match('/[\r\n]/', $proxyUrl) === 1) {
            return false;
        }

        $parts = parse_url($proxyUrl);

        return is_array($parts)
            && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https', 'socks5', 'socks5h'], true)
            && filled($parts['host'] ?? null)
            && isset($parts['port'])
            && (int) $parts['port'] >= 1
            && (int) $parts['port'] <= 65535
            && blank($parts['query'] ?? null)
            && blank($parts['fragment'] ?? null)
            && in_array((string) ($parts['path'] ?? ''), ['', '/'], true);
    }
}
