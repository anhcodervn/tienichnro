<?php

namespace App\Features\Captcha\Services;

use App\Models\CaptchaProvider;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class CaptchaProviderClientService
{
    /** @return array{success: bool, result: ?string, payload: array<string, mixed>, http_status: ?int} */
    public function solve(CaptchaProvider $provider, string $image, float $timeout): array
    {
        $key = '';
        try {
            $key = (string) $provider->api_key;
            $request = Http::acceptJson()->connectTimeout(min(5, $timeout))->timeout($timeout)
                ->withOptions(['allow_redirects' => false]);
            if (in_array($provider->driver, ['plusdino', 'phamgiang'], true)) {
                if ($key === '') {
                    return $this->unavailable();
                }
                $separator = str_contains($provider->endpoint, '?') ? '&' : '?';
                $request = $provider->driver === 'phamgiang' ? $request->asMultipart() : $request->asForm();
                $response = $request->post($provider->endpoint.$separator.http_build_query(['token' => $key]), ['image' => $image]);
            } elseif ($provider->driver === 'nro_giaicaptcha') {
                if ($key !== '') {
                    $request->withToken($key);
                }
                $response = $request->asJson()->post($provider->endpoint, ['image' => $image]);
            } else {
                return $this->unavailable();
            }
            $payload = $response->json();
            if (! is_array($payload)) {
                $payload = ['success' => false, 'error' => 'invalid_response', 'body' => mb_substr($response->body(), 0, 10000)];
            }
            $result = in_array($provider->driver, ['plusdino', 'phamgiang'], true) ? ($payload['captcha'] ?? null) : ($payload['result'] ?? null);
            $success = $response->successful() && is_string($result) && trim($result) !== '';
            if ($provider->driver === 'phamgiang') {
                $success = $success && ($payload['status'] ?? null) === 0;
            } elseif ($provider->driver === 'plusdino') {
                $success = $success && preg_match('/\A[a-zA-Z0-9]{4,7}\z/', trim((string) $result)) === 1
                    && ($payload['success'] ?? true) !== false && ($payload['status'] ?? true) !== false;
            } else {
                $success = $success && ($payload['success'] ?? null) === true;
            }

            return [
                'success' => $success, 'result' => $success ? trim($result) : null,
                'payload' => $this->redact($payload, $key), 'http_status' => $response->status(),
            ];
        } catch (ConnectionException|DecryptException) {
            return $this->unavailable();
        }
    }

    /** @return array{success: bool, result: null, payload: array<string, mixed>, http_status: null} */
    private function unavailable(): array
    {
        return ['success' => false, 'result' => null, 'payload' => ['success' => false, 'error' => 'provider_unavailable'], 'http_status' => null];
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function redact(array $payload, string $key): array
    {
        foreach ($payload as $name => $value) {
            if (in_array(strtolower((string) $name), ['token', 'api_key', 'apikey', 'authorization', 'password', 'secret'], true)) {
                $payload[$name] = '[REDACTED]';
            } elseif (is_array($value)) {
                $payload[$name] = $this->redact($value, $key);
            } elseif (is_string($value) && $key !== '') {
                $payload[$name] = str_replace(array_unique([$key, rawurlencode($key), urlencode($key)]), '[REDACTED]', $value);
            }
        }

        return $payload;
    }
}
