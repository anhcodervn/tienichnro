<?php

use App\Features\Client\GameImage\Services\GameImageAssetService;
use App\Features\Client\GameImage\Services\GameImageCredentialService;
use App\Features\Client\GameImage\Services\GameImageOrderService;
use App\Models\GameImageAccount;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery\MockInterface;

function toolRsaKeys(int $slot = 1, int $bits = 2048): array
{
    static $keys = [];
    $id = $slot.'-'.$bits;
    if (! isset($keys[$id])) {
        $config = tempnam(sys_get_temp_dir(), 'tool-key-');
        file_put_contents($config, "[req]\ndistinguished_name=req_distinguished_name\n[req_distinguished_name]\n");
        try {
            $options = ['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => $bits, 'config' => $config];
            $key = openssl_pkey_new($options);
            if ($key === false || ! openssl_pkey_export($key, $private, null, $options)) {
                throw new RuntimeException('Unable to generate test key.');
            }
        } finally {
            unlink($config);
        }
        $keys[$id] = ['public' => openssl_pkey_get_details($key)['key'], 'private' => $private];
    }

    return $keys[$id];
}

function toolDecryptPassword(array $envelope, string $privateKey): string|false
{
    if (! @openssl_private_decrypt(base64_decode($envelope['encrypted_key'], true), $key, $privateKey, OPENSSL_PKCS1_OAEP_PADDING)) {
        return false;
    }

    return openssl_decrypt(base64_decode($envelope['ciphertext'], true), 'aes-256-gcm', $key, OPENSSL_RAW_DATA,
        base64_decode($envelope['iv'], true), base64_decode($envelope['tag'], true), base64_decode($envelope['aad'], true));
}

function toolCaptureZip(string $prefix = '', bool $executable = false): UploadedFile
{
    $path = Storage::disk('local')->path('capture-'.Str::uuid().'.zip');
    $zip = new PharData($path, 0, null, Phar::ZIP);
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aJXkAAAAASUVORK5CYII=');
    foreach ([...GameImageAssetService::PANELS, 'details/item-0047.png', 'details/item-0085.png'] as $file) {
        $zip->addFromString($prefix.$file, $png);
    }
    $zip->addFromString($prefix.'metadata.json', '{"cName":"kztiger"}');
    if ($executable) {
        $zip->addFromString('run.php', '<?php echo 1;');
    }
    unset($zip);

    return new UploadedFile($path, 'capture.zip', 'application/zip', null, true);
}

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('game-image-uploads');
    app(GameImageOrderService::class)->configure(100);
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->member = User::factory()->create();
    $this->wallet = Wallet::factory()->create(['user_id' => $this->member->id, 'balance' => 1000]);
    $this->account = app(GameImageOrderService::class)->create($this->member, ['username' => 'capture-user', 'password' => 'game-secret', 'server' => '1']);
    $this->claim = ['capture_version' => 1, 'claim_token' => (string) Str::uuid(), 'public_key' => toolRsaKeys()['public']];
    $this->withToken($this->admin->createToken('capture-tool')->plainTextToken);
});

test('pending API uses bearer auth paginates active owners and never leaks passwords', function (): void {
    GameImageAccount::factory()->count(21)->create(['user_id' => $this->member->id]);
    GameImageAccount::factory()->create(['status' => 'success']);
    $blocked = User::factory()->create(['status' => 'banned']);
    GameImageAccount::factory()->create(['user_id' => $blocked->id]);
    $this->getJson(route('api.game-image.pending'))->assertOk()->assertJsonPath('meta.total', 22)
        ->assertJsonPath('meta.per_page', 20)->assertJsonCount(20, 'data')->assertJsonMissingPath('data.0.password')
        ->assertJsonMissingPath('data.0.capture_claim_hash')->assertDontSee('game-secret');
    $this->getJson(route('api.game-image.pending', ['page' => 2]))->assertOk()->assertJsonCount(2, 'data');
    $this->getJson(route('api.game-image.pending', ['per_page' => 101]))->assertUnprocessable();
});

