<?php

namespace App\Features\License\Services;

use App\Models\License;
use App\Models\LicenseChallenge;
use App\Models\LicenseDevice;
use App\Models\LicenseEvent;
use App\Models\LicenseNonce;
use App\Models\LicenseSession;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class LicenseSessionService
{
    public function __construct(private readonly DeviceSignatureService $signatures) {}

    public static function hashKey(string $key): string
    {
        return hash('sha256', strtoupper(str_replace('-', '', trim($key))));
    }

    /** @return array<string, mixed> */
    public function challenge(): array
    {
        $nonce = bin2hex(random_bytes(32));
        $challenge = LicenseChallenge::query()->create(['id' => (string) Str::uuid(), 'nonce_hash' => hash('sha256', $nonce), 'expires_at' => now()->addSeconds(config('license.challenge_ttl'))]);

        return ['challenge_id' => $challenge->id, 'nonce' => $nonce, 'expires_at' => $challenge->expires_at->toISOString(), 'server_time' => now()->toISOString()];
    }

    /** @return array<string, mixed> */
    public function activate(Request $request, bool $transfer = false): array
    {
        $hash = self::hashKey((string) $request->input('license_key'));

        return Cache::store(config('license.cache_store'))->lock('license:lock:'.$hash, 15)->block(3, function () use ($request, $hash, $transfer): array {
            return DB::transaction(function () use ($request, $hash, $transfer): array {
                $license = License::query()->where('key_hash', $hash)->lockForUpdate()->first();
                if (! $license) {
                    throw new LicenseFailure('INVALID_LICENSE', 404);
                }
                $this->assertLicense($license, (string) $request->input('product_code'));
                if (version_compare((string) $request->input('client_version'), $license->product->minimum_version, '<')) {
                    throw new LicenseFailure('CLIENT_VERSION_UNSUPPORTED');
                }
                $this->signatures->verify($request, (string) $request->input('public_key'), '');
                $challenge = LicenseChallenge::query()->whereKey($request->input('challenge_id'))->lockForUpdate()->first();
                if (! $challenge || $challenge->consumed_at || $challenge->expires_at->lte(now()) || ! hash_equals($challenge->nonce_hash, hash('sha256', (string) $request->input('nonce')))) {
                    throw new LicenseFailure('REPLAY_DETECTED', 409);
                }
                $uuid = (string) $request->input('device_uuid');
                $changingDevice = $license->current_device_uuid && $license->current_device_uuid !== $uuid;
                if ($transfer) {
                    Gate::forUser($request->user())->authorize('transfer', $license);
                    if ($changingDevice && $license->last_transfer_at?->copy()->addSeconds($license->transfer_cooldown)->isFuture()) {
                        throw new LicenseFailure('TRANSFER_COOLDOWN', 409);
                    }
                } elseif ($changingDevice) {
                    throw new LicenseFailure('DEVICE_ALREADY_ACTIVE', 409);
                }
                $device = $license->devices()->where('device_uuid', $uuid)->first();
                if ($device && ! hash_equals($device->public_key, (string) $request->input('public_key'))) {
                    throw new LicenseFailure('DEVICE_MISMATCH');
                }
                $challenge->update(['consumed_at' => now()]);
                $device ??= $license->devices()->create(['device_uuid' => $uuid, 'device_name' => $request->input('device_name'), 'public_key' => $request->input('public_key'), 'hwid_hash' => $request->input('hwid_hash'), 'first_activated_at' => now(), 'last_seen_at' => now(), 'last_ip' => $request->ip()]);
                $license->devices()->update(['status' => 'inactive']);
                $device->update(['status' => 'active', 'client_version' => $request->input('client_version'), 'last_seen_at' => now(), 'last_ip' => $request->ip()]);
                $this->revokeSessions($license, $changingDevice ? 'transfer' : 'reconnect');
                if (! $license->activated_at) {
                    $license->activated_at = now();
                    $license->expires_at = $license->duration_days === null ? null : now()->addDays($license->duration_days);
                }
                if ($changingDevice || ! $license->current_device_uuid) {
                    $license->last_transfer_at = now();
                }
                $license->forceFill(['current_device_uuid' => $uuid, 'status' => 'active', 'generation' => $license->generation + 1])->save();
                $token = bin2hex(random_bytes(32));
                $session = $license->sessions()->create(['id' => (string) Str::uuid(), 'device_id' => $device->id, 'token_hash' => hash('sha256', $token), 'generation' => $license->generation, 'status' => 'active', 'started_at' => now(), 'last_heartbeat_at' => now(), 'lease_expires_at' => $this->leaseExpiry($license)]);
                $this->mirrorLease($session);
                $this->event($license, $changingDevice ? 'transferred' : 'activated', $request);

                return [...$this->state($license, $session, $device), 'session_token' => $token];
            }, 3);
        });
    }

    /** @return array<string, mixed> */
    public function sessionRequest(Request $request, string $operation): array
    {
        $found = LicenseSession::query()->whereKey($request->input('session_id'))->first();
        if (! $found || ! $request->bearerToken() || ! hash_equals($found->token_hash, hash('sha256', $request->bearerToken()))) {
            throw new LicenseFailure('INVALID_SESSION', 401);
        }

        return DB::transaction(function () use ($request, $operation, $found): array {
            $license = License::query()->whereKey($found->license_id)->lockForUpdate()->firstOrFail();
            $session = LicenseSession::query()->whereKey($found->id)->lockForUpdate()->firstOrFail();
            $device = $session->device;
            if ($device->device_uuid !== $request->input('device_uuid')) {
                throw new LicenseFailure('DEVICE_MISMATCH');
            }
            $this->signatures->verify($request, $device->public_key, $session->id);
            $this->consumeNonce($device, (string) $request->input('nonce'));
            if ($operation === 'deactivate' && $session->status === 'terminated') {
                return ['status' => 'terminated'];
            }
            $this->assertLicense($license, (string) $request->input('product_code'));
            if (version_compare($device->client_version, $license->product->minimum_version, '<')) {
                throw new LicenseFailure('CLIENT_VERSION_UNSUPPORTED');
            }
            if ($license->generation !== $session->generation || (int) $request->input('generation') !== $session->generation || $license->current_device_uuid !== $device->device_uuid) {
                throw new LicenseFailure('SESSION_REPLACED', 401);
            }
            if ($session->status !== 'active') {
                throw new LicenseFailure('SESSION_REVOKED', 401);
            }
            if ($session->lease_expires_at->lte(now())) {
                throw new LicenseFailure('SESSION_EXPIRED', 401);
            }
            if ($operation === 'heartbeat') {
                $session->update(['last_heartbeat_at' => now(), 'lease_expires_at' => $this->leaseExpiry($license)]);
                $device->update(['last_seen_at' => now(), 'last_ip' => $request->ip()]);
            } elseif ($operation === 'deactivate') {
                $session->update(['status' => 'terminated', 'revoked_at' => now(), 'revocation_reason' => 'logout']);
                $device->update(['status' => 'inactive']);
                $this->event($license, 'deactivated', $request);
            }
            $this->mirrorLease($session);

            return $this->state($license, $session, $device);
        }, 3);
    }

    private function mirrorLease(LicenseSession $session): void
    {
        $id = $session->id;
        $generation = $session->generation;
        $expiry = $session->lease_expires_at->timestamp;
        $active = $session->status === 'active';
        DB::afterCommit(function () use ($id, $generation, $expiry, $active): void {
            try {
                $cache = Cache::store(config('license.cache_store'));
                if ($active && $expiry > now()->timestamp) {
                    $cache->put('license:lease:'.$id, ['generation' => $generation, 'expires_at' => $expiry], $expiry - now()->timestamp);
                } else {
                    $cache->forget('license:lease:'.$id);
                }
            } catch (\Throwable) {
            }
        });
    }

    public function assertLicense(License $license, string $productCode): void
    {
        if ($license->product->product_code !== $productCode || ! $license->product->is_active) {
            throw new LicenseFailure('INVALID_LICENSE');
        }
        if (in_array($license->status, ['suspended', 'revoked'], true)) {
            throw new LicenseFailure('LICENSE_'.strtoupper($license->status));
        }
        if ($license->status === 'expired' || $license->expires_at?->lte(now())) {
            throw new LicenseFailure('LICENSE_EXPIRED');
        }
    }

    public function revokeSessions(License $license, string $reason): void
    {
        $license->sessions()->where('status', 'active')->update(['status' => 'revoked', 'revoked_at' => now(), 'revocation_reason' => $reason]);
    }

    private function consumeNonce(LicenseDevice $device, string $nonce): void
    {
        $hash = hash('sha256', $nonce);
        if (! LicenseNonce::query()->insertOrIgnore(['device_id' => $device->id, 'nonce_hash' => $hash, 'expires_at' => now()->addSeconds(config('license.timestamp_tolerance') * 2 + 30)])) {
            throw new LicenseFailure('REPLAY_DETECTED', 409);
        }
        if (! Cache::store(config('license.cache_store'))->add('license:nonce:'.$device->id.':'.$hash, true, config('license.timestamp_tolerance') * 2 + 30)) {
            throw new LicenseFailure('REPLAY_DETECTED', 409);
        }
    }

    private function leaseExpiry(License $license): Carbon
    {
        $expiry = now()->addSeconds($license->product->lease_duration);

        return $license->expires_at && $license->expires_at->lt($expiry) ? $license->expires_at : $expiry;
    }

    public function event(License $license, string $event, Request $request, ?string $reason = null): void
    {
        LicenseEvent::query()->create(['license_id' => $license->id, 'actor_id' => $request->user()?->id, 'event' => $event, 'reason' => $reason, 'device_uuid' => $license->current_device_uuid, 'ip' => $request->ip()]);
    }

    /** @return array<string, mixed> */
    private function state(License $license, LicenseSession $session, LicenseDevice $device): array
    {
        return ['status' => $session->status, 'license_status' => $license->status, 'product_code' => $license->product->product_code, 'session_id' => $session->id, 'generation' => $session->generation, 'device_id' => $device->device_uuid, 'expires_at' => $license->expires_at?->toISOString(), 'lease_expires_at' => $session->lease_expires_at->toISOString(), 'heartbeat_interval' => $license->product->heartbeat_interval, 'lease_duration' => $license->product->lease_duration, 'next_transfer_at' => $license->last_transfer_at?->copy()->addSeconds($license->transfer_cooldown)->toISOString(), 'server_time' => now()->toISOString()];
    }
}
