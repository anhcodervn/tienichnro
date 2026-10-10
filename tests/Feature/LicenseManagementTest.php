<?php

use App\Features\License\Services\LicenseSessionService;
use App\Models\AdminAuditLog;
use App\Models\License;
use App\Models\LicensePlan;
use App\Models\LicenseProduct;
use App\Models\LicenseSession;
use App\Models\User;
use Database\Seeders\LicensePlanSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

beforeEach(function (): void {
    config(['license.cache_store' => 'array']);
    $this->owner = User::factory()->create(['password' => 'owner-secret', 'email_verified_at' => now()]);
    $this->product = LicenseProduct::factory()->create();
    $this->plan = LicensePlan::factory()->create(['product_id' => $this->product->id]);
    $this->key = 'ABCD-1234-ABCD-1234-ABCD-1234-ABCD-1234';
    $this->license = License::factory()->create(['product_id' => $this->product->id, 'plan_id' => $this->plan->id, 'key_hash' => LicenseSessionService::hashKey($this->key), 'user_id' => $this->owner->id]);
    $this->device = (string) Str::uuid();
    $this->privateKey = openssl_pkey_get_private(file_get_contents(base_path('tests/Fixtures/license-device-test.pem')));
    expect($this->privateKey)->not->toBeFalse();
    $pem = openssl_pkey_get_details($this->privateKey)['key'];
    $this->publicKey = str_replace(['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----', "\n", "\r"], '', $pem);
});

/** @param array<string, mixed> $payload */
function signedLicenseRequest(TestCase $case, string $path, array $payload, ?string $token = null, string $method = 'POST', bool $badSignature = false): TestResponse
{
    $payload += ['timestamp' => now()->timestamp, 'nonce' => bin2hex(random_bytes(32))];
    $body = $method === 'GET' ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($method === 'GET') {
        $path .= '?'.http_build_query($payload);
    }
    $message = implode("\n", [$method, $path, hash('sha256', $body), (string) $payload['timestamp'], $payload['nonce'], $payload['session_id'] ?? '']);
    openssl_sign($message, $signature, $case->privateKey, OPENSSL_ALGO_SHA256);
    $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_LICENSE_SIGNATURE' => $badSignature ? base64_encode('wrong') : base64_encode($signature)];
    if ($token) {
        $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
    }

    return $case->call($method, $path, [], [], [], $headers, $body);
}

/** @return array<string, mixed> */
function licenseActivationPayload(TestCase $case, array $extra = []): array
{
    $challenge = $case->postJson('/api/v1/licenses/challenge')->assertOk()->json('data');

    return ['license_key' => $case->key, 'product_code' => $case->product->product_code, 'device_uuid' => $case->device, 'device_name' => 'INTERNAL-PC', 'public_key' => $case->publicKey, 'client_version' => '1.0.0', 'challenge_id' => $challenge['challenge_id'], 'nonce' => $challenge['nonce'], ...$extra];
}

/** @return array<string, mixed> */
function activateTestLicense(TestCase $case): array
{
    return signedLicenseRequest($case, '/api/v1/licenses/activate', licenseActivationPayload($case))->assertOk()->json('data');
}

/** @return array<string, mixed> */
function licenseHeartbeatPayload(TestCase $case, array $session, array $extra = []): array
{
    return ['product_code' => $case->product->product_code, 'device_uuid' => $case->device, 'session_id' => $session['session_id'], 'generation' => $session['generation'], ...$extra];
}

test('valid key activates with hashed credentials and a bounded lease', function (): void {
    $session = activateTestLicense($this);
    expect($session['heartbeat_interval'])->toBe(20)->and($session['generation'])->toBe(1);
    expect($this->license->fresh()->status)->toBe('active');
    expect(LicenseSession::query()->first()->token_hash)->toBe(hash('sha256', $session['session_token']));
    expect($this->license->fresh()->toArray())->not->toHaveKey('key_hash');
    expect($this->license->fresh()->expires_at->diffInDays(now(), true))->toBeGreaterThan(29);
});

test('invalid expired suspended revoked and wrong product keys are denied', function (string $condition, string $code): void {
    $extra = [];
    if ($condition === 'invalid') {
        $extra['license_key'] = str_repeat('A', 32);
    } elseif ($condition === 'expired') {
        $this->license->update(['expires_at' => now()->subSecond()]);
    } elseif ($condition === 'product') {
        $extra['product_code'] = 'OTHER';
    } else {
        $this->license->update(['status' => $condition]);
    }
    signedLicenseRequest($this, '/api/v1/licenses/activate', licenseActivationPayload($this, $extra))->assertJsonPath('code', $code);
    $this->assertDatabaseCount('license_sessions', 0);
})->with([['invalid', 'INVALID_LICENSE'], ['expired', 'LICENSE_EXPIRED'], ['suspended', 'LICENSE_SUSPENDED'], ['revoked', 'LICENSE_REVOKED'], ['product', 'INVALID_LICENSE']]);

