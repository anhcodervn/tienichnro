<?php

use App\Features\NroGameData\Services\GameDataCatalogService;
use App\Features\NroGameData\Services\GameDataSyncService;
use App\Models\NroGameDataSync;
use App\Models\NroMap;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    Cache::flush();
});

function importPublicGameData(string $dataset, array $data): void
{
    $sync = NroGameDataSync::query()->create([
        'dataset' => $dataset, 'status' => 'queued', 'total' => count($data),
        'total_chunks' => 1, 'completed_chunks' => [],
    ]);
    app(GameDataSyncService::class)->process($sync->id, 0, app(GameDataCatalogService::class)->normalize($dataset, $data));
}

test('guests can read every dataset and repeated reads avoid catalog queries', function (string $dataset): void {
    $this->getJson('/api/nro/game-data/'.$dataset)->assertOk()->assertJsonPath('data', [])
        ->assertJsonPath('meta.has_more', false)->assertJsonPath('meta.next_after_id', null);
    DB::enableQueryLog();
    $this->getJson('/api/nro/game-data/'.$dataset)->assertOk();
    expect(collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'nro_')))->toBeEmpty();
    DB::disableQueryLog();
})->with(array_keys(GameDataCatalogService::DATASETS));

test('public reads support cursor paging game ID zero and hash projections', function (): void {
    importPublicGameData('maps', [
        ['mapId' => 0, 'mapName' => 'Zero'], ['mapId' => 7, 'mapName' => 'Seven'], ['mapId' => 9, 'mapName' => 'Nine'],
    ]);
    $first = $this->getJson('/api/nro/game-data/maps?limit=2')->assertOk()
        ->assertJsonCount(2, 'data')->assertJsonPath('data.0.source.mapName', 'Zero')->assertJsonPath('meta.has_more', true);
    $this->getJson('/api/nro/game-data/maps?after_id='.$first->json('meta.next_after_id'))->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id_map', 9)->assertJsonPath('meta.has_more', false);
    $this->getJson('/api/nro/game-data/maps?ids[]=0&view=hashes')->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id_map', 0)->assertJsonMissingPath('data.0.source');
});

test('updates invalidate only the changed dataset after commit and no-op syncs keep cache', function (): void {
    $data = [['mapId' => 0, 'mapName' => 'Before']];
    importPublicGameData('maps', $data);
    $this->getJson('/api/nro/game-data/maps')->assertOk();
    $this->getJson('/api/nro/game-data/items')->assertOk();
    $mapsKey = 'nro:game-data:records:v1:maps';
    $itemsKey = 'nro:game-data:records:v1:items';
    $this->travel(1)->year();
    expect(Cache::has($mapsKey))->toBeTrue();
    importPublicGameData('maps', $data);
    expect(Cache::has($mapsKey))->toBeTrue();
    DB::beginTransaction();
    importPublicGameData('maps', [['mapId' => 0, 'mapName' => 'After']]);
    expect(Cache::has($mapsKey))->toBeTrue();
    DB::commit();
    expect(Cache::has($mapsKey))->toBeFalse()->and(Cache::has($itemsKey))->toBeTrue();
    $this->getJson('/api/nro/game-data/maps')->assertOk()->assertJsonPath('data.0.name', 'After');
    importPublicGameData('maps', [['mapId' => 1, 'mapName' => 'Added']]);
    $this->getJson('/api/nro/game-data/maps')->assertOk()->assertJsonCount(2, 'data');
    NroMap::query()->where('id_map', 1)->firstOrFail()->delete();
    $this->getJson('/api/nro/game-data/maps')->assertOk()->assertJsonCount(1, 'data');
});

test('rolled back edits preserve cached data and manual asset edits refresh it', function (): void {
    importPublicGameData('maps', [['mapId' => 0, 'mapName' => 'Original']]);
    $this->getJson('/api/nro/game-data/maps')->assertOk();
    DB::beginTransaction();
    NroMap::query()->sole()->update(['name' => 'Rolled back']);
    DB::rollBack();
    expect(Cache::has('nro:game-data:records:v1:maps'))->toBeTrue();
    $this->getJson('/api/nro/game-data/maps')->assertOk()->assertJsonPath('data.0.name', 'Original');
    NroMap::query()->sole()->update(['icon' => '/icons/manual.png']);
    $this->getJson('/api/nro/game-data/maps')->assertOk()->assertJsonPath('data.0.icon', '/icons/manual.png');
});

test('item and skill options have independent caches and classes can be filtered', function (): void {
    foreach (['item-options', 'skill-options'] as $dataset) {
        $this->getJson('/api/nro/game-data/'.$dataset)->assertOk();
    }
    importPublicGameData('item-options', [['id' => 0, 'name' => 'Item option', 'type' => 0, 'color' => 0]]);
    expect(Cache::has('nro:game-data:records:v1:item-options'))->toBeFalse()
        ->and(Cache::has('nro:game-data:records:v1:skill-options'))->toBeTrue();
    $this->getJson('/api/nro/game-data/item-options')->assertOk()->assertJsonPath('data.0.kind', 'item');
    $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/NroGameData/ClassesAndSkills.json')), true, 512, JSON_THROW_ON_ERROR);
    importPublicGameData('classes-and-skills', $fixture['data']);
    $this->getJson('/api/nro/game-data/classes-and-skills?class_id=0')->assertOk()
        ->assertJsonPath('data.0.class_id', 0);
    $this->getJson('/api/nro/game-data/classes-and-skills?class_id=999')->assertOk()->assertJsonCount(0, 'data');
});

test('public endpoints reject invalid filters and do not expose write access', function (): void {
    $this->getJson('/api/nro/game-data/maps?limit=501')->assertUnprocessable();
    $this->getJson('/api/nro/game-data/maps?class_id=0')->assertUnprocessable();
    $this->getJson('/api/nro/game-data/maps?ids[]=0&ids[]=0')->assertUnprocessable();
    $this->getJson('/api/nro/game-data/maps?after_id=-1')->assertUnprocessable();
    $this->getJson('/api/nro/game-data/unknown')->assertNotFound();
    $this->getJson('/api/admin-api/nro/game-data/maps')->assertUnauthorized();
    $this->postJson('/api/admin-api/nro/game-data/maps', [])->assertUnauthorized();
    $this->postJson('/api/nro/game-data/maps', [])->assertMethodNotAllowed();
});
