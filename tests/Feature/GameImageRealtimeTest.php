<?php

use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Features\Client\GameImage\Services\GameImageAccountFeedService;
use App\Features\Client\GameImage\Services\GameImageOrderService;
use App\Models\GameImageAccount;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\EventStreamRuntime;
use Illuminate\Support\Facades\Cache;

test('snapshots scope paginate and escape errors without credentials', function (): void {
    $user = User::factory()->create();
    GameImageAccount::factory()->count(21)->create(['user_id' => $user->id, 'status' => 'error', 'server' => 'Target', 'error_code' => 'LOGIN_FAILED', 'error_message' => '<script>bad()</script>', 'password' => 'never-send-password']);
    GameImageAccount::factory()->create(['username' => 'other-owner-secret']);
    $feed = app(GameImageAccountFeedService::class);
    $snapshot = $feed->snapshot($user, ['status' => 'error', 'q' => 'Target']);
    expect($snapshot['total'])->toBe(21)->and($snapshot['html'])->toContain('LOGIN_FAILED', '&lt;script&gt;bad()&lt;/script&gt;', '/ghep-anh-game?')
        ->not->toContain('never-send-password', 'other-owner-secret', '<script>bad()', '/trang-thai/stream?')
        ->and(substr_count($snapshot['html'], 'data-game-image-username '))->toBe(20)
        ->and($feed->snapshot($user, ['status' => 'pending'])['total'])->toBe(0)
        ->and($feed->snapshot($user, ['status' => 'error', 'q' => 'Target'])['signature'])->toBe($snapshot['signature']);
});

test('generator emits changes and heartbeat then stops when access is revoked', function (): void {
    $account = GameImageAccount::factory()->create();
    $user = User::findOrFail($account->user_id);
    $events = app(GameImageAccountFeedService::class)->events($user, [], 5, 0);
    expect($events->current()->event)->toBe('accounts');
    $events->next();
    expect($events->current()->event)->toBe('heartbeat');
    $account->forceFill(['status' => 'processing'])->save();
    $events->next();
    expect($events->current()->event)->toBe('accounts');
    $user->update(['status' => 'inactive']);
    $events->next();
    expect($events->current()->event)->toBe('access-expired');
    $events->next();
    expect($events->valid())->toBeFalse();
});

test('stream authenticates validates limits connections and releases its lock', function (): void {
    $url = route('tools.game-image.stream');
    $this->getJson($url)->assertUnauthorized();
    $user = User::factory()->create();
    $this->actingAs($user)->getJson($url.'?status=invalid')->assertUnprocessable();
    $feed = app(GameImageAccountFeedService::class);
    $this->mock(GameImageAccountFeedService::class)->shouldReceive('events')->once()->andReturn($feed->events($user, [], 0, 0));
    $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'text/event-stream; charset=utf-8')->assertHeader('X-Accel-Buffering', 'no');
    expect($response->streamedContent())->toContain('event: accounts', '"html"')->not->toContain('password_encrypted');
    $first = Cache::lock('game-image:stream:'.$user->id.':0', 40);
    $second = Cache::lock('game-image:stream:'.$user->id.':1', 40);
    expect($first->get())->toBeTrue()->and($second->get())->toBeTrue();
    $this->getJson($url)->assertStatus(429);
    $first->release();
    $second->release();
});

test('error code persists with one refund and clears on recapture', function (): void {
    $orders = app(GameImageOrderService::class);
    $orders->configure(100);
    $user = User::factory()->create();
    $wallet = Wallet::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
    $account = $orders->create($user, ['username' => 'capture-account', 'password' => 'secret', 'server' => '1']);
    $admin = User::factory()->create(['role' => 'admin']);
    $url = route('admin.game-image.status', $account);
    $payload = ['status' => 'error', 'error_code' => 'CLIENT_EXITED', 'error_message' => 'LiteClient exited before capture completed.'];
    $this->actingAs($admin)->patchJson($url, $payload)->assertOk()->assertJsonPath('data.error_code', 'CLIENT_EXITED');
    $this->patchJson($url, [...$payload, 'error_code' => 'DUPLICATE'])->assertOk()->assertJsonPath('data.error_code', 'CLIENT_EXITED');
    expect($wallet->fresh()->balance)->toBe(1000)->and(WalletTransaction::query()->where('direction', 'credit')->count())->toBe(1)
        ->and(app(GameImageAccountFeedService::class)->snapshot($user, [])['html'])->toContain('CLIENT_EXITED', $payload['error_message']);
    $orders->recapture($account->fresh(), 1, 100);
    expect($account->fresh()->error_code)->toBeNull()->and($account->fresh()->error_message)->toBeNull();
    $this->patchJson($url, [...$payload, 'capture_version' => 2, 'error_code' => '<invalid>'])->assertUnprocessable();
});

test('stream releases its slot when rendering fails', function (): void {
    $user = User::factory()->create();
    $events = (function (): Generator {
        throw new RuntimeException('Snapshot unavailable');
        yield;
    })();
    $this->mock(GameImageAccountFeedService::class)->shouldReceive('events')->once()->andReturn($events);
    $response = $this->actingAs($user)->get(route('tools.game-image.stream'))->assertOk();
    ob_start();
    try {
        expect(fn () => $response->baseResponse->sendContent())->toThrow(RuntimeException::class, 'Snapshot unavailable');
    } finally {
        ob_end_clean();
    }
    $lock = Cache::lock('game-image:stream:'.$user->id.':0', 40);
    expect($lock->get())->toBeTrue();
    $lock->release();
});

test('built in server sends one snapshot without retaining its worker', function (): void {
    $this->app->instance(EventStreamRuntime::class, new EventStreamRuntime('cli-server'));
    $user = User::factory()->create();
    $started = microtime(true);
    $response = $this->actingAs($user)->get(route('tools.game-image.stream'))->assertOk();
    $body = $response->streamedContent();
    expect($body)->toContain("retry: 3000\n\n", 'event: accounts')
        ->and(substr_count($body, 'event: accounts'))->toBe(1)
        ->and($body)->not->toContain('event: heartbeat')
        ->and(microtime(true) - $started)->toBeLessThan(2)
        ->and((new EventStreamRuntime('fpm-fcgi'))->durationSeconds())->toBe(25.0);
    $lock = Cache::lock('game-image:stream:'.$user->id.':0', 40);
    expect($lock->get())->toBeTrue();
    $lock->release();
});

test('maintenance ends existing streams without another account snapshot', function (): void {
    $user = User::factory()->create();
    $tools = $this->mock(ToolAvailabilityService::class);
    $tools->shouldReceive('find')->with('game_image')->once()->andReturn(['is_enabled' => true]);
    $tools->shouldReceive('find')->with('game_image')->once()->andReturn(['is_enabled' => false]);
    $events = app(GameImageAccountFeedService::class)->events($user, [], 5, 0);
    expect($events->current()->event)->toBe('accounts');
    $events->next();
    expect($events->current()->event)->toBe('maintenance');
    $events->next();
    expect($events->valid())->toBeFalse();
});