test('another device cannot activate even after logout or lease expiry', function (string $state): void {
    $session = activateTestLicense($this);
    if ($state === 'logout') {
        signedLicenseRequest($this, '/api/v1/licenses/deactivate', licenseHeartbeatPayload($this, $session), $session['session_token'])->assertOk();
    } else {
        $this->travel(61)->seconds();
    }
    signedLicenseRequest($this, '/api/v1/licenses/activate', licenseActivationPayload($this, ['device_uuid' => (string) Str::uuid()]))->assertConflict()->assertJsonPath('code', 'DEVICE_ALREADY_ACTIVE');
})->with(['logout', 'expired']);

test('heartbeat and signed status succeed without exposing credentials', function (): void {
    $session = activateTestLicense($this);
    $this->travel(20)->seconds();
    signedLicenseRequest($this, '/api/v1/licenses/heartbeat', licenseHeartbeatPayload($this, $session), $session['session_token'])->assertOk()->assertJsonPath('data.status', 'active')->assertJsonMissingPath('data.session_token');
    signedLicenseRequest($this, '/api/v1/licenses/status', licenseHeartbeatPayload($this, $session), $session['session_token'], 'GET')->assertOk()->assertJsonMissingPath('data.public_key');
});

test('elapsed lease cannot be revived by heartbeat before scheduler runs', function (): void {
    $session = activateTestLicense($this);
    $expiry = LicenseSession::query()->first()->lease_expires_at;
    $this->travel(61)->seconds();
    signedLicenseRequest($this, '/api/v1/licenses/heartbeat', licenseHeartbeatPayload($this, $session), $session['session_token'])->assertUnauthorized()->assertJsonPath('code', 'SESSION_EXPIRED');
    expect(LicenseSession::query()->first()->lease_expires_at->equalTo($expiry))->toBeTrue();
});

test('bad signatures timestamps device generation and tokens cannot renew a lease', function (string $condition, string $code): void {
    $session = activateTestLicense($this);
    $extra = match ($condition) {
        'timestamp' => ['timestamp' => now()->timestamp - 100], 'device' => ['device_uuid' => (string) Str::uuid()], 'generation' => ['generation' => 999], default => []
    };
    signedLicenseRequest($this, '/api/v1/licenses/heartbeat', licenseHeartbeatPayload($this, $session, $extra), $condition === 'token' ? 'wrong' : $session['session_token'], 'POST', $condition === 'signature')->assertJsonPath('code', $code);
})->with([['signature', 'INVALID_SIGNATURE'], ['timestamp', 'INVALID_SIGNATURE'], ['device', 'DEVICE_MISMATCH'], ['generation', 'SESSION_REPLACED'], ['token', 'INVALID_SESSION']]);

test('activation challenge is single use and expires', function (): void {
    $payload = licenseActivationPayload($this);
    signedLicenseRequest($this, '/api/v1/licenses/activate', $payload)->assertOk();
    signedLicenseRequest($this, '/api/v1/licenses/activate', $payload)->assertConflict()->assertJsonPath('code', 'REPLAY_DETECTED');
    $payload = licenseActivationPayload($this);
    $this->travel(121)->seconds();
    signedLicenseRequest($this, '/api/v1/licenses/activate', $payload)->assertConflict();
    $this->assertDatabaseCount('license_sessions', 1);
});

test('replay remains denied after redis cache loss and state cannot resurrect a replaced session', function (): void {
    $session = activateTestLicense($this);
    $payload = licenseHeartbeatPayload($this, $session, ['nonce' => bin2hex(random_bytes(32)), 'timestamp' => now()->timestamp]);
    signedLicenseRequest($this, '/api/v1/licenses/heartbeat', $payload, $session['session_token'])->assertOk();
    Cache::store('array')->flush();
    signedLicenseRequest($this, '/api/v1/licenses/heartbeat', $payload, $session['session_token'])->assertConflict()->assertJsonPath('code', 'REPLAY_DETECTED');
    activateTestLicense($this);
    Cache::store('array')->flush();
    signedLicenseRequest($this, '/api/v1/licenses/heartbeat', licenseHeartbeatPayload($this, $session), $session['session_token'])->assertJsonPath('code', 'SESSION_REPLACED');
});

