<?php

use App\Features\Admin\License\Services\ProductReleaseService;
use App\Models\License;
use App\Models\LicenseProduct;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
});

function productReleaseZip(string $name = 'tool.zip'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, base64_decode('UEsDBBQAAAAAAAJlWF1ijVP/DAAAAAwAAAAIAAAAdG9vbC50eHR0b29sIHJlbGVhc2VQSwECFAAUAAAAAAACZVhdYo1T/wwAAAAMAAAACAAAAAAAAAAAAAAAgAEAAAAAdG9vbC50eHRQSwUGAAAAAAEAAQA2AAAAMgAAAAAA'));
}

/** @return array<string, mixed> */
function productReleaseData(array $extra = []): array
{
    return ['name' => 'Release tool', 'product_code' => 'RELEASE_TOOL', 'description' => null,
        'minimum_version' => '1.0.0', 'latest_version' => '1.2.0', 'is_active' => true,
        'heartbeat_interval' => 20, 'lease_duration' => 60, 'transfer_cooldown' => 1800,
        'max_active_devices' => 1, 'offline_grace' => 0, ...$extra];
}

test('admin uploads private tool and update ZIP files with editable latest version', function (): void {
    $response = $this->actingAs($this->admin)->post('/api/admin-api/license/products', productReleaseData([
        'tool_zip' => productReleaseZip(), 'update_zip' => productReleaseZip('update.zip'),
    ]), ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.latest_version', '1.2.0')
        ->assertJsonPath('data.tool_zip_name', 'tool.zip')->assertJsonPath('data.update_zip_name', 'update.zip')
        ->assertJsonMissingPath('data.tool_zip_path')->assertJsonMissingPath('data.update_zip_path');
    $product = LicenseProduct::findOrFail($response->json('data.id'));
    Storage::disk('local')->assertExists([$product->tool_zip_path, $product->update_zip_path]);
    expect($product->tool_zip_sha256)->toBe(hash('sha256', Storage::disk('local')->get($product->tool_zip_path)));

    $this->getJson('/api/admin-api/license/products')->assertOk()->assertJsonMissingPath('data.0.tool_zip_path');
});

test('multipart method override replaces only the selected ZIP and preserves the other file', function (): void {
    $product = LicenseProduct::factory()->create();
    $url = '/api/admin-api/license/products/'.$product->id;
    $this->actingAs($this->admin)->post($url, productReleaseData([
        '_method' => 'PATCH', 'tool_zip' => productReleaseZip(), 'update_zip' => productReleaseZip('update.zip'),
    ]), ['Accept' => 'application/json'])->assertOk();
    $oldTool = $product->refresh()->tool_zip_path;
    $oldUpdate = $product->update_zip_path;

    $this->post($url, productReleaseData([
        '_method' => 'PATCH', 'latest_version' => '1.3.0', 'update_zip' => productReleaseZip('update-1.3.zip'),
    ]), ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.latest_version', '1.3.0');
    expect($product->refresh()->tool_zip_path)->toBe($oldTool)
        ->and($product->update_zip_path)->not->toBe($oldUpdate);
    Storage::disk('local')->assertExists([$oldTool, $product->update_zip_path]);
    Storage::disk('local')->assertMissing($oldUpdate);

    $newUpdate = $product->update_zip_path;
    $this->patchJson($url, productReleaseData(['latest_version' => '1.3.1']))->assertOk();
    expect($product->refresh()->update_zip_path)->toBe($newUpdate);
    $this->patchJson($url, productReleaseData(['latest_version' => null]))->assertUnprocessable()->assertJsonValidationErrors('latest_version');
});

test('upload validates real ZIP content extension size and version', function (string $invalid): void {
    $data = productReleaseData(['tool_zip' => productReleaseZip()]);
    $field = 'tool_zip';
    if ($invalid === 'content') {
        $fake = UploadedFile::fake()->createWithContent('fake.zip', 'plain text');
        $data['tool_zip'] = new UploadedFile($fake->getPathname(), 'fake.zip', null, null, true);
    } elseif ($invalid === 'extension') {
        $data['tool_zip'] = productReleaseZip('tool.exe');
    } elseif ($invalid === 'size') {
        $data['tool_zip'] = UploadedFile::fake()->create('tool.zip', 102401, 'application/zip');
    } else {
        $field = 'latest_version';
        $data['latest_version'] = $invalid === 'version' ? 'latest' : null;
    }
    $this->actingAs($this->admin)->post('/api/admin-api/license/products', $data, ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('license_products', 0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with(['content', 'extension', 'size', 'version', 'missing-version']);

test('non admin cannot upload product files', function (): void {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_USER]))->post('/api/admin-api/license/products', productReleaseData([
        'tool_zip' => productReleaseZip(),
    ]), ['Accept' => 'application/json'])->assertForbidden();
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('failed database save cleans new uploads and preserves the existing archive', function (): void {
    $service = app(ProductReleaseService::class);
    $product = $service->save(productReleaseData(['tool_zip' => productReleaseZip()]));
    expect(fn () => $service->save(productReleaseData(['tool_zip' => productReleaseZip('new.zip')])))
        ->toThrow(QueryException::class);
    expect(Storage::disk('local')->allFiles())->toBe([$product->tool_zip_path]);
    $this->assertDatabaseCount('license_products', 1);
});

test('deleting an unused product cleans its private ZIP files', function (): void {
    $product = app(ProductReleaseService::class)->save(productReleaseData([
        'tool_zip' => productReleaseZip(), 'update_zip' => productReleaseZip('update.zip'),
    ]));
    $this->actingAs($this->admin)->deleteJson('/api/admin-api/license/products/'.$product->id)->assertOk();
    Storage::disk('local')->assertMissing([$product->tool_zip_path, $product->update_zip_path]);
});

test('license owner can download both archives and sees the latest version in dashboard', function (): void {
    $owner = User::factory()->create();
    $license = License::factory()->create(['user_id' => $owner->id]);
    Storage::disk('local')->put('license-products/tool.zip', 'tool contents');
    Storage::disk('local')->put('license-products/update.zip', 'update contents');
    $license->product->forceFill(['latest_version' => '2.1.0', 'tool_zip_path' => 'license-products/tool.zip', 'update_zip_path' => 'license-products/update.zip'])->save();

    $this->actingAs($owner)->get(route('account.profile.dashboard'))->assertOk()->assertSee('2.1.0')
        ->assertSee('Tải tool ZIP')->assertSee('Tải ZIP cập nhật')->assertDontSee('license-products/tool.zip');
    foreach (['tool', 'update'] as $kind) {
        $this->get(route('account.profile.licenses.download', [$license, $kind]))->assertOk()
            ->assertDownload($license->product->product_code.'-'.$kind.'-2.1.0.zip')
            ->assertHeader('Cache-Control', 'no-store, private')->assertStreamedContent($kind.' contents');
    }
});

test('downloads enforce license ownership status expiry product availability and authentication', function (string $reason): void {
    $owner = User::factory()->create();
    $license = License::factory()->create(['user_id' => $owner->id]);
    $license->product->forceFill(['latest_version' => '1.0.0', 'tool_zip_path' => 'license-products/tool.zip'])->save();
    Storage::disk('local')->put('license-products/tool.zip', 'contents');
    $url = route('account.profile.licenses.download', [$license, 'tool']);
    if ($reason === 'guest') {
        $this->get($url)->assertRedirect(route('login'));

        return;
    }
    if (in_array($reason, ['revoked', 'suspended', 'expired'], true)) {
        $license->update(['status' => $reason]);
    } elseif ($reason === 'past-expiry') {
        $license->update(['expires_at' => now()->subSecond()]);
    } elseif ($reason === 'disabled') {
        $license->product->update(['is_active' => false]);
    } else {
        $owner = User::factory()->create(['role' => $reason === 'foreign-admin' ? User::ROLE_ADMIN : User::ROLE_USER]);
    }
    $this->actingAs($owner)->get($url)->assertForbidden();
})->with(['guest', 'foreign-user', 'foreign-admin', 'revoked', 'suspended', 'expired', 'past-expiry', 'disabled']);

test('missing archives and unsupported download kinds return not found', function (): void {
    $owner = User::factory()->create();
    $license = License::factory()->create(['user_id' => $owner->id]);
    $license->product->forceFill(['tool_zip_path' => 'missing.zip'])->save();
    $this->actingAs($owner)->get(route('account.profile.licenses.download', [$license, 'tool']))->assertNotFound();
    $this->get(route('account.profile.licenses.download', [$license, 'update']))->assertNotFound();
    $this->get(route('account.profile.licenses.download', [$license, 'other']))->assertNotFound();
});
