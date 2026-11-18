<?php

use App\Features\Client\GameImage\Services\GameImageAssetService;
use App\Models\GameImageAccount;
use App\Models\ManagedServer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function accountCapturePng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aJXkAAAAASUVORK5CYII=');
}

function accountCapturePayload(): array
{
    $paths = [...GameImageAssetService::PANELS, 'details/item-0001.png', 'metadata.json'];

    return ['paths' => $paths, 'files' => array_map(fn (string $path): UploadedFile => UploadedFile::fake()->createWithContent(basename($path),
        str_ends_with($path, '.png') ? accountCapturePng() : '{"cname":"Captured character","items":[1]}'), $paths)];
}

beforeEach(function (): void {
    Storage::fake('local');
});

test('capture form and admin options use only active game servers and reject other server types', function (): void {
    $server = ManagedServer::query()->create(['type' => 'game', 'server_id' => '731', 'name' => 'Shared capture server', 'is_active' => true, 'sort_order' => 0]);
    ManagedServer::query()->create(['type' => 'game', 'server_id' => '732', 'name' => 'Inactive capture server', 'is_active' => false, 'sort_order' => 0]);
    ManagedServer::factory()->create(['type' => 'web', 'server_id' => '733', 'name' => 'Website only server']);
    ManagedServer::factory()->create(['type' => 'napcarot', 'server_id' => '734', 'name' => 'Carot only server']);
    $response = $this->actingAs(User::factory()->create())->get(route('tools.game-image'))->assertOk()
        ->assertViewHas('servers', fn ($servers): bool => $servers->pluck('id')->all() === ManagedServer::activeOptions('game')->pluck('id')->all());
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//select[@id="capture-server" and @name="server"]')->length)->toBe(1)
        ->and($xpath->query('//select[@id="capture-server"]/option[@value="731"]')->item(0)->textContent)->toContain($server->name)
        ->and($xpath->query('//select[@id="capture-server"]/option[@value="732"]')->length)->toBe(0)
        ->and($xpath->query('//input[@id="capture-server"]')->length)->toBe(0);
    $this->postJson(route('tools.game-image.store'), ['username' => 'new-user', 'password' => 'secret', 'server' => '733'])->assertUnprocessable();
    $this->postJson(route('tools.game-image.store'), ['username' => 'new-user', 'password' => 'secret', 'server' => '734'])->assertUnprocessable();
    $this->postJson(route('tools.game-image.store'), ['username' => 'new-user', 'password' => 'secret', 'server' => '732'])->assertUnprocessable();
    $this->assertDatabaseCount('game_image_accounts', 0);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson(route('admin.game-image.history'))->assertOk()
        ->assertJsonPath('servers.0.code', '731')->assertJsonCount(ManagedServer::activeOptions('game')->count(), 'servers');
});

test('account table masks usernames and paginates only the owners accounts with stable row numbers', function (): void {
    $user = User::factory()->create();
    GameImageAccount::factory()->count(21)->create(['user_id' => $user->id, 'server' => 'Server A']);
    $account = GameImageAccount::factory()->create(['user_id' => $user->id, 'username' => 'abc-secret-xyz']);
    GameImageAccount::factory()->create(['username' => 'other-owner-private']);
    $html = $this->actingAs($user)->get(route('tools.game-image'))->assertOk()
        ->assertViewHas('accounts', fn ($accounts): bool => $accounts->total() === 22 && $accounts->perPage() === 20)
        ->assertSee('data-game-image-account-table', false)->assertSee('abc***xyz')
        ->assertDontSee('other-owner-private')->getContent();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//table[@data-game-image-account-table]//thead//th'))->toHaveCount(6)
        ->and($xpath->query('//tbody/tr'))->toHaveCount(20)
        ->and($xpath->query('//*[@data-username="abc-secret-xyz"]//*[@data-game-image-username-value]')->item(0)->textContent)->toBe('abc***xyz');
    $this->get(route('tools.game-image', ['page' => 2]))->assertOk()->assertSee('>21</td>', false);
});

