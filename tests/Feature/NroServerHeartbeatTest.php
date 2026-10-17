<?php

use App\Features\NroNotification\Services\ServerHeartbeatService;
use App\Models\NroServer;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;

beforeEach(function (): void {
    config()->set('services.nro_bot', [
        'heartbeat_key' => 'heartbeat-secret', 'heartbeat_interval_seconds' => 30, 'missed_heartbeats' => 5,
    ]);
    $this->travelTo(now()->startOfSecond());
});

test('heartbeat requires its dedicated nonempty key', function (): void {
    $server = NroServer::factory()->create();
    $payload = ['server_code' => $server->server_code];
    $this->postJson('/api/nro/internal/servers/ping', $payload)->assertForbidden();
    $this->withToken('wrong')->postJson('/api/nro/internal/servers/ping', $payload)->assertForbidden();
    config()->set('services.nro_bot.heartbeat_key', '');
    $this->withToken('')->postJson('/api/nro/internal/servers/ping', $payload)->assertForbidden();
    expect($server->fresh()->last_ping_at)->toBeNull()->and($server->fresh()->connection)->toBeFalse();
});

test('heartbeat records website receipt time for the supplied server code only', function (): void {
    $server = NroServer::factory()->create();
    $other = NroServer::factory()->create();
    $this->withToken('heartbeat-secret')->postJson('/api/nro/internal/servers/ping', [
        'server_code' => $server->server_code, 'server_id' => $other->id,
        'last_ping_at' => now()->addYears(10)->toISOString(), 'connection' => false,
    ])->assertOk()->assertJsonPath('connection', true)->assertJsonPath('server_code', $server->server_code)
        ->assertJsonPath('ping_interval_seconds', 30)->assertJsonPath('offline_after_seconds', 150);
    expect($server->fresh()->last_ping_at->equalTo(now()))->toBeTrue()
        ->and($other->fresh()->connection)->toBeFalse()->and($other->fresh()->last_ping_at)->toBeNull();
});

test('heartbeat rejects missing invalid unknown and disabled server codes', function (): void {
    $disabled = NroServer::factory()->create(['is_active' => false]);
    $this->withToken('heartbeat-secret');
    foreach ([[], ['server_code' => -1], ['server_code' => 'abc'], ['server_code' => 4294967296], ['server_code' => $disabled->server_code]] as $payload) {
        $this->postJson('/api/nro/internal/servers/ping', $payload)->assertUnprocessable()->assertJsonValidationErrors('server_code');
    }
    $unknown = NroServer::query()->max('server_code') + 1;
    $this->postJson('/api/nro/internal/servers/ping', ['server_code' => $unknown])->assertUnprocessable();
});

test('server becomes offline at five missed heartbeats even before scheduler runs', function (): void {
    $server = NroServer::factory()->create();
    app(ServerHeartbeatService::class)->ping($server->server_code);
    $this->travel(149)->seconds();
    expect($server->fresh()->connection)->toBeTrue();
    $this->getJson('/api/nro/internal/servers')->assertOk()->assertJsonFragment(['server_code' => $server->server_code, 'connection' => true]);
    $this->travel(1)->seconds();
    expect($server->fresh()->connection)->toBeFalse();
    $this->getJson('/api/nro/internal/servers')->assertOk()->assertJsonFragment(['server_code' => $server->server_code, 'connection' => false]);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/api/admin-api/nro/servers')
        ->assertOk()->assertJsonFragment(['server_code' => $server->server_code, 'connection' => false]);
});

test('new ping resets timeout and reconnects an offline server', function (): void {
    $server = NroServer::factory()->create();
    $service = app(ServerHeartbeatService::class);
    $service->ping($server->server_code);
    $this->travel(120)->seconds();
    $service->ping($server->server_code);
    $this->travel(149)->seconds();
    expect($server->fresh()->connection)->toBeTrue();
    $this->travel(1)->seconds();
    $this->artisan('nro:expire-server-connections')->assertSuccessful();
    expect((bool) $server->fresh()->getRawOriginal('connection'))->toBeFalse();
    $this->withToken('heartbeat-secret')->postJson('/api/nro/internal/servers/ping', ['server_code' => $server->server_code])
        ->assertOk()->assertJsonPath('connection', true);
});

test('expiry changes only stale connections and preserves heartbeat timestamps', function (): void {
    $oldTime = now()->subSeconds(150);
    $old = NroServer::factory()->create();
    $old->forceFill(['connection' => true, 'last_ping_at' => $oldTime])->save();
    $fresh = NroServer::factory()->create();
    $fresh->forceFill(['connection' => true, 'last_ping_at' => now()->subSeconds(149)])->save();
    $never = NroServer::factory()->create();
    $this->artisan('nro:expire-server-connections')->assertSuccessful();
    expect((bool) $old->fresh()->getRawOriginal('connection'))->toBeFalse()
        ->and($old->fresh()->last_ping_at->equalTo($oldTime))->toBeTrue()
        ->and($fresh->fresh()->connection)->toBeTrue()->and($never->fresh()->connection)->toBeFalse();
    $this->artisan('nro:expire-server-connections')->expectsOutput('Expired connections: 0')->assertSuccessful();
});

test('heartbeat interval and missed count are configurable', function (): void {
    config()->set('services.nro_bot.heartbeat_interval_seconds', 10);
    config()->set('services.nro_bot.missed_heartbeats', 3);
    $server = NroServer::factory()->create();
    $this->withToken('heartbeat-secret')->postJson('/api/nro/internal/servers/ping', ['server_code' => $server->server_code])
        ->assertOk()->assertJsonPath('ping_interval_seconds', 10)->assertJsonPath('offline_after_seconds', 30);
    $this->travel(29)->seconds();
    expect($server->fresh()->connection)->toBeTrue();
    $this->travel(1)->seconds();
    expect($server->fresh()->connection)->toBeFalse();
});

test('offline expiry is scheduled every minute without overlapping', function (): void {
    $event = collect(app(Schedule::class)->events())->first(fn ($event): bool => str_contains($event->command ?? '', 'nro:expire-server-connections'));
    expect($event)->not->toBeNull()->and($event->expression)->toBe('* * * * *')->and($event->withoutOverlapping)->toBeTrue();
});
