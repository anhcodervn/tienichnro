<?php

use App\Features\NroGameData\Services\GameDataCatalogService;
use App\Models\NroMap;

function browseGameDataSeed(string $dataset, string $fixture): void
{
    $catalog = app(GameDataCatalogService::class);
    $payload = json_decode(file_get_contents(base_path('tests/Fixtures/NroGameData/'.$fixture.'.json')), true, 512, JSON_THROW_ON_ERROR);
    foreach ($catalog->normalize($dataset, $payload['data']) as $record) {
        $catalog->query($dataset)->create($record);
    }
}

test('game data page is public with category navigation responsive cards and an empty state', function (): void {
    $this->get('/data-game')->assertOk()->assertSeeText('Dữ liệu game')->assertSeeText('Vật phẩm')
        ->assertSeeText('Chưa có dữ liệu trong danh mục này')
        ->assertSee('lg:grid-cols-[220px_minmax(0,1fr)]', false)
        ->assertSee('data-game-data-category', false);
    $this->getJson('/api/admin-api/nro/game-data/items')->assertUnauthorized();
});

test('each catalog category renders cards with lazy full detail links', function (string $dataset, string $fixture): void {
    browseGameDataSeed($dataset, $fixture);
    $this->get('/data-game?type='.$dataset)->assertOk()->assertSeeText('Xem đầy đủ')
        ->assertSee('data-game-data-open', false)->assertSee('sm:grid-cols-2 xl:grid-cols-3', false)
        ->assertViewHas('records', function ($records): bool {
            $attributes = $records->first()->getAttributes();

            return ! array_key_exists('source', $attributes) && ! array_key_exists('levels', $attributes) && ! array_key_exists('menu', $attributes);
        });
})->with([
    ['items', 'ItemTemplates'], ['maps', 'Maps'], ['npcs', 'NpcTemplates'], ['mobs', 'MobTemplates'],
    ['item-options', 'ItemOptions'], ['skill-options', 'SkillOptions'], ['classes-and-skills', 'ClassesAndSkills'],
]);

test('item cards show gender badge game ID and abbreviated strength without fabricated images', function (): void {
    browseGameDataSeed('items', 'ItemTemplates');
    $this->get('/data-game')->assertOk()->assertSeeText('Áo vải 3 lỗ')->assertSeeText('Trái đất')
        ->assertSeeText('ID: 0')->assertSeeText('Áo [0]')->assertSeeText('1.2K');
});

test('search matches names game ID zero and treats SQL wildcard characters literally', function (): void {
    $catalog = app(GameDataCatalogService::class);
    foreach ($catalog->normalize('maps', [['mapId' => 0, 'mapName' => 'Zero map'], ['mapId' => 1, 'mapName' => '100% special'], ['mapId' => 2, 'mapName' => '1000 other']]) as $row) {
        NroMap::query()->create($row);
    }
    $this->get('/data-game?type=maps&q=0')->assertOk()->assertSeeText('Zero map');
    $this->get('/data-game?type=maps&q='.urlencode('%'))->assertOk()->assertSeeText('100% special')->assertDontSeeText('1000 other');
    $this->get('/data-game?type=maps&q=no-match')->assertOk()->assertSeeText('Không tìm thấy dữ liệu phù hợp');
});

test('catalog pagination stays bounded and preserves the chosen category', function (): void {
    $catalog = app(GameDataCatalogService::class);
    $rows = array_map(fn (int $id): array => ['mapId' => $id, 'mapName' => 'Test Map '.$id], range(0, 29));
    foreach ($catalog->normalize('maps', $rows) as $row) {
        NroMap::query()->create($row);
    }
    $this->get('/data-game?type=maps')->assertOk()->assertViewHas('records', fn ($records): bool => $records->count() === 24 && $records->total() === 30)
        ->assertSee('type=maps', false)->assertSee('page=2', false);
    $this->get('/data-game?type=maps&page=2')->assertOk()->assertViewHas('records', fn ($records): bool => $records->count() === 6);
});

test('full JSON is public on demand without exposing synchronization metadata', function (): void {
    browseGameDataSeed('maps', 'Maps');
    $record = NroMap::query()->where('id_map', 0)->firstOrFail();
    $this->getJson('/data-game/maps/'.$record->id)->assertOk()->assertJsonPath('data.mapId', 0)
        ->assertJsonPath('data.mapName', 'Làng Aru')->assertJsonMissingPath('data.content_hash')
        ->assertJsonMissingPath('data.account_id')->assertJsonMissingPath('data.source');
    $this->get('/data-game/maps/'.$record->id)->assertOk()->assertSeeText('Dữ liệu đầy đủ')->assertSeeText('mapName');
    $this->getJson('/data-game/mobs/'.$record->id)->assertNotFound();
    $this->get('/data-game/unknown/1')->assertNotFound();
});

test('game names JSON and media URLs cannot inject executable HTML', function (): void {
    $raw = '<script>alert(1)</script>';
    $catalog = app(GameDataCatalogService::class);
    $record = NroMap::query()->create($catalog->normalize('maps', [['mapId' => 0, 'mapName' => $raw]])[0]);
    $record->update(['icon' => 'javascript:alert(1)', 'image' => '//evil.example/image.png']);
    $this->get('/data-game?type=maps')->assertOk()->assertDontSee($raw, false)->assertSee(e($raw), false)
        ->assertDontSee('src="javascript:', false)->assertDontSee('src="//evil.example', false);
    $this->get('/data-game/maps/'.$record->id)->assertOk()->assertDontSee($raw, false)->assertSee(e($raw), false);
});

test('public browse rejects invalid datasets oversized searches and malformed filters', function (): void {
    foreach (['type=unknown', 'type[]=maps', 'q[]=invalid', 'q='.str_repeat('a', 201), 'page=-1', 'gender=999'] as $query) {
        $this->getJson('/data-game?'.$query)->assertUnprocessable();
    }
});