test('account table filters username server and saved image status without exposing another owner', function (): void {
    $user = User::factory()->create();
    GameImageAccount::factory()->create(['user_id' => $user->id, 'username' => 'saved-account', 'server' => 'Server A', 'preview_path' => 'saved.png', 'status' => 'success']);
    GameImageAccount::factory()->create(['user_id' => $user->id, 'username' => 'pending-account', 'server' => 'Server B']);
    GameImageAccount::factory()->create(['username' => 'saved-stranger', 'preview_path' => 'other.png', 'status' => 'success']);
    $this->actingAs($user)->get(route('tools.game-image', ['q' => 'Server A', 'status' => 'success']))->assertOk()
        ->assertViewHas('accounts', fn ($accounts): bool => $accounts->count() === 1 && $accounts->first()->username === 'saved-account')
        ->assertSee('data-client-image-viewer', false)->assertSee('Tải ảnh')->assertDontSee('pending-account')->assertDontSee('saved-stranger');
    $this->get(route('tools.game-image', ['status' => 'pending']))->assertOk()
        ->assertViewHas('accounts', fn ($accounts): bool => $accounts->count() === 1 && $accounts->first()->username === 'pending-account');
});

test('saving composition stores its private full size image and supports inline preview and download', function (): void {
    $account = GameImageAccount::factory()->create();
    $this->actingAs(User::findOrFail($account->user_id));
    $payload = ['layout' => ['item_ids' => [], 'show_details' => true, 'positions' => []], 'preview' => 'data:image/png;base64,'.base64_encode(accountCapturePng())];
    $this->patchJson(route('tools.game-image.update', $account), $payload)->assertOk()->assertJsonPath('data.preview_url', route('tools.game-image.preview', $account));
    $path = $account->fresh()->preview_path;
    Storage::disk('local')->assertExists($path);
    expect(Storage::disk('local')->get($path))->toBe(accountCapturePng());
    $this->get(route('tools.game-image.preview', $account))->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->get(route('tools.game-image.preview', ['account' => $account, 'download' => 1]))->assertDownload('nro-account-'.$account->id.'.png');
    $this->patchJson(route('tools.game-image.update', $account), $payload)->assertOk();
    Storage::disk('local')->assertMissing($path);
    $this->get(route('tools.game-image'))->assertOk()->assertSee('Tải ảnh')->assertSee('Chờ ghép');
});

test('capture upload retains all panels detail and metadata and downloads them together as a ZIP', function (): void {
    $account = GameImageAccount::factory()->create();
    $this->actingAs(User::findOrFail($account->user_id))->post(route('tools.game-image.capture', $account), accountCapturePayload(), ['Accept' => 'application/json'])
        ->assertOk()->assertJsonPath('data.data_download_url', route('tools.game-image.download-data', $account));
    $account->refresh();
    expect($account->capture_files)->toHaveCount(10);
    expect($account->cname)->toBe('Captured character');
    $this->get(route('tools.game-image'))->assertOk()->assertSee('Captured character &#8226;', false);
    $response = $this->get(route('tools.game-image.download-data', $account))->assertOk()->assertDownload('nro-account-'.$account->id.'-data.zip');
    $zip = new PharData($response->baseResponse->getFile()->getPathname());
    foreach ($account->capture_files as $path) {
        expect(isset($zip[$path]))->toBeTrue();
    }
    expect($zip['details/item-0001.png']->getContent())->toBe(accountCapturePng())
        ->and($zip['metadata.json']->getContent())->toContain('Captured character')
        ->and($zip['_account.json']->getContent())->toContain($account->username)->not->toContain($account->password);
    $this->get(route('tools.game-image.source', ['account' => $account, 'path' => 'details/item-0001.png']))->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->get(route('tools.game-image.edit', $account))->assertOk()->assertViewHas('account', fn (array $data): bool => count($data['capture_urls']) === 9);
});