test('transfer requires verified owner password and respects cooldown', function (): void {
    activateTestLicense($this);
    $this->actingAs($this->owner);
    $payload = licenseActivationPayload($this, ['device_uuid' => (string) Str::uuid(), 'owner_password' => 'owner-secret']);
    signedLicenseRequest($this, '/api/v1/licenses/transfer', $payload)->assertConflict()->assertJsonPath('code', 'TRANSFER_COOLDOWN');
    $payload['owner_password'] = 'wrong';
    signedLicenseRequest($this, '/api/v1/licenses/transfer', $payload)->assertUnprocessable();
    $this->actingAs(User::factory()->create(['password' => 'owner-secret', 'email_verified_at' => now()]));
    signedLicenseRequest($this, '/api/v1/licenses/transfer', licenseActivationPayload($this, ['device_uuid' => (string) Str::uuid(), 'owner_password' => 'owner-secret']))->assertForbidden();
});

test('transfer replaces old generation and old heartbeat cannot extend it', function (): void {
    $old = activateTestLicense($this);
    $this->travel(1801)->seconds();
    $this->actingAs($this->owner);
    $target = (string) Str::uuid();
    $new = signedLicenseRequest($this, '/api/v1/licenses/transfer', licenseActivationPayload($this, ['device_uuid' => $target, 'owner_password' => 'owner-secret']))->assertOk()->json('data');
    expect($new['generation'])->toBe(2)->and(LicenseSession::query()->where('status', 'active')->count())->toBe(1);
    $oldExpiry = LicenseSession::query()->findOrFail($old['session_id'])->lease_expires_at;
    signedLicenseRequest($this, '/api/v1/licenses/heartbeat', licenseHeartbeatPayload($this, $old), $old['session_token'])->assertUnauthorized()->assertJsonPath('code', 'SESSION_REPLACED');
    expect(LicenseSession::query()->findOrFail($old['session_id'])->lease_expires_at->equalTo($oldExpiry))->toBeTrue();
    signedLicenseRequest($this, '/api/v1/licenses/heartbeat', licenseHeartbeatPayload($this, $new, ['device_uuid' => $target]), $new['session_token'])->assertOk();
    signedLicenseRequest($this, '/api/v1/licenses/transfer', licenseActivationPayload($this, ['device_uuid' => (string) Str::uuid(), 'owner_password' => 'owner-secret']))->assertConflict();
});

test('logout is idempotent with fresh nonces and same device reconnect revokes old token', function (): void {
    $session = activateTestLicense($this);
    for ($i = 0; $i < 2; $i++) {
        signedLicenseRequest($this, '/api/v1/licenses/deactivate', licenseHeartbeatPayload($this, $session), $session['session_token'])->assertOk()->assertJsonPath('data.status', 'terminated');
    }
    $new = activateTestLicense($this);
    expect($new['generation'])->toBe(2)->and($this->license->fresh()->last_transfer_at->equalTo($this->license->fresh()->activated_at))->toBeTrue();
});

test('same device replacement leaves exactly one valid session', function (): void {
    $old = activateTestLicense($this);
    activateTestLicense($this);
    expect(LicenseSession::query()->where('status', 'active')->count())->toBe(1);
    signedLicenseRequest($this, '/api/v1/licenses/heartbeat', licenseHeartbeatPayload($this, $old), $old['session_token'])->assertUnauthorized();
});

test('admin can issue batch inspect extend suspend reset and permanently revoke', function (): void {
    $session = activateTestLicense($this);
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);
    $issued = $this->postJson('/api/admin-api/license/keys', ['plan_id' => $this->plan->id, 'quantity' => 2, 'user_id' => $this->owner->id])->assertCreated()->json('data');
    expect($issued[0]['key'])->not->toBe($issued[1]['key']);
    $this->getJson('/api/admin-api/license/keys')->assertOk()->assertJsonMissingPath('data.0.key_hash')->assertJsonMissingPath('data.0.key');
    $this->getJson('/api/admin-api/license/keys/'.$this->license->id)->assertOk()->assertJsonMissingPath('data.devices.0.public_key')->assertJsonMissingPath('data.sessions.0.token_hash');
    $endpoint = '/api/admin-api/license/keys/'.$this->license->id;
    $this->patchJson($endpoint, ['action' => 'extend', 'days' => 7, 'reason' => 'Gia h?n n?i b?'])->assertOk();
    $this->patchJson($endpoint, ['action' => 'suspend', 'reason' => 'T?m ng?ng tool'])->assertOk();
    signedLicenseRequest($this, '/api/v1/licenses/heartbeat', licenseHeartbeatPayload($this, $session), $session['session_token'])->assertJsonPath('code', 'LICENSE_SUSPENDED');
    $this->patchJson($endpoint, ['action' => 'resume', 'reason' => 'Cho ph?p s? d?ng'])->assertOk();
    $this->patchJson($endpoint, ['action' => 'reset-device', 'reason' => 'Thay m?y n?i b?'])->assertOk()->assertJsonPath('data.current_device_uuid', null);
    $this->patchJson($endpoint, ['action' => 'revoke', 'reason' => 'Thu h?i v?nh vi?n'])->assertOk();
    signedLicenseRequest($this, '/api/v1/licenses/heartbeat', licenseHeartbeatPayload($this, $session), $session['session_token'])->assertJsonPath('code', 'LICENSE_REVOKED');
    $this->patchJson($endpoint, ['action' => 'resume', 'reason' => 'Kh?ng ???c m? l?i'])->assertUnprocessable();
});

