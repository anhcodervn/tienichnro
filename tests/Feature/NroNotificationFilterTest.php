<?php

use App\Features\NroNotification\Services\NroNotificationFeedService;
use App\Models\Boss;
use App\Models\CodeNotify;
use App\Models\User;
use Database\Seeders\NroNotificationSeeder;

beforeEach(function (): void {
    $this->seed(NroNotificationSeeder::class);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
});

test('compact filters place the submit action last and omit unused supplementary rows', function (): void {
    foreach (['' => true, 'BOSS' => false] as $code => $hidden) {
        $html = $this->get(route('nro.notifies.page', ['code' => $code]))->assertOk()->getContent();
        preg_match('/<form[^>]*data-nro-filters.*?<\/form>/s', $html, $matches);
        $form = $matches[0] ?? '';

        expect($form)->toContain('grid grid-cols-2 items-end gap-3')
            ->not->toContain('Lọc bổ sung', 'Chọn loại thông báo để lọc thêm.');
        expect(strpos($form, 'type="submit"'))->toBeGreaterThan(strpos($form, 'name="limit"'));
        preg_match('/<div data-nro-extra-group([^>]*)>/', $form, $group);
        expect(str_contains($group[1] ?? '', 'hidden'))->toBe($hidden);
        expect(substr_count($form, 'type="submit"'))->toBe(1);
    }
});

test('admin controls per type supplementary filters and seeding preserves the configuration', function (): void {
    $type = CodeNotify::query()->where('code', 'BOSS')->firstOrFail();
    expect($type->additional_filters)->toBe(['boss', 'state']);
    $payload = ['name' => $type->name, 'type_id' => $type->type_id];
    $this->patchJson('/api/admin-api/nro/notification-types/'.$type->id, $payload + ['additional_filters' => ['boss']])->assertOk()->assertJsonPath('data.additional_filters', ['boss']);
    $this->patchJson('/api/admin-api/nro/notification-types/'.$type->id, $payload + ['additional_filters' => ['unsupported']])->assertUnprocessable()->assertJsonValidationErrors('additional_filters.0');
    $this->patchJson('/api/admin-api/nro/notification-types/'.$type->id, $payload + ['additional_filters' => ['boss', 'boss']])->assertUnprocessable()->assertJsonValidationErrors('additional_filters.0');
    $this->patchJson('/api/admin-api/nro/notification-types/'.$type->id, $payload + ['additional_filters' => []])->assertOk()->assertJsonPath('data.additional_filters', []);
    $this->seed(NroNotificationSeeder::class);
    expect($type->fresh()->additional_filters)->toBe([]);
    $this->get('/thong-bao-game?code=BOSS')->assertOk()->assertSee('data-nro-extra="boss"  hidden', false)->assertSee('name="boss_id" disabled', false);
});

test('type selectors keywords and limit are rendered and SSR SSE share normalized filters', function (): void {
    $boss = Boss::factory()->create(['name' => 'Broly 3']);
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'Broly 3 vừa xuất hiện tại Rừng Bamboo khu 5', 'occurred_at' => now()->toIso8601String()])->assertCreated();
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'code' => 'OTHER', 'content' => 'Game news', 'occurred_at' => now()->toIso8601String()])->assertCreated();
    $this->get('/thong-bao-game?code=BOSS&boss_id='.$boss->id.'&q=Broly&limit=25')->assertOk()->assertSee('name="code"', false)->assertSee('name="q"', false)->assertSee('select name="limit"', false)->assertSee('Boss: Broly 3')->assertDontSee('Game news');
    $url = '/thong-bao-game?code=OTHER&boss_id='.$boss->id.'&state=living&q=Game&limit=10';
    $response = $this->get($url)->assertOk()->assertSee('Game news')->assertDontSee('Boss: Broly 3');
    preg_match('/data-nro-stream="([^"]+)"/', $response->getContent(), $matches);
    $streamUrl = html_entity_decode($matches[1]);
    expect($streamUrl)->toContain('code=OTHER', 'q=Game', 'limit=10')->not->toContain('boss_id=', 'state=');
    $this->getJson('/api/nro/notifies?code=OTHER&boss_id='.$boss->id.'&state=living&q=Game')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'OTHER');
    $feed = app(NroNotificationFeedService::class);
    $this->mock(NroNotificationFeedService::class)->shouldReceive('events')->once()->with(['code' => 'OTHER', 'q' => 'Game', 'limit' => '10'])->andReturnUsing(fn (array $filters) => $feed->events($filters, 0));
    $stream = $this->get($streamUrl)->assertOk();
    $content = $stream->streamedContent();
    expect($content)->toContain('event: notifications', 'Game news')->not->toContain('Boss: Broly 3');
});

test('keyword search is grouped with server and type constraints and searches lifecycle fields', function (): void {
    $boss = Boss::factory()->create(['name' => 'Broly 3']);
    foreach ([1, 2] as $server) {
        $this->postJson('/api/admin-api/nro/notifies', ['server_code' => $server, 'content' => 'Broly 3 vừa xuất hiện tại Rừng Bamboo khu 5', 'occurred_at' => now()->subMinutes(10)->toIso8601String()])->assertCreated();
        $this->postJson('/api/admin-api/nro/notifies', ['server_code' => $server, 'content' => 'Broly 3 vừa bị tiêu diệt bởi Tester', 'occurred_at' => now()->subMinutes(5)->toIso8601String()])->assertOk();
    }
    foreach (['Broly', 'Bamboo', 'Tester'] as $keyword) {
        $this->getJson('/api/nro/notifies?server_code=1&code=BOSS&boss_id='.$boss->id.'&state=history&q='.$keyword)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.server_code', 1);
    }
    $this->getJson('/api/nro/notifies?code=OTHER&q=Tester')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/nro/notifies?q='.str_repeat('x', 201))->assertUnprocessable()->assertJsonValidationErrors('q');
});

test('keyword wildcards are literal and filtering happens before limiting', function (): void {
    foreach (['Only 10%_! discount', 'Other unrelated news'] as $index => $content) {
        $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'code' => 'OTHER', 'content' => $content, 'occurred_at' => now()->addSeconds($index)->toIso8601String()])->assertCreated();
    }
    $this->getJson('/api/nro/notifies?q='.urlencode('  %_!  ').'&limit=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.content', 'Only 10%_! discount');
    $this->getJson('/api/nro/notifies?q=0')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.content', 'Only 10%_! discount');
    $this->get('/thong-bao-game?q=discount&limit=1')->assertOk()->assertSee('Only 10%_! discount')->assertDontSee('Other unrelated news');
});