test('successful account subtitles show escaped character name and server only after completion', function (): void {
    $user = User::factory()->create();
    foreach (['pending', 'processing', 'error', 'success'] as $status) {
        GameImageAccount::factory()->create(['user_id' => $user->id, 'status' => $status, 'cname' => $status.'-character', 'server' => 'Server A']);
    }
    GameImageAccount::factory()->create(['user_id' => $user->id, 'status' => 'success', 'cname' => '<b>Hero</b>', 'server' => 'Server <1>']);
    GameImageAccount::factory()->create(['user_id' => $user->id, 'status' => 'success', 'layout' => ['document' => ['cname' => 'Legacy hero']]]);
    $this->actingAs($user)->get(route('tools.game-image'))->assertOk()
        ->assertSee('success-character &#8226; Server A', false)
        ->assertSee('&lt;b&gt;Hero&lt;/b&gt; &#8226; Server &lt;1&gt;', false)
        ->assertSee('Legacy hero &#8226;', false)
        ->assertDontSee('pending-character')->assertDontSee('processing-character')->assertDontSee('error-character');
});

test('capture character name input takes priority over metadata and validates its length', function (): void {
    $account = GameImageAccount::factory()->create();
    $this->actingAs(User::findOrFail($account->user_id));
    $this->postJson(route('tools.game-image.capture', $account), [...accountCapturePayload(), 'cName' => str_repeat('x', 101)])
        ->assertUnprocessable()->assertJsonValidationErrors('cName');
    $this->postJson(route('tools.game-image.capture', $account), [...accountCapturePayload(), 'cName' => 'Tên nhân vật'])
        ->assertOk()->assertJsonPath('data.cName', 'Tên nhân vật');
    expect($account->fresh()->cname)->toBe('Tên nhân vật');
    $this->postJson(route('tools.game-image.capture', $account), [...accountCapturePayload(), 'cName' => 'Duplicate callback'])
        ->assertOk()->assertJsonPath('data.cName', 'Tên nhân vật');
});

test('capture reads cName metadata safely without requiring optional character information', function (string $metadata, ?string $expected): void {
    $account = GameImageAccount::factory()->create();
    $payload = accountCapturePayload();
    $payload['files'][9] = UploadedFile::fake()->createWithContent('metadata.json', $metadata);
    $this->actingAs(User::findOrFail($account->user_id))->postJson(route('tools.game-image.capture', $account), $payload)->assertOk();
    expect($account->fresh()->cname)->toBe($expected);
    $this->get(route('tools.game-image'))->assertOk();
})->with([
    'camel case field' => ['{"cName":"  Son Goku  "}', 'Son Goku'],
    'invalid name type' => ['{"cName":{"nested":"value"}}', null],
    'invalid JSON' => ['not-json', null],
    'oversize metadata name' => [json_encode(['cName' => str_repeat('x', 101)]), null],
]);

test('only the active owner can read or upload private account images and source archives', function (): void {
    $account = GameImageAccount::factory()->create();
    foreach (['tools.game-image.preview', 'tools.game-image.download-data'] as $routeName) {
        $this->getJson(route($routeName, $account))->assertUnauthorized();
    }
    $this->actingAs(User::factory()->create())->get(route('tools.game-image.preview', $account))->assertNotFound();
    $this->get(route('tools.game-image.download-data', $account))->assertNotFound();
    $this->post(route('tools.game-image.capture', $account), accountCapturePayload(), ['Accept' => 'application/json'])->assertForbidden();
    $this->get(route('tools.game-image.source', ['account' => $account, 'path' => 'panel-quest.png']))->assertNotFound();
    $owner = User::findOrFail($account->user_id);
    $owner->update(['status' => 'banned']);
    $this->actingAs($owner)->get(route('tools.game-image.preview', $account))->assertNotFound();
});

test('missing images and data return 404 and invalid previews are rejected before saving', function (): void {
    $account = GameImageAccount::factory()->create();
    $this->actingAs(User::findOrFail($account->user_id));
    $this->get(route('tools.game-image.preview', $account))->assertNotFound();
    $this->get(route('tools.game-image.download-data', $account))->assertNotFound();
    $this->patchJson(route('tools.game-image.update', $account), ['layout' => ['item_ids' => [], 'show_details' => true, 'positions' => []], 'preview' => 'data:image/png;base64,'.base64_encode('<script>bad</script>')])
        ->assertUnprocessable()->assertJsonValidationErrors('preview');
    expect($account->fresh()->layout)->toBeNull()->and($account->fresh()->preview_path)->toBeNull();
});

