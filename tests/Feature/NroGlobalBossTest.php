<?php

use App\Models\Boss;
use App\Models\CodeNotify;
use App\Models\Notify;
use App\Models\User;
use Database\Seeders\NroNotificationSeeder;

beforeEach(function (): void {
    $this->seed(NroNotificationSeeder::class);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
});

test('unconfigured bosses preserve parsed fields and close only their own alias and server', function (): void {
    $ids = [];
    foreach ([[1, 1], [1, 2], [2, 1]] as [$server, $variant]) {
        $ids[$server][$variant] = $this->postJson('/api/admin-api/nro/notifies', [
            'server_code' => $server, 'content' => 'bOsS Fide Đại Ca '.$variant.' VỪA XUẤT HIỆN TẠI Núi khỉ vàng KHU: Đông', 'occurred_at' => now()->subMinute()->toISOString(),
        ])->assertCreated()->assertJsonPath('data.code', 'BOSS')->assertJsonPath('data.is_boss', true)->assertJsonPath('data.boss_global', true)
            ->assertJsonPath('data.boss_id', null)->assertJsonPath('data.boss_name', 'Fide Đại Ca '.$variant)->assertJsonPath('data.map_name', 'Núi khỉ vàng')
            ->assertJsonPath('data.zone', null)->assertJsonPath('data.zone_name', 'Đông')->json('data.id');
    }
    $death = ['server_code' => 1, 'content' => 'hoainamz86: ĐÃ TIÊU DIỆT ĐƯỢC fide đại ca 1 MỌI NGƯỜI ĐỀU NGƯỠNG MỘ.', 'occurred_at' => now()->toISOString()];
    $this->postJson('/api/admin-api/nro/notifies', $death)->assertOk()->assertJsonPath('status', 'updated')->assertJsonPath('data.id', $ids[1][1])
        ->assertJsonPath('data.char_name', 'hoainamz86')->assertJsonPath('data.respawn_at', null)->assertJsonPath('data.state', 'dead');
    $this->postJson('/api/admin-api/nro/notifies', $death)->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('data.id', $ids[1][1]);
    expect(Notify::findOrFail($ids[1][2])->death_time)->toBeNull();
    expect(Notify::findOrFail($ids[2][1])->death_time)->toBeNull();
    $this->getJson('/api/nro/notifies?server_code=1&code=boss&state=history')->assertOk()->assertJsonCount(2, 'data');
    $this->get('/thong-bao-game?server_code=1&code=boss')->assertOk()->assertSee('Boss global')->assertSee('Boss: Fide Đại Ca 1')->assertSee('Người tiêu diệt: hoainamz86')->assertSee('Không xác định');
    expect(Boss::query()->count())->toBe(0);
});

test('boss word is classified globally regardless of casing without a supported format', function (string $content): void {
    $payload = ['server_code' => 1, 'content' => $content, 'occurred_at' => now()->toISOString()];
    $this->postJson('/api/admin-api/nro/notifies', $payload)->assertCreated()->assertJsonPath('data.code', 'BOSS')->assertJsonPath('data.is_boss', true)
        ->assertJsonPath('data.boss_global', true)->assertJsonPath('data.boss_name', null)->assertJsonPath('data.content', $content);
    $this->postJson('/api/admin-api/nro/notifies', $payload)->assertOk()->assertJsonPath('duplicate', true);
    $this->get('/thong-bao-game?code=BOSS&state=history')->assertOk()->assertSee($content)->assertSee('Boss global');
    $this->assertDatabaseCount('notifies', 1);
})->with(['BOSS đang di chuyển', 'Thông báo bOsS: sắp có sự kiện', 'boss chưa biết tên']);

test('unknown death without a spawn is retained globally', function (): void {
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'Player: đã tiêu diệt được Xên lạ mọi người đều ngưỡng mộ.', 'occurred_at' => now()->toISOString()])
        ->assertCreated()->assertJsonPath('data.boss_global', true)->assertJsonPath('data.boss_name', 'Xên lạ')->assertJsonPath('data.state', 'dead')->assertJsonPath('data.respawn_at', null);
    $this->get('/thong-bao-game')->assertOk()->assertSee('Người tiêu diệt: Player')->assertDontSee('Thời gian xuất hiện');
});

test('boss substring inside another word is not a boss notification', function (): void {
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'bossiness', 'occurred_at' => now()->toISOString()])
        ->assertCreated()->assertJsonPath('data.code', 'OTHER')->assertJsonPath('data.is_boss', false)->assertJsonPath('data.boss_global', false);
});

test('configured aliases boss codes and type keywords compare without case sensitivity', function (): void {
    $boss = Boss::factory()->create(['code' => 'FIDE', 'game_names' => ['FIDE ĐẠI CA 1']]);
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'boss fide đại ca 1 XuẤt HiỆn TạI Núi khỉ vàng', 'occurred_at' => now()->subMinute()->toISOString()])
        ->assertCreated()->assertJsonPath('data.boss_id', $boss->id)->assertJsonPath('data.boss_global', false);
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 2, 'boss_code' => 'fide', 'code' => 'boss', 'content' => 'BOSS Fide Đại Ca 1 xuất hiện tại Núi khỉ vàng', 'occurred_at' => now()->toISOString()])
        ->assertCreated()->assertJsonPath('data.boss_id', $boss->id);
    CodeNotify::query()->where('code', 'MAINTENANCE')->update(['keywords' => ['BẢO TRÌ']]);
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'máy chủ Bảo Trì', 'occurred_at' => now()->toISOString()])
        ->assertCreated()->assertJsonPath('data.code', 'MAINTENANCE')->assertJsonPath('data.content', 'máy chủ Bảo Trì');
});
