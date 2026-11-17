<?php

use App\Features\Client\GamePassword\Services\GamePasswordConfigurationService;
use App\Features\Client\GamePassword\Services\GamePasswordFeedService;
use App\Features\Client\GamePassword\Services\GamePasswordHistoryCleanupService;
use App\Features\Client\GamePassword\Services\GamePasswordOrderService;
use App\Models\GamePasswordOrder;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\EventStreamRuntime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    $this->app->instance(EventStreamRuntime::class, new EventStreamRuntime('cli-server'));
    Queue::fake();
});

test('owner SSE is private paginated filtered and excludes credentials and other owners', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    GamePasswordOrder::factory()->create(['user_id' => $other->id, 'username' => 'another-owner-account']);
    GamePasswordOrder::factory()->count(21)->create(['user_id' => $owner->id]);
    $this->getJson(route('tools.game-password.stream'))->assertUnauthorized();
    $response = $this->actingAs($owner)->get(route('tools.game-password.stream', ['page' => 2]));
    $response->assertOk()->assertHeader('content-type', 'text/event-stream; charset=utf-8');
    $data = $response->streamedContent();
    expect($data)->toContain('event: orders', 'data-password-order', '21', 'page=1')
        ->not->toContain('another-owner-account', 'old-password', 'new-password', 'payload_hash');
    $this->getJson(route('tools.game-password.stream', ['status' => 'bad']))->assertUnprocessable();
    $owner->update(['status' => 'banned']);
    $this->getJson(route('tools.game-password.stream'))->assertForbidden();
});

test('feed emits each changed account phase and closes when access expires', function (): void {
    $owner = User::factory()->create();
    $order = GamePasswordOrder::factory()->create(['user_id' => $owner->id]);
    $events = app(GamePasswordFeedService::class)->events($owner, [], durationSeconds: 10, pollMicroseconds: 0);
    expect($events->current()->event)->toBe('orders');
    expect($events->current()->data['html'])->toContain('data-password-status="pending"');
    $order->forceFill(['status' => 'processing', 'progress_code' => 'login'])->save();
    $events->next();
    expect($events->current()->data['html'])->toContain('data-password-status="processing"');
    app(GamePasswordOrderService::class)->progress($order->id, 'verify');
    $events->next();
    expect($events->current()->event)->toBe('orders');
    $order->forceFill(['status' => 'success', 'progress_code' => 'success'])->save();
    $events->next();
    expect($events->current()->data['html'])->toContain('data-password-status="success"');
    $events->next();
    expect($events->current()->event)->toBe('heartbeat');
    $owner->update(['status' => 'banned']);
    $events->next();
    expect($events->current()->event)->toBe('access-expired');
    $events->next();
    expect($events->valid())->toBeFalse();
});

test('admin stream guards roles and sends safe status resources', function (): void {
    $owner = User::factory()->create();
    $order = GamePasswordOrder::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner)->getJson(route('admin.game-password.stream'))->assertForbidden();
    $admin = User::factory()->create(['role' => 'admin']);
    $response = $this->actingAs($admin)->get(route('admin.game-password.stream'));
    $response->assertOk();
    expect($response->streamedContent())->toContain('event: orders', '"status":"pending"')->not->toContain('old-password', 'new-password', 'payload_hash');
    $events = app(GamePasswordFeedService::class)->events($admin, [], admin: true, durationSeconds: 10, pollMicroseconds: 0);
    expect($events->current()->data['data'][0]['id'])->toBe($order->id);
    $admin->update(['role' => 'user']);
    $events->next();
    expect($events->current()->event)->toBe('access-expired');
});

test('cleanup only deletes old terminal orders and retains wallet records and replay protection', function (): void {
    app(GamePasswordConfigurationService::class)->configure(['price' => 100, 'captcha_api_key' => 'test-key']);
    $owner = User::factory()->create();
    $wallet = Wallet::factory()->create(['user_id' => $owner->id, 'balance' => 1000]);
    $requestId = (string) Str::uuid();
    $entries = [['username' => 'account', 'old_password' => 'old-secret', 'new_password' => 'new-secret', 'server_code' => 3]];
    $service = app(GamePasswordOrderService::class);
    $order = $service->create($owner, $entries, $requestId, 100, ['key' => 'user-api-key', 'secret' => 'user-api-secret'])->first();
    $service->finish($order->id, 'error', 'LOGIN_FAILED');
    $order->refresh()->forceFill(['completed_at' => now()->subDays(31)])->save();
    GamePasswordOrder::factory()->create(['user_id' => $owner->id, 'status' => 'success', 'completed_at' => now()->subDays(31)]);
    $retained = collect(['pending', 'processing', 'needs_review'])->map(fn (string $status) => GamePasswordOrder::factory()->create(['status' => $status, 'completed_at' => now()->subDays(40)]));
    $retained->push(GamePasswordOrder::factory()->create(['status' => 'success', 'completed_at' => now()->subDays(29)]));
    $retained->push(GamePasswordOrder::factory()->create(['status' => 'success', 'completed_at' => null]));
    $retained->push(GamePasswordOrder::factory()->create(['status' => 'error', 'completed_at' => now()->subDays(40), 'active_key' => 'still-active']));
    $transactions = WalletTransaction::count();
    $this->actingAs($owner)->deleteJson(route('admin.game-password.clear'), ['older_than_days' => 30])->assertForbidden();
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->deleteJson(route('admin.game-password.clear'), ['older_than_days' => 0])->assertUnprocessable();
    $this->deleteJson(route('admin.game-password.clear'), ['older_than_days' => 30])->assertOk()->assertJsonPath('data.deleted', 2)->assertJsonPath('data.has_more', false);
    expect(GamePasswordOrder::whereKey($order->id)->exists())->toBeFalse()
        ->and(GamePasswordOrder::whereIn('id', $retained->pluck('id'))->count())->toBe(6)
        ->and(WalletTransaction::count())->toBe($transactions)->and($wallet->fresh()->balance)->toBe(900);
    $this->deleteJson(route('admin.game-password.clear'), ['older_than_days' => 30])->assertOk()->assertJsonPath('data.deleted', 0);
    expect(fn () => $service->create($owner, $entries, $requestId, 100, ['key' => 'user-api-key', 'secret' => 'user-api-secret']))->toThrow(HttpException::class);
    expect(WalletTransaction::count())->toBe($transactions)->and($wallet->fresh()->balance)->toBe(900);
    $admin->update(['status' => 'banned']);
    $this->deleteJson(route('admin.game-password.clear'), ['older_than_days' => 30])->assertForbidden();
});

