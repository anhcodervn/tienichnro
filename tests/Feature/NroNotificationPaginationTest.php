<?php

use App\Features\NroNotification\Services\NroNotificationFeedService;
use App\Models\User;
use Database\Seeders\NroNotificationSeeder;

beforeEach(function (): void {
    $this->seed(NroNotificationSeeder::class);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    foreach (range(1, 25) as $index) {
        $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => sprintf('Notice %02d', $index), 'occurred_at' => now()->startOfSecond()->subMinutes(26 - $index)->toISOString()])->assertCreated();
    }
});

test('public page and API default to ten notifications with numbered pages', function (): void {
    $this->get('/thong-bao-game')->assertOk()->assertViewHas('limit', 10)
        ->assertViewHas('snapshot', fn (array $snapshot): bool => $snapshot['count'] === 10 && $snapshot['total'] === 25 && $snapshot['last_page'] === 3)
        ->assertSee('Notice 25')->assertDontSee('Notice 15')->assertSee('Trang 1 / 3')->assertSee('aria-label="Trang 2"', false);
    $this->getJson('/api/nro/notifies')->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('meta.per_page', 10)->assertJsonPath('meta.last_page', 3);
    $this->get('/thong-bao-game?page=2')->assertOk()->assertSee('Notice 15')->assertSee('Notice 06')->assertDontSee('Notice 25')->assertDontSee('Notice 05')->assertSee('Trang 2 / 3');
    $this->get('/thong-bao-game?page=3')->assertOk()->assertViewHas('snapshot', fn (array $snapshot): bool => $snapshot['count'] === 5)->assertSee('Notice 05')->assertSee('Notice 01');
});

test('pagination preserves filters and custom limits and filtering restarts at page one', function (): void {
    $response = $this->get('/thong-bao-game?server_code=1&code=OTHER&q=Notice&limit=7&page=2')->assertOk()
        ->assertViewHas('snapshot', fn (array $snapshot): bool => $snapshot['count'] === 7 && $snapshot['total'] === 25 && $snapshot['current_page'] === 2);
    preg_match('/rel="next" href="([^"]+)"/', $response->getContent(), $matches);
    $url = html_entity_decode($matches[1]);
    expect($url)->toContain('/thong-bao-game?', 'server_code=1', 'code=OTHER', 'q=Notice', 'limit=7', 'page=3')->not->toContain('/stream');
    $this->get($url)->assertOk()->assertSee('Notice 11')->assertSee('Notice 05')->assertDontSee('Notice 12');
    $this->get('/thong-bao-game?server_code=2&limit=7')->assertOk()->assertViewHas('snapshot', fn (array $snapshot): bool => $snapshot['total'] === 0 && $snapshot['current_page'] === 1);
    expect($response->getContent())->not->toContain('name="page"');
});

test('SSR and SSE honor the same requested page and update pagination metadata', function (): void {
    $response = $this->get('/thong-bao-game?code=OTHER&limit=10&page=2')->assertOk();
    preg_match('/data-nro-stream="([^"]+)"/', $response->getContent(), $matches);
    $streamUrl = html_entity_decode($matches[1]);
    expect($streamUrl)->toContain('page=2', 'limit=10', 'code=OTHER');
    $feed = app(NroNotificationFeedService::class);
    $this->mock(NroNotificationFeedService::class)->shouldReceive('events')->once()->with(['limit' => '10', 'code' => 'OTHER', 'page' => '2'])->andReturnUsing(fn (array $filters) => $feed->events($filters, 0));
    $content = $this->get($streamUrl)->assertOk()->streamedContent();
    expect($content)->toContain('event: notifications', 'Notice 15', 'Notice 06', '"current_page":2', '"total":25')->not->toContain('Notice 25', 'Notice 05');
    $old = $feed->snapshot(['limit' => 10, 'page' => 2]);
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'Older notice', 'occurred_at' => now()->subDays(2)->toISOString()])->assertCreated();
    $new = $feed->snapshot(['limit' => 10, 'page' => 2]);
    expect($new['html'])->toBe($old['html']);
    expect($new['total'])->toBe(26);
    expect($new['signature'])->not->toBe($old['signature']);
});

test('empty and out of range pages remain navigable and invalid pagination is rejected', function (): void {
    $this->get('/thong-bao-game?page=99')->assertOk()->assertViewHas('snapshot', fn (array $snapshot): bool => $snapshot['count'] === 0)->assertSee('aria-label="Trang 1"', false);
    $this->get('/thong-bao-game?q=no-match')->assertOk()->assertSee('Chưa có thông báo phù hợp')->assertSee('Trang 1 / 1');
    foreach (['page=0', 'page=-1', 'page=abc', 'limit=101'] as $query) {
        $this->getJson('/api/nro/notifies?'.$query)->assertUnprocessable();
    }
});

test('AJAX filtering returns the same page snapshot and normalized SSE URL as SSR', function (): void {
    $url = '/thong-bao-game?server_code=1&code=OTHER&q=Notice&limit=7&page=2';
    $snapshot = $this->get($url)->assertOk()->viewData('snapshot');
    $json = $this->getJson($url)->assertOk()->assertJsonPath('data.count', 7)->assertJsonPath('data.total', 25)
        ->assertJsonPath('data.current_page', 2)->assertJsonPath('data.per_page', 7)->assertJsonPath('data.signature', $snapshot['signature']);
    expect($json->json('data.html'))->toBe($snapshot['html'])->toContain('Notice 18', 'Notice 12')->not->toContain('Notice 19');
    expect($json->json('url'))->toContain('/thong-bao-game?', 'limit=7', 'page=2', 'server_code=1');
    expect($json->json('stream_url'))->toContain('/api/nro/notifies/stream?', 'limit=7', 'page=2', 'server_code=1');
});

test('AJAX filtering validates inputs and clears supplementary filters for unrelated types', function (): void {
    $this->getJson('/thong-bao-game?limit=101')->assertUnprocessable()->assertJsonValidationErrors('limit');
    $this->getJson('/thong-bao-game?code=OTHER&state=living')->assertOk()->assertJsonMissingPath('filters.state')->assertJsonPath('data.per_page', 10);
    $this->get('/thong-bao-game')->assertOk()->assertSee('data-nro-loading', false)->assertSee('data-nro-error', false)->assertSee('data-nro-refresh', false)->assertSee('motion-reduce:animate-none', false);
});
