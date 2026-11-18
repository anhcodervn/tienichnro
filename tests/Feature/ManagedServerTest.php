<?php

use App\Features\Admin\ManagedServer\Services\NapCarotServerService;
use App\Models\ManagedServer;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\ManagedServerSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->service = app(NapCarotServerService::class);
});

test('managed servers reject guests members and inactive administrators', function (): void {
    $this->getJson('/api/admin-api/managed-servers/website')->assertUnauthorized();
    $this->actingAs(User::factory()->create())->postJson('/api/admin-api/managed-servers/game', [])->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'banned']))->getJson('/api/admin-api/managed-servers/carot')->assertForbidden();
    $this->getJson('/api/admin-api/managed-servers/napcarot/configuration')->assertForbidden();
    $this->postJson('/api/admin-api/managed-servers/napcarot/sync')->assertForbidden();
});

test('servers have independent IDs and cannot be moved or changed through another type', function (): void {
    $this->actingAs($this->admin);
    $payload = ['server_id' => '1', 'name' => 'Server one', 'is_active' => true, 'sort_order' => 0, 'type' => 'game'];
    foreach (ManagedServer::TYPES as $type) {
        $this->postJson('/api/admin-api/managed-servers/'.$type, $payload)->assertCreated()->assertJsonPath('data.type', $type);
    }
    $website = ManagedServer::query()->where('type', 'website')->sole();
    $this->postJson('/api/admin-api/managed-servers/website', $payload)->assertUnprocessable();
    $this->putJson('/api/admin-api/managed-servers/website/'.$website->id, [...$payload, 'name' => 'Updated', 'is_active' => false])
        ->assertOk()->assertJsonPath('data.type', 'website')->assertJsonPath('data.is_active', false);
    $this->putJson('/api/admin-api/managed-servers/game/'.$website->id, [...$payload, 'server_id' => '2'])->assertNotFound();
    $this->deleteJson('/api/admin-api/managed-servers/game/'.$website->id)->assertNotFound();
    $this->deleteJson('/api/admin-api/managed-servers/website/'.$website->id)->assertOk();
    $this->assertDatabaseCount('managed_servers', 2);
    $this->getJson('/api/admin-api/managed-servers/unknown')->assertNotFound();
    $this->postJson('/api/admin-api/managed-servers/game', [...$payload, 'server_id' => '../bad', 'sort_order' => -1])->assertUnprocessable();
});

test('server listing is typed filtered sorted and paginated at twenty rows', function (): void {
    ManagedServer::factory()->count(21)->create(['type' => 'website', 'sort_order' => 1]);
    $match = ManagedServer::factory()->create(['type' => 'website', 'name' => 'Special', 'server_id' => 'NRO-X', 'is_active' => false, 'sort_order' => 0]);
    ManagedServer::factory()->create(['type' => 'game', 'name' => 'Special']);
    $this->actingAs($this->admin)->getJson('/api/admin-api/managed-servers/website')->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('total', 22)->assertJsonPath('data.0.id', $match->id);
    $this->getJson('/api/admin-api/managed-servers/website?page=2')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('/api/admin-api/managed-servers/website?q=NRO-X&active=0')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/admin-api/managed-servers/website?q=Special&active=1')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/admin-api/managed-servers/website?active=bad')->assertUnprocessable();
});

test('sample servers match NapCarot IDs while login samples use login codes and seeding preserves edits', function (): void {
    $this->seed(ManagedServerSeeder::class);
    $this->assertDatabaseCount('managed_servers', 57);
    $this->assertDatabaseHas('managed_servers', ['type' => 'carot', 'server_id' => '4', 'name' => '4 sao']);
    $this->assertDatabaseHas('managed_servers', ['type' => 'website', 'server_id' => '6', 'name' => '4 sao']);
    $this->assertDatabaseHas('managed_servers', ['type' => 'game', 'server_id' => '6', 'name' => '4 sao']);
    ManagedServer::query()->where('type', 'website')->where('server_id', '6')->update(['name' => 'Custom']);
    $this->seed(ManagedServerSeeder::class);
    $this->assertDatabaseCount('managed_servers', 57);
    $this->assertDatabaseHas('managed_servers', ['type' => 'website', 'server_id' => '6', 'name' => 'Custom']);
});

