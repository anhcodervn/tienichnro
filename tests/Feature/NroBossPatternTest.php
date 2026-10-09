<?php

use App\Models\Boss;
use App\Models\Notify;
use App\Models\User;
use Database\Seeders\NroNotificationSeeder;

beforeEach(function (): void {
    $this->seed(NroNotificationSeeder::class);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
});

test('numeric patterns are saved through boss management and track exact observed lifecycles', function (): void {
    $bossId = $this->postJson('/api/admin-api/nro/bosses', ['code' => 'SUPER_BROLY', 'name' => 'Super Broly', 'game_names' => 'Super Broly [x], Broly [x]', 'respawn_seconds' => 1800, 'is_active' => true])
        ->assertCreated()->assertJsonPath('data.game_names', ['Super Broly [x]', 'Broly [x]'])->json('data.id');
    $ids = [];
    foreach ([[1, 27], [1, 28], [2, 27]] as [$server, $number]) {
        $ids[$server][$number] = $this->postJson('/api/admin-api/nro/notifies', ['server_code' => $server, 'content' => 'BOSS Super Broly '.$number.' xuất hiện tại Núi khỉ vàng', 'occurred_at' => now()->subMinute()->toISOString()])
            ->assertCreated()->assertJsonPath('data.boss_id', $bossId)->assertJsonPath('data.boss_name', 'Super Broly '.$number)->assertJsonPath('data.boss_global', false)->json('data.id');
    }
    $time = now()->startOfSecond();
    $death = ['server_code' => 1, 'content' => 'Player: đã tiêu diệt được super broly 27 mọi người đều ngưỡng mộ.', 'occurred_at' => $time->toISOString()];
    $this->postJson('/api/admin-api/nro/notifies', $death)->assertOk()->assertJsonPath('data.id', $ids[1][27])->assertJsonPath('data.respawn_at', $time->copy()->addSeconds(1800)->toISOString());
    $this->postJson('/api/admin-api/nro/notifies', $death)->assertOk()->assertJsonPath('duplicate', true);
    expect(Notify::findOrFail($ids[1][28])->death_time)->toBeNull();
    expect(Notify::findOrFail($ids[2][27])->death_time)->toBeNull();
    $this->getJson('/api/nro/notifies?server_code=1&code=BOSS&boss_id='.$bossId)->assertOk()->assertJsonCount(2, 'data');
    $this->get('/thong-bao-game?server_code=1&code=BOSS&boss_id='.$bossId)->assertOk()->assertSee('Boss: Super Broly 27')->assertSee('Boss: Super Broly 28')->assertSee('data-nro-respawn=', false);
    expect(file_get_contents(resource_path('js/pages/admin/nro/bosses/index.vue')))->toContain('Dùng [x] cho số bất kỳ');
});

test('patterns match only numeric placeholders and escape literal regex characters', function (string $pattern, string $name, bool $matches): void {
    $boss = Boss::factory()->create(['game_names' => [$pattern]]);
    $response = $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'BOSS '.$name.' xuất hiện tại Núi khỉ vàng', 'occurred_at' => now()->toISOString()])->assertCreated();
    $response->assertJsonPath('data.boss_id', $matches ? $boss->id : null)->assertJsonPath('data.boss_global', ! $matches)->assertJsonPath('data.boss_name', $name);
})->with([
    'zero' => ['Broly [x]', 'Broly 0', true],
    'many digits' => ['Broly [x]', 'Broly 123456', true],
    'unicode casing and whitespace' => ['  XÊN   [X]  ', 'xên 27', true],
    'multiple placeholders' => ['Boss [x] cấp [x]', 'Boss 2 cấp 18', true],
    'literal regex' => ['Broly (VIP)+.[x]~', 'Broly (VIP)+.25~', true],
    'regex must not execute' => ['Broly (VIP)+.[x]~', 'Broly VIPa25~', false],
    'missing number' => ['Broly [x]', 'Broly', false],
    'letters' => ['Broly [x]', 'Broly abc', false],
    'negative' => ['Broly [x]', 'Broly -2', false],
    'decimal' => ['Broly [x]', 'Broly 2.5', false],
    'extra suffix' => ['Broly [x]', 'Broly 27 VIP', false],
    'extra prefix' => ['Broly [x]', 'Super Broly 27', false],
    'unicode digits' => ['Broly [x]', 'Broly ２７', false],
]);

test('exact aliases take precedence and ambiguous patterns stay global', function (): void {
    Boss::factory()->create(['game_names' => ['Broly [x]']]);
    $exact = Boss::factory()->create(['game_names' => ['BROLY 27']]);
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'BOSS Broly 27 xuất hiện tại Núi khỉ vàng', 'occurred_at' => now()->toISOString()])
        ->assertCreated()->assertJsonPath('data.boss_id', $exact->id);
    Boss::factory()->create(['game_names' => ['broly [X]']]);
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'BOSS Broly 28 xuất hiện tại Núi khỉ vàng', 'occurred_at' => now()->toISOString()])
        ->assertCreated()->assertJsonPath('data.boss_global', true)->assertJsonPath('data.boss_id', null);
});
