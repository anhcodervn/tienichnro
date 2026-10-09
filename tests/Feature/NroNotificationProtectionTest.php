<?php

use App\Features\NroNotification\Services\NotificationAccessService;
use App\Features\NroNotification\Services\NotificationStreamSlotsService;
use App\Features\NroNotification\Services\NroNotificationFeedService;
use App\Features\NroNotification\Services\NroNotificationService;
use App\Models\User;
use App\Support\SettingStore;
use Database\Seeders\NroNotificationSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    $this->seed(NroNotificationSeeder::class);
    $this->travelTo(now()->startOfMinute());
    $settings = app(SettingStore::class);
    $settings->putMany(['turnstile_enabled' => true, 'turnstile_site_key' => 'test-site-key']);
    $settings->putEncryptedString('turnstile_secret_key', 'test-secret-key');
    Http::fake(['challenges.cloudflare.com/*' => fn () => Http::response(config('nro_test_response', ['success' => true, 'action' => 'nro_realtime', 'hostname' => 'localhost']))]);
});

function protectedNotification(string $content, int $minutes): void
{
    app(NroNotificationService::class)->ingest(['server_code' => 1, 'content' => $content, 'occurred_at' => now()->subMinutes($minutes)->toISOString()]);
}

test('guest trials show current notifications with row and history caps', function (): void {
    protectedNotification('Fresh secret', 1);
    protectedNotification('Old history', 70);
    protectedNotification('Outside last hour', 61);
    foreach (range(6, 45) as $minutes) {
        protectedNotification('Preview '.$minutes, $minutes);
    }
    $page = $this->get('/thong-bao-game?limit=100')->assertOk()->assertSee('Fresh secret')->assertDontSee('Old history')
        ->assertDontSee('Outside last hour')->assertSee('data-nro-stream=', false)->assertSee('sau lần nhắc thứ 3')->assertHeader('Cache-Control', 'no-store, private');
    $page->assertSee('Vui lòng đăng nhập để gỡ bỏ hạn chế.');
    $page->assertViewHas('snapshot', fn (array $snapshot): bool => $snapshot['count'] === 10 && $snapshot['total'] === 30 && $snapshot['last_page'] === 3);
    $this->getJson('/thong-bao-game?limit=100')->assertOk()->assertJsonPath('realtime', true)->assertJsonPath('guest', true)
        ->assertJsonPath('data.total', 30)->assertJsonPath('data.per_page', 10)->assertSee('Fresh secret');
    $this->getJson('/api/nro/notifies?limit=100&_preview_cutoff=2099-01-01')->assertOk()->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.total', 30)->assertSee('Fresh secret');
    foreach (['/thong-bao-game?page=4', '/api/nro/notifies?page=4'] as $url) {
        $this->getJson($url)->assertForbidden();
    }
});

test('logged in members receive realtime without Turnstile', function (): void {
    protectedNotification('Fresh secret', 1);
    $this->actingAs(User::factory()->create(['role' => 'user']))->get('/thong-bao-game')->assertOk()
        ->assertDontSee('data-nro-access-form', false)->assertSee('Fresh secret')->assertSee('data-nro-stream=', false)->assertDontSee('test-secret-key')
        ->assertDontSee('Vui lòng đăng nhập để gỡ bỏ hạn chế.');
    $this->getJson('/thong-bao-game')->assertOk()->assertJsonPath('realtime', true);
});

test('guests immediately see recent lifecycle deaths attached to older spawns', function (): void {
    $service = app(NroNotificationService::class);
    $service->ingest(['server_code' => 1, 'content' => 'Broly 77 vừa xuất hiện tại Rừng Bamboo khu 5', 'occurred_at' => now()->subMinutes(10)->toISOString()]);
    $service->ingest(['server_code' => 1, 'content' => 'Broly 77 vừa bị tiêu diệt bởi SecretPlayer', 'occurred_at' => now()->subMinute()->toISOString()]);
    $this->getJson('/api/nro/notifies')->assertOk()->assertJsonCount(1, 'data')->assertSee('SecretPlayer');
});