test('NapCarot credentials are encrypted masked retained and only sent on explicit sync', function (): void {
    $url = '/api/admin-api/managed-servers/napcarot/configuration';
    $this->actingAs($this->admin)->putJson($url, ['api_key' => 'ak_private', 'api_secret' => 'sk_private'])->assertOk()->assertJsonPath('data.credentials_saved', true)->assertDontSee('ak_private')->assertDontSee('sk_private');
    $this->putJson($url, [])->assertOk()->assertJsonPath('data.credentials_saved', true);
    $this->getJson($url)->assertOk()->assertDontSee('ak_private')->assertDontSee('sk_private');
    expect(Setting::query()->where('key', 'managed_servers.napcarot.api_secret')->value('value'))->not->toContain('sk_private');
    $this->putJson($url, ['api_key' => 'missing secret'])->assertUnprocessable();
    Http::assertNothingSent();
});

test('NapCarot sync imports only NRO and retains local states and other server types', function (): void {
    $this->service->save(['api_key' => 'ak_private', 'api_secret' => 'sk_private']);
    $carot = ManagedServer::factory()->create(['type' => 'carot', 'server_id' => '4', 'is_active' => false, 'sort_order' => 88]);
    $website = ManagedServer::factory()->create(['type' => 'website', 'server_id' => '4', 'name' => 'Local']);
    $game = ManagedServer::factory()->create(['type' => 'game', 'server_id' => '4', 'name' => 'Game']);
    Http::fake(['https://napcarot.com/api/v1/catalog' => Http::response(['status' => true, 'data' => [
        ['slug' => 'ngoc-rong-online', 'servers' => [['id' => 4, 'name' => '4 sao'], ['id' => 19, 'name' => '15 sao']]],
        ['slug' => 'ninja-school', 'servers' => [['id' => 200, 'name' => 'Other game']]],
    ]])]);
    $this->actingAs($this->admin)->postJson('/api/admin-api/managed-servers/napcarot/sync')->assertOk()->assertJsonPath('data.count', 2);
    $this->postJson('/api/admin-api/managed-servers/napcarot/sync')->assertOk();
    expect($carot->fresh()->name)->toBe('4 sao')->and($carot->fresh()->is_active)->toBeFalse()->and($carot->fresh()->sort_order)->toBe(88)
        ->and($website->fresh()->name)->toBe('Local')->and($game->fresh()->name)->toBe('Game');
    $this->assertDatabaseCount('managed_servers', 4);
    Http::assertSent(fn ($request) => $request->hasHeader('X-API-Key', 'ak_private') && $request->hasHeader('X-API-Secret', 'sk_private') && $request->method() === 'GET');
});

test('invalid or duplicate catalog entries never partially update local servers', function (array $servers): void {
    $this->service->save(['api_key' => 'key', 'api_secret' => 'secret']);
    $existing = ManagedServer::factory()->create(['type' => 'carot', 'server_id' => '4', 'name' => 'Keep']);
    Http::fake(['https://napcarot.com/api/v1/catalog' => Http::response(['status' => true, 'data' => [['slug' => 'ngoc-rong-online', 'servers' => $servers]]])]);
    $this->actingAs($this->admin)->postJson('/api/admin-api/managed-servers/napcarot/sync')->assertUnprocessable();
    expect($existing->fresh()->name)->toBe('Keep');
    $this->assertDatabaseCount('managed_servers', 1);
})->with([
    [[['id' => 4, 'name' => 'New'], ['id' => 0, 'name' => 'Invalid']]],
    [[['id' => 4, 'name' => 'New'], ['id' => 4, 'name' => 'Duplicate']]],
    [[]],
]);

test('provider errors missing credentials and overlapping sync return safe failures', function (): void {
    $this->actingAs($this->admin)->postJson('/api/admin-api/managed-servers/napcarot/sync')->assertUnprocessable();
    $this->service->save(['api_key' => 'key', 'api_secret' => 'secret']);
    Http::fake(['https://napcarot.com/api/v1/catalog' => Http::response(['private' => 'do not expose'], 500)]);
    $this->postJson('/api/admin-api/managed-servers/napcarot/sync')->assertUnprocessable()->assertDontSee('do not expose');
    Http::fake(['https://napcarot.com/api/v1/catalog' => fn () => throw new ConnectionException]);
    $this->postJson('/api/admin-api/managed-servers/napcarot/sync')->assertUnprocessable();
    $lock = Cache::lock('managed_servers:napcarot:sync', 30);
    $lock->get();
    try {
        $this->postJson('/api/admin-api/managed-servers/napcarot/sync')->assertStatus(409);
    } finally {
        $lock->release();
    }
    $this->assertDatabaseCount('managed_servers', 0);
});
