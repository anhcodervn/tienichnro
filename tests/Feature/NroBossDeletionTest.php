<?php

use App\Models\Boss;
use App\Models\Notify;
use App\Models\User;
use Database\Seeders\NroNotificationSeeder;

beforeEach(function (): void {
    $this->seed(NroNotificationSeeder::class);
});

test('boss deletion is restricted to administrators and deleted bosses cannot be edited or deleted twice', function (): void {
    $boss = Boss::factory()->create();
    $url = '/api/admin-api/nro/bosses/'.$boss->id;
    $this->deleteJson($url)->assertUnauthorized();
    $this->actingAs(User::factory()->create(['role' => 'user']))->deleteJson($url)->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'admin']))->deleteJson($url)->assertNoContent();
    $this->assertSoftDeleted('bosses', ['id' => $boss->id]);
    $this->deleteJson($url)->assertNotFound();
    $this->patchJson($url, ['name' => 'Changed', 'code' => $boss->code, 'is_active' => true])->assertNotFound();
    $this->postJson('/api/admin-api/nro/bosses', ['name' => 'Duplicate', 'code' => $boss->code, 'is_active' => true])->assertUnprocessable()->assertJsonValidationErrors('code');
});

test('deleting a boss preserves history retries and existing deaths but removes configuration and makes new spawns global', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $boss = Boss::factory()->create(['name' => 'Fide Đại Ca', 'code' => 'FIDE', 'game_names' => ['Fide Đại Ca 1'], 'respawn_seconds' => 1800]);
    $spawn = ['server_code' => 1, 'content' => 'BOSS Fide Đại Ca 1 xuất hiện tại Núi khỉ vàng', 'occurred_at' => now()->subMinutes(10)->toISOString()];
    $id = $this->postJson('/api/admin-api/nro/notifies', $spawn)->assertCreated()->json('data.id');
    $this->deleteJson('/api/admin-api/nro/bosses/'.$boss->id)->assertNoContent();
    $this->getJson('/api/admin-api/nro/bosses')->assertOk()->assertJsonCount(0, 'data');
    $this->get('/thong-bao-game?code=BOSS')->assertOk()->assertSee('Boss: Fide Đại Ca 1')->assertDontSee('value="'.$boss->id.'">Fide Đại Ca', false);
    $this->postJson('/api/admin-api/nro/notifies', $spawn)->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('data.id', $id)->assertJsonPath('data.boss.code', 'FIDE');
    $this->postJson('/api/admin-api/nro/notifies', array_replace($spawn, ['occurred_at' => now()->toISOString()]))->assertCreated()->assertJsonPath('data.boss_global', true);
    $deathTime = now()->startOfSecond()->subMinutes(5);
    $death = ['server_code' => 1, 'content' => 'Player: đã tiêu diệt được Fide Đại Ca 1 mọi người đều ngưỡng mộ.', 'occurred_at' => $deathTime->toISOString()];
    $this->postJson('/api/admin-api/nro/notifies', $death)->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.boss.code', 'FIDE')->assertJsonPath('data.respawn_at', $deathTime->copy()->addSeconds(1800)->toISOString());
    $this->postJson('/api/admin-api/nro/notifies', $death)->assertOk()->assertJsonPath('duplicate', true);
    $this->getJson('/api/admin-api/nro/notifies?boss_id='.$boss->id)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.boss.code', 'FIDE');
    expect(Notify::findOrFail($id)->boss->id)->toBe($boss->id);
    $this->get('/thong-bao-game')->assertOk()->assertSee('Boss: Fide Đại Ca 1')->assertSee('Người tiêu diệt: Player');
    $page = file_get_contents(resource_path('js/pages/admin/nro/bosses/index.vue'));
    expect($page)->toContain('nroNotificationService.deleteBoss', '@click="remove(boss)"', 'showCancelButton: true');
});
