<?php

namespace App\Features\Captcha\Services;

use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Models\CaptchaSolve;
use App\Models\User;
use App\Support\SettingStore;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CaptchaDemoService
{
    public function __construct(
        private readonly SettingStore $settings,
        private readonly CaptchaImageService $images,
        private readonly CaptchaProviderRunnerService $providers,
        private readonly CaptchaProviderClientService $client,
        private readonly ToolAvailabilityService $tools,
    ) {}

    public function siteKey(): string
    {
        return $this->settings->get('turnstile_enabled', false)
            && $this->settings->getString('turnstile_secret_key') !== ''
            ? $this->settings->getString('turnstile_site_key') : '';
    }

    /** @return array{status: bool, code: ?string, time: float, message?: string} */
    public function solve(Request $request, string $image, string $token): array
    {
        if ($this->siteKey() === '') {
            throw new CaptchaFailure('Chưa cấu hình Cloudflare Turnstile cho lượt giải miễn phí.');
        }
        $decoded = $this->images->decode($image);
        $this->verify($request, $token);
        $tool = $this->tools->all()->first(fn (array $tool): bool => ($tool['route'] ?? null) === 'tools.captcha'
            || rtrim((string) parse_url((string) ($tool['url'] ?? ''), PHP_URL_PATH), '/') === '/giai-captcha-nro');
        if (! ($tool['is_enabled'] ?? false)) {
            throw new CaptchaFailure('Công cụ giải captcha đang bảo trì. Vui lòng thử lại sau.');
        }
        $provider = $this->providers->primary();
        if (! $provider) {
            throw new CaptchaFailure('Chưa chọn nguồn giải captcha chính. Vui lòng thử lại sau.');
        }
        $startedAt = hrtime(true);
        $outcome = $this->client->solve($provider, $decoded['base64'], min(30, max(1, $provider->timeout)));
        $duration = (int) round((hrtime(true) - $startedAt) / 1000000);
        if ($request->user() instanceof User) {
            CaptchaSolve::query()->create([
                'user_id' => $request->user()->id, 'request_id' => (string) Str::uuid(),
                'image_hash' => $decoded['hash'], 'image_path' => $this->images->store($decoded['bytes']),
                'source' => 'demo', 'status' => $outcome['success'] ? 'success' : 'failed',
                'result' => $outcome['result'], 'response' => $outcome['payload'],
                'http_status' => $outcome['http_status'], 'provider_name' => $provider->name, 'duration_ms' => $duration,
            ]);
        }

        return [
            'status' => $outcome['success'], 'code' => $outcome['result'], 'time' => $duration / 1000,
            ...($outcome['success'] ? [] : ['message' => 'Chưa giải được ảnh này. Vui lòng thử lại với ảnh khác.']),
        ];
    }

    private function verify(Request $request, string $token): void
    {
        try {
            $response = Http::asForm()->connectTimeout(3)->timeout(8)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $this->settings->getString('turnstile_secret_key'), 'response' => $token, 'remoteip' => $request->ip(),
            ]);
        } catch (ConnectionException) {
            throw new CaptchaFailure('Không kết nối được Cloudflare. Vui lòng thử lại.');
        }
        $result = $response->json();
        if (! $response->successful() || ! is_array($result) || ($result['success'] ?? false) !== true
            || ($result['action'] ?? '') !== 'captcha_demo' || ($result['hostname'] ?? '') !== $request->getHost()
            || ! Cache::add('captcha:turnstile:'.hash('sha256', $token), true, now()->addMinutes(5))) {
            throw ValidationException::withMessages(['cf-turnstile-response' => 'Xác minh Cloudflare không hợp lệ hoặc đã hết hạn. Vui lòng xác minh lại.']);
        }
    }
}
