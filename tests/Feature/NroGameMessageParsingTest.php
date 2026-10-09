<?php

use App\Features\NroNotification\Services\NroNotificationFeedService;
use App\Models\Boss;
use App\Models\CodeNotify;
use App\Models\Notify;
use App\Models\User;
use Database\Seeders\NroNotificationSeeder;

beforeEach(function (): void {
    $this->seed(NroNotificationSeeder::class);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
});

test('game options contain only active servers', function (): void {
    $response = $this->getJson('/api/nro/options')->assertOk()->assertJsonCount(22, 'data.servers');
    expect(array_keys($response->json('data')))->toBe(['servers']);
});

test('raw game messages extract names without requiring client boss or notification codes', function (): void {
    $boss = Boss::factory()->create(['name' => 'Fide Đại Ca 1', 'respawn_seconds' => 1800]);
    $spawn = ['server_code' => 1, 'content' => 'BOSS Fide Đại Ca 1 vừa xuất hiện tại Núi khỉ vàng', 'occurred_at' => now()->subMinutes(10)->toIso8601String()];
    $id = $this->postJson('/api/admin-api/nro/notifies', $spawn)->assertCreated()
        ->assertJsonPath('data.boss_id', $boss->id)->assertJsonPath('data.boss_name', 'Fide Đại Ca 1')
        ->assertJsonPath('data.map_name', 'Núi khỉ vàng')->assertJsonPath('data.zone', null)->json('data.id');
    $this->postJson('/api/admin-api/nro/notifies', $spawn)->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('data.id', $id);
    $death = ['server_code' => 1, 'content' => 'hoainamz86: Đã tiêu diệt được Fide Đại Ca 1 mọi người đều ngưỡng mộ.', 'occurred_at' => now()->subMinutes(5)->toIso8601String()];
    $this->postJson('/api/admin-api/nro/notifies', $death)->assertOk()->assertJsonPath('status', 'updated')
        ->assertJsonPath('data.id', $id)->assertJsonPath('data.char_name', 'hoainamz86')->assertJsonPath('data.killed_by', 'hoainamz86');
    $this->assertDatabaseHas('notifies', ['id' => $id, 'boss_name' => 'Fide Đại Ca 1', 'map_name' => 'Núi khỉ vàng', 'char_name' => 'hoainamz86', 'content' => $spawn['content'], 'death_content' => $death['content']]);
    $this->assertDatabaseCount('notifies', 1);
    $boss->update(['name' => 'Tên cấu hình mới']);
    $this->get('/thong-bao-game')->assertOk()->assertSee('Boss: Fide Đại Ca 1')->assertSee('Map: Núi khỉ vàng')->assertSee('Người tiêu diệt: hoainamz86');
});

test('character first death messages target the correct boss and server', function (): void {
    Boss::factory()->create(['name' => 'Fide Đại Ca 1']);
    Boss::factory()->create(['name' => 'Fide Đại Ca 2']);
    $time = now()->subMinutes(10)->toIso8601String();
    foreach ([[1, 'Fide Đại Ca 1'], [1, 'Fide Đại Ca 2'], [2, 'Fide Đại Ca 2']] as [$server, $name]) {
        $this->postJson('/api/admin-api/nro/notifies', ['server_code' => $server, 'content' => 'BOSS '.$name.' vừa xuất hiện tại Núi khỉ vàng khu 3', 'occurred_at' => $time])->assertCreated()->assertJsonPath('data.zone', 3);
    }
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'hoainamz86: Đã tiêu diệt được Fide Đại Ca 2 mọi người đều ngưỡng mộ.', 'occurred_at' => now()->toIso8601String()])->assertOk()->assertJsonPath('data.boss_name', 'Fide Đại Ca 2')->assertJsonPath('data.char_name', 'hoainamz86');
    expect(Notify::query()->whereNotNull('death_time')->count())->toBe(1);
    expect(Notify::query()->living()->count())->toBe(2);
});