test('admin APIs reject guests ordinary members tool tokens and invalid settings', function (): void {
    $this->getJson('/api/admin-api/license/keys')->assertUnauthorized();
    $session = activateTestLicense($this);
    $this->withToken($session['session_token'])->getJson('/api/admin-api/license/keys')->assertUnauthorized();
    $this->actingAs($this->owner)->getJson('/api/admin-api/license/keys')->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->postJson('/api/admin-api/license/products', [...$this->product->only(['name', 'product_code', 'minimum_version', 'is_active', 'heartbeat_interval', 'lease_duration', 'transfer_cooldown', 'max_active_devices', 'offline_grace']), 'product_code' => 'NEW_TOOL', 'lease_duration' => 10])->assertUnprocessable()->assertJsonValidationErrors('lease_duration');
    $this->patchJson('/api/admin-api/license/keys/'.$this->license->id, ['action' => 'reset-device'])->assertUnprocessable()->assertJsonValidationErrors('reason');
});

test('perpetual key remains without expiry and product disable denies existing sessions', function (): void {
    $this->license->update(['duration_days' => null]);
    $session = activateTestLicense($this);
    expect($session['expires_at'])->toBeNull();
    $this->product->update(['is_active' => false]);
    signedLicenseRequest($this, '/api/v1/licenses/heartbeat', licenseHeartbeatPayload($this, $session), $session['session_token'])->assertJsonPath('code', 'INVALID_LICENSE');
});

test('scheduler cleans history and marks expiry without granting new sessions', function (): void {
    activateTestLicense($this);
    $this->travel(61)->seconds();
    $this->artisan('licenses:cleanup')->assertExitCode(0);
    expect(LicenseSession::query()->first()->status)->toBe('expired');
    $this->assertDatabaseCount('license_sessions', 1);
});

