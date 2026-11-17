<?php

namespace App\Features\Client\GamePassword\Services;

use App\Features\Client\GamePassword\Exceptions\GamePasswordFailure;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

class GamePasswordCaptchaService
{
    public const PROVIDERS = ['captcha69' => 'Captcha69', 'autocaptcha' => 'AutoCaptcha'];

    public function __construct(private readonly GamePasswordConfigurationService $configuration) {}

    /** @param array{key?: string, secret?: string} $credentials */
    public function solveWithUserCredentials(string $sitekey, string $pageUrl, float $deadline, array $credentials): string
    {
        if (blank($credentials['key'] ?? null) || blank($credentials['secret'] ?? null)) {
            throw new GamePasswordFailure('USER_SOLVER_NOT_CONFIGURED');
        }
        $headers = ['X-API-KEY' => $credentials['key'], 'X-API-SECRET' => $credentials['secret']];
        $created = $this->client($deadline)->acceptJson()->withHeaders($headers)->post('https://giaicaptcha.vn/api/v1/create', [
            'service_code' => 'cloudflare-turnstile', 'task' => ['website_url' => $pageUrl, 'website_key' => $sitekey],
        ]);
        if (in_array($created->status(), [401, 403], true)) {
            throw new GamePasswordFailure('USER_SOLVER_AUTH_FAILED');
        }
        $task = $created->json('data.task_code');
        if (! $created->successful() || $created->json('status') !== true || ! is_string($task) || blank($task)) {
            throw new GamePasswordFailure('SOLVER_FAILED');
        }
        for ($attempt = 0; $attempt < 45; $attempt++) {
            if ($deadline - microtime(true) <= 3) {
                throw new GamePasswordFailure('REMOTE_TIMEOUT');
            }
            Sleep::for(3)->seconds();
            $result = $this->client($deadline)->acceptJson()->withHeaders($headers)->post('https://giaicaptcha.vn/api/v1/result', ['task_code' => $task]);
            if (in_array($result->status(), [401, 403], true)) {
                throw new GamePasswordFailure('USER_SOLVER_AUTH_FAILED');
            }
            if (! $result->successful() || $result->json('status') !== true) {
                throw new GamePasswordFailure('SOLVER_FAILED');
            }
            $token = $result->json('data.captcha');
            if (is_string($token) && filled($token)) {
                return $token;
            }
        }

        throw new GamePasswordFailure('REMOTE_TIMEOUT');
    }

    public function solve(string $sitekey, string $pageUrl, float $deadline): string
    {
        $provider = $this->configuration->provider();
        $key = $this->configuration->apiKey();
        if (blank($key)) {
            throw new GamePasswordFailure('SOLVER_NOT_CONFIGURED');
        }

        return match ($provider) {
            'captcha69' => $this->captcha69($key, $sitekey, $pageUrl, $deadline),
            'autocaptcha' => $this->autoCaptcha($key, $sitekey, $pageUrl, $deadline),
            default => throw new GamePasswordFailure('SOLVER_NOT_CONFIGURED'),
        };
    }

    private function client(float $deadline): PendingRequest
    {
        if (microtime(true) >= $deadline) {
            throw new GamePasswordFailure('REMOTE_TIMEOUT');
        }

        return Http::timeout(max(0.1, min(45, $deadline - microtime(true))))->connectTimeout(5)->withoutRedirecting();
    }

    private function autoCaptcha(string $key, string $sitekey, string $pageUrl, float $deadline): string
    {
        $data = $this->client($deadline)->post('https://autocaptcha.pro/apiv3/process', [
            'key' => $key, 'type' => 'cloudflare', 'websitekey' => $sitekey, 'pageurl' => $pageUrl,
        ])->throw()->json();
        if (! ($data['success'] ?? false) || ! is_string($data['captcha']['token'] ?? null) || blank($data['captcha']['token'])) {
            throw new GamePasswordFailure('SOLVER_FAILED');
        }

        return $data['captcha']['token'];
    }

    private function captcha69(string $key, string $sitekey, string $pageUrl, float $deadline): string
    {
        $proxy = $this->configuration->proxy();
        if (blank($proxy)) {
            throw new GamePasswordFailure('SOLVER_NOT_CONFIGURED');
        }
        $result = trim($this->client($deadline)->asForm()->post('https://captcha69.com/in.php', [
            'key' => $key, 'method' => 'turnstile_point', 'sitekey' => $sitekey, 'pageurl' => $pageUrl, 'proxy' => $proxy,
        ])->throw()->body());
        if (! str_starts_with($result, 'OK|') || blank($task = substr($result, 3))) {
            throw new GamePasswordFailure('SOLVER_FAILED');
        }
        for ($attempt = 0; $attempt < 45; $attempt++) {
            if ($deadline - microtime(true) <= 5) {
                throw new GamePasswordFailure('REMOTE_TIMEOUT');
            }
            Sleep::for(5)->seconds();
            $result = trim($this->client($deadline)->asForm()->post('https://captcha69.com/res.php', [
                'key' => $key, 'action' => 'get', 'id' => $task,
            ])->throw()->body());
            if (str_starts_with($result, 'OK|') && filled($token = substr($result, 3))) {
                return $token;
            }
            if ($result !== 'CAPCHA_NOT_READY') {
                throw new GamePasswordFailure('SOLVER_FAILED');
            }
        }

        throw new GamePasswordFailure('REMOTE_TIMEOUT');
    }
}
