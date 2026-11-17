<?php

namespace App\Features\Client\GamePassword\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class GamePasswordCaptchaProfileService
{
    public function snapshot(?User $user): array
    {
        return ['saved' => $this->hasCredentials($user), 'username' => $user?->game_password_captcha_profile['username'] ?? null,
            'balance' => $user?->game_password_captcha_profile['balance'] ?? null, 'checked_at' => $user?->game_password_captcha_profile['checked_at'] ?? null];
    }

    public function hasCredentials(?User $user): bool
    {
        return $user !== null && filled($user->getRawOriginal('game_password_captcha_key')) && filled($user->getRawOriginal('game_password_captcha_secret'));
    }

    public function credentials(User $user): array
    {
        return $this->hasCredentials($user) ? ['key' => $user->game_password_captcha_key, 'secret' => $user->game_password_captcha_secret] : [];
    }

    public function save(User $user, array $credentials): array
    {
        $profile = $this->fetchProfile($credentials);

        return DB::transaction(function () use ($user, $credentials, $profile): array {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($locked->status === 'active', 403);
            $locked->forceFill(['game_password_captcha_key' => $credentials['key'], 'game_password_captcha_secret' => $credentials['secret'], 'game_password_captcha_profile' => $profile])->save();

            return $this->snapshot($locked);
        }, 3);
    }

    public function refresh(User $user): array
    {
        $credentials = $this->credentials($user);
        if ($credentials === []) {
            throw ValidationException::withMessages(['captcha_api_key' => 'Bạn chưa lưu khóa giaicaptcha.vn.']);
        }
        $profile = $this->fetchProfile($credentials);

        return DB::transaction(function () use ($user, $credentials, $profile): array {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($locked->status === 'active', 403);
            abort_unless($this->credentials($locked) === $credentials, 409, 'Khóa đã thay đổi. Vui lòng làm mới lại thông tin.');
            $locked->forceFill(['game_password_captcha_profile' => $profile])->save();

            return $this->snapshot($locked);
        }, 3);
    }

    private function fetchProfile(array $credentials): array
    {
        try {
            $response = Http::acceptJson()->timeout(12)->connectTimeout(5)->withoutRedirecting()->withHeaders([
                'X-API-KEY' => $credentials['key'], 'X-API-SECRET' => $credentials['secret'],
            ])->get('https://giaicaptcha.vn/api/v1/user');
        } catch (Throwable) {
            throw ValidationException::withMessages(['captcha_api_key' => 'Chưa kết nối được giaicaptcha.vn. Vui lòng thử lại.']);
        }
        $username = $response->json('data.user.username');
        $balance = $response->json('data.wallet.balance');
        if (! $response->successful() || $response->json('status') !== true || $response->json('data.user.status') !== 'active'
            || ! is_string($username) || blank($username) || mb_strlen($username) > 100
            || (! is_string($balance) && ! is_int($balance) && ! is_float($balance)) || ! preg_match('/^\d{1,20}(?:\.\d{1,8})?$/', (string) $balance)) {
            throw ValidationException::withMessages(['captcha_api_key' => 'Không xác minh được tài khoản giaicaptcha.vn. Kiểm tra API Key và API Secret.']);
        }

        return ['username' => $username, 'balance' => (string) $balance, 'checked_at' => now()->toISOString()];
    }
}
