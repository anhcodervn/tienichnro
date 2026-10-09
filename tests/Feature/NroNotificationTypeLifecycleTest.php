<?php

use App\Models\Boss;
use App\Models\CodeNotify;
use App\Models\User;
use Database\Seeders\NroNotificationSeeder;

beforeEach(function (): void {
    $this->seed(NroNotificationSeeder::class);
});

test('only administrators can delete notification types', function (): void {
    $type = CodeNotify::query()->firstOrFail();
    $url = '/api/admin-api/nro/notification-types/'.$type->id;
    $this->deleteJson($url)->assertUnauthorized();
    $this->actingAs(User::factory()->create(['role' => 'user']))->deleteJson($url)->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'admin']))->deleteJson($url)->assertNoContent();
    $this->assertSoftDeleted('code_notifies', ['id' => $type->id]);
    $this->deleteJson($url)->assertNotFound();
});

test('default boss and fallback codes are editable without breaking ingestion filters or repeated seeding', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $boss = Boss::factory()->create(['name' => 'Broly 3']);
    foreach (['BOSS' => 'BOSS_NEW', 'OTHER' => 'GENERAL_NEW'] as $old => $new) {
        $type = CodeNotify::query()->where('code', $old)->firstOrFail();
        $this->patchJson('/api/admin-api/nro/notification-types/'.$type->id, ['code' => strtolower($new), 'name' => $type->name, 'type_id' => $type->type_id])->assertOk()->assertJsonPath('data.code', $new)->assertJsonPath('data.id', $type->id);
    }
    $payload = ['server_code' => 1, 'event_id' => 'renamed-boss', 'content' => 'Broly 3 vừa xuất hiện tại Rừng Bamboo khu 5', 'occurred_at' => now()->subMinutes(10)->toIso8601String()];
    $id = $this->postJson('/api/admin-api/nro/notifies', $payload)->assertCreated()->assertJsonPath('data.code', 'BOSS_NEW')->json('data.id');
    $this->postJson('/api/admin-api/nro/notifies', $payload)->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('data.id', $id);
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'Broly 3 vừa bị tiêu diệt bởi Tester', 'occurred_at' => now()->subMinutes(5)->toIso8601String()])->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.code', 'BOSS_NEW');
    $this->get('/thong-bao-game?boss_id='.$boss->id)->assertOk()->assertSee('value="BOSS_NEW"', false);
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'Unclassified message', 'occurred_at' => now()->toIso8601String()])->assertCreated()->assertJsonPath('data.code', 'GENERAL_NEW');
    $this->seed(NroNotificationSeeder::class);
    $this->assertDatabaseCount('code_notifies', 8);
    $this->assertDatabaseMissing('code_notifies', ['code' => 'BOSS']);
    $this->assertDatabaseMissing('code_notifies', ['code' => 'OTHER']);
});

test('deleting a referenced type removes it from configuration and keeps history and retries', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $type = CodeNotify::query()->where('code', 'OTHER')->firstOrFail();
    $payload = ['server_code' => 1, 'content' => 'Historical game message', 'event_id' => 'historical', 'occurred_at' => now()->toIso8601String()];
    $id = $this->postJson('/api/admin-api/nro/notifies', $payload)->assertCreated()->json('data.id');
    $this->deleteJson('/api/admin-api/nro/notification-types/'.$type->id)->assertNoContent();
    $this->getJson('/api/nro/options')->assertOk()->assertJsonMissingPath('data.notification_types')->assertJsonMissingPath('data.bosses');
    expect(collect($this->getJson('/api/admin-api/nro/notification-types')->assertOk()->json('data.codes'))->pluck('id'))->not->toContain($type->id);
    $this->get('/thong-bao-game')->assertOk()->assertSee('Historical game message');
    $this->postJson('/api/admin-api/nro/notifies', $payload)->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('data.id', $id);
    $this->postJson('/api/admin-api/nro/notifies', array_replace($payload, ['content' => 'New unknown message', 'event_id' => 'new']))->assertUnprocessable()->assertJsonValidationErrors('content');
    $this->postJson('/api/admin-api/nro/notifies', array_replace($payload, ['code_id' => $type->id]))->assertUnprocessable()->assertJsonValidationErrors('code_id');
    $this->postJson('/api/admin-api/nro/notifies', array_replace($payload, ['code' => 'OTHER']))->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->seed(NroNotificationSeeder::class);
    expect(CodeNotify::query()->where('system_key', 'OTHER')->exists())->toBeFalse();
    $this->assertDatabaseCount('notifies', 1);
});

test('deleted boss type still permits death updates but blocks new lifecycles without configuration', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    Boss::factory()->create(['name' => 'Broly 3']);
    $id = $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'Broly 3 vừa xuất hiện tại Rừng Bamboo khu 5', 'occurred_at' => now()->subMinutes(10)->toIso8601String()])->assertCreated()->json('data.id');
    $type = CodeNotify::query()->where('system_key', 'BOSS')->firstOrFail();
    $this->deleteJson('/api/admin-api/nro/notification-types/'.$type->id)->assertNoContent();
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'Broly 3 vừa bị tiêu diệt bởi Tester', 'occurred_at' => now()->subMinutes(5)->toIso8601String()])->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.state', 'dead');
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'Broly 3 vừa xuất hiện tại Rừng Bamboo khu 5', 'occurred_at' => now()->toIso8601String()])->assertUnprocessable()->assertJsonValidationErrors('content');
    $this->get('/thong-bao-game')->assertOk()->assertSee('Broly 3')->assertSee('Tester');
});

test('internal roles cannot be edited and default codes must be unique', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $type = CodeNotify::query()->where('code', 'OTHER')->firstOrFail();
    $payload = ['name' => $type->name, 'type_id' => $type->type_id];
    $this->patchJson('/api/admin-api/nro/notification-types/'.$type->id, $payload + ['code' => 'BOSS'])->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->patchJson('/api/admin-api/nro/notification-types/'.$type->id, $payload + ['system_key' => 'BOSS'])->assertUnprocessable()->assertJsonValidationErrors('system_key');
    $this->patchJson('/api/admin-api/nro/notification-types/'.$type->id, $payload + ['code' => 'OTHER'])->assertOk();
});
