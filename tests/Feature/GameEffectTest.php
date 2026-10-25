<?php

use App\Features\NroGameData\Services\GameEffectDecoderService;
use App\Models\NroNpc;
use App\Models\User;
use App\Support\SettingStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function effectTestPng(): string
{
    $chunk = fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

    return "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', 167, 168, 8, 6, 0, 0, 0))
        .$chunk('IDAT', gzcompress(str_repeat("\0".str_repeat("\0\0\0\0", 167), 168))).$chunk('IEND', '');
}

function effectTestBinary(): string
{
    $bytes = pack('C', 10);
    for ($i = 0; $i < 10; $i++) {
        $bytes .= pack('CCCCC', $i, $i * 10, 0, 10, 10);
    }
    $bytes .= pack('n', 4);
    for ($i = 0; $i < 4; $i++) {
        $bytes .= pack('C', 11).pack('nnC', -72, -110, 9).pack('nnC', 64, 77, 0);
        for ($j = 2; $j < 11; $j++) {
            $bytes .= pack('nnC', $j + $i, -$j, $j % 10);
        }
    }
    $bytes .= pack('n', 12);
    foreach ([0, 0, 0, 1, 1, 1, 2, 2, 2, 3, 3, 3] as $frame) {
        $bytes .= pack('n', $frame);
    }

    return $bytes."\x32\x32";
}

beforeEach(function (): void {
    Storage::fake('public');
    $this->admin = User::factory()->create(['role' => 'admin']);
});

test('effect binary preserves signed offsets array order repeated pieces sequence and unconsumed tail', function (): void {
    $decoder = app(GameEffectDecoderService::class);
    $data = $decoder->binary(effectTestBinary());
    expect(strlen(effectTestBinary()))->toBe(305)->and($data['bytesConsumed'])->toBe(303)->and($data['trailingHex'])->toBe('3232');
    $render = $decoder->normalize($data, 167, 168, 17, 30);
    expect($render['width'])->toBe(146)->and($render['height'])->toBe(197)
        ->and($render['originX'])->toBe(72)->and($render['originY'])->toBe(110)
        ->and($render['frames'][0]['pieces'][0])->toBe(['imageId' => 9, 'dx' => -72, 'dy' => -110, 'drawOrder' => 0])
        ->and($render['frames'][0]['pieces'][1]['imageId'])->toBe(0)
        ->and($render['sequence'])->toBe([0, 0, 0, 1, 1, 1, 2, 2, 2, 3, 3, 3]);
});

