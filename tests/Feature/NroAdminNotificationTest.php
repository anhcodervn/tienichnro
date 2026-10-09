<?php

use App\Models\Boss;
use App\Models\CodeNotify;
use App\Models\User;
use Database\Seeders\NroNotificationSeeder;

beforeEach(function (): void {
    $this->seed(NroNotificationSeeder::class);
});

test('notification management endpoints require administrator authorization', function (): void {
    $type = CodeNotify::query()->firstOrFail();
    $payload = ['name' => 'New label', 'type_id' => $type->type_id];
    $this->getJson('/api/admin-api/nro/notification-types')->assertUnauthorized();
    $this->postJson('/api/admin-api/nro/notification-types', $payload + ['code' => 'GAME_EVENT'])->assertUnauthorized();
    $this->getJson('/api/admin-api/nro/notifies')->assertUnauthorized();
    $this->patchJson('/api/admin-api/nro/notification-types/'.$type->id, $payload)->assertUnauthorized();
    $this->actingAs(User::factory()->create(['role' => 'user']));
    $this->getJson('/api/admin-api/nro/notification-types')->assertForbidden();
    $this->postJson('/api/admin-api/nro/notification-types', $payload + ['code' => 'GAME_EVENT'])->assertForbidden();
    $this->getJson('/api/admin-api/nro/notifies')->assertForbidden();
    $this->patchJson('/api/admin-api/nro/notification-types/'.$type->id, $payload)->assertForbidden();
});

test('administrators edit notification labels and validate changed API codes', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $type = CodeNotify::query()->where('code', 'OTHER')->firstOrFail();
    $this->getJson('/api/admin-api/nro/notification-types')->assertOk()->assertJsonCount(8, 'data.codes')->assertJsonCount(2, 'data.groups')->assertJsonStructure(['data' => ['codes' => [['type' => ['id', 'code', 'name']]]]]);
    $this->patchJson('/api/admin-api/nro/notification-types/'.$type->id, ['name' => 'Thông báo khác', 'type_id' => $type->type_id])->assertOk()->assertJsonPath('data.code', 'OTHER')->assertJsonPath('data.name', 'Thông báo khác');
    $this->patchJson('/api/admin-api/nro/notification-types/'.$type->id, ['name' => 'Invalid', 'type_id' => 999999, 'code' => 'BOSS'])->assertUnprocessable()->assertJsonValidationErrors(['type_id', 'code']);
    $this->patchJson('/api/admin-api/nro/notification-types/'.$type->id, ['name' => '', 'type_id' => $type->type_id])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->patchJson('/api/admin-api/nro/notification-types/'.$type->id, ['name' => 'Keep code', 'type_id' => $type->type_id, 'code' => null])->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->patchJson('/api/admin-api/nro/notification-types/999999', ['name' => 'Missing', 'type_id' => $type->type_id])->assertNotFound();
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'code' => 'OTHER', 'content' => 'Game message', 'occurred_at' => now()->toIso8601String()])->assertCreated();
    $this->get('/thong-bao-game')->assertOk()->assertSee('Thông báo khác')->assertSee('Game message');
});

test('new notification types can receive game API events and be edited without creating messages manually', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $group = CodeNotify::query()->where('code', 'OTHER')->firstOrFail()->type_id;
    $payload = ['code' => ' game_event ', 'name' => 'Sự kiện game', 'type_id' => $group, 'additional_filters' => []];
    $id = $this->postJson('/api/admin-api/nro/notification-types', $payload)->assertCreated()->assertJsonPath('data.code', 'GAME_EVENT')->assertJsonPath('data.additional_filters', [])->assertJsonPath('data.type.id', $group)->json('data.id');
    $this->assertDatabaseCount('notifies', 0);
    $this->get('/thong-bao-game')->assertOk()->assertSee('Sự kiện game');
    $this->postJson('/api/admin-api/nro/notification-types', $payload)->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->patchJson('/api/admin-api/nro/notification-types/'.$id, ['name' => 'Sự kiện mới', 'type_id' => $group, 'additional_filters' => ['boss']])->assertOk()->assertJsonPath('data.code', 'GAME_EVENT')->assertJsonPath('data.additional_filters', ['boss']);
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'code' => 'GAME_EVENT', 'event_id' => 'new-type', 'content' => 'Game event from collector', 'occurred_at' => now()->toIso8601String()])->assertCreated()->assertJsonPath('data.code_id', $id)->assertJsonPath('data.code', 'GAME_EVENT');
    $this->getJson('/api/nro/notifies?code=GAME_EVENT')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'GAME_EVENT');
    $this->get('/thong-bao-game?code=GAME_EVENT')->assertOk()->assertSee('Sự kiện mới')->assertSee('Game event from collector');
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'code' => 'UNKNOWN_TYPE', 'content' => 'Unknown type', 'occurred_at' => now()->toIso8601String()])->assertUnprocessable()->assertJsonValidationErrors('code');
});