test('only active admins can access tool API endpoints', function (): void {
    foreach ([null, $this->member, User::factory()->create(['role' => 'admin', 'status' => 'banned'])] as $user) {
        auth()->forgetGuards();
        $this->withHeader('Authorization', $user ? 'Bearer '.$user->createToken('unauthorized')->plainTextToken : '');
        $response = $this->getJson(route('api.game-image.pending'));
        $user ? $response->assertForbidden() : $response->assertUnauthorized();
        $response = $this->postJson(route('api.game-image.claim', $this->account), $this->claim);
        $user ? $response->assertForbidden() : $response->assertUnauthorized();
    }
    expect($this->account->fresh()->status)->toBe('pending');
});

test('claim is exclusive idempotent and returns credentials only to the successful claimant', function (): void {
    $url = route('api.game-image.claim', $this->account);
    $first = $this->postJson($url, $this->claim)->assertOk()->assertJsonMissingPath('data.password')->assertJsonPath('data.status', 'processing')->assertDontSee('game-secret');
    expect(toolDecryptPassword($first->json('data.password_encrypted'), toolRsaKeys()['private']))->toBe('game-secret');
    $time = $this->account->fresh()->processing_at;
    $retry = $this->postJson($url, $this->claim)->assertOk()->assertJsonMissingPath('data.password');
    expect(toolDecryptPassword($retry->json('data.password_encrypted'), toolRsaKeys()['private']))->toBe('game-secret');
    $this->postJson($url, [...$this->claim, 'claim_token' => (string) Str::uuid()])->assertConflict();
    $this->postJson($url, [...$this->claim, 'capture_version' => 2])->assertConflict();
    $this->getJson(route('api.game-image.show', $this->account))->assertOk()->assertJsonMissingPath('data.password');
    $this->patchJson(route('api.game-image.status', $this->account), [...$this->claim, 'status' => 'processing'])->assertOk()->assertJsonMissingPath('data.password');
    $this->getJson(route('api.game-image.pending'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'processing')
        ->assertJsonMissingPath('data.0.password')->assertJsonMissingPath('data.0.password_encrypted');
    $this->getJson(route('api.game-image.pending', ['status' => 'pending']))->assertOk()->assertJsonCount(0, 'data');
    expect($this->account->fresh()->processing_at->equalTo($time))->toBeTrue()
        ->and($this->account->fresh()->capture_claim_hash)->toBe(hash('sha256', $this->claim['claim_token']))
        ->and($this->wallet->fresh()->balance)->toBe(900);
});

test('tool queue includes processing captures for resuming and filters out completed or failed captures', function (): void {
    GameImageAccount::factory()->create(['user_id' => $this->member->id, 'status' => 'processing', 'username' => 'resume-capture']);
    GameImageAccount::factory()->create(['user_id' => $this->member->id, 'status' => 'success', 'username' => 'completed-capture']);
    GameImageAccount::factory()->create(['user_id' => $this->member->id, 'status' => 'error', 'username' => 'failed-capture']);
    $this->getJson(route('api.game-image.pending'))->assertOk()->assertJsonPath('meta.total', 2)
        ->assertDontSee('completed-capture')->assertDontSee('failed-capture');
    $this->getJson(route('api.game-image.pending', ['status' => 'processing']))->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.username', 'resume-capture')->assertJsonPath('data.0.status', 'processing');
    $this->getJson(route('api.game-image.pending', ['status' => 'pending']))->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->account->id);
    $this->getJson(route('api.game-image.pending', ['status' => 'success']))->assertUnprocessable()->assertJsonValidationErrors('status');
    expect($this->wallet->fresh()->balance)->toBe(900);
});

