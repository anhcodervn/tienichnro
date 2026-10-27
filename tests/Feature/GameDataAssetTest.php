<?php

use App\Features\Admin\Upload\Services\ImageUploadService;
use App\Features\Admin\Upload\Services\RemoteImageUploadService;
use App\Features\NroGameData\Services\GameDataAssetService;
use App\Features\NroGameData\Services\GameDataCatalogService;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function gameAssetPngUpload(int $width = 16, int $height = 20): UploadedFile
{
    $chunk = fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    $bytes = "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0))
        .$chunk('IDAT', gzcompress(str_repeat("\0".str_repeat("\xff\0\0\0", $width), $height)))
        .$chunk('IEND', '');

    return UploadedFile::fake()->createWithContent('asset.png', $bytes);
}

beforeEach(function (): void {
    Storage::fake('public');
});

test('game assets overwrite a stable identity without watermark resize or duplicate files', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $first = $this->post('/api/uploads/image', [
        'image' => gameAssetPngUpload(30, 40), 'category' => 'items', 'icon_id' => 0,
    ])->assertOk()->assertJsonPath('data.path', 'upload/data-game/items/0.png');
    $second = $this->postJson('/api/uploads/image', [
        'base64' => base64_encode(gameAssetPngUpload(50, 60)->getContent()),
        'category' => 'items', 'icon_id' => 0,
    ])->assertOk()->assertJsonPath('data.path', 'upload/data-game/items/0.png');
    $disk = Storage::disk('public');
    expect($disk->allFiles())->toHaveCount(1)
        ->and($first->json('data.url'))->not->toBe($second->json('data.url'));
    $bytes = $disk->get('upload/data-game/items/0.png');
    expect(getimagesizefromstring($bytes)[0])->toBe(50)
        ->and(getimagesizefromstring($bytes)[1])->toBe(60)
        ->and($bytes)->toBe(gameAssetPngUpload(50, 60)->getContent());
});

test('game upload requires an allowed category paired with a bounded numeric ID', function (array $identity): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->postJson('/api/uploads/image', [...$identity,
            'base64' => base64_encode(gameAssetPngUpload()->getContent()),
        ])->assertUnprocessable();
    expect(Storage::disk('public')->allFiles())->toBe([]);
})->with([
    [['category' => '../items', 'icon_id' => 1]], [['category' => 'items']], [['icon_id' => 1]],
    [['category' => 'heads', 'icon_id' => -1]], [['category' => 'maps', 'icon_id' => 4294967296]],
    [['category' => 'maps', 'icon_id' => 'file.php']],
    [['variant' => 'image']],
    [['category' => 'maps', 'icon_id' => 7, 'variant' => '../icon']],
    [['category' => 'maps', 'icon_id' => 7, 'variant' => ['image']]],
]);

test('image and icon variants coexist and overwriting one preserves the other', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $icon = $this->post('/api/uploads/image', [
        'image' => gameAssetPngUpload(16, 20), 'category' => 'mobs', 'icon_id' => 7, 'variant' => 'icon',
    ])->assertOk()->assertJsonPath('data.path', 'upload/data-game/mobs/icon/7.png')->assertJsonPath('data.variant', 'icon');
    $image = $this->postJson('/api/uploads/image', [
        'base64' => base64_encode(gameAssetPngUpload(100, 120)->getContent()),
        'category' => 'mobs', 'icon_id' => 7, 'variant' => 'image',
    ])->assertOk()->assertJsonPath('data.path', 'upload/data-game/mobs/image/7.png');
    $replacement = $this->postJson('/api/uploads/image', [
        'base64' => base64_encode(gameAssetPngUpload(32, 40)->getContent()),
        'category' => 'mobs', 'icon_id' => 7, 'variant' => 'icon',
    ])->assertOk()->assertJsonPath('data.path', $icon->json('data.path'));
    $disk = Storage::disk('public');
    expect($disk->allFiles())->toHaveCount(2)
        ->and($replacement->json('data.url'))->not->toBe($icon->json('data.url'))
        ->and($disk->get($image->json('data.path')))->toBe(gameAssetPngUpload(100, 120)->getContent());
});

test('cards prefer icons while details prefer full image variants with an icon fallback', function (): void {
    $catalog = app(GameDataCatalogService::class);
    $assets = app(GameDataAssetService::class);
    $map = $catalog->query('maps')->create($catalog->normalize('maps', [['mapId' => 7, 'mapName' => 'Map']])[0]);
    $assets->store(gameAssetPngUpload(), 'maps', 7);
    $icon = $assets->store(gameAssetPngUpload(16, 20), 'maps', 7, 'icon');
    $image = $assets->store(gameAssetPngUpload(100, 120), 'maps', 7, 'image');
    $this->get('/data-game?type=maps')->assertOk()
        ->assertViewHas('cards', fn ($cards): bool => $cards->first()['media'] === $icon['url'] && $cards->first()['mediaFallback'] === $image['url']);
    $this->getJson('/data-game/maps/'.$map->id)->assertOk()
        ->assertJsonPath('preview.image', $image['url'])->assertJsonPath('preview.fallback', $icon['url']);
    $this->get('/data-game/maps/'.$map->id)->assertOk()->assertSee('src="'.$image['url'].'"', false);
    Storage::disk('public')->delete($image['path']);
    $this->getJson('/data-game/maps/'.$map->id)->assertOk()->assertJsonPath('preview.image', $icon['url']);
    Storage::disk('public')->delete($icon['path']);
    expect($assets->forRecord('maps', $map))->toBe($assets->find('maps', 7));
});

