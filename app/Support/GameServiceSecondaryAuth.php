<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GameServiceSecondaryAuth
{
    public const HEADER_NAME = 'X-Game-Service-Secondary-Token';

    public function isConfigured(User $user): bool
    {
        return $this->credentialFingerprintSource($user) !== '';
    }

    public function ttlMinutes(): int
    {
        return min(max((int) config('services.game_service_secondary_auth.ttl_minutes', 30), 5), 1440);
    }

    /** @return array{token: string, expires_at: string, expires_in_minutes: int} */
    public function unlock(User $user, string $password): array
    {
        abort_unless(
            $this->isConfigured($user),
            503,
            $user->role === User::ROLE_COLLABORATOR
                ? 'Tài khoản CTV chưa được quản trị viên cấu hình mật khẩu C2.'
                : 'Mật khẩu C2 global của admin chưa được cấu hình trên hệ thống.',
        );

        if (! $this->passwordMatches($user, $password)) {
            throw ValidationException::withMessages([
                'password' => 'Mật khẩu C2 không chính xác.',
            ]);
        }

        $token = Str::random(80);
        $expiresAt = now()->addMinutes($this->ttlMinutes());
        Cache::put($this->cacheKey($user, $token), true, $expiresAt);

        return [
            'token' => $token,
            'expires_at' => $expiresAt->toISOString(),
            'expires_in_minutes' => $this->ttlMinutes(),
        ];
    }

    public function isUnlocked(User $user, Request $request): bool
    {
        if (! $this->isConfigured($user)) {
            return false;
        }

        $token = $request->header(self::HEADER_NAME);

        if (! is_string($token) || Str::length($token) < 40) {
            return false;
        }

        return Cache::get($this->cacheKey($user, $token)) === true;
    }

    private function configuredPassword(): string
    {
        return (string) config('services.game_service_secondary_auth.password', '');
    }

    private function passwordMatches(User $user, string $password): bool
    {
        if ($user->role === User::ROLE_ADMIN) {
            return hash_equals(hash('sha256', $this->configuredPassword()), hash('sha256', $password));
        }

        if ($user->role === User::ROLE_COLLABORATOR) {
            $passwordHash = (string) $user->game_service_secondary_password;

            if ($passwordHash === '' || ! Hash::check($password, $passwordHash)) {
                return false;
            }

            if (Hash::needsRehash($passwordHash)) {
                $user->forceFill(['game_service_secondary_password' => $password])->save();
            }

            return true;
        }

        return false;
    }

    private function credentialFingerprintSource(User $user): string
    {
        return match ($user->role) {
            User::ROLE_ADMIN => $this->configuredPassword(),
            User::ROLE_COLLABORATOR => (string) $user->game_service_secondary_password,
            default => '',
        };
    }

    private function cacheKey(User $user, string $token): string
    {
        return 'game-service-secondary-auth:'
            .$user->getKey().':'
            .$user->role.':'
            .hash('sha256', $this->credentialFingerprintSource($user)).':'
            .hash('sha256', $token);
    }
}