test('ZIP upload extracts root or wrapped folders into public cName directory and completes the order', function (string $prefix): void {
    $this->postJson(route('api.game-image.claim', $this->account), $this->claim)->assertOk();
    $url = route('api.game-image.upload', $this->account);
    $payload = [...$this->claim, 'cName' => 'kztiger', 'package' => toolCaptureZip($prefix)];
    $response = $this->postJson($url, $payload)->assertOk()->assertJsonPath('data.status', 'success')->assertJsonMissingPath('data.password');
    $account = $this->account->fresh();
    expect($account->capture_disk)->toBe('game-image-uploads')->and($account->cname)->toBe('kztiger')
        ->and($account->capture_directory)->toStartWith('kztiger/account-'.$account->id.'-v1-')
        ->and($response->json('data.upload_directory'))->toBe('/uploads/ghep-anh/'.$account->capture_directory);
    Storage::disk('game-image-uploads')->assertExists($account->capture_directory.'/details/item-0085.png');
    expect(Storage::disk('local')->allFiles('game-image-uploads'))->toBeEmpty();
    $this->postJson($url, [...$payload, 'package' => toolCaptureZip()])->assertOk();
    expect($account->fresh()->capture_directory)->toBe($account->capture_directory)
        ->and(count(Storage::disk('game-image-uploads')->allFiles()))->toBe(11)
        ->and($this->wallet->fresh()->balance)->toBe(900);
    $this->patchJson(route('api.game-image.status', $account), [...$this->claim, 'status' => 'error', 'error_message' => 'Late error'])->assertConflict();
    $this->actingAs($this->member)->get(route('tools.game-image.source', ['account' => $account, 'path' => 'panel-quest.png']))->assertOk();
    $download = $this->get(route('tools.game-image.download-data', $account))->assertDownload();
    $zip = new PharData($download->baseResponse->getFile()->getPathname());
    expect(isset($zip['details/item-0085.png']))->toBeTrue()->and($zip['_account.json']->getContent())->not->toContain('game-secret');
})->with(['', 'kztiger/']);

test('tool errors refund once and recapture becomes pending for a new claim version', function (): void {
    $this->postJson(route('api.game-image.claim', $this->account), $this->claim)->assertOk();
    $error = [...$this->claim, 'status' => 'error', 'error_code' => 'LOGIN_FAILED', 'error_message' => 'Login failed'];
    $this->patchJson(route('api.game-image.status', $this->account), $error)->assertOk()->assertJsonPath('data.status', 'error')->assertJsonPath('data.error_code', 'LOGIN_FAILED');
    $this->patchJson(route('api.game-image.status', $this->account), $error)->assertOk();
    expect($this->wallet->fresh()->balance)->toBe(1000)->and(WalletTransaction::query()->where('direction', 'credit')->count())->toBe(1);
    app(GameImageOrderService::class)->recapture($this->account->fresh(), 1, 100);
    $this->getJson(route('api.game-image.pending'))->assertOk()->assertJsonPath('data.0.capture_version', 2)->assertJsonPath('data.0.status', 'pending');
    $this->patchJson(route('api.game-image.status', $this->account), $error)->assertConflict();
    $this->postJson(route('api.game-image.upload', $this->account), [...$this->claim, 'cName' => 'kztiger', 'package' => toolCaptureZip()])->assertConflict();
    $this->postJson(route('api.game-image.claim', $this->account), ['capture_version' => 2, 'claim_token' => (string) Str::uuid(), 'public_key' => toolRsaKeys()['public']])->assertOk();
    expect($this->wallet->fresh()->balance)->toBe(900);
});

test('worker can return a claimed capture to pending without charging or refunding', function (): void {
    $this->postJson(route('api.game-image.claim', $this->account), $this->claim)->assertOk();
    $this->patchJson(route('api.game-image.status', $this->account), [...$this->claim, 'status' => 'pending'])->assertOk()->assertJsonPath('data.status', 'pending');
    $this->postJson(route('api.game-image.upload', $this->account), [...$this->claim, 'cName' => 'kztiger', 'package' => toolCaptureZip()])->assertConflict();
    $this->postJson(route('api.game-image.claim', $this->account), [...$this->claim, 'claim_token' => (string) Str::uuid()])->assertOk();
    expect($this->wallet->fresh()->balance)->toBe(900)->and(WalletTransaction::query()->count())->toBe(1);
});