test('NPC composition resolves full part images uploaded with variants', function (): void {
    $catalog = app(GameDataCatalogService::class);
    $npc = $catalog->query('npcs')->create($catalog->normalize('npcs', [[
        'npcTemplateId' => 0, 'name' => 'NPC', 'headId' => 1, 'bodyId' => 2, 'legId' => 3, 'menu' => [],
    ]])[0]);
    $assets = app(GameDataAssetService::class);
    foreach (['heads' => 1, 'bodies' => 2, 'legs' => 3] as $category => $id) {
        $assets->store(gameAssetPngUpload(), $category, $id, 'icon');
        $assets->store(gameAssetPngUpload(30, 40), $category, $id, 'image');
    }
    $response = $this->getJson('/data-game/npcs/'.$npc->id)->assertOk()->assertJsonPath('preview.missing', []);
    foreach (['head' => ['heads', 1], 'body' => ['bodies', 2], 'leg' => ['legs', 3]] as $part => [$category, $id]) {
        expect($response->json('preview.parts.'.$part.'.image'))->toBe($assets->find($category, $id, 'image'));
    }
});

test('game assets retain upload authentication and authorization', function (): void {
    $this->postJson('/api/uploads/image', [])->assertUnauthorized();
    $this->actingAs(User::factory()->create(['role' => 'user']))->postJson('/api/uploads/image', [])->assertForbidden();
});

test('NPC detail resolves all parts independently and map detail prefers the complete uploaded map', function (): void {
    $assets = app(GameDataAssetService::class);
    $catalog = app(GameDataCatalogService::class);
    foreach (['heads', 'bodies', 'legs', 'maps', 'items'] as $category) {
        $assets->store(gameAssetPngUpload(16, 20), $category, 7);
    }
    $npc = $catalog->query('npcs')->create($catalog->normalize('npcs', [[
        'npcTemplateId' => 0, 'name' => 'NPC', 'headId' => 7, 'bodyId' => 7, 'legId' => 7, 'menu' => [],
    ]])[0]);
    $response = $this->getJson('/data-game/npcs/'.$npc->id)->assertOk()->assertJsonPath('preview.missing', []);
    foreach (['head' => 'heads', 'body' => 'bodies', 'leg' => 'legs'] as $part => $category) {
        expect($response->json('preview.parts.'.$part.'.image'))->toStartWith('/storage/upload/data-game/'.$category.'/7.png?v=');
    }
    $map = $catalog->query('maps')->create($catalog->normalize('maps', [['mapId' => 7, 'mapName' => 'Full map', 'icon' => '/thumbnail.png']])[0]);
    $response = $this->getJson('/data-game/maps/'.$map->id)->assertOk();
    expect($response->json('preview.image'))->toStartWith('/storage/upload/data-game/maps/7.png?v=');
    $this->get('/data-game/maps/'.$map->id)->assertOk()->assertSee('data-game-data-preview', false)->assertSee('/storage/upload/data-game/maps/7.png', false);
    $this->get('/data-game?type=maps')->assertOk()->assertSee('/storage/upload/data-game/maps/7.png', false);
    Storage::disk('public')->delete('upload/data-game/legs/7.png');
    $this->getJson('/data-game/npcs/'.$npc->id)->assertOk()->assertJsonPath('preview.missing', ['leg']);
    expect(Storage::disk('public')->allFiles())->toHaveCount(4);
});

test('item previews resolve icon IDs rather than item IDs', function (): void {
    $catalog = app(GameDataCatalogService::class);
    $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/NroGameData/ItemTemplates.json')), true);
    $row = $fixture['data'][0];
    $row['iconID'] = 123;
    $item = $catalog->query('items')->create($catalog->normalize('items', [$row])[0]);
    app(GameDataAssetService::class)->store(gameAssetPngUpload(), 'items', 123);
    $response = $this->getJson('/data-game/items/'.$item->id)->assertOk();
    expect($response->json('preview.image'))->toStartWith('/storage/upload/data-game/items/123.png?v=');
    $this->get('/data-game')->assertOk()->assertSee('/storage/upload/data-game/items/123.png', false);
});

