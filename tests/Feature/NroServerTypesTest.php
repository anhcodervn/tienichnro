<?php

use App\Models\ManagedServer;
use App\Models\Notify;
use App\Models\NroServer;
use App\Models\User;
use Database\Seeders\ManagedServerSeeder;
use Database\Seeders\NroNotificationSeeder;
use Illuminate\Support\Facades\DB;

test('original NRO rows retain their IDs and are copied identically into web and game types', function (): void {
    $originals = ManagedServer::query()->where('type', 'napcarot')->orderBy('id')->get();
    expect($originals)->not->toBeEmpty();
    foreach (['napcarot', 'web', 'game'] as $type) {
        expect(ManagedServer::query()->where('type', $type)->count())->toBe($originals->count());
        foreach ($originals as $original) {
            $row = ManagedServer::query()->where('type', $type)->where('server_code', $original->server_code)->sole();
            expect($row->name)->toBe($original->name)->and($row->code)->toBe($original->code)
                ->and($row->is_active)->toBe($original->is_active)->and($row->sort_order)->toBe($original->sort_order);
            if ($type === 'napcarot') {
                expect($row->id)->toBe($original->id);
            } else {
                expect($row->id)->not->toBe($original->id);
            }
        }
    }
});

test('copies can be edited independently and reseeding does not overwrite edited IDs names and states', function (): void {
    $source = NroServer::query()->orderBy('id')->firstOrFail();
    $web = ManagedServer::query()->where('type', 'web')->where('server_code', $source->server_code)->sole();
    $web->update(['server_id' => '911', 'name' => 'Custom website', 'is_active' => false, 'sort_order' => 88]);
    $this->seed(ManagedServerSeeder::class);
    expect($web->fresh()->name)->toBe('Custom website')->and($web->fresh()->is_active)->toBeFalse()
        ->and($source->fresh()->name)->toBe($source->name)->and($web->fresh()->server_code)->toBe(911);
    expect(ManagedServer::query()->where('type', 'web')->count())->toBe(NroServer::query()->count());
    expect(ManagedServer::query()->where('type', 'game')->where('server_code', $source->server_code)->sole()->name)->toBe($source->name);
});

test('NRO bot server API remains scoped to game rows and unique rules allow identical codes in other groups', function (): void {
    $original = NroServer::query()->orderBy('id')->firstOrFail();
    $this->getJson(route('nro.internal.servers.index'))->assertOk()
        ->assertJsonCount(NroServer::query()->where('is_active', true)->count());
    $admin = User::factory()->create(['role' => 'admin']);
    $copy = ManagedServer::factory()->create(['type' => 'web', 'server_id' => '731', 'code' => 'sv731']);
    $this->actingAs($admin)->postJson('/api/admin-api/nro/servers', ['server_code' => 731, 'code' => 'sv731', 'name' => 'Original new NRO server', 'is_active' => true])->assertCreated();
    expect(NroServer::query()->where('server_code', 731)->sole()->id)->not->toBe($copy->id);
    expect(NroServer::query()->find($copy->id))->toBeNull();
    expect(NroServer::query()->find($original->id))->not->toBeNull();
});

test('typed admin changes and tool options read the original servers table', function (): void {
    $source = NroServer::query()->orderBy('id')->firstOrFail();
    $web = ManagedServer::query()->where('type', 'web')->where('server_code', $source->server_code)->sole();
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->putJson('/api/admin-api/managed-servers/web/'.$web->id, ['server_id' => '911', 'name' => 'Website 911', 'is_active' => true, 'sort_order' => 0])
        ->assertOk()->assertJsonPath('data.type', 'web')->assertJsonPath('data.server_id', '911');
    expect(DB::table('servers')->where('id', $web->id)->value('server_code'))->toBe(911);
    expect(ManagedServer::activeOptions('web')->first()->server_code)->toBe(911);
    expect(ManagedServer::activeOptions('game')->pluck('server_code'))->not->toContain(911);
});

test('notification selectors ingestion and heartbeat accept only active game servers', function (): void {
    $this->seed(NroNotificationSeeder::class);
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);
    config()->set('services.nro_bot.heartbeat_key', 'heartbeat-secret');
    foreach (['web', 'napcarot'] as $index => $type) {
        $other = ManagedServer::factory()->create(['type' => $type, 'server_id' => (string) (731 + $index)]);
        $this->postJson('/api/admin-api/nro/notifies', ['server_id' => $other->id, 'content' => 'Other catalog'])
            ->assertUnprocessable()->assertJsonValidationErrors('server_id');
        $this->postJson('/api/admin-api/nro/notifies', ['server_code' => $other->server_code, 'content' => 'Other catalog'])
            ->assertUnprocessable()->assertJsonValidationErrors('server_code');
        $this->withToken('heartbeat-secret')->postJson('/api/nro/internal/servers/ping', ['server_code' => $other->server_code])
            ->assertUnprocessable()->assertJsonValidationErrors('server_code');
        $this->getJson('/api/nro/options')->assertOk()->assertJsonMissing(['id' => $other->id, 'server_code' => $other->server_code]);
    }

    $game = NroServer::query()->where('server_code', 1)->sole();
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'code' => 'OTHER', 'content' => 'Game catalog', 'occurred_at' => now()->toISOString()])
        ->assertCreated()->assertJsonPath('data.server_id', $game->id);
    $this->withToken('heartbeat-secret')->postJson('/api/nro/internal/servers/ping', ['server_code' => 1])->assertOk();
    expect(DB::table('servers')->where('type', '!=', 'game')->whereNotNull('last_ping_at')->count())->toBe(0);

    $game->update(['is_active' => false]);
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'code' => 'OTHER', 'content' => 'Disabled game', 'occurred_at' => now()->toISOString()])
        ->assertUnprocessable()->assertJsonValidationErrors('server_code');
    $this->withToken('heartbeat-secret')->postJson('/api/nro/internal/servers/ping', ['server_code' => 1])
        ->assertUnprocessable()->assertJsonValidationErrors('server_code');
});

test('notification history migration preserves notifications receipts replay detection and heartbeat', function (): void {
    $this->seed(NroNotificationSeeder::class);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $source = ManagedServer::query()->where('type', 'napcarot')->where('server_code', 1)->sole();
    $game = NroServer::query()->where('server_code', 1)->sole();
    $payload = ['server_code' => 1, 'code' => 'OTHER', 'event_id' => 'legacy-catalog-event', 'content' => 'Legacy notification', 'occurred_at' => now()->toISOString()];
    $id = $this->postJson('/api/admin-api/nro/notifies', $payload)->assertCreated()->json('data.id');
    DB::table('notifies')->where('id', $id)->update(['server_id' => $source->id]);
    DB::table('nro_event_receipts')->where('notify_id', $id)->update(['server_id' => $source->id]);
    DB::table('servers')->where('id', $source->id)->update(['connection' => true, 'last_ping_at' => now()]);

    $migration = require database_path('migrations/2026_11_18_104301_move_nro_notification_history_to_game_servers.php');
    $migration->up();
    $migration->up();
    $migration->down();
    $this->assertDatabaseHas('notifies', ['id' => $id, 'server_id' => $game->id]);
    $this->assertDatabaseHas('nro_event_receipts', ['notify_id' => $id, 'server_id' => $game->id]);
    expect(Notify::findOrFail($id)->server->type)->toBe('game')
        ->and($game->fresh()->connection)->toBeTrue();
    $this->postJson('/api/admin-api/nro/notifies', $payload)->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('data.id', $id);
});
