<?php

use App\Models\NroGameDataSync;
use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    config()->set('services.nro_api_rate_limits', [
        'catalog' => 2, 'poll' => 2, 'uploads' => 2, 'notifications' => 2,
    ]);
    config()->set('queue.default', 'database');
    Cache::flush();
});

test('authenticated upload and game data requests are unlimited while notification quotas remain enforced', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $syncId = $this->postJson('/api/admin-api/nro/game-data/maps', ['data' => []])->assertAccepted()->json('data.id');
    Cache::flush();
    foreach (range(1, 5) as $attempt) {
        $this->getJson('/api/admin-api/nro/game-data/maps')->assertOk();
        $this->getJson('/api/admin-api/nro/game-data/syncs/'.$syncId)->assertOk();
        $this->postJson('/api/admin-api/nro/game-data/maps', ['data' => []])->assertAccepted();
        $this->postJson('/api/admin-api/nro/game-data/maps/compare', ['data' => []])->assertOk();
        $this->postJson('/api/uploads/image', [])->assertUnprocessable();
        $this->postJson('/api/uploads/image/import', [])->assertUnprocessable();
    }
    foreach (range(1, 2) as $attempt) {
        $this->postJson('/api/admin-api/nro/notifies', [])->assertUnprocessable();
    }
    $this->postJson('/api/admin-api/nro/notifies', [])->assertStatus(429);
    $this->travel(61)->seconds();
    $this->getJson('/api/admin-api/nro/game-data/maps')->assertOk();
});

test('one admin exhausting a notification quota does not block another admin or data uploads', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    foreach (range(1, 2) as $attempt) {
        $this->postJson('/api/admin-api/nro/notifies', [])->assertUnprocessable();
    }
    $this->postJson('/api/admin-api/nro/notifies', [])->assertStatus(429);
    $this->getJson('/api/admin-api/nro/game-data/maps')->assertOk();
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->postJson('/api/admin-api/nro/notifies', [])->assertUnprocessable();
});

test('isolated rate limits retain sanctum and administrator protection', function (): void {
    $sync = NroGameDataSync::query()->create(['dataset' => 'maps', 'status' => 'queued']);
    foreach (['/api/admin-api/nro/game-data/maps', '/api/admin-api/nro/notifies'] as $url) {
        $this->getJson($url)->assertUnauthorized();
    }
    $this->postJson('/api/uploads/image')->assertUnauthorized();
    $this->postJson('/api/uploads/image/import')->assertUnauthorized();
    $this->postJson('/api/admin-api/nro/game-data/maps')->assertUnauthorized();
    $this->postJson('/api/admin-api/nro/game-data/maps/compare')->assertUnauthorized();
    $this->getJson('/api/admin-api/nro/game-data/syncs/'.$sync->id)->assertUnauthorized();
    $this->actingAs(User::factory()->create(['role' => 'user']));
    $this->getJson('/api/admin-api/nro/game-data/maps')->assertForbidden();
    $this->getJson('/api/admin-api/nro/notifies')->assertForbidden();
    $this->postJson('/api/uploads/image')->assertForbidden();
    $this->postJson('/api/uploads/image/import')->assertForbidden();
    $this->postJson('/api/admin-api/nro/game-data/maps')->assertForbidden();
    $this->postJson('/api/admin-api/nro/game-data/maps/compare')->assertForbidden();
    $this->getJson('/api/admin-api/nro/game-data/syncs/'.$sync->id)->assertForbidden();
});

test('upload and game data routes resolve without throttle middleware', function (string $name): void {
    $router = app('router');
    $route = $router->getRoutes()->getByName($name);
    expect($route)->not->toBeNull();
    foreach ($router->gatherRouteMiddleware($route) as $middleware) {
        expect($middleware)->not->toStartWith(ThrottleRequests::class)
            ->not->toStartWith(ThrottleRequestsWithRedis::class);
    }
})->with([
    'admin.uploads.image.store', 'admin.uploads.image.import',
    'admin.nro.game-data.index', 'admin.nro.game-data.store',
    'admin.nro.game-data.compare', 'admin.nro.game-data.syncs.show',
]);