test('capture uploads reject traversal duplicate paths missing panels and disguised images', function (string $invalid): void {
    $account = GameImageAccount::factory()->create();
    $payload = accountCapturePayload();
    if ($invalid === 'traversal') {
        $payload['paths'][0] = '../escape.png';
    } elseif ($invalid === 'duplicate') {
        $payload['paths'][1] = $payload['paths'][0];
    } elseif ($invalid === 'panel') {
        $payload['paths'][0] = 'missing-panel.png';
    } else {
        $payload['files'][0] = UploadedFile::fake()->createWithContent('panel-quest.png', 'This is not a PNG');
    }
    $this->actingAs(User::findOrFail($account->user_id))->post(route('tools.game-image.capture', $account), $payload, ['Accept' => 'application/json'])->assertUnprocessable();
    expect($account->fresh()->capture_directory)->toBeNull();
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
})->with(['traversal', 'duplicate', 'panel', 'image']);

test('captured images in shared snapshots require valid signatures and revoked shares lose access', function (): void {
    $account = GameImageAccount::factory()->create(['layout' => ['item_ids' => [], 'show_details' => true, 'positions' => []]]);
    $this->actingAs(User::findOrFail($account->user_id))->post(route('tools.game-image.capture', $account), accountCapturePayload(), ['Accept' => 'application/json'])->assertOk();
    $url = $this->postJson(route('tools.game-image.share', $account))->assertOk()->json('url');
    auth()->forgetGuards();
    $response = $this->get($url)->assertOk();
    $assetUrl = $response->viewData('account')['capture_urls']['panel-quest.png'];
    $this->get($assetUrl)->assertOk();
    $this->get(strtok($assetUrl, '?'))->assertForbidden();
    $this->actingAs(User::findOrFail($account->user_id))->deleteJson(route('tools.game-image.share.revoke', $account))->assertOk();
    $this->get($assetUrl)->assertNotFound();
});

test('invalid account table filters are rejected', function (): void {
    $this->actingAs(User::factory()->create())->getJson(route('tools.game-image', ['q' => ['invalid']]))->assertUnprocessable();
    $this->getJson(route('tools.game-image', ['status' => 'invalid']))->assertUnprocessable();
    $this->getJson(route('tools.game-image', ['page' => -1]))->assertUnprocessable();
});

test('a single uploaded ZIP retains more files than the PHP multipart upload limit', function (): void {
    $account = GameImageAccount::factory()->create();
    $disk = Storage::disk('local');
    $archive = new PharData($disk->path('capture.zip'), 0, null, Phar::ZIP);
    foreach (GameImageAssetService::PANELS as $file) {
        $archive->addFromString($file, accountCapturePng());
    }
    foreach (range(1, 30) as $index) {
        $archive->addFromString(sprintf('details/item-%04d.png', $index), accountCapturePng());
    }
    unset($archive);
    $upload = new UploadedFile($disk->path('capture.zip'), 'capture.zip', 'application/zip', null, true);
    $this->actingAs(User::findOrFail($account->user_id))->post(route('tools.game-image.capture', $account), ['package' => $upload], ['Accept' => 'application/json'])->assertOk();
    expect($account->fresh()->capture_files)->toHaveCount(38);
    $this->get(route('tools.game-image.download-data', $account))->assertDownload();
    expect($disk->allFiles('game-image-uploads'))->toBeEmpty();
});

test('a ZIP with executable files never changes the captured account', function (): void {
    $account = GameImageAccount::factory()->create();
    $disk = Storage::disk('local');
    $archive = new PharData($disk->path('bad.zip'), 0, null, Phar::ZIP);
    $archive->addFromString('execute.php', '<?php echo "bad";');
    unset($archive);
    $upload = new UploadedFile($disk->path('bad.zip'), 'bad.zip', 'application/zip', null, true);
    $this->actingAs(User::findOrFail($account->user_id))->post(route('tools.game-image.capture', $account), ['package' => $upload], ['Accept' => 'application/json'])->assertUnprocessable();
    expect($account->fresh()->capture_directory)->toBeNull()->and($disk->allFiles('game-image-uploads'))->toBeEmpty();
});
