<?php

namespace App\Features\Captcha\Services;

use App\Models\CaptchaProvider;
use App\Models\CaptchaProviderAttempt;
use App\Models\CaptchaSolve;
use Illuminate\Support\Facades\DB;
use Throwable;

class CaptchaProviderRunnerService
{
    public function __construct(private readonly CaptchaProviderClientService $client, private readonly CaptchaProviderConfigurationService $configuration) {}

    public function primary(): ?CaptchaProvider
    {
        return CaptchaProvider::query()->whereKey($this->configuration->primaryId())->where('is_enabled', true)->first();
    }

    /** @return array{success: bool, result: ?string, payload: array<string, mixed>, http_status: ?int, provider_name: ?string} */
    public function run(CaptchaSolve $solve, string $image, CaptchaProvider $provider): array
    {
        $failure = ['success' => false, 'result' => null, 'payload' => ['success' => false, 'error' => 'provider_unavailable'], 'http_status' => null];
        $prepared = DB::transaction(function () use ($solve, $provider): ?array {
            $locked = CaptchaProvider::query()->lockForUpdate()->find($provider->id);
            if (! $locked || ! $locked->is_enabled || $this->configuration->primaryId() !== $locked->id) {
                return null;
            }
            $attempt = CaptchaProviderAttempt::query()->create([
                'captcha_solve_id' => $solve->id, 'captcha_provider_id' => $locked->id,
                'provider_name' => $locked->name, 'driver' => $locked->driver,
            ]);

            return [$locked, $attempt];
        });
        if (! $prepared) {
            return [...$failure, 'provider_name' => null];
        }
        [$current, $attempt] = $prepared;
        $startedAt = hrtime(true);
        try {
            $result = $this->client->solve($current, $image, min(30, max(1, $current->timeout)));
        } catch (Throwable $exception) {
            report($exception);
            $result = $failure;
        }
        $attempt->update([
            'status' => $result['success'] ? 'success' : 'failed', 'http_status' => $result['http_status'],
            'response' => $result['payload'], 'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1000000),
        ]);

        return [...$result, 'provider_name' => $current->name];
    }
}
