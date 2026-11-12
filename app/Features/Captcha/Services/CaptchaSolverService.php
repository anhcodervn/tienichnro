<?php

namespace App\Features\Captcha\Services;

use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Models\CaptchaSolve;
use App\Models\CaptchaWallet;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CaptchaSolverService
{
    public function __construct(
        private readonly CaptchaWalletService $wallets,
        private readonly CaptchaImageService $images,
        private readonly ToolAvailabilityService $tools,
    ) {}

    public function solve(User $user, string $image, string $requestId, string $source): CaptchaSolve
    {
        $tool = $this->tools->all()->first(fn (array $tool): bool => ($tool['route'] ?? null) === 'tools.captcha'
            || rtrim((string) parse_url((string) ($tool['url'] ?? ''), PHP_URL_PATH), '/') === '/giai-captcha-nro'
        ) ?? ['is_enabled' => false, 'maintenance_message' => 'Công cụ chưa được bật trong danh sách công cụ.'];
        abort_unless($tool['is_enabled'], 503, $tool['maintenance_message']);
        $decoded = $this->images->decode($image);
        $solve = DB::transaction(function () use ($user, $requestId, $source, $decoded): CaptchaSolve {
            $wallet = $this->wallets->lock($user);
            $this->expireLocked($user, $wallet);
            $existing = CaptchaSolve::query()->where('user_id', $user->id)->where('request_id', $requestId)->first();
            if ($existing) {
                if ($existing->image_hash !== $decoded['hash']) {
                    throw ValidationException::withMessages(['request_id' => 'Mã lượt giải đã được dùng cho ảnh khác.']);
                }

                return $existing;
            }
            if ($wallet->balance - $wallet->reserved < 1) {
                throw ValidationException::withMessages(['balance' => 'Không đủ token_captcha. Vui lòng mua thêm token.']);
            }
            $path = $this->images->store($decoded['bytes']);
            try {
                $solve = CaptchaSolve::query()->create([
                    'user_id' => $user->id, 'request_id' => $requestId, 'image_hash' => $decoded['hash'],
                    'image_path' => $path, 'source' => $source,
                ]);
                $wallet->increment('reserved');
            } catch (\Throwable $exception) {
                Storage::disk('local')->delete($path);
                throw $exception;
            }

            return $solve;
        });
        if (! $solve->wasRecentlyCreated) {
            return $solve;
        }

        $startedAt = hrtime(true);
        try {
            $response = Http::acceptJson()->asJson()->connectTimeout(5)->timeout(30)
                ->withOptions(['allow_redirects' => false])
                ->post(config('captcha.endpoint'), ['image' => $decoded['base64']]);
            $payload = $response->json();
            if (! is_array($payload)) {
                $payload = ['success' => false, 'error' => 'invalid_response', 'body' => mb_substr($response->body(), 0, 10000)];
            }
            $httpStatus = $response->status();
            $success = $response->successful() && ($payload['success'] ?? null) === true
                && is_string($payload['result'] ?? null) && trim($payload['result']) !== '';
        } catch (ConnectionException) {
            $payload = ['success' => false, 'error' => 'provider_unavailable'];
            $httpStatus = null;
            $success = false;
        }

        $durationMs = (int) round((hrtime(true) - $startedAt) / 1000000);

        return DB::transaction(function () use ($user, $solve, $payload, $httpStatus, $success, $durationMs): CaptchaSolve {
            $wallet = $this->wallets->lock($user);
            $locked = CaptchaSolve::query()->lockForUpdate()->findOrFail($solve->id);
            if ($locked->status !== 'processing') {
                return $locked;
            }
            $wallet->decrement('reserved');
            if ($success) {
                $this->wallets->record($wallet, 1, 'debit', 'solve:'.$locked->id, 'Giải captcha #'.$locked->id);
            }
            $locked->update([
                'status' => $success ? 'success' : 'failed', 'response' => $payload,
                'http_status' => $httpStatus, 'result' => $success ? $payload['result'] : null, 'duration_ms' => $durationMs,
            ]);

            return $locked;
        }, 3);
    }

    public function expire(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $wallet = $this->wallets->lock($user);
            $this->expireLocked($user, $wallet);
        }, 3);
    }

    private function expireLocked(User $user, CaptchaWallet $wallet): void
    {
        $expired = CaptchaSolve::query()->where('user_id', $user->id)->where('status', 'processing')
            ->where('created_at', '<', now()->subMinutes(5))
            ->update(['status' => 'failed', 'response' => json_encode(['success' => false, 'error' => 'processing_expired'], JSON_THROW_ON_ERROR)]);
        if ($expired > 0) {
            $wallet->decrement('reserved', $expired);
        }
    }
}
