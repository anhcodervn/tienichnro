<?php

use App\Models\NroServer;
use App\Models\User;
use Database\Seeders\NroNotificationSeeder;

test('server catalog matches the supplied twenty two rows and next primary key', function (): void {
    $names = ['Vũ trụ 1', 'Vũ trụ 2', 'Vũ trụ 3', 'Vũ trụ 4', 'Vũ trụ 5', 'Vũ trụ 6', 'Vũ trụ 7', 'Vũ trụ 8', 'Vũ trụ 9', 'Vũ trụ 10', 'Vũ trụ 11', 'Vũ trụ 12', 'Võ đài liên vũ trụ', 'Universe 1', 'Naga', 'Super 1', 'Super 2', 'Vũ trụ 13', 'VIP 2', 'Vũ trụ 14', 'Vũ trụ 15', 'Super 3'];
    $this->assertDatabaseCount('servers', 22);
    foreach ($names as $index => $name) {
        $this->assertDatabaseHas('servers', ['id' => $index + 1, 'server_code' => $index + 1, 'code' => 'sv'.($index + 1), 'name' => $name, 'is_active' => true, 'sort_order' => $index + 1]);
    }
    expect(NroServer::factory()->create()->id)->toBe(23);
});

test('catalog sync preserves existing primary keys notification references and custom servers', function (): void {
    NroServer::query()->delete();
    $server = NroServer::factory()->create(['id' => 100, 'server_code' => 1, 'name' => 'Old name', 'is_active' => false]);
    $custom = NroServer::factory()->create(['id' => 200, 'server_code' => 900, 'code' => 'custom']);
    $server->update(['is_active' => true]);
    $this->seed(NroNotificationSeeder::class);
    $notifyId = $this->actingAs(User::factory()->create(['role' => 'admin']))->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'event_id' => 'catalog-sync', 'content' => 'Catalog notice', 'occurred_at' => now()->toIso8601String()])->assertCreated()->json('data.id');
    $migration = require database_path('migrations/2026_10_08_113205_populate_default_nro_servers.php');
    $migration->up();
    $migration->up();
    $this->assertDatabaseCount('servers', 23);
    $this->assertDatabaseHas('servers', ['id' => 100, 'server_code' => 1, 'code' => 'sv1', 'name' => 'Vũ trụ 1', 'sort_order' => 1]);
    $this->assertDatabaseHas('servers', ['id' => $custom->id, 'code' => 'custom']);
    $this->assertDatabaseHas('notifies', ['id' => $notifyId, 'server_id' => 100]);
    $migration->down();
    $this->assertDatabaseHas('notifies', ['id' => $notifyId, 'server_id' => 100]);
});

test('conflicting text codes stop catalog sync before changing any rows', function (): void {
    NroServer::query()->where('server_code', 22)->update(['code' => 'temporary']);
    NroServer::factory()->create(['server_code' => 900, 'code' => 'sv22']);
    NroServer::query()->where('server_code', 1)->update(['name' => 'Keep this name']);
    $migration = require database_path('migrations/2026_10_08_113205_populate_default_nro_servers.php');
    expect(fn () => $migration->up())->toThrow(RuntimeException::class);
    $this->assertDatabaseHas('servers', ['server_code' => 1, 'name' => 'Keep this name']);
});

test('admin edits text codes and sort order and public options follow that order', function (): void {
    $server = NroServer::query()->where('server_code', 22)->firstOrFail();
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->patchJson('/api/admin-api/nro/servers/'.$server->id, ['code' => 'super-3', 'sort_order' => 0])->assertOk()->assertJsonPath('data.code', 'super-3')->assertJsonPath('data.sort_order', 0)->assertJsonPath('data.server_code', 22);
    $this->getJson('/api/nro/options')->assertOk()->assertJsonPath('data.servers.0.id', $server->id);
    $this->getJson('/api/admin-api/nro/servers')->assertOk()->assertJsonPath('data.0.id', $server->id);
    $this->get('/thong-bao-game')->assertOk();
    $this->patchJson('/api/admin-api/nro/servers/'.$server->id, ['code' => 'super-3'])->assertOk();
    $this->patchJson('/api/admin-api/nro/servers/'.$server->id, ['code' => 'sv1'])->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->patchJson('/api/admin-api/nro/servers/'.$server->id, ['code' => 'invalid space', 'sort_order' => -1])->assertUnprocessable()->assertJsonValidationErrors(['code', 'sort_order']);
    $this->patchJson('/api/admin-api/nro/servers/'.$server->id, ['sort_order' => 4294967296])->assertUnprocessable()->assertJsonValidationErrors('sort_order');
});