test('member realtime persists beyond the former verification deadline', function (): void {
    protectedNotification('Fresh secret', 1);
    $user = User::factory()->create(['role' => 'user']);
    $this->actingAs($user)->postJson(route('nro.notifies.verify'), ['cf-turnstile-response' => 'good-token'])->assertOk();
    $this->withCredentials()->withCookie(config('session.cookie'), app('session')->getId());
    Http::assertSent(fn ($request): bool => $request['secret'] === 'test-secret-key' && $request['response'] === 'good-token');
    $this->get('/thong-bao-game')->assertOk()->assertSee('Fresh secret')->assertSee('data-nro-stream=', false)->assertDontSee('data-nro-access-form', false);
    $this->getJson('/api/nro/notifies')->assertOk()->assertJsonCount(1, 'data');
    $this->travel(16)->minutes();
    $this->getJson('/thong-bao-game')->assertOk()->assertJsonPath('realtime', true);
    $this->getJson('/thong-bao-game')->assertOk()->assertJsonPath('realtime', true);
});

test('verification rejects provider failure wrong action and wrong hostname', function (array $result): void {
    config()->set('nro_test_response', $result);
    $this->actingAs(User::factory()->create(['role' => 'user']))->postJson(route('nro.notifies.verify'), ['cf-turnstile-response' => 'bad-token'])
        ->assertUnprocessable()->assertJsonValidationErrors('cf-turnstile-response');
    $this->getJson('/thong-bao-game')->assertOk()->assertJsonPath('realtime', true);
})->with([
    [['success' => false, 'error-codes' => ['timeout-or-duplicate']]],
    [['success' => true, 'action' => 'other', 'hostname' => 'localhost']],
    [['success' => true, 'action' => 'nro_realtime', 'hostname' => 'evil.example']],
]);

test('Turnstile errors do not revoke logged in member access', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'user']));
    Http::swap(new Factory);
    Http::fake(['challenges.cloudflare.com/*' => Http::failedConnection()]);
    $this->postJson(route('nro.notifies.verify'), ['cf-turnstile-response' => 'token'])->assertStatus(503);
    app(SettingStore::class)->putMany(['turnstile_enabled' => false]);
    $this->postJson(route('nro.notifies.verify'), ['cf-turnstile-response' => 'token'])->assertStatus(503);
    $this->getJson('/thong-bao-game')->assertOk()->assertJsonPath('realtime', true);
});

test('optional verification requires login while realtime follows the authenticated account', function (): void {
    $this->postJson(route('nro.notifies.verify'), ['cf-turnstile-response' => 'token'])->assertUnauthorized();
    $user = User::factory()->create(['role' => 'user']);
    $this->actingAs($user)->postJson(route('nro.notifies.verify'), [])->assertUnprocessable()->assertJsonValidationErrors('cf-turnstile-response');
    $this->postJson(route('nro.notifies.verify'), ['cf-turnstile-response' => 'token'])->assertOk();
    $this->withCredentials()->withCookie(config('session.cookie'), app('session')->getId());
    $this->getJson('/thong-bao-game')->assertJsonPath('realtime', true);
    $this->withHeader('User-Agent', 'another-client')->getJson('/thong-bao-game')->assertJsonPath('realtime', true);
    $this->withHeader('User-Agent', 'Symfony')->getJson('/thong-bao-game')->assertJsonPath('realtime', true);
    $this->actingAs(User::factory()->create(['role' => 'user']))->getJson('/thong-bao-game')->assertJsonPath('realtime', true);
});

