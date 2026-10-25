<?php

use App\Features\License\Services\ClientGameReleaseService;
use App\Models\License;
use App\Models\LicenseDevice;
use App\Models\LicenseSession;
use App\Models\Setting;
use App\Models\User;
use App\Support\SettingStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

beforeEach(function (): void {
    config(['license.cache_store' => 'array']);
    Storage::fake('local');
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
});

function clientGameZip(string $name = 'game.zip'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, base64_decode('UEsDBBQAAAAAAAJlWF1ijVP/DAAAAAwAAAAIAAAAdG9vbC50eHR0b29sIHJlbGVhc2VQSwECFAAUAAAAAAACZVhdYo1T/wwAAAAMAAAACAAAAAAAAAAAAAAAgAEAAAAAdG9vbC50eHRQSwUGAAAAAAEAAQA2AAAAMgAAAAAA'));
}

/** @return array{session: LicenseSession, token: string} */
function clientGameLicenseSession(TestCase $case): array
{
    $case->privateKey = openssl_pkey_get_private(file_get_contents(base_path('tests/Fixtures/license-device-test.pem')));
    $pem = openssl_pkey_get_details($case->privateKey)['key'];
    $publicKey = str_replace(['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----', "\n", "\r"], '', $pem);
    $uuid = (string) Str::uuid();
    $license = License::factory()->create(['status' => 'active', 'generation' => 1, 'current_device_uuid' => $uuid, 'expires_at' => now()->addDays(30)]);
    $device = LicenseDevice::factory()->create(['license_id' => $license->id, 'device_uuid' => $uuid, 'public_key' => $publicKey, 'client_version' => '1.0.0']);
    $token = bin2hex(random_bytes(32));
    $session = LicenseSession::factory()->create(['device_id' => $device->id, 'license_id' => $license->id, 'token_hash' => hash('sha256', $token)]);

    return ['session' => $session, 'token' => $token];
}

/**
 * @param  array{session: LicenseSession, token: string}  $auth
 * @param  array<string, mixed>  $extra
 */
function signedClientGameRequest(TestCase $case, string $path, array $auth, array $extra = [], bool $badSignature = false): TestResponse
{
    $session = $auth['session'];
    $payload = [
        'product_code' => $session->license->product->product_code,
        'device_uuid' => $session->device->device_uuid,
        'session_id' => $session->id,
        'generation' => $session->generation,
        'timestamp' => now()->timestamp,
        'nonce' => bin2hex(random_bytes(32)),
        ...$extra,
    ];
    $path .= '?'.http_build_query($payload);
    $message = implode("\n", ['GET', $path, hash('sha256', ''), (string) $payload['timestamp'], $payload['nonce'], $payload['session_id']]);
    openssl_sign($message, $signature, $case->privateKey, OPENSSL_ALGO_SHA256);

    return $case->call('GET', $path, [], [], [], [
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_AUTHORIZATION' => 'Bearer '.$auth['token'],
        'HTTP_X_LICENSE_SIGNATURE' => $badSignature ? base64_encode('wrong') : base64_encode($signature),
    ], '');
}

test('client game uses signed license session for metadata and both archives without extending the lease', function (): void {
    $auth = clientGameLicenseSession($this);
    $expiry = $auth['session']->lease_expires_at;
    signedClientGameRequest($this, '/api/v1/licenses/client-game', $auth)->assertOk()
        ->assertExactJson(['success' => true, 'data' => ['original' => null, 'update' => null]]);
    foreach (['original', 'update'] as $kind) {
        $file = clientGameZip();
        $contents = file_get_contents($file->getRealPath());
        app(ClientGameReleaseService::class)->save($kind, '1.2.0', $file);
        signedClientGameRequest($this, '/api/v1/licenses/client-game/'.$kind.'/download', $auth)->assertOk()
            ->assertDownload('client-game-'.$kind.'-1.2.0.zip')->assertStreamedContent($contents)
            ->assertHeader('Cache-Control', 'no-store, private');
    }
    signedClientGameRequest($this, '/api/v1/licenses/client-game', $auth)->assertOk()->assertJsonPath('success', true)
        ->assertJsonPath('data.original.download_url', '/api/v1/licenses/client-game/original/download')->assertJsonMissingPath('data.original.path');
    expect($auth['session']->fresh()->lease_expires_at->equalTo($expiry))->toBeTrue();
});