test('remote game images use categorized storage through the protected downloader', function (?string $variant): void {
    $service = new class(app(ImageUploadService::class)) extends RemoteImageUploadService
    {
        protected function resolveHostAddresses(string $host): array
        {
            return ['93.184.216.34'];
        }
    };
    app()->instance(RemoteImageUploadService::class, $service);
    Http::preventStrayRequests();
    Http::fake(['https://cdn.example.com/map.png' => Http::response(gameAssetPngUpload()->getContent(), 200, ['Content-Type' => 'image/png'])]);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->postJson('/api/uploads/image', [
        'url' => 'https://cdn.example.com/map.png', 'category' => 'maps', 'icon_id' => 42, 'variant' => $variant,
    ])->assertOk()->assertJsonPath('data.path', 'upload/data-game/maps/'.($variant !== null ? $variant.'/' : '').'42.png');
})->with([null, 'icon', 'image']);

test('non PNG game images overwrite the same PNG path after conversion', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $first = $this->post('/api/uploads/image', ['image' => gameAssetPngUpload(), 'category' => 'heads', 'icon_id' => 5])->assertOk();
    $this->post('/api/uploads/image', [
        'image' => UploadedFile::fake()->image('head.jpg', 16, 20), 'category' => 'heads', 'icon_id' => 5,
    ])->assertOk()->assertJsonPath('data.path', $first->json('data.path'));
    expect(Storage::disk('public')->allFiles())->toHaveCount(1)
        ->and(getimagesizefromstring(Storage::disk('public')->get($first->json('data.path')))[2])->toBe(IMAGETYPE_PNG);
})->skip(! function_exists('imagecreatetruecolor'), 'GD is required to create and convert JPEGs.');

test('original image and standing icon variants never overwrite each other or legacy URLs', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $legacy = $this->post('/api/uploads/image', [
        'image' => gameAssetPngUpload(20, 24), 'category' => 'mobs', 'icon_id' => 0,
    ])->assertOk();
    $original = $this->postJson('/api/uploads/image', [
        'base64' => 'data:image/png;base64,'.base64_encode(gameAssetPngUpload(80, 120)->getContent()),
        'category' => 'mobs', 'icon_id' => 0, 'variant' => 'image',
    ])->assertOk()->assertJsonPath('data.path', 'upload/data-game/mobs/image/0.png')->assertJsonPath('data.variant', 'image');
    $icon = $this->postJson('/api/uploads/image', [
        'base64' => base64_encode(gameAssetPngUpload(24, 32)->getContent()),
        'category' => 'mobs', 'icon_id' => 0, 'variant' => 'icon',
    ])->assertOk()->assertJsonPath('data.path', 'upload/data-game/mobs/icon/0.png')->assertJsonPath('data.variant', 'icon');
    $disk = Storage::disk('public');
    expect($disk->allFiles())->toHaveCount(3)
        ->and($disk->get($legacy->json('data.path')))->toBe(gameAssetPngUpload(20, 24)->getContent())
        ->and($disk->get($original->json('data.path')))->toBe(gameAssetPngUpload(80, 120)->getContent())
        ->and($disk->get($icon->json('data.path')))->toBe(gameAssetPngUpload(24, 32)->getContent());
    $assets = app(GameDataAssetService::class);
    expect($assets->find('mobs', 0))->toBe($legacy->json('data.url'))
        ->and($assets->find('mobs', 0, 'image'))->toBe($original->json('data.url'))
        ->and($assets->find('mobs', 0, 'icon'))->toBe($icon->json('data.url'));
    $this->postJson('/api/uploads/image', [
        'base64' => base64_encode(gameAssetPngUpload(26, 36)->getContent()),
        'category' => 'mobs', 'icon_id' => 0, 'variant' => 'icon',
    ])->assertOk();
    expect($disk->allFiles())->toHaveCount(3)
        ->and($disk->get($original->json('data.path')))->toBe(gameAssetPngUpload(80, 120)->getContent());
});

test('image variants reject invalid paths and require the categorized identity', function (array $identity): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']))->postJson('/api/uploads/image', [
        ...$identity, 'base64' => base64_encode(gameAssetPngUpload()->getContent()),
    ])->assertUnprocessable();
    expect(Storage::disk('public')->allFiles())->toBe([]);
})->with([
    [['category' => 'mobs', 'icon_id' => 0, 'variant' => '../image']],
    [['category' => 'mobs', 'icon_id' => 0, 'variant' => 'other']],
    [['variant' => 'image']],
    [['category' => 'mobs', 'variant' => 'icon']],
]);

test('categorized variants support every requested category and unsigned IDs', function (string $category): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']))->postJson('/api/uploads/image', [
        'base64' => base64_encode(gameAssetPngUpload()->getContent()),
        'category' => $category, 'icon_id' => 4294967295, 'variant' => 'image',
    ])->assertOk()->assertJsonPath('data.path', 'upload/data-game/'.$category.'/image/4294967295.png');
})->with(['items', 'classes-and-skills', 'npcs', 'mobs', 'maps', 'heads', 'bodies', 'legs']);