test('historical name backfill preserves existing extracted values and can be rerun', function (): void {
    Boss::factory()->create(['name' => 'Fide Đại Ca 1']);
    $id = $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'BOSS Fide Đại Ca 1 vừa xuất hiện tại Núi khỉ vàng', 'occurred_at' => now()->toIso8601String()])->assertCreated()->json('data.id');
    $notify = Notify::findOrFail($id);
    $notify->update(['boss_name' => null, 'char_name' => null, 'killed_by' => 'LegacyPlayer']);
    $migration = require database_path('migrations/2026_10_08_143225_backfill_notification_boss_names.php');
    $migration->up();
    expect($notify->fresh()->boss_name)->toBe('Fide Đại Ca 1');
    expect($notify->fresh()->char_name)->toBe('LegacyPlayer');
    $notify->update(['boss_name' => 'Historical boss', 'char_name' => 'Historical player']);
    $migration->up();
    expect($notify->fresh()->boss_name)->toBe('Historical boss');
    expect($notify->fresh()->char_name)->toBe('Historical player');
});

test('message keywords parse Unicode case whitespace map and textual or numeric zones', function (string $content, ?int $zone, string $zoneName): void {
    Boss::factory()->create(['name' => 'Fide Đại Ca 1', 'respawn_seconds' => 1800]);
    $id = $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => $content, 'occurred_at' => now()->subMinutes(10)->toIso8601String()])
        ->assertCreated()->assertJsonPath('data.boss_name', 'fide ĐẠI ca 1')->assertJsonPath('data.map_name', 'Núi Khỉ Vàng')
        ->assertJsonPath('data.zone', $zone)->assertJsonPath('data.zone_name', $zoneName)->assertJsonPath('data.content', $content)->json('data.id');
    $deathTime = now()->startOfSecond()->subMinutes(5);
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'HoaiNamZ86: đã TIÊU DIỆT được FIDE đại CA 1 MỌI NGƯỜI ĐỀU NGƯỠNG MỘ.', 'occurred_at' => $deathTime->toIso8601String()])
        ->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.char_name', 'HoaiNamZ86')
        ->assertJsonPath('data.respawn_at', $deathTime->addSeconds(1800)->toISOString());
    $html = app(NroNotificationFeedService::class)->snapshot(['server_code' => 1])['html'];
    expect($html)->toContain('data-nro-respawn=', 'khu '.$zoneName);
    expect(strpos($html, 'data-nro-respawn='))->toBeLessThan(strpos($html, '<details'));
})->with([
    'text zone' => ['boss fide ĐẠI ca 1 XUẤT HIỆN TẠI Núi Khỉ Vàng, KHU: Đặc biệt.', null, 'Đặc biệt'],
    'numeric zone' => ["BOSS  fide ĐẠI ca 1\nVỪA XUẤT HIỆN TẠI: Núi Khỉ Vàng KHU:5", 5, '5'],
    'old zone syntax' => ['Boss fide ĐẠI ca 1 xuất hiện tại Núi Khỉ Vàng khu 05', 5, '05'],
]);

test('keyword type code drives filtering while boss configuration drives respawn', function (): void {
    $boss = Boss::factory()->create(['name' => 'Fide Đại Ca 1', 'respawn_seconds' => 900]);
    $type = CodeNotify::query()->create(['code' => 'FIDE', 'name' => 'Boss Fide', 'type_id' => CodeNotify::query()->firstOrFail()->type_id, 'keywords' => ['fide đại ca'], 'additional_filters' => ['boss', 'state']]);
    $id = $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'BOSS Fide Đại Ca 1 xuất hiện tại Núi khỉ vàng khu: 2', 'occurred_at' => now()->subMinutes(10)->toIso8601String()])
        ->assertCreated()->assertJsonPath('data.code_id', $type->id)->assertJsonPath('data.boss_id', $boss->id)->json('data.id');
    $time = now()->startOfSecond()->subMinutes(5);
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'Player: đã tiêu diệt được Fide Đại Ca 1 mọi người đều ngưỡng mộ.', 'occurred_at' => $time->toIso8601String()])
        ->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.code', 'FIDE')->assertJsonPath('data.respawn_at', $time->addSeconds(900)->toISOString());
    $this->getJson('/api/nro/notifies?code=FIDE&server_code=1')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/nro/notifies?code=OTHER&server_code=1')->assertOk()->assertJsonCount(0, 'data');
});

test('parsed zone values respect storage limits', function (string $zone): void {
    Boss::factory()->create(['name' => 'Fide Đại Ca 1']);
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'BOSS Fide Đại Ca 1 xuất hiện tại Núi khỉ vàng khu: '.$zone, 'occurred_at' => now()->toIso8601String()])
        ->assertUnprocessable()->assertJsonValidationErrors('content');
    $this->assertDatabaseCount('notifies', 0);
})->with(['numeric overflow' => ['65536'], 'text overflow' => [str_repeat('x', 101)]]);
