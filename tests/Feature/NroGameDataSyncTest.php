<?php

use App\Features\NroGameData\Jobs\SyncGameDataChunk;
use App\Features\NroGameData\Services\GameDataCatalogService;
use App\Features\NroGameData\Services\GameDataSyncService;
use App\Models\NroGameDataSync;
use App\Models\NroMap;
use App\Models\NroMob;
use App\Models\NroNpc;
use App\Models\NroOption;
use App\Models\NroSkill;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

function gameDataFixture(string $file): array
{
    return json_decode(file_get_contents(base_path('tests/Fixtures/NroGameData/'.$file.'.json')), true, 512, JSON_THROW_ON_ERROR);
}

function gameDataSubmit(string $dataset, array $payload): int
{
    return test()->postJson('/api/admin-api/nro/game-data/'.$dataset, $payload)->assertAccepted()->json('data.id');
}

function gameDataRun(int $id): NroGameDataSync
{
    foreach (Queue::pushed(SyncGameDataChunk::class, fn ($job): bool => $job->syncId === $id) as $job) {
        $job->handle(app(GameDataSyncService::class));
    }

    return NroGameDataSync::query()->findOrFail($id)->refresh();
}

beforeEach(function (): void {
    config()->set('queue.default', 'database');
    Queue::fake();
    $this->travelTo(now()->startOfSecond());
    $admin = User::factory()->create(['role' => 'admin']);
    $this->withToken($admin->createToken('winforms')->plainTextToken);
});

test('catalog endpoints use the same Sanctum administrator authentication as notification ingestion', function (): void {
    $this->withToken('invalid')->getJson('/api/admin-api/nro/game-data/maps')->assertUnauthorized();
    $this->postJson('/api/admin-api/nro/game-data/maps', gameDataFixture('Maps'))->assertUnauthorized();
    $user = User::factory()->create(['role' => 'user']);
    $this->withToken($user->createToken('ordinary-user')->plainTextToken)
        ->getJson('/api/admin-api/nro/game-data/maps')->assertForbidden();
    $this->postJson('/api/admin-api/nro/game-data/maps', gameDataFixture('Maps'))->assertForbidden();
    Queue::assertNothingPushed();
});

test('each source JSON dataset is accepted and imported with nullable manual assets', function (string $dataset, string $fixture): void {
    $payload = gameDataFixture($fixture);
    $id = gameDataSubmit($dataset, $payload);
    $catalog = app(GameDataCatalogService::class);
    expect($catalog->query($dataset)->count())->toBe(0);
    $sync = gameDataRun($id);
    $expected = count($catalog->normalize($dataset, $payload['data']));
    expect($sync->status)->toBe('completed')->and($sync->created)->toBe($expected)->and($sync->processed)->toBe($expected);
    expect($catalog->query($dataset)->count())->toBe($expected);
    foreach ($catalog->query($dataset)->get() as $record) {
        expect($record->icon)->toBeNull()->and($record->image)->toBeNull()
            ->and(strlen($record->content_hash))->toBe(64)->and($record->source)->toBeArray();
    }
    $this->getJson('/api/admin-api/nro/game-data/syncs/'.$id)->assertOk()->assertJsonPath('data.status', 'completed');
})->with([
    ['maps', 'Maps'], ['npcs', 'NpcTemplates'], ['mobs', 'MobTemplates'], ['items', 'ItemTemplates'],
    ['item-options', 'ItemOptions'], ['skill-options', 'SkillOptions'], ['classes-and-skills', 'ClassesAndSkills'],
]);

test('large submissions create bounded chunks on the dedicated asynchronous queue', function (): void {
    $payload = ['data' => array_map(fn (int $id): array => ['mapId' => $id, 'mapName' => 'Map '.$id], range(0, 120))];
    $id = gameDataSubmit('maps', $payload);
    Queue::assertPushed(SyncGameDataChunk::class, 3);
    Queue::assertPushed(SyncGameDataChunk::class, fn ($job): bool => $job->connection === 'database' && $job->queue === 'game-data' && $job->afterCommit && count($job->rows) === 50);
    expect(NroMap::query()->count())->toBe(0);
    $sync = gameDataRun($id);
    expect($sync->processed)->toBe(121)->and($sync->created)->toBe(121)->and($sync->status)->toBe('completed');
    gameDataRun($id);
    expect($sync->fresh()->processed)->toBe(121)->and(NroMap::query()->count())->toBe(121);
});