test('stream slots enforce session limits and release locks', function (): void {
    $request = Request::create('/api/nro/notifies/stream', 'GET', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
    $request->setLaravelSession(app('session')->driver());
    $request->session()->start();
    $slots = app(NotificationStreamSlotsService::class);
    $first = $slots->acquire($request);
    $second = $slots->acquire($request);
    try {
        $slots->acquire($request);
        test()->fail('Third session stream should be rejected');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(429);
    }
    $slots->release($first);
    $third = $slots->acquire($request);
    $slots->release($second);
    $slots->release($third);
});

test('stream slots cap concurrent sessions sharing the same IP', function (): void {
    $slots = app(NotificationStreamSlotsService::class);
    $held = [];
    try {
        foreach (range(1, 6) as $number) {
            $request = Request::create('/api/nro/notifies/stream', 'GET', [], [], [], ['REMOTE_ADDR' => '192.0.2.10']);
            $session = clone app('session')->driver();
            $session->setId(str_repeat((string) $number, 40));
            $request->setLaravelSession($session);
            if ($number === 6) {
                expect(fn () => $slots->acquire($request))->toThrow(HttpException::class);
            } else {
                $held[] = $slots->acquire($request);
            }
        }
    } finally {
        foreach ($held as $locks) {
            $slots->release($locks);
        }
    }
});

test('guest streams stop as soon as the 15 minute trial expires', function (): void {
    $this->get('/thong-bao-game')->assertOk();
    $this->withCredentials()->withCookie(config('session.cookie'), app('session')->getId());
    $this->mock(NroNotificationFeedService::class)->shouldReceive('events')->once()->andReturnUsing(function (): Generator {
        test()->travel(16)->minutes();
        yield new StreamedEvent('notifications', ['html' => 'Fresh secret']);
    });
    $content = $this->get('/api/nro/notifies/stream')->assertOk()->streamedContent();
    expect($content)->toContain('event: guest-limit')->not->toContain('Fresh secret');
});

test('guest trial deadline survives refresh and expires exactly at 15 minutes while login restores realtime', function (): void {
    protectedNotification('Current notification', 0);
    $this->get('/thong-bao-game')->assertOk()->assertSee('Current notification');
    $deadline = session(NotificationAccessService::GUEST_EXPIRY_KEY);
    $this->withCredentials()->withCookie(config('session.cookie'), app('session')->getId());
    $this->travel(5)->minutes();
    $this->getJson('/thong-bao-game')->assertOk()->assertJsonPath('realtime', true)->assertJsonPath('guest_expires_at', $deadline * 1000);
    $this->travel(10)->minutes();
    $this->getJson('/thong-bao-game')->assertOk()->assertJsonPath('realtime', false)->assertJsonPath('stream_url', '')
        ->assertJsonPath('guest_expires_at', $deadline * 1000)->assertSee('Current notification');
    $this->getJson('/api/nro/notifies/stream')->assertForbidden();
    app(SettingStore::class)->putMany(['turnstile_enabled' => false]);
    $this->actingAs(User::factory()->create(['role' => 'user']))->getJson('/thong-bao-game')->assertOk()
        ->assertJsonPath('guest', false)->assertJsonPath('guest_expires_at', null)->assertJsonPath('realtime', true);
});

test('guest streams apply the same caps and include notifications arriving after connection', function (): void {
    $this->mock(NroNotificationFeedService::class)->shouldReceive('events')->once()->withArgs(fn (array $filters): bool => $filters['limit'] === 10 && isset($filters['_preview_since']) && ! isset($filters['_preview_cutoff']))
        ->andReturnUsing(function (): Generator {
            test()->travel(2)->seconds();
            yield new StreamedEvent('notifications', ['html' => 'Newest notification']);
        });
    $content = $this->get('/api/nro/notifies/stream?limit=100')->assertOk()->streamedContent();
    expect($content)->toContain('Newest notification');
});

test('read and verification rate limits reject excessive requests with retry guidance', function (): void {
    $user = User::factory()->create(['role' => 'user']);
    $this->actingAs($user);
    $key = 'nro:read:identity:'.hash('sha256', (string) $user->id);
    foreach (range(1, 60) as $attempt) {
        RateLimiter::hit($key, 60);
    }
    $this->getJson('/thong-bao-game')->assertTooManyRequests()->assertHeader('Retry-After');
    $key = 'nro:verify:identity:'.hash('sha256', (string) $user->id);
    foreach (range(1, 5) as $attempt) {
        RateLimiter::hit($key, 60);
    }
    $this->postJson(route('nro.notifies.verify'), ['cf-turnstile-response' => 'token'])->assertTooManyRequests();
});
