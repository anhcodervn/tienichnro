<?php

use App\Models\NroServer;
use App\Models\User;
use Database\Seeders\NroNotificationSeeder;

beforeEach(function (): void {
    $this->seed(NroNotificationSeeder::class);
});

test('only administrators can list create and update servers', function (): void {
    $server = NroServer::factory()->create();
    $payload = ['name' => 'New server', 'server_code' => 4000, 'is_active' => true];
    $this->getJson('/api/admin-api/nro/servers')->assertUnauthorized();
    $this->postJson('/api/admin-api/nro/servers', $payload)->assertUnauthorized();
    $this->patchJson('/api/admin-api/nro/servers/'.$server->id, $payload)->assertUnauthorized();
    $this->actingAs(User::factory()->create());
    $this->getJson('/api/admin-api/nro/servers')->assertForbidden();
    $this->postJson('/api/admin-api/nro/servers', $payload)->assertForbidden();
    $this->patchJson('/api/admin-api/nro/servers/'.$server->id, $payload)->assertForbidden();
});

test('administrators can create and edit servers while preserving the primary key', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $response = $this->postJson('/api/admin-api/nro/servers', ['name' => 'Custom server', 'server_code' => 4000, 'is_active' => true, 'id' => 999999])->assertCreated();
    $id = $response->json('data.id');
    expect($id)->not->toBe(999999);
    $this->patchJson('/api/admin-api/nro/servers/'.$id, ['name' => 'Renamed server', 'server_code' => 4000, 'is_active' => false, 'id' => 999999])
        ->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.server_code', 4000)->assertJsonPath('data.is_active', false);
    $this->getJson('/api/admin-api/nro/servers')->assertOk()->assertJsonFragment(['id' => $id, 'name' => 'Renamed server', 'is_active' => false]);
    $this->get('/admin/nro/servers')->assertOk();
    $this->patchJson('/api/admin-api/nro/servers/999999999', ['is_active' => true])->assertNotFound();
});

test('server codes support both boundaries of an unsigned integer', function (int $code): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->postJson('/api/admin-api/nro/servers', ['name' => 'Boundary server', 'server_code' => $code, 'is_active' => true])
        ->assertCreated()->assertJsonPath('data.server_code', $code);
})->with([0, 4294967295]);

test('invalid server attributes and duplicate codes are rejected', function (array $invalid, string $field): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->postJson('/api/admin-api/nro/servers', array_replace(['name' => 'New server', 'server_code' => 4000, 'is_active' => true], $invalid))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'negative code' => [['server_code' => -1], 'server_code'],
    'overflow code' => [['server_code' => 4294967296], 'server_code'],
    'fractional code' => [['server_code' => 2.5], 'server_code'],
    'duplicate code' => [['server_code' => 1], 'server_code'],
    'blank name' => [['name' => '   '], 'name'],
    'long name' => [['name' => str_repeat('a', 101)], 'name'],
    'invalid state' => [['is_active' => 'invalid'], 'is_active'],
]);

test('updating a server cannot take another server code', function (): void {
    $server = NroServer::query()->where('server_code', 1)->firstOrFail();
    $this->actingAs(User::factory()->create(['role' => 'admin']))->patchJson('/api/admin-api/nro/servers/'.$server->id, ['server_code' => 2])
        ->assertUnprocessable()->assertJsonValidationErrors('server_code');
    expect($server->fresh()->server_code)->toBe(1);
});

test('switching a server off blocks new events and preserves history across renaming and reactivation', function (): void {
    $server = NroServer::query()->where('server_code', 1)->firstOrFail();
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $payload = ['server_code' => 1, 'event_id' => 'server-test-event', 'content' => 'Server notice', 'occurred_at' => now()->toIso8601String()];
    $notifyId = $this->postJson('/api/admin-api/nro/notifies', $payload)->assertCreated()->json('data.id');
    $this->patchJson('/api/admin-api/nro/servers/'.$server->id, ['is_active' => false])->assertOk();
    expect(collect($this->getJson('/api/nro/options')->assertOk()->json('data.servers'))->pluck('id')->all())->not->toContain($server->id);
    $this->postJson('/api/admin-api/nro/notifies', $payload)->assertUnprocessable()->assertJsonValidationErrors('server_code');
    $this->getJson('/api/nro/notifies?server_id='.$server->id)->assertOk()->assertJsonPath('data.0.id', $notifyId);
    $this->patchJson('/api/admin-api/nro/servers/'.$server->id, ['is_active' => true, 'name' => 'Renamed server', 'server_code' => 4000])->assertOk();
    $this->postJson('/api/admin-api/nro/notifies', $payload)->assertUnprocessable();
    $payload['server_code'] = 4000;
    $this->postJson('/api/admin-api/nro/notifies', $payload)->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('data.id', $notifyId);
    $this->assertDatabaseHas('notifies', ['id' => $notifyId, 'server_id' => $server->id]);
    $this->assertDatabaseCount('notifies', 1);
    $this->assertDatabaseCount('nro_event_receipts', 1);
    $this->seed(NroNotificationSeeder::class);
    $this->assertDatabaseCount('servers', 22);
    $this->assertDatabaseMissing('servers', ['server_code' => 1]);
    $this->assertDatabaseHas('servers', ['id' => $server->id, 'server_code' => 4000, 'name' => 'Renamed server']);
});