test('non-asynchronous queue drivers never execute catalog updates in the HTTP request', function (string $connection, string $driver): void {
    config()->set('queue.default', $connection);
    config()->set('queue.connections.'.$connection.'.driver', $driver);
    $this->postJson('/api/admin-api/nro/game-data/maps', gameDataFixture('Maps'))->assertStatus(503);
    expect(NroMap::query()->count())->toBe(0)->and(NroGameDataSync::query()->count())->toBe(0);
    Queue::assertNothingPushed();
})->with([['sync', 'sync'], ['discard', 'null']]);

test('unchanged records do not write timestamps and missing assets reset existing references to null', function (): void {
    $payload = gameDataFixture('Maps');
    gameDataRun(gameDataSubmit('maps', $payload));
    $map = NroMap::query()->where('id_map', $payload['data'][0]['mapId'])->firstOrFail();
    $timestamp = $map->fresh()->updated_at->toISOString();
    $this->travel(10)->seconds();
    $payload['data'] = array_map(fn (array $row): array => array_reverse($row, true), $payload['data']);
    $payload['version']++;
    $sync = gameDataRun(gameDataSubmit('maps', $payload));
    expect($sync->unchanged)->toBe(count($payload['data']))->and($sync->updated)->toBe(0)
        ->and($map->fresh()->updated_at->toISOString())->toBe($timestamp);
    $map->update(['icon' => '/icons/manual.png', 'image' => '/maps/manual.png']);
    $payload['data'][0]['mapName'] = 'Updated map';
    $sync = gameDataRun(gameDataSubmit('maps', $payload));
    expect($sync->updated)->toBe(1)->and($sync->unchanged)->toBe(count($payload['data']) - 1)
        ->and($map->fresh()->name)->toBe('Updated map')
        ->and($map->fresh()->icon)->toBeNull()->and($map->fresh()->image)->toBeNull();
});

test('option IDs are separate for item and skill option namespaces', function (): void {
    gameDataRun(gameDataSubmit('item-options', gameDataFixture('ItemOptions')));
    gameDataRun(gameDataSubmit('skill-options', gameDataFixture('SkillOptions')));
    expect(NroOption::query()->where('id_option', 0)->count())->toBe(2);
    $this->getJson('/api/admin-api/nro/game-data/skill-options?ids[]=0')->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.kind', 'skill');
});

test('game data preserves empty NPC names signed part IDs large HP and skill class identity', function (): void {
    gameDataRun(gameDataSubmit('npcs', gameDataFixture('NpcTemplates')));
    expect(NroNpc::query()->where('name', '')->exists())->toBeTrue()
        ->and(NroNpc::query()->where('head_id', -1)->exists())->toBeTrue();
    gameDataRun(gameDataSubmit('mobs', gameDataFixture('MobTemplates')));
    expect(NroMob::query()->max('hp'))->toBe(10000000000);
    $payload = gameDataFixture('ClassesAndSkills');
    gameDataRun(gameDataSubmit('classes-and-skills', $payload));
    expect(NroSkill::query()->where('id_skill', 19)->count())->toBe(4);
    $skill = NroSkill::query()->where('class_id', 0)->where('id_skill', 24)->firstOrFail();
    expect($skill->levels[0]['powRequire'])->toBe(60000000000);
    foreach ($payload['data'] as &$class) {
        foreach ($class['skills'] as &$entry) {
            $entry['levels'] = array_reverse($entry['levels']);
        }
        unset($entry);
    }
    unset($class);
    expect(gameDataRun(gameDataSubmit('classes-and-skills', $payload))->updated)->toBe(0);
});

test('partial class skill submissions do not remove other skills or levels', function (): void {
    $payload = gameDataFixture('ClassesAndSkills');
    gameDataRun(gameDataSubmit('classes-and-skills', $payload));
    $count = NroSkill::query()->count();
    $class = $payload['data'][0];
    $class['skills'] = [$class['skills'][0]];
    $class['skills'][0]['name'] = 'Updated skill';
    $sync = gameDataRun(gameDataSubmit('classes-and-skills', ['data' => [$class]]));
    expect($sync->updated)->toBe(1)->and(NroSkill::query()->count())->toBe($count);
});