test('admin installs original PNG BIN and JSON without GD resize or watermark and NPC automatically uses its renderer', function (): void {
    $data = app(GameEffectDecoderService::class)->binary(effectTestBinary());
    $result = $this->actingAs($this->admin)->post('/api/admin-api/nro/game-data/effects/17', [
        'sheet' => UploadedFile::fake()->createWithContent('res_x1_effectdata_17_img.png', effectTestPng()),
        'binary' => UploadedFile::fake()->createWithContent('effect-17.data.bin', effectTestBinary()),
        'animation' => UploadedFile::fake()->createWithContent('effect-17.json', json_encode($data)),
    ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.effectId', 17)->assertJsonPath('data.width', 146);
    $sheetPath = substr(parse_url($result->json('data.sheetUrl'), PHP_URL_PATH), strlen('/storage/'));
    $directory = dirname($sheetPath);
    expect(Storage::disk('public')->get($sheetPath))->toBe(effectTestPng())
        ->and(Storage::disk('public')->get($directory.'/source.data.bin'))->toBe(effectTestBinary())
        ->and(Storage::disk('public')->get($directory.'/source.json'))->toBe(json_encode($data));
    $npc = NroNpc::query()->create(['id_npc' => 24, 'name' => 'Dragon', 'head_id' => 103, 'body_id' => -1, 'leg_id' => -1,
        'menu' => [], 'source' => ['npcTemplateId' => 24], 'content_hash' => str_repeat('a', 64), 'icon' => '/head-103.png', 'image' => '/sheet.png']);
    $this->get('/data-game?type=npcs')->assertOk()->assertSee('data-game-effect', false)->assertDontSee('src="/head-103.png"', false)
        ->assertDontSee('src="/sheet.png"', false);
    $this->get('/data-game/npcs/'.$npc->id)->assertOk()->assertSee('data-game-effect', false);
    $this->getJson('/data-game/npcs/'.$npc->id)->assertOk()->assertJsonPath('data.effect.effectId', 17);
});

test('image API recognizes effect sprite filename and base64 name and keeps the PNG byte for byte', function (string $kind): void {
    $payload = $kind === 'file'
        ? ['image' => UploadedFile::fake()->createWithContent('res_x1_effectdata_17_img.png', effectTestPng()), 'name' => 'custom-alias']
        : ['base64' => base64_encode(effectTestPng()), 'name' => 'res_x1_effectdata_17_img'];
    $response = $this->actingAs($this->admin)->post('/api/uploads/image', $payload, ['Accept' => 'application/json'])
        ->assertOk()->assertJsonPath('data.effect_id', 17)->assertJsonPath('data.requires_animation', true)->assertJsonPath('data.extension', 'png');
    expect(Storage::disk('public')->get($response->json('data.path')))->toBe(effectTestPng());
})->with(['file', 'base64']);

test('invalid effect packages are rejected without publishing a partial replacement', function (string $invalid): void {
    app(SettingStore::class)->putArray('game_effect_17', ['dataUrl' => '/previous.json']);
    $data = app(GameEffectDecoderService::class)->binary(effectTestBinary());
    if ($invalid === 'region') {
        $data['regions'][0]['x'] = 200;
    } elseif ($invalid === 'piece') {
        $data['frames'][0]['pieces'][0]['imageId'] = 100;
    } elseif ($invalid === 'sequence') {
        $data['sequence'][0] = 9;
    } elseif ($invalid === 'duplicate-region') {
        $data['regions'][1]['id'] = 0;
    } elseif ($invalid === 'bounds') {
        $data['frames'][0]['pieces'][0]['dx'] = -100;
    }
    $this->actingAs($this->admin)->post('/api/admin-api/nro/game-data/effects/17', [
        'sheet' => UploadedFile::fake()->createWithContent('sheet.png', effectTestPng()),
        'animation' => UploadedFile::fake()->createWithContent('effect.json', $invalid === 'json' ? '{bad' : json_encode($data)),
    ], ['Accept' => 'application/json'])->assertUnprocessable();
    expect(app(SettingStore::class)->getArray('game_effect_17'))->toBe(['dataUrl' => '/previous.json'])
        ->and(Storage::disk('public')->allFiles('game-effects'))->toBe([]);
})->with(['region', 'piece', 'sequence', 'duplicate-region', 'bounds', 'json']);

test('only admins may install effects and original binary must not be truncated', function (): void {
    $this->postJson('/api/admin-api/nro/game-data/effects/17')->assertUnauthorized();
    $this->actingAs(User::factory()->create(['role' => 'user']))->postJson('/api/admin-api/nro/game-data/effects/17')->assertForbidden();
    $this->actingAs($this->admin)->post('/api/admin-api/nro/game-data/effects/17', [
        'sheet' => UploadedFile::fake()->createWithContent('sheet.png', effectTestPng()),
        'binary' => UploadedFile::fake()->createWithContent('effect.bin', substr(effectTestBinary(), 0, 40)),
    ], ['Accept' => 'application/json'])->assertUnprocessable();
});

test('NPC 24 never displays its head or raw atlas while effect resources are missing', function (): void {
    NroNpc::query()->create(['id_npc' => 24, 'name' => 'Dragon', 'head_id' => 103, 'body_id' => -1, 'leg_id' => -1,
        'menu' => [], 'source' => [], 'content_hash' => str_repeat('b', 64), 'icon' => '/head.png', 'image' => '/atlas.png']);
    $this->get('/data-game?type=npcs')->assertOk()->assertSee('data-game-effect', false)
        ->assertDontSee('src="/head.png"', false)->assertDontSee('src="/atlas.png"', false);
});

test('JSON only packages support non sequential region IDs and configured tick rates', function (): void {
    $data = ['regions' => [['id' => 42, 'x' => 0, 'y' => 0, 'width' => 10, 'height' => 10]],
        'frames' => [['pieces' => [['imageId' => 42, 'dx' => -5, 'dy' => -10], ['imageId' => 42, 'dx' => 5, 'dy' => 0]]]],
        'sequence' => [0, 0, 0], 'timing' => ['tickRate' => 60]];
    $result = $this->actingAs($this->admin)->post('/api/admin-api/nro/game-data/effects/25', [
        'sheet' => UploadedFile::fake()->createWithContent('sheet.png', effectTestPng()),
        'animation' => UploadedFile::fake()->createWithContent('effect.json', json_encode($data)), 'tick_rate' => 60,
    ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.tickRate', 60)->assertJsonPath('data.width', 20);
    $path = substr(parse_url($result->json('data.dataUrl'), PHP_URL_PATH), strlen('/storage/'));
    $normalized = json_decode(Storage::disk('public')->get($path), true);
    expect($normalized['frames'][0]['pieces'][0]['imageId'])->toBe(42)->and($normalized['tickRate'])->toBe(60);
});

test('a mismatched JSON cannot be published alongside the original binary', function (): void {
    $data = app(GameEffectDecoderService::class)->binary(effectTestBinary());
    $data['frames'][0]['pieces'][5]['dx']++;
    $this->actingAs($this->admin)->post('/api/admin-api/nro/game-data/effects/17', [
        'sheet' => UploadedFile::fake()->createWithContent('sheet.png', effectTestPng()),
        'animation' => UploadedFile::fake()->createWithContent('effect.json', json_encode($data)),
        'binary' => UploadedFile::fake()->createWithContent('effect.bin', effectTestBinary()),
    ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('animation');
    expect(app(SettingStore::class)->getArray('game_effect_17'))->toBe([]);
});
