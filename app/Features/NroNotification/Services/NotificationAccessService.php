<?php

namespace App\Features\NroNotification\Services;

use App\Support\SettingStore;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NotificationAccessService
{
    public const SESSION_KEY = 'nro_realtime_access';

    public function __construct(private readonly SettingStore $settings) {}

    public function isAdmin(Request $request): bool
    {
        return $request->user()?->role === 'admin';
    }

    public function configured(): bool
    {
        return (bool) $this->settings->get('turnstile_enabled', false)
            && $this->settings->getString('turnstile_site_key') !== ''
            && $this->settings->getString('turnstile_secret_key') !== '';
    }

    public function siteKey(): string
    {
        return $this->configured() ? $this->settings->getString('turnstile_site_key') : '';
    }

    private function binding(Request $request): string
    {
        return hash('sha256', $request->session()->getId().'|'.$request->ip().'|'.$request->userAgent());
    }

    public function grantKey(Request $request): ?string
    {
        if (! $request->user() || ! $request->hasSession()) {
            return null;
        }
        $grant = $request->session()->get(self::SESSION_KEY);
        if (! is_array($grant) || ($grant['user_id'] ?? null) !== $request->user()->getAuthIdentifier()
            || ($grant['binding'] ?? '') !== $this->binding($request) || ! is_string($grant['token'] ?? null)) {
            return null;
        }

        return 'nro:grant:'.hash('sha256', $grant['token']);
    }

    public function realtime(Request $request): bool
    {
        if ($this->isAdmin($request)) {
            return true;
        }
        $key = $this->grantKey($request);

        return $this->configured() && $key !== null && (int) Cache::get($key, 0) > now()->timestamp;
    }

    public function verify(Request $request, string $token): void
    {
        abort_unless($this->configured(), 503, 'Chưa cấu hình xác minh Turnstile. Vui lòng quay lại sau.');
        try {
            $response = Http::asForm()->connectTimeout(3)->timeout(8)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $this->settings->getString('turnstile_secret_key'), 'response' => $token, 'remoteip' => $request->ip(),
            ]);
        } catch (ConnectionException) {
            abort(503, 'Không kết nối được dịch vụ xác minh. Vui lòng thử lại.');
        }
        $result = $response->json();
        if (! $response->successful() || ! is_array($result) || ($result['success'] ?? false) !== true
            || ($result['action'] ?? '') !== 'nro_realtime' || ($result['hostname'] ?? '') !== $request->getHost()) {
            throw ValidationException::withMessages(['cf-turnstile-response' => 'Xác minh không hợp lệ hoặc đã hết hạn. Vui lòng thử lại.']);
        }
        $old = $this->grantKey($request);
        if ($old !== null) {
            Cache::forget($old);
        }
        $token = Str::random(64);
        $expires = now()->addMinutes(15);
        Cache::put('nro:grant:'.hash('sha256', $token), $expires->timestamp, $expires);
        $request->session()->put(self::SESSION_KEY, ['user_id' => $request->user()->getAuthIdentifier(), 'binding' => $this->binding($request), 'token' => $token]);
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function filters(Request $request, array $filters): array
    {
        if ($this->realtime($request)) {
            return $filters;
        }
        $cutoff = now()->subMinutes(5)->startOfMinute();
        abort_if((int) ($filters['page'] ?? 1) > 3, 403, 'Đăng nhập và xác minh để xem thêm lịch sử thông báo.');

        return [...$filters, 'limit' => 10,
            '_preview_cutoff' => $cutoff->format('Y-m-d H:i:s.u'),
            '_preview_since' => now()->subHour()->startOfMinute()->format('Y-m-d H:i:s.u')];
    }
}
