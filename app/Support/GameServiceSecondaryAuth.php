<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
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

    /** @return array{token: string} */
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

        $token = Crypt::encryptString(json_encode([
            'user_id' => (string) $user->getKey(),
            'role' => $user->role,
            'credential' => $this->credentialFingerprint($user),
            'nonce' => Str::random(40),
        ], JSON_THROW_ON_ERROR));

        return [
            'token' => $token,
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

        try {
            $grant = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return false;
        }

        if (! is_array($grant)) {
            return false;
        }

        return hash_equals((string) ($grant['user_id'] ?? ''), (string) $user->getKey())
            && hash_equals((string) ($grant['role'] ?? ''), (string) $user->role)
            && hash_equals((string) ($grant['credential'] ?? ''), $this->credentialFingerprint($user));
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

    private function credentialFingerprint(User $user): string
    {
        return hash('sha256', $this->credentialFingerprintSource($user));
    }
}