test('client game rejects invalid license sessions on metadata and download', function (string $condition, string $code, string $endpoint): void {
    $auth = clientGameLicenseSession($this);
    app(ClientGameReleaseService::class)->save('original', '1.0.0', clientGameZip());
    $session = $auth['session'];
    $extra = [];
    if ($condition === 'token') {
        $auth['token'] = 'wrong';
    } elseif ($condition === 'missing-token') {
        $auth['token'] = '';
    } elseif ($condition === 'expired-session') {
        $session->update(['lease_expires_at' => now()->subSecond()]);
    } elseif ($condition === 'revoked-session') {
        $session->update(['status' => 'revoked']);
    } elseif ($condition === 'replaced') {
        $session->license->update(['generation' => 2]);
    } elseif ($condition === 'disabled-product') {
        $session->license->product->update(['is_active' => false]);
    } elseif ($condition === 'old-client') {
        $session->license->product->update(['minimum_version' => '2.0.0']);
    } elseif (in_array($condition, ['suspended', 'revoked', 'expired'], true)) {
        $session->license->update(['status' => $condition]);
    } elseif ($condition === 'past-expiry') {
        $session->license->update(['expires_at' => now()->subSecond()]);
    } elseif ($condition === 'device') {
        $extra['device_uuid'] = (string) Str::uuid();
    } elseif ($condition === 'product') {
        $extra['product_code'] = 'OTHER';
    } elseif ($condition === 'timestamp') {
        $extra['timestamp'] = now()->timestamp - 100;
    }
    signedClientGameRequest($this, '/api/v1/licenses/client-game'.$endpoint, $auth, $extra, $condition === 'signature')
        ->assertJsonPath('success', false)->assertJsonPath('code', $code)->assertJsonMissingPath('data')
        ->assertHeader('Cache-Control', 'no-store, private');
})->with([
    ['token', 'INVALID_SESSION'], ['missing-token', 'INVALID_SESSION'], ['signature', 'INVALID_SIGNATURE'],
    ['timestamp', 'INVALID_SIGNATURE'], ['expired-session', 'SESSION_EXPIRED'], ['revoked-session', 'SESSION_REVOKED'],
    ['replaced', 'SESSION_REPLACED'], ['disabled-product', 'INVALID_LICENSE'], ['product', 'INVALID_LICENSE'],
    ['device', 'DEVICE_MISMATCH'], ['suspended', 'LICENSE_SUSPENDED'], ['revoked', 'LICENSE_REVOKED'],
    ['expired', 'LICENSE_EXPIRED'], ['past-expiry', 'LICENSE_EXPIRED'], ['old-client', 'CLIENT_VERSION_UNSUPPORTED'],
])->with(['', '/original/download']);

test('client game rejects replay of a signed request', function (): void {
    $auth = clientGameLicenseSession($this);
    $extra = ['nonce' => bin2hex(random_bytes(32))];
    signedClientGameRequest($this, '/api/v1/licenses/client-game', $auth, $extra)->assertOk();
    signedClientGameRequest($this, '/api/v1/licenses/client-game', $auth, $extra)->assertConflict()->assertJsonPath('code', 'REPLAY_DETECTED');
});

test('authenticated client game missing archive returns license protocol error', function (): void {
    $auth = clientGameLicenseSession($this);
    signedClientGameRequest($this, '/api/v1/licenses/client-game/original/download', $auth)
        ->assertNotFound()->assertJsonPath('success', false)->assertJsonPath('code', 'FILE_NOT_FOUND');
});

test('old public client game endpoints are removed and website login does not bypass license authentication', function (): void {
    $this->getJson('/api/v1/client-game')->assertNotFound();
    $this->getJson('/api/v1/client-game/original/download')->assertNotFound();
    $this->getJson('/api/v1/client-game/update/download')->assertNotFound();
    $this->actingAs($this->admin)->getJson('/api/v1/licenses/client-game')->assertUnprocessable()->assertJsonPath('code', 'INVALID_REQUEST');
});