test('invalid notification type creation is rejected', function (array $invalid, string $field): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $group = CodeNotify::query()->where('code', 'OTHER')->firstOrFail()->type_id;
    $this->postJson('/api/admin-api/nro/notification-types', array_replace(['code' => 'GAME_EVENT', 'name' => 'Game event', 'type_id' => $group, 'additional_filters' => []], $invalid))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'empty code' => [['code' => ''], 'code'],
    'invalid code' => [['code' => 'bad code'], 'code'],
    'oversized code' => [['code' => str_repeat('X', 65)], 'code'],
    'reserved spawn alias' => [['code' => 'boss_appear'], 'code'],
    'reserved death alias' => [['code' => 'BOSS_DIE'], 'code'],
    'empty name' => [['name' => ''], 'name'],
    'unknown group' => [['type_id' => 999999], 'type_id'],
    'unknown filter' => [['additional_filters' => ['invalid']], 'additional_filters.0'],
]);

test('admin notification list paginates filters and includes boss lifecycle data from the game API', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $boss = Boss::factory()->create(['name' => 'Broly 3', 'respawn_seconds' => 1800]);
    $spawnId = $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'Broly 3 vừa xuất hiện tại Rừng Bamboo khu 5', 'occurred_at' => '2026-10-08T10:00:00+07:00'])->assertCreated()->json('data.id');
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'Broly 3 vừa bị tiêu diệt bởi Tester', 'occurred_at' => '2026-10-08T10:05:00+07:00'])->assertOk();
    foreach ([1, 2] as $server) {
        $this->postJson('/api/admin-api/nro/notifies', ['server_code' => $server, 'code' => 'OTHER', 'content' => 'Server message '.$server, 'occurred_at' => '2026-10-08T10:10:00+07:00'])->assertCreated();
    }
    $this->getJson('/api/admin-api/nro/notifies?per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 3)->assertJsonPath('meta.last_page', 3);
    $this->getJson('/api/admin-api/nro/notifies?per_page=1&page=3')->assertOk()->assertJsonPath('data.0.id', $spawnId);
    $this->getJson('/api/admin-api/nro/notifies?server_code=1&boss_id='.$boss->id.'&code=BOSS&state=history')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.killed_by', 'Tester')->assertJsonPath('data.0.map_name', 'Rừng Bamboo')->assertJsonPath('data.0.respawn_at', '2026-10-08T03:35:00.000000Z');
    $this->getJson('/api/admin-api/nro/notifies?per_page=101&state=invalid')->assertUnprocessable()->assertJsonValidationErrors(['per_page', 'state']);
    $this->assertDatabaseCount('notifies', 3);
});

test('game notification administration has separate pages and no manual message creation UI', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    foreach (['bosses', 'notification-types', 'notifies'] as $page) {
        $this->get('/admin/nro/'.$page)->assertOk();
    }
    $bossPage = file_get_contents(resource_path('js/pages/admin/nro/bosses/index.vue'));
    $typePage = file_get_contents(resource_path('js/pages/admin/nro/notification-types/index.vue'));
    $notifyPage = file_get_contents(resource_path('js/pages/admin/nro/notifies/index.vue'));
    $service = file_get_contents(resource_path('js/services/nro-notification.service.ts'));
    $menu = file_get_contents(resource_path('js/layouts/admin/sidebar/navigation.ts'));
    expect($menu)->toContain("label: 'Thông báo game'", "href: '/admin/nro/notification-types'", "href: '/admin/nro/notifies'", "href: '/admin/nro/bosses'");
    expect($bossPage)->not->toContain('Gửi thông báo kiểm tra', 'nroNotificationService.ingest', 'event_id');
    expect($bossPage)->toContain('<Dialog :open="showForm"', ':initial-focus="bossNameInput"', '<DialogPanel', '<DialogTitle', '@click="add"', '@click="edit(boss)"', '@close="close"', 'role="alert"', '<fieldset :disabled="saving"');
    expect($bossPage)->not->toContain('scrollIntoView', 'ref="bossForm"');
    expect($typePage)->toContain('Thêm loại thông báo', 'Sửa loại thông báo', 'nroNotificationService.createNotificationType', 'nroNotificationService.updateNotificationType');
    expect($typePage)->toContain('nroNotificationService.deleteNotificationType', '@click="remove(type)"')->not->toContain(':disabled="editing !== null"');
    expect($typePage)->toContain('<Dialog :open="showForm"', ':initial-focus="nameInput"', '<DialogPanel', '<DialogTitle', '@close="close"', 'role="alert"');
    expect($typePage)->not->toContain('scrollIntoView', 'ref="form"');
    expect($notifyPage)->not->toContain('<textarea', 'saveNotify', 'ingest(');
    expect($service)->not->toContain("api.post('/api/admin-api/nro/notifies'");
    $this->postJson('/api/admin-api/nro/bosses', ['code' => 'CONFIG_BOSS', 'name' => 'Config Boss', 'respawn_seconds' => 600, 'is_active' => true])->assertCreated();
    $this->assertDatabaseCount('notifies', 0);
});