test('record and hash listings support cursor paging and game ID zero', function (): void {
    gameDataRun(gameDataSubmit('maps', ['data' => [
        ['mapId' => 0, 'mapName' => 'Map zero'], ['mapId' => 5, 'mapName' => 'Map five'], ['mapId' => 10, 'mapName' => 'Map ten'],
    ]]));
    $response = $this->getJson('/api/admin-api/nro/game-data/maps?limit=2')->assertOk()
        ->assertJsonCount(2, 'data')->assertJsonPath('data.0.id_map', 0)->assertJsonPath('data.0.source.mapName', 'Map zero')
        ->assertJsonPath('meta.has_more', true);
    $this->getJson('/api/admin-api/nro/game-data/maps?after_id='.$response->json('meta.next_after_id'))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id_map', 10)->assertJsonPath('meta.has_more', false);
    $this->getJson('/api/admin-api/nro/game-data/maps?view=hashes&ids[]=0')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id_map', 0)->assertJsonMissingPath('data.0.source')->assertJsonMissingPath('data.0.name');
});

test('queued snapshots cannot overtake earlier batches and stale chunk replays are harmless', function (): void {
    $oldId = gameDataSubmit('maps', ['data' => [['mapId' => 0, 'mapName' => 'Old']]]);
    $newId = gameDataSubmit('maps', ['data' => [['mapId' => 0, 'mapName' => 'New']]]);
    $service = app(GameDataSyncService::class);
    $newJob = Queue::pushed(SyncGameDataChunk::class, fn ($job): bool => $job->syncId === $newId)->first();
    expect($service->process($newId, $newJob->chunkIndex, $newJob->rows))->toBeFalse();
    gameDataRun($oldId);
    gameDataRun($newId);
    gameDataRun($oldId);
    expect(NroMap::query()->sole()->name)->toBe('New');
});

test('chunk failures roll back partial changes and failed jobs expose status', function (): void {
    $id = gameDataSubmit('maps', ['data' => [['mapId' => 0, 'mapName' => 'Good'], ['mapId' => 1, 'mapName' => 'Fail']]]);
    NroMap::saving(function (NroMap $map): void {
        if ($map->id_map === 1) {
            throw new RuntimeException('Simulated database failure');
        }
    });
    $job = Queue::pushed(SyncGameDataChunk::class)->first();
    expect(fn () => $job->handle(app(GameDataSyncService::class)))->toThrow(RuntimeException::class);
    expect(NroMap::query()->count())->toBe(0)->and(NroGameDataSync::query()->findOrFail($id)->processed)->toBe(0);
    $job->failed(new RuntimeException('Simulated worker failure'));
    $this->getJson('/api/admin-api/nro/game-data/syncs/'.$id)->assertOk()->assertJsonPath('data.status', 'failed');
});

test('invalid or duplicate game rows are rejected before queueing', function (): void {
    foreach ([
        ['data' => [['mapId' => 0, 'mapName' => 'Name', 'icon' => ['invalid']]]],
        ['data' => [['mapId' => -1, 'mapName' => 'Name']]],
        ['data' => [['mapId' => 0]]],
        ['data' => [['mapId' => 0, 'mapName' => 'Name'], ['mapId' => '0', 'mapName' => 'Duplicate']]],
    ] as $payload) {
        $this->postJson('/api/admin-api/nro/game-data/maps', $payload)->assertUnprocessable();
    }
    $payload = gameDataFixture('ClassesAndSkills');
    $payload['data'][0]['skills'][] = $payload['data'][0]['skills'][0];
    $this->postJson('/api/admin-api/nro/game-data/classes-and-skills', $payload)->assertUnprocessable();
    $this->getJson('/api/admin-api/nro/game-data/invalid-table')->assertNotFound();
    $this->getJson('/api/admin-api/nro/game-data/maps?limit=501')->assertUnprocessable();
    Queue::assertNothingPushed();
});

test('empty incremental uploads finish without deleting existing records', function (): void {
    gameDataRun(gameDataSubmit('maps', gameDataFixture('Maps')));
    $count = NroMap::query()->count();
    $id = gameDataSubmit('maps', ['data' => []]);
    expect(NroGameDataSync::query()->findOrFail($id)->status)->toBe('completed')->and(NroMap::query()->count())->toBe($count);
});

