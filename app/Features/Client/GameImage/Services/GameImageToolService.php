<?php

namespace App\Features\Client\GameImage\Services;

use App\Models\GameImageAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GameImageToolService
{
    public function __construct(private readonly GameImageCredentialService $credentials) {}

    public function assertClaim(GameImageAccount $account, int $version, string $token): void
    {
        abort_unless($account->capture_version === $version && filled($account->capture_claim_hash)
            && hash_equals($account->capture_claim_hash, hash('sha256', $token)), 409, 'Capture claim is invalid or stale.');
    }

    public function release(GameImageAccount $account, int $version, string $token): GameImageAccount
    {
        return DB::transaction(function () use ($account, $version, $token): GameImageAccount {
            User::query()->lockForUpdate()->findOrFail($account->user_id);
            $locked = GameImageAccount::query()->lockForUpdate()->findOrFail($account->id);
            $this->assertClaim($locked, $version, $token);
            abort_unless($locked->status === 'processing', 409, 'Only a processing capture can be returned to pending.');
            $locked->forceFill(['status' => 'pending', 'processing_at' => null, 'capture_claim_hash' => null, 'capture_public_key' => null])->save();

            return $locked;
        }, 3);
    }

    public function claim(GameImageAccount $account, int $version, string $token, string $publicKey): GameImageAccount
    {
        $publicKey = $this->credentials->publicKey($publicKey);

        return DB::transaction(function () use ($account, $version, $token, $publicKey): GameImageAccount {
            $user = User::query()->lockForUpdate()->findOrFail($account->user_id);
            abort_unless($user->status === 'active', 409, 'Account owner is inactive.');
            $locked = GameImageAccount::query()->lockForUpdate()->findOrFail($account->id);
            abort_unless($locked->capture_version === $version, 409, 'Capture version is stale.');
            if ($locked->status === 'processing' && hash_equals((string) $locked->capture_claim_hash, hash('sha256', $token))) {
                abort_if($locked->capture_public_key !== null && ! hash_equals($locked->capture_public_key, $publicKey), 409, 'The claim is bound to another public key.');
                if ($locked->capture_public_key === null) {
                    $locked->forceFill(['capture_public_key' => $publicKey])->save();
                }

                return $locked;
            }
            abort_unless($locked->status === 'pending', 409, 'Capture has already been claimed or completed.');
            $locked->forceFill(['status' => 'processing', 'processing_at' => now(), 'capture_claim_hash' => hash('sha256', $token), 'capture_public_key' => $publicKey])->save();

            return $locked;
        }, 3);
    }
}
