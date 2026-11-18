<?php

use App\Models\ManagedServer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    DB::table('servers')->delete();
    $this->admin = User::factory()->create(['role' => 'admin']);
});

test('managed servers reject guests members and inactive administrators', function (): void {
    $this->getJson('/api/admin-api/managed-servers/web')->assertUnauthorized();
    $this->actingAs(User::factory()->create())->postJson('/api/admin-api/managed-servers/game', [])->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'banned']))->getJson('/api/admin-api/managed-servers/napcarot')->assertForbidden();
});

test('servers have independent IDs and cannot be moved or changed through another type', function (): void {
    $this->actingAs($this->admin);
    $payload = ['server_id' => '1', 'name' => 'Server one', 'is_active' => true, 'sort_order' => 0, 'type' => 'game'];
    foreach (ManagedServer::TYPES as $type) {
        $this->postJson('/api/admin-api/managed-servers/'.$type, $payload)->assertCreated()->assertJsonPath('data.type', $type);
    }
    $website = ManagedServer::query()->where('type', 'web')->sole();
    $this->postJson('/api/admin-api/managed-servers/web', $payload)->assertUnprocessable();
    $this->putJson('/api/admin-api/managed-servers/web/'.$website->id, [...$payload, 'name' => 'Updated', 'is_active' => false])
        ->assertOk()->assertJsonPath('data.type', 'web')->assertJsonPath('data.is_active', false);
    $this->putJson('/api/admin-api/managed-servers/game/'.$website->id, [...$payload, 'server_id' => '2'])->assertNotFound();
    $this->deleteJson('/api/admin-api/managed-servers/game/'.$website->id)->assertNotFound();
    $this->deleteJson('/api/admin-api/managed-servers/web/'.$website->id)->assertOk();
    $this->assertDatabaseCount('servers', 2);
    $this->getJson('/api/admin-api/managed-servers/unknown')->assertNotFound();
    $this->postJson('/api/admin-api/managed-servers/game', [...$payload, 'server_id' => '../bad', 'sort_order' => -1])->assertUnprocessable();
});

test('server listing is typed filtered sorted and paginated at twenty rows', function (): void {
    ManagedServer::factory()->count(21)->create(['type' => 'web', 'sort_order' => 1]);
    $match = ManagedServer::factory()->create(['type' => 'web', 'name' => 'Special', 'server_id' => '731', 'is_active' => false, 'sort_order' => 0]);
    ManagedServer::factory()->create(['type' => 'game', 'name' => 'Special']);
    $this->actingAs($this->admin)->getJson('/api/admin-api/managed-servers/web')->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('total', 22)->assertJsonPath('data.0.id', $match->id);
    $this->getJson('/api/admin-api/managed-servers/web?page=2')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('/api/admin-api/managed-servers/web?q=731&active=0')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/admin-api/managed-servers/web?q=Special&active=1')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/admin-api/managed-servers/web?active=bad')->assertUnprocessable();
});