test('comparison returns only new or changed identities without creating jobs or sync records', function (): void {
    gameDataRun(gameDataSubmit('maps', ['data' => [['mapId' => 0, 'mapName' => 'Map zero'], ['mapId' => 1, 'mapName' => 'Map one']]]));
    $payload = ['data' => [['mapId' => 0, 'mapName' => 'Map zero'], ['mapId' => 1, 'mapName' => 'Changed'], ['mapId' => 2, 'mapName' => 'New']]];
    $this->postJson('/api/admin-api/nro/game-data/maps/compare', $payload)->assertOk()
        ->assertJsonPath('data.0.identity.id_map', 0)->assertJsonPath('data.0.needs_update', false)
        ->assertJsonPath('data.1.needs_update', true)->assertJsonPath('data.2.needs_update', true);
    expect(NroGameDataSync::query()->count())->toBe(1)->and(NroMap::query()->where('id_map', 1)->firstOrFail()->name)->toBe('Map one');
    Queue::assertPushed(SyncGameDataChunk::class, 1);
});

test('complete external game exports validate and synchronize when an export directory is supplied', function (): void {
    $directory = getenv('NRO_GAME_DATA_SOURCE_DIR');
    if (! $directory) {
        $this->markTestSkipped('Optional full-export check requires NRO_GAME_DATA_SOURCE_DIR.');
    }
    foreach (['maps' => 'Maps', 'npcs' => 'NpcTemplates', 'mobs' => 'MobTemplates', 'items' => 'ItemTemplates', 'item-options' => 'ItemOptions', 'skill-options' => 'SkillOptions', 'classes-and-skills' => 'ClassesAndSkills'] as $dataset => $file) {
        $payload = json_decode(file_get_contents($directory.'/'.$file.'.json'), true, 512, JSON_THROW_ON_ERROR);
        $sync = gameDataRun(gameDataSubmit($dataset, $payload));
        expect($sync->status)->toBe('completed')->and($sync->created)->toBe($sync->total);
    }
});