test('license errors redact internals even with debug enabled and fail closed without cache', function (): void {
    config(['app.debug' => true, 'license.cache_store' => 'missing']);
    $response = $this->postJson('/api/v1/licenses/challenge')->assertStatus(503)->assertJsonPath('code', 'SERVICE_UNAVAILABLE')->assertJsonMissingPath('trace');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('service navigation and catalog modal are hidden by default and can be restored', function (): void {
    $this->get('/')->assertOk()->assertDontSee('data-client-services-open', false)->assertDontSee('id="client-services-modal"', false);
    config(['license.services_visible' => true]);
    $this->get('/')->assertOk()->assertSee('data-client-services-open', false)->assertSee('id="client-services-modal"', false);
});

test('catalog CRUD validates single device policy and seeder creates all standard plans', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $payload = ['name' => 'Internal Tool', 'product_code' => 'INTERNAL_TOOL', 'description' => 'Private tool', 'minimum_version' => '1.0.0', 'is_active' => true, 'heartbeat_interval' => 20, 'lease_duration' => 60, 'transfer_cooldown' => 1800, 'max_active_devices' => 1, 'offline_grace' => 0];
    $id = $this->postJson('/api/admin-api/license/products', $payload)->assertCreated()->json('data.id');
    $this->patchJson('/api/admin-api/license/products/'.$id, [...$payload, 'lease_duration' => 90])->assertOk()->assertJsonPath('data.lease_duration', 90);
    $this->postJson('/api/admin-api/license/products', [...$payload, 'product_code' => 'MULTI', 'max_active_devices' => 2])->assertUnprocessable();
    $this->postJson('/api/admin-api/license/products', [...$payload, 'product_code' => 'GRACE', 'offline_grace' => 30])->assertUnprocessable();
    $plan = ['product_id' => $id, 'name' => 'Lifetime', 'duration_days' => null, 'price' => 0, 'max_active_devices' => 1, 'transfer_cooldown' => 1800, 'is_active' => true];
    $planId = $this->postJson('/api/admin-api/license/plans', $plan)->assertCreated()->json('data.id');
    $issued = $this->postJson('/api/admin-api/license/keys', ['plan_id' => $planId, 'quantity' => 1])->assertCreated()->json('data.0.id');
    expect(License::query()->findOrFail($issued)->duration_days)->toBeNull();
    $this->patchJson('/api/admin-api/license/plans/'.$planId, [...$plan, 'duration_days' => 7, 'price' => 100])->assertOk();
    expect(License::query()->findOrFail($issued)->duration_days)->toBeNull();
    $this->patchJson('/api/admin-api/license/plans/'.$planId, [...$plan, 'product_id' => $this->product->id])->assertUnprocessable();
    $this->seed(LicensePlanSeeder::class);
    expect(LicensePlan::query()->where('product_id', $id)->whereIn('duration_days', [1, 7, 30, 90, 365])->distinct()->count('duration_days'))->toBe(5);
});

test('device cannot replace its registered public key and minimum version is enforced', function (): void {
    activateTestLicense($this);
    $device = $this->license->devices()->firstOrFail();
    $device->update(['public_key' => 'different-key']);
    signedLicenseRequest($this, '/api/v1/licenses/activate', licenseActivationPayload($this))->assertJsonPath('code', 'DEVICE_MISMATCH');
    $this->product->update(['minimum_version' => '2.0.0']);
    signedLicenseRequest($this, '/api/v1/licenses/activate', licenseActivationPayload($this))->assertJsonPath('code', 'CLIENT_VERSION_UNSUPPORTED');
});

test('admin transfer requires a verified target and unused extension updates activation duration', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $endpoint = '/api/admin-api/license/keys/'.$this->license->id;
    $this->patchJson($endpoint, ['action' => 'extend', 'days' => 7, 'reason' => 'Extend unused key'])->assertOk();
    expect($this->license->fresh()->duration_days)->toBe(37)->and($this->license->fresh()->expires_at)->toBeNull();
    $this->patchJson($endpoint, ['action' => 'transfer', 'device_uuid' => (string) Str::uuid(), 'reason' => 'Unverified target'])->assertUnprocessable();
    expect($this->license->fresh()->generation)->toBe(0);
});

test('rate limiting rejects excess requests without leaking secrets', function (): void {
    for ($i = 0; $i < 120; $i++) {
        $this->postJson('/api/v1/licenses/challenge')->assertOk();
    }
    $this->postJson('/api/v1/licenses/challenge')->assertStatus(429)->assertJsonPath('code', 'RATE_LIMITED')->assertJsonMissingPath('trace');
});

test('tool token is insufficient to transfer even with a known license key', function (): void {
    $session = activateTestLicense($this);
    signedLicenseRequest($this, '/api/v1/licenses/transfer', licenseActivationPayload($this, ['device_uuid' => (string) Str::uuid(), 'owner_password' => 'owner-secret']), $session['session_token'])->assertUnauthorized();
});

test('raising minimum version stops an already running older client at heartbeat', function (): void {
    $session = activateTestLicense($this);
    $this->product->update(['minimum_version' => '2.0.0']);
    signedLicenseRequest($this, '/api/v1/licenses/heartbeat', licenseHeartbeatPayload($this, $session), $session['session_token'])->assertForbidden()->assertJsonPath('code', 'CLIENT_VERSION_UNSUPPORTED');
});

test('license administration audit redacts credentials and never records issued key plaintext', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $issued = $this->postJson('/api/admin-api/license/keys', ['plan_id' => $this->plan->id, 'quantity' => 1, 'license_key' => 'SECRET-LICENSE', 'session_token' => 'SECRET-SESSION', 'owner_password' => 'SECRET-PASSWORD'])->assertCreated()->json('data.0.key');
    $log = AdminAuditLog::query()->where('path', '/api/admin-api/license/keys')->firstOrFail();
    expect(data_get($log->new_values, 'input.license_key'))->toBe('[REDACTED]');
    expect(data_get($log->new_values, 'input.session_token'))->toBe('[REDACTED]');
    expect(data_get($log->new_values, 'input.owner_password'))->toBe('[REDACTED]');
    expect(json_encode($log->new_values))->not->toContain($issued);
});