test('invalid claim names archives and direct success statuses cannot mutate an order', function (): void {
    $this->postJson(route('api.game-image.claim', $this->account), $this->claim)->assertOk();
    $url = route('api.game-image.upload', $this->account);
    $this->postJson($url, [...$this->claim, 'cName' => '../escape', 'package' => toolCaptureZip()])->assertUnprocessable();
    $this->postJson($url, [...$this->claim, 'claim_token' => (string) Str::uuid(), 'cName' => 'kztiger', 'package' => toolCaptureZip()])->assertConflict();
    $this->patchJson(route('api.game-image.status', $this->account), [...$this->claim, 'claim_token' => (string) Str::uuid(), 'status' => 'error', 'error_message' => 'Wrong worker'])->assertConflict();
    $this->postJson($url, [...$this->claim, 'cName' => 'kztiger', 'package' => toolCaptureZip('', true)])->assertUnprocessable();
    $this->patchJson(route('api.game-image.status', $this->account), [...$this->claim, 'status' => 'success'])->assertUnprocessable();
    $this->patchJson(route('api.game-image.status', $this->account), [...$this->claim, 'status' => 'error'])->assertUnprocessable();
    expect($this->account->fresh()->status)->toBe('processing')->and(Storage::disk('game-image-uploads')->allFiles())->toBeEmpty()
        ->and(Storage::disk('local')->allFiles('game-image-uploads'))->toBeEmpty();
});

test('recapture replaces assets across storage disks and deletes old sources and previews only after success', function (): void {
    $account = $this->account->fresh();
    $account->forceFill(['status' => 'success', 'capture_directory' => 'old-capture', 'capture_files' => ['panel-quest.png'], 'preview_path' => 'old-preview.png'])->save();
    Storage::disk('local')->put('old-capture/panel-quest.png', 'old image');
    Storage::disk('local')->put('old-preview.png', 'old preview');
    $orders = app(GameImageOrderService::class);
    $orders->recapture($account, 1, 100);
    $claim = ['capture_version' => 2, 'claim_token' => (string) Str::uuid(), 'public_key' => toolRsaKeys()['public']];
    $this->postJson(route('api.game-image.claim', $account), $claim)->assertOk();
    Storage::disk('local')->assertExists('old-capture/panel-quest.png');
    $this->postJson(route('api.game-image.upload', $account), [...$claim, 'cName' => 'kztiger', 'package' => toolCaptureZip()])->assertOk();
    Storage::disk('local')->assertMissing('old-capture/panel-quest.png');
    Storage::disk('local')->assertMissing('old-preview.png');
    $old = $account->fresh()->capture_directory;
    $orders->recapture($account->fresh(), 2, 100);
    $claim = ['capture_version' => 3, 'claim_token' => (string) Str::uuid(), 'public_key' => toolRsaKeys()['public']];
    $this->postJson(route('api.game-image.claim', $account), $claim)->assertOk();
    $this->postJson(route('api.game-image.upload', $account), [...$claim, 'cName' => 'kztiger', 'package' => toolCaptureZip()])->assertOk();
    Storage::disk('game-image-uploads')->assertMissing($old.'/panel-quest.png');
    Storage::disk('game-image-uploads')->assertExists($account->fresh()->capture_directory.'/panel-quest.png');
});

test('tool accepts the full kztiger capture ZIP including all 85 item detail images', function (): void {
    $path = Storage::disk('local')->path('full-kztiger.zip');
    $zip = new PharData($path, 0, null, Phar::ZIP);
    $files = File::allFiles(public_path('images/demo/capture/kztiger'));
    foreach ($files as $file) {
        $zip->addFile($file->getPathname(), 'kztiger/'.str_replace('\\', '/', $file->getRelativePathname()));
    }
    unset($zip);
    $this->postJson(route('api.game-image.claim', $this->account), $this->claim)->assertOk();
    $this->postJson(route('api.game-image.upload', $this->account), [...$this->claim, 'cName' => 'kztiger',
        'package' => new UploadedFile($path, 'kztiger.zip', 'application/zip', null, true)])->assertOk()->assertJsonPath('data.status', 'success');
    $account = $this->account->fresh();
    expect($account->capture_files)->toHaveCount(count($files));
    foreach ([47, 68, 69, 85] as $id) {
        Storage::disk('game-image-uploads')->assertExists($account->capture_directory.sprintf('/details/item-%04d.png', $id));
    }
});