/** @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function gameDataAssetPayload(array $payload, bool $include, ?string $icon = null, ?string $image = null): array
{
    $set = function (array $entry) use ($include, $icon, $image): array {
        unset($entry['icon'], $entry['image']);
        if ($include) {
            $entry['icon'] = $icon;
            $entry['image'] = $image;
        }

        return $entry;
    };
    foreach ($payload['data'] as &$entry) {
        if (isset($entry['skills'])) {
            $entry['skills'] = array_map($set, $entry['skills']);
        } else {
            $entry = $set($entry);
        }
    }
    unset($entry);

    return $payload;
}

test('every hook dataset accepts image and icon and clears them when omitted', function (string $dataset, string $fixture): void {
    $payload = gameDataAssetPayload(gameDataFixture($fixture), true, '/storage/uploads/2026-10-18/image/icon.webp', 'https://cdn.example.com/image.png');
    $withoutAssets = gameDataAssetPayload($payload, false);
    $original = gameDataRun(gameDataSubmit($dataset, $withoutAssets));
    $catalog = app(GameDataCatalogService::class);
    $originalIds = $catalog->query($dataset)->orderBy('id')->pluck('id')->all();
    foreach ($catalog->query($dataset)->get() as $row) {
        expect($row->image)->toBeNull()->and($row->icon)->toBeNull();
    }
    $comparison = $this->postJson('/api/admin-api/nro/game-data/'.$dataset.'/compare', $payload)->assertOk();
    foreach ($comparison->json('data') as $decision) {
        expect($decision['needs_update'])->toBeTrue();
    }
    $filled = gameDataRun(gameDataSubmit($dataset, $payload));
    expect($filled->status)->toBe('completed')->and($filled->created)->toBe(0)
        ->and($filled->updated)->toBe($original->total)
        ->and($catalog->query($dataset)->orderBy('id')->pluck('id')->all())->toBe($originalIds);
    foreach ($catalog->query($dataset)->get() as $row) {
        expect($row->icon)->toBe('/storage/uploads/2026-10-18/image/icon.webp')
            ->and($row->image)->toBe('https://cdn.example.com/image.png');
    }
    $catalog = app(GameDataCatalogService::class);
    $record = $catalog->query($dataset)->firstOrFail();
    expect($record->icon)->toBe('/storage/uploads/2026-10-18/image/icon.webp')
        ->and($record->image)->toBe('https://cdn.example.com/image.png')
        ->and($record->source['icon'])->toBe($record->icon)
        ->and($record->source['image'])->toBe($record->image);
    $this->getJson('/api/admin-api/nro/game-data/'.$dataset)->assertOk()
        ->assertJsonPath('data.0.icon', $record->icon)->assertJsonPath('data.0.image', $record->image);
    $this->postJson('/api/admin-api/nro/game-data/'.$dataset.'/compare', $payload)
        ->assertOk()->assertJsonPath('data.0.needs_update', false);

    if ($dataset === 'classes-and-skills') {
        $payload['data'][0]['skills'][0]['image'] = '/storage/new-skill-image.webp';
    } else {
        $payload['data'][0]['image'] = '/storage/new-image.webp';
    }
    $this->postJson('/api/admin-api/nro/game-data/'.$dataset.'/compare', $payload)
        ->assertOk()->assertJsonPath('data.0.needs_update', true);
    $sync = gameDataRun(gameDataSubmit($dataset, $payload));
    expect($sync->updated)->toBe(1);

    $omitted = gameDataAssetPayload($payload, false);
    $this->postJson('/api/admin-api/nro/game-data/'.$dataset.'/compare', $omitted)
        ->assertOk()->assertJsonPath('data.0.needs_update', true);
    $sync = gameDataRun(gameDataSubmit($dataset, $omitted));
    expect($sync->updated)->toBe($sync->total);
    foreach ($catalog->query($dataset)->get() as $row) {
        expect($row->icon)->toBeNull()->and($row->image)->toBeNull()
            ->and($row->source)->toHaveKeys(['icon', 'image'])
            ->and($row->source['icon'])->toBeNull()->and($row->source['image'])->toBeNull();
    }
    $explicitNull = gameDataAssetPayload($omitted, true);
    $this->postJson('/api/admin-api/nro/game-data/'.$dataset.'/compare', $explicitNull)
        ->assertOk()->assertJsonPath('data.0.needs_update', false);
})->with([
    ['maps', 'Maps'], ['npcs', 'NpcTemplates'], ['mobs', 'MobTemplates'], ['items', 'ItemTemplates'],
    ['item-options', 'ItemOptions'], ['skill-options', 'SkillOptions'], ['classes-and-skills', 'ClassesAndSkills'],
]);

test('compare detects manual asset drift even when the source hash is unchanged', function (): void {
    $payload = ['data' => [['mapId' => 0, 'mapName' => 'Map zero']]];
    gameDataRun(gameDataSubmit('maps', $payload));
    $map = NroMap::query()->firstOrFail();
    $hash = $map->content_hash;
    $map->update(['icon' => '/manual/icon.png', 'image' => '/manual/image.png']);
    $this->postJson('/api/admin-api/nro/game-data/maps/compare', $payload)->assertOk()
        ->assertJsonPath('data.0.content_hash', $hash)->assertJsonPath('data.0.needs_update', true);
    $sync = gameDataRun(gameDataSubmit('maps', $payload));
    expect($sync->updated)->toBe(1)->and($map->fresh()->icon)->toBeNull()->and($map->fresh()->image)->toBeNull();
});

test('explicit null and empty asset references clear stored images', function (?string $value): void {
    $payload = ['data' => [['mapId' => 0, 'mapName' => 'Map zero', 'icon' => '/icon.png', 'image' => '/image.png']]];
    gameDataRun(gameDataSubmit('maps', $payload));
    $payload['data'][0]['icon'] = $value;
    $payload['data'][0]['image'] = $value;
    $sync = gameDataRun(gameDataSubmit('maps', $payload));
    expect($sync->updated)->toBe(1)->and(NroMap::query()->firstOrFail()->icon)->toBeNull()
        ->and(NroMap::query()->firstOrFail()->image)->toBeNull();
})->with([null, '', '   ']);

test('hook asset fields reject non strings and oversized references', function (mixed $value): void {
    $payload = ['data' => [['mapId' => 0, 'mapName' => 'Map zero', 'image' => $value]]];
    $this->postJson('/api/admin-api/nro/game-data/maps', $payload)->assertUnprocessable();
    $this->postJson('/api/admin-api/nro/game-data/maps/compare', $payload)->assertUnprocessable();
    Queue::assertNothingPushed();
})->with([[['invalid']], [123], [true], [str_repeat('x', 2049)]]);
