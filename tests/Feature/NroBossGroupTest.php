<?php

use App\Models\Boss;
use App\Models\Notify;
use App\Models\User;
use Database\Seeders\NroNotificationSeeder;

beforeEach(function (): void {
    $this->seed(NroNotificationSeeder::class);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
});

test('boss groups normalize comma separated game names and preserve aliases on unrelated edits', function (): void {
    $payload = ['code' => 'FIDE_DAI_CA', 'name' => 'Fide Đại Ca', 'game_names' => ' Fide Đại Ca 1, fide đại ca 1, , Fide  Đại Ca 2, Fide Đại Ca 3,', 'respawn_seconds' => 1800, 'is_active' => true];
    $id = $this->postJson('/api/admin-api/nro/bosses', $payload)->assertCreated()->assertJsonPath('data.game_names', ['Fide Đại Ca 1', 'Fide Đại Ca 2', 'Fide Đại Ca 3'])->json('data.id');
    unset($payload['game_names']);
    $this->patchJson('/api/admin-api/nro/bosses/'.$id, $payload)->assertOk()->assertJsonPath('data.game_names', ['Fide Đại Ca 1', 'Fide Đại Ca 2', 'Fide Đại Ca 3']);
    $this->patchJson('/api/admin-api/nro/bosses/'.$id, $payload + ['game_names' => ['Fide Đại Ca 4']])->assertOk()->assertJsonPath('data.game_names', ['Fide Đại Ca 4']);
    $page = file_get_contents(resource_path('js/pages/admin/nro/bosses/index.vue'));
    expect($page)->toContain('v-model="draft.game_names"', 'Tên nhóm Boss trong bộ lọc', 'cách nhau bằng dấu phẩy');
});

test('aliases share a filter and interval but death only closes the exact alias on its server', function (): void {
    $boss = Boss::factory()->create(['code' => 'FIDE_DAI_CA', 'name' => 'Fide Đại Ca', 'game_names' => ['Fide Đại Ca 1', 'Fide Đại Ca 2', 'Fide Đại Ca 3'], 'respawn_seconds' => 1800]);
    $ids = [];
    foreach ([[1, 1], [1, 2], [1, 3], [2, 1]] as [$server, $variant]) {
        $ids[$server][$variant] = $this->postJson('/api/admin-api/nro/notifies', ['server_code' => $server, 'content' => 'BOSS Fide Đại Ca '.$variant.' xuất hiện tại Núi khỉ vàng khu: 2', 'occurred_at' => now()->subMinutes(10 - $variant)->toISOString()])
            ->assertCreated()->assertJsonPath('data.boss_id', $boss->id)->assertJsonPath('data.boss.code', 'FIDE_DAI_CA')->json('data.id');
    }
    $time = now()->startOfSecond();
    $death = ['server_code' => 1, 'content' => 'Player: đã tiêu diệt được fide đại ca 1 mọi người đều ngưỡng mộ.', 'occurred_at' => $time->toISOString()];
    $this->postJson('/api/admin-api/nro/notifies', $death)->assertOk()->assertJsonPath('data.id', $ids[1][1])->assertJsonPath('data.respawn_at', $time->copy()->addSeconds(1800)->toISOString());
    expect(Notify::findOrFail($ids[1][2])->death_time)->toBeNull();
    expect(Notify::findOrFail($ids[1][3])->death_time)->toBeNull();
    expect(Notify::findOrFail($ids[2][1])->death_time)->toBeNull();
    $this->postJson('/api/admin-api/nro/notifies', $death)->assertOk()->assertJsonPath('duplicate', true);
    $this->getJson('/api/nro/notifies?server_code=1&code=BOSS&boss_id='.$boss->id)->assertOk()->assertJsonCount(3, 'data');
    $this->get('/thong-bao-game?server_code=1&code=BOSS&boss_id='.$boss->id)->assertOk()->assertSee('Boss: Fide Đại Ca 1')->assertSee('Boss: Fide Đại Ca 2')->assertSee('Boss: Fide Đại Ca 3')->assertSee('data-nro-respawn=', false);
    $boss->update(['respawn_seconds' => 60]);
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'Player: đã tiêu diệt được Fide Đại Ca 2 mọi người đều ngưỡng mộ.', 'occurred_at' => $time->toISOString()])->assertOk()->assertJsonPath('data.id', $ids[1][2])->assertJsonPath('data.respawn_at', $time->copy()->addSeconds(60)->toISOString());
    expect(Notify::findOrFail($ids[1][1])->respawn_at->toISOString())->toBe($time->copy()->addSeconds(1800)->toISOString());
});

test('unknown or ambiguous aliases never close another variant in the shared group', function (): void {
    Boss::factory()->create(['name' => 'Fide', 'game_names' => ['Fide Đại Ca 1', 'Fide Đại Ca 2']]);
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'BOSS Fide Đại Ca 2 xuất hiện tại Núi khỉ vàng', 'occurred_at' => now()->subMinute()->toISOString()])->assertCreated();
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'Player: đã tiêu diệt được Fide Đại Ca 1 mọi người đều ngưỡng mộ.', 'occurred_at' => now()->toISOString()])->assertOk()->assertJsonPath('status', 'ignored_no_living_boss');
    Boss::factory()->create(['name' => 'Another group', 'game_names' => ['fide đại ca 2']]);
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'BOSS Fide Đại Ca 2 xuất hiện tại Núi khỉ vàng', 'occurred_at' => now()->toISOString()])->assertCreated()->assertJsonPath('data.boss_global', true);
    expect(Notify::query()->living()->count())->toBe(2);
});

test('invalid game names are rejected', function (mixed $names, string $field): void {
    $this->postJson('/api/admin-api/nro/bosses', ['code' => 'FIDE', 'name' => 'Fide', 'game_names' => $names, 'is_active' => true])->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'empty' => ['', 'game_names'],
    'nested values' => [[[['invalid']]], 'game_names.0'],
    'non string' => [[123], 'game_names.0'],
    'too long' => [[str_repeat('x', 101)], 'game_names.0'],
    'too many' => [array_map(fn (int $index): string => 'Boss '.$index, range(1, 51)), 'game_names'],
]);