test('selected ids delete only specified terminal orders and retain replay and wallet records', function (): void {
    app(GamePasswordConfigurationService::class)->configure(['price' => 100, 'captcha_api_key' => 'test-key']);
    $owner = User::factory()->create();
    $wallet = Wallet::factory()->create(['user_id' => $owner->id, 'balance' => 1000]);
    $requestId = (string) Str::uuid();
    $entries = [['username' => 'selected-account', 'old_password' => 'old-secret', 'new_password' => 'new-secret', 'server_code' => 3]];
    $service = app(GamePasswordOrderService::class);
    $order = $service->create($owner, $entries, $requestId, 100, ['key' => 'user-api-key', 'secret' => 'user-api-secret'])->first();
    $service->finish($order->id, 'success');
    $other = GamePasswordOrder::factory()->create(['status' => 'error', 'completed_at' => now()]);
    $unselected = GamePasswordOrder::factory()->create(['status' => 'success', 'completed_at' => now()->subDays(40)]);
    $protected = collect(['pending', 'processing', 'needs_review'])->map(fn (string $status) => GamePasswordOrder::factory()->create(['status' => $status]));
    $protected->push(GamePasswordOrder::factory()->create(['status' => 'success', 'completed_at' => null]));
    $protected->push(GamePasswordOrder::factory()->create(['status' => 'success', 'completed_at' => now(), 'active_key' => 'still-active']));
    $ids = [$order->id, $other->id, ...$protected->pluck('id')->all(), 999999];
    $this->actingAs($owner)->deleteJson(route('admin.game-password.clear'), ['ids' => $ids])->assertForbidden();
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->deleteJson(route('admin.game-password.clear'), ['ids' => $ids])->assertOk()
        ->assertJsonPath('data.deleted', 2)->assertJsonPath('data.skipped', 6);
    expect(GamePasswordOrder::whereIn('id', [$order->id, $other->id])->count())->toBe(0)
        ->and(GamePasswordOrder::whereIn('id', $protected->pluck('id'))->count())->toBe(5)
        ->and($unselected->fresh())->not->toBeNull()->and($wallet->fresh()->balance)->toBe(900)
        ->and(WalletTransaction::count())->toBe(1);
    $this->deleteJson(route('admin.game-password.clear'), ['ids' => $ids])->assertOk()->assertJsonPath('data.deleted', 0)->assertJsonPath('data.skipped', count($ids));
    expect(fn () => $service->create($owner, $entries, $requestId, 100, ['key' => 'user-api-key', 'secret' => 'user-api-secret']))->toThrow(HttpException::class);
    expect($wallet->fresh()->balance)->toBe(900)->and(WalletTransaction::count())->toBe(1);
    $admin->update(['status' => 'banned']);
    $this->deleteJson(route('admin.game-password.clear'), ['ids' => [$unselected->id]])->assertForbidden();
});

test('selected deletion rejects empty duplicate oversized and ambiguous cleanup requests', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    foreach ([[], ['ids' => []], ['ids' => [1, 1]], ['ids' => [0]], ['ids' => '1,2'], ['ids' => range(1, 1001)], ['ids' => [1], 'older_than_days' => 30]] as $payload) {
        $this->deleteJson(route('admin.game-password.clear'), $payload)->assertUnprocessable();
    }
});

test('cleanup is bounded to one thousand rows per action', function (): void {
    $owner = User::factory()->create();
    $now = now();
    $rows = [];
    for ($index = 0; $index < 1001; $index++) {
        $rows[] = ['user_id' => $owner->id, 'request_id' => (string) Str::uuid(), 'row_index' => 0, 'payload_hash' => str_repeat('a', 64),
            'username' => 'account-'.$index, 'server_code' => 3, 'status' => 'success', 'progress_code' => 'success',
            'completed_at' => $now->copy()->subDays(31), 'created_at' => $now->copy()->subDays(32), 'updated_at' => $now];
    }
    foreach (array_chunk($rows, 100) as $chunk) {
        DB::table('game_password_orders')->insert($chunk);
    }
    $service = app(GamePasswordHistoryCleanupService::class);
    expect($service->clear(30))->toBe(['deleted' => 1000, 'has_more' => true]);
    expect($service->clear(30))->toBe(['deleted' => 1, 'has_more' => false]);
});
