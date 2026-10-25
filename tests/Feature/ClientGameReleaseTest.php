<?php

use App\Features\License\Services\ClientGameReleaseService;
use App\Models\Setting;
use App\Models\User;
use App\Support\SettingStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
});

function clientGameZip(string $name = 'game.zip'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, base64_decode('UEsDBBQAAAAAAAJlWF1ijVP/DAAAAAwAAAAIAAAAdG9vbC50eHR0b29sIHJlbGVhc2VQSwECFAAUAAAAAAACZVhdYo1T/wwAAAAMAAAACAAAAAAAAAAAAAAAgAEAAAAAdG9vbC50eHRQSwUGAAAAAAEAAQA2AAAAMgAAAAAA'));
}

test('client game accepts account login bearer token without license device or signature', function (): void {
    $member = User::factory()->create(['role' => User::ROLE_USER, 'password' => 'Client-password-123', 'status' => 'active']);
    $token = $this->postJson('/api/auth/login', ['login' => $member->email, 'password' => 'Client-password-123'])
        ->assertOk()->assertJsonPath('token_type', 'Bearer')->json('access_token');
    auth()->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/licenses/client-game')->assertOk()
        ->assertExactJson(['success' => true, 'data' => ['original' => null, 'update' => null]]);
    foreach (['original', 'update'] as $kind) {
        $file = clientGameZip();
        $contents = file_get_contents($file->getRealPath());
        app(ClientGameReleaseService::class)->save($kind, '1.2.0', $file);
        auth()->forgetGuards();
        $this->get('/api/v1/licenses/client-game/'.$kind.'/download', ['Accept' => 'application/json'])->assertOk()
            ->assertDownload('client-game-'.$kind.'-1.2.0.zip')->assertStreamedContent($contents)
            ->assertHeader('Cache-Control', 'no-store, private');
    }
    auth()->forgetGuards();
    $this->getJson('/api/v1/licenses/client-game')->assertOk()->assertJsonPath('success', true)
        ->assertJsonPath('data.original.download_url', '/api/v1/licenses/client-game/original/download')->assertJsonMissingPath('data.original.path');
    $this->assertDatabaseCount('licenses', 0);
    $this->assertDatabaseCount('license_devices', 0);
    $this->assertDatabaseCount('license_sessions', 0);
});

test('client game rejects missing invalid revoked expired tokens and blocked accounts', function (string $condition, string $endpoint): void {
    app(ClientGameReleaseService::class)->save('original', '1.0.0', clientGameZip());
    app(ClientGameReleaseService::class)->save('update', '1.0.0', clientGameZip());
    $member = User::factory()->create(['status' => $condition === 'blocked' ? 'banned' : 'active']);
    $token = $member->createToken('launcher', ['*'], $condition === 'expired' ? now()->subMinute() : null);
    $value = $token->plainTextToken;
    if ($condition === 'invalid' || $condition === 'license-token') {
        $value = $condition === 'invalid' ? 'invalid-token' : bin2hex(random_bytes(32));
    } elseif ($condition === 'revoked') {
        $token->accessToken->delete();
    }
    if ($condition !== 'missing') {
        $this->withToken($value);
    }
    $response = $this->getJson('/api/v1/licenses/client-game'.$endpoint);
    if ($condition === 'blocked') {
        $response->assertForbidden();
    } else {
        $response->assertUnauthorized();
    }
})->with(['missing', 'invalid', 'revoked', 'expired', 'blocked', 'license-token'])->with(['', '/original/download', '/update/download']);

test('client game downloads remain usable until the account bearer token is logged out', function (): void {
    app(ClientGameReleaseService::class)->save('original', '1.0.0', clientGameZip());
    $member = User::factory()->create(['status' => 'active']);
    $token = $member->createToken('launcher')->plainTextToken;
    for ($i = 0; $i < 2; $i++) {
        auth()->forgetGuards();
        $this->withToken($token)->get('/api/v1/licenses/client-game/original/download', ['Accept' => 'application/json'])->assertOk();
    }
    auth()->forgetGuards();
    $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
    auth()->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/licenses/client-game/original/download')->assertUnauthorized();
});

test('account authenticated client game missing archive returns a not found response', function (): void {
    $member = User::factory()->create(['status' => 'active']);
    $token = $member->createToken('launcher')->plainTextToken;
    $this->withToken($token)->getJson('/api/v1/licenses/client-game/original/download')
        ->assertNotFound()->assertJsonPath('success', false)->assertJsonPath('code', 'FILE_NOT_FOUND');
});

test('old public client game endpoints remain removed and website authentication follows sanctum', function (): void {
    $this->getJson('/api/v1/client-game')->assertNotFound();
    $this->getJson('/api/v1/client-game/original/download')->assertNotFound();
    $this->getJson('/api/v1/client-game/update/download')->assertNotFound();
    $this->actingAs($this->admin)->getJson('/api/v1/licenses/client-game')->assertOk();
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