test('admin uploads independent original and update releases and downloads exact archives', function (): void {
    $this->actingAs($this->admin)->getJson('/api/admin-api/license/client-game')->assertOk()->assertExactJson(['data' => ['original' => null, 'update' => null]]);
    foreach (['original' => '1.0.0', 'update' => '1.1.0'] as $kind => $version) {
        $file = clientGameZip($kind.'.zip');
        $contents = file_get_contents($file->getRealPath());
        $this->actingAs($this->admin)->post('/api/admin-api/license/client-game/'.$kind, ['version' => $version, 'zip' => $file], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.'.$kind.'.version', $version)->assertJsonPath('data.'.$kind.'.name', $kind.'.zip')
            ->assertJsonPath('data.'.$kind.'.sha256', hash('sha256', $contents))->assertJsonPath('data.'.$kind.'.available', true)
            ->assertJsonMissingPath('data.'.$kind.'.path');
        $this->get('/api/admin-api/license/client-game/'.$kind.'/download')->assertOk()->assertDownload('client-game-'.$kind.'-'.$version.'.zip')
            ->assertHeader('Content-Type', 'application/zip')->assertHeader('Cache-Control', 'no-store, private')
            ->assertStreamedContent($contents);
    }
    $this->getJson('/api/admin-api/license/client-game')->assertOk()->assertJsonPath('data.original.version', '1.0.0')
        ->assertJsonPath('data.update.version', '1.1.0')->assertJsonMissingPath('data.original.path');
});

test('only admins can inspect and upload client game releases', function (string $role): void {
    if ($role !== 'guest') {
        $this->actingAs(User::factory()->create(['role' => $role]));
    }
    $expected = $role === 'guest' ? 401 : 403;
    $this->getJson('/api/admin-api/license/client-game')->assertStatus($expected);
    $this->getJson('/api/admin-api/license/client-game/original/download')->assertStatus($expected);
    $this->postJson('/api/admin-api/license/client-game/original', ['version' => '1.0.0', 'zip' => clientGameZip()])->assertStatus($expected);
    expect(Storage::disk('local')->allFiles('client-game'))->toBe([]);
})->with(['guest', User::ROLE_USER]);

test('client game validates content extension size file presence and version', function (string $invalid): void {
    $data = ['version' => '1.0.0', 'zip' => clientGameZip()];
    $field = 'zip';
    if ($invalid === 'content') {
        $fake = UploadedFile::fake()->createWithContent('bad.zip', 'not a zip');
        $data['zip'] = new UploadedFile($fake->getPathname(), 'bad.zip', null, null, true);
    } elseif ($invalid === 'extension') {
        $data['zip'] = clientGameZip('game.exe');
    } elseif ($invalid === 'size') {
        $data['zip'] = UploadedFile::fake()->create('large.zip', 102401, 'application/zip');
    } elseif ($invalid === 'missing-file') {
        unset($data['zip']);
    } else {
        $field = 'version';
        $data['version'] = $invalid === 'missing-version' ? '' : '../bad';
    }
    $this->actingAs($this->admin)->postJson('/api/admin-api/license/client-game/original', $data)
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(Storage::disk('local')->allFiles('client-game'))->toBe([]);
})->with(['content', 'extension', 'size', 'missing-file', 'missing-version', 'version']);

test('replacing a release preserves the other release and removes the previous archive', function (): void {
    $service = app(ClientGameReleaseService::class);
    $service->save('original', '1.0.0', clientGameZip());
    $service->save('update', '1.1.0', clientGameZip());
    $settings = app(SettingStore::class);
    $oldPath = $settings->getArray('client_game_update')['path'];
    $original = $settings->getArray('client_game_original');
    $service->save('update', '1.2.0', clientGameZip('new.zip'));
    expect($settings->getArray('client_game_original'))->toBe($original);
    $new = $settings->getArray('client_game_update');
    expect($new['path'])->not->toBe($oldPath);
    Storage::disk('local')->assertMissing($oldPath);
    Storage::disk('local')->assertExists([$original['path'], $new['path']]);
});

test('failed metadata persistence cleans new upload and preserves old downloadable release', function (): void {
    $service = app(ClientGameReleaseService::class);
    $service->save('original', '1.0.0', clientGameZip());
    $previous = app(SettingStore::class)->getArray('client_game_original');
    $mock = Mockery::mock(SettingStore::class)->makePartial();
    $mock->shouldReceive('putArray')->once()->andThrow(new RuntimeException('Database failure'));
    $failing = new ClientGameReleaseService($mock);
    expect(fn () => $failing->save('original', '2.0.0', clientGameZip()))->toThrow(RuntimeException::class);
    expect(app(SettingStore::class)->getArray('client_game_original'))->toBe($previous)
        ->and(Storage::disk('local')->allFiles('client-game'))->toBe([$previous['path']]);
    $this->actingAs($this->admin)->get('/api/admin-api/license/client-game/original/download')->assertOk()->assertDownload('client-game-original-1.0.0.zip');
});

test('missing archives and unsupported kinds return not found', function (): void {
    $this->actingAs($this->admin)->get('/api/admin-api/license/client-game/original/download')->assertNotFound();
    $this->get('/api/admin-api/license/client-game/update/download')->assertNotFound();
    $this->get('/api/admin-api/license/client-game/other/download')->assertNotFound();
    $this->actingAs($this->admin)->postJson('/api/admin-api/license/client-game/other', ['version' => '1.0.0', 'zip' => clientGameZip()])->assertNotFound();
    app(ClientGameReleaseService::class)->save('original', '1.0.0', clientGameZip());
    $path = app(SettingStore::class)->getArray('client_game_original')['path'];
    Storage::disk('local')->delete($path);
    $this->getJson('/api/admin-api/license/client-game')->assertOk()->assertJsonPath('data.original.available', false);
    $this->get('/api/admin-api/license/client-game/original/download')->assertNotFound();
    expect(Setting::query()->where('key', 'client_game_original')->exists())->toBeTrue();
});
