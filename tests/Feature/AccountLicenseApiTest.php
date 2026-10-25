<?php

use App\Features\License\Services\LicenseSessionService;
use App\Models\License;
use App\Models\LicensePlan;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Queue::fake();
    config(['license.cache_store' => 'array']);
    $this->member = User::factory()->create(['status' => 'active', 'password' => 'Launcher-password-123']);
});

test('launcher can log in fetch its assigned key and activate through the existing signed protocol', function (): void {
    $key = 'ABCD-1234-ABCD-1234-ABCD-1234-ABCD-1234';
    $license = License::factory()->create(['user_id' => $this->member->id, 'encrypted_key' => $key, 'key_hash' => LicenseSessionService::hashKey($key)]);
    $token = $this->postJson('/api/auth/login', ['login' => $this->member->email, 'password' => 'Launcher-password-123'])
        ->assertOk()->json('access_token');
    auth()->forgetGuards();
    $response = $this->withToken($token)->getJson('/api/account/licenses?product_code='.$license->product->product_code)->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.key', $key)->assertJsonPath('data.0.can_activate', true)
        ->assertHeader('Cache-Control', 'no-store, private')->assertJsonMissingPath('data.0.key_hash')
        ->assertJsonMissingPath('data.0.encrypted_key')->assertJsonMissingPath('data.0.sessions');
    auth()->forgetGuards();
    $this->getJson('/api/account/licenses/'.$license->id)->assertOk()->assertJsonPath('data.key', $key)
        ->assertHeader('Cache-Control', 'no-store, private');
    $challenge = $this->postJson('/api/v1/licenses/challenge')->assertOk()->json('data');
    $private = openssl_pkey_get_private(file_get_contents(base_path('tests/Fixtures/license-device-test.pem')));
    $pem = openssl_pkey_get_details($private)['key'];
    $timestamp = now()->timestamp;
    $payload = [
        'license_key' => $response->json('data.0.key'), 'product_code' => $response->json('data.0.product_code'),
        'device_uuid' => (string) Str::uuid(), 'device_name' => 'LAUNCHER-PC',
        'public_key' => str_replace(['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----', "\n", "\r"], '', $pem),
        'client_version' => '1.0.0', 'challenge_id' => $challenge['challenge_id'], 'nonce' => $challenge['nonce'], 'timestamp' => $timestamp,
    ];
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $path = '/api/v1/licenses/activate';
    $message = implode("\n", ['POST', $path, hash('sha256', $body), (string) $timestamp, $payload['nonce'], '']);
    openssl_sign($message, $signature, $private, OPENSSL_ALGO_SHA256);
    $this->call('POST', $path, [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_LICENSE_SIGNATURE' => base64_encode($signature),
    ], $body)->assertOk()->assertJsonPath('data.status', 'active');
    expect($license->fresh()->status)->toBe('active');
});

test('account license list and detail never expose foreign or unassigned keys including for admins', function (string $role): void {
    $this->member->update(['role' => $role]);
    $owned = License::factory()->create(['user_id' => $this->member->id, 'encrypted_key' => 'OWNED-KEY']);
    $foreign = License::factory()->create(['user_id' => User::factory()->create()->id, 'encrypted_key' => 'FOREIGN-KEY']);
    $unassigned = License::factory()->create(['user_id' => null, 'encrypted_key' => 'UNASSIGNED-KEY']);
    $token = $this->member->createToken('launcher')->plainTextToken;
    $this->withToken($token)->getJson('/api/account/licenses')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $owned->id)->assertDontSee('FOREIGN-KEY')->assertDontSee('UNASSIGNED-KEY');
    foreach ([$foreign->id, $unassigned->id, 999999] as $id) {
        auth()->forgetGuards();
        $this->getJson('/api/account/licenses/'.$id)->assertNotFound();
    }
})->with([User::ROLE_USER, User::ROLE_ADMIN]);

test('unusable and legacy licenses expose state without an activation key', function (string $reason): void {
    $license = License::factory()->create(['user_id' => $this->member->id, 'encrypted_key' => 'SECRET-UNUSABLE']);
    if ($reason === 'past-expiry') {
        $license->update(['expires_at' => now()->subSecond()]);
    } elseif ($reason === 'disabled-product') {
        $license->product->update(['is_active' => false]);
    } elseif ($reason === 'legacy') {
        $license->update(['encrypted_key' => null]);
    } else {
        $license->update(['status' => $reason]);
    }
    $token = $this->member->createToken('launcher')->plainTextToken;
    $this->withToken($token)->getJson('/api/account/licenses')->assertOk()->assertJsonPath('data.0.key', null)
        ->assertJsonPath('data.0.can_activate', false)->assertDontSee('SECRET-UNUSABLE');
    auth()->forgetGuards();
    $this->getJson('/api/account/licenses/'.$license->id)->assertOk()->assertJsonPath('data.key', null)->assertJsonPath('data.can_activate', false);
})->with(['expired', 'past-expiry', 'suspended', 'revoked', 'disabled-product', 'legacy']);

test('account licenses require a valid token and an active account', function (string $reason, string $endpoint): void {
    $license = License::factory()->create(['user_id' => $this->member->id, 'encrypted_key' => 'PRIVATE-KEY']);
    if ($reason !== 'missing') {
        $token = $this->member->createToken('launcher', ['*'], $reason === 'expired' ? now()->subMinute() : null);
        $value = $token->plainTextToken;
        if ($reason === 'invalid') {
            $value = 'wrong-token';
        } elseif ($reason === 'revoked') {
            $token->accessToken->delete();
        } elseif ($reason === 'blocked') {
            $this->member->update(['status' => 'banned']);
        }
        $this->withToken($value);
    }
    $response = $this->getJson('/api/account/licenses'.($endpoint === 'detail' ? '/'.$license->id : ''));
    if ($reason === 'blocked') {
        $response->assertForbidden();
    } else {
        $response->assertUnauthorized();
    }
    $response->assertDontSee('PRIVATE-KEY');
})->with(['missing', 'invalid', 'revoked', 'expired', 'blocked'])->with(['list', 'detail']);

test('account license list filters by tool and paginates without mixing products', function (): void {
    $plan = LicensePlan::factory()->create();
    License::factory()->count(21)->create(['user_id' => $this->member->id, 'plan_id' => $plan->id, 'encrypted_key' => 'MY-KEY']);
    License::factory()->create(['user_id' => $this->member->id, 'encrypted_key' => 'OTHER-TOOL']);
    $token = $this->member->createToken('launcher')->plainTextToken;
    $url = '/api/account/licenses?product_code='.$plan->product->product_code;
    $this->withToken($token)->getJson($url)->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('meta.total', 21)->assertDontSee('OTHER-TOOL');
    auth()->forgetGuards();
    $this->getJson($url.'&page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 21);
    auth()->forgetGuards();
    $this->getJson('/api/account/licenses?product_code=UNKNOWN')->assertOk()->assertJsonPath('data', []);
    auth()->forgetGuards();
    $this->getJson('/api/account/licenses?product_code[]=BAD')->assertUnprocessable()->assertJsonValidationErrors('product_code');
});