test('claim requires a valid strong public key before claiming the account', function (string $case): void {
    $payload = $this->claim;
    if ($case === 'missing') {
        unset($payload['public_key']);
    } else {
        $payload['public_key'] = match ($case) {
            'weak' => toolRsaKeys(1, 1024)['public'],
            'private' => toolRsaKeys()['private'],
            'oversize' => str_repeat('x', 4097),
            default => 'not-a-public-key',
        };
    }
    $this->postJson(route('api.game-image.claim', $this->account), $payload)->assertUnprocessable()->assertJsonValidationErrors('public_key');
    expect($this->account->fresh()->status)->toBe('pending')->and($this->account->fresh()->capture_claim_hash)->toBeNull()
        ->and($this->account->fresh()->capture_public_key)->toBeNull()->and($this->wallet->fresh()->balance)->toBe(900);
})->with(['missing', 'invalid', 'weak', 'private', 'oversize']);

test('encrypted password is randomized decryptable only by the tool and rejects tampered payloads', function (): void {
    $password = str_repeat('密', 100);
    $this->account->update(['password' => $password]);
    $url = route('api.game-image.claim', $this->account);
    $response = $this->postJson($url, $this->claim)->assertOk()->assertJsonMissingPath('data.password')->assertDontSee($password);
    $envelope = $response->json('data.password_encrypted');
    expect($envelope['key_algorithm'])->toBe('RSA-OAEP')->and($envelope['oaep_hash'])->toBe('SHA-1')
        ->and($envelope['content_algorithm'])->toBe('A256GCM')->and(strlen(base64_decode($envelope['iv'], true)))->toBe(12)
        ->and(strlen(base64_decode($envelope['tag'], true)))->toBe(16)
        ->and(toolDecryptPassword($envelope, toolRsaKeys()['private']))->toBe($password)
        ->and(toolDecryptPassword($envelope, toolRsaKeys(2)['private']))->toBeFalse();
    foreach (['ciphertext', 'tag', 'aad', 'encrypted_key'] as $field) {
        $tampered = $envelope;
        $binary = base64_decode($tampered[$field], true);
        $binary[0] = $binary[0] ^ chr(1);
        $tampered[$field] = base64_encode($binary);
        expect(toolDecryptPassword($tampered, toolRsaKeys()['private']))->toBeFalse();
    }
    $retry = $this->postJson($url, $this->claim)->assertOk()->json('data.password_encrypted');
    expect($retry['ciphertext'])->not->toBe($envelope['ciphertext'])
        ->and(base64_decode($envelope['aad'], true))->toBe('game-image|'.$this->account->id.'|1|'.hash('sha256', $this->claim['claim_token']));
    $this->getJson(route('api.game-image.show', $this->account))->assertOk()->assertJsonMissingPath('data.password_encrypted')->assertJsonMissingPath('data.password');
});

test('claim pins its public key while accepting equivalent PEM formatting', function (): void {
    $url = route('api.game-image.claim', $this->account);
    $first = $this->postJson($url, $this->claim)->assertOk()->json('data.password_encrypted.key_id');
    $this->postJson($url, [...$this->claim, 'public_key' => toolRsaKeys(2)['public']])->assertConflict();
    $this->postJson($url, [...$this->claim, 'public_key' => str_replace("\n", "\r\n", $this->claim['public_key'])])->assertOk()->assertJsonPath('data.password_encrypted.key_id', $first);
    expect($this->account->fresh()->capture_public_key)->toBe(toolRsaKeys()['public']);
});

test('credential encryption failure rolls back the claim without exposing a password', function (): void {
    $this->partialMock(GameImageCredentialService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('encrypt')->once()->andThrow(new RuntimeException('Credential encryption failed.'));
    });
    $this->postJson(route('api.game-image.claim', $this->account), $this->claim)->assertServerError()->assertDontSee('game-secret');
    expect($this->account->fresh()->status)->toBe('pending')->and($this->account->fresh()->capture_claim_hash)->toBeNull()
        ->and($this->account->fresh()->capture_public_key)->toBeNull();
});

test('private tool polling continues beyond the previous minute limit', function (): void {
    for ($attempt = 0; $attempt < 130; $attempt++) {
        $this->getJson(route('api.game-image.pending'))->assertOk();
    }
});
