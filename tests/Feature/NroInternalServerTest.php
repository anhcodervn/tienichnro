<?php

use App\Models\NroServer;

beforeEach(function (): void {
    NroServer::query()->update(['is_active' => false]);
});

test('server list is public regardless of registration key configuration', function (): void {
    config()->set('services.zalo_bot.registration_key', 'server-list-key');
    $this->getJson('/api/nro/internal/servers')->assertOk();
    config()->set('services.zalo_bot.registration_key', '');
    $this->getJson('/api/nro/internal/servers')->assertOk();
});

test('internal server list returns connection status in a plain ordered array of active servers', function (): void {
    $last = NroServer::factory()->create(['server_code' => 1001, 'code' => 'internal-sv1', 'name' => 'Server 1', 'sort_order' => 2]);
    $second = NroServer::factory()->create(['server_code' => 1003, 'code' => 'internal-sv3', 'name' => 'Server 3', 'sort_order' => 1]);
    $first = NroServer::factory()->create(['server_code' => 1002, 'code' => 'internal-sv2', 'name' => 'Server 2', 'sort_order' => 1]);
    NroServer::factory()->create(['server_code' => 1004, 'code' => 'internal-sv4', 'is_active' => false, 'sort_order' => 0]);

    $this->getJson('/api/nro/internal/servers')->assertOk()->assertExactJson([
        ['id' => $first->id, 'name' => 'Server 2', 'code' => 'internal-sv2', 'server_code' => 1002, 'connection' => false, 'last_ping_at' => null],
        ['id' => $second->id, 'name' => 'Server 3', 'code' => 'internal-sv3', 'server_code' => 1003, 'connection' => false, 'last_ping_at' => null],
        ['id' => $last->id, 'name' => 'Server 1', 'code' => 'internal-sv1', 'server_code' => 1001, 'connection' => false, 'last_ping_at' => null],
    ]);
});

test('internal server list returns an empty array when no active server exists', function (): void {
    $this->getJson('/api/nro/internal/servers')->assertOk()->assertExactJson([]);
});
