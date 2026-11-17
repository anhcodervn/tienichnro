<?php

use App\Features\Client\GamePassword\Services\GamePasswordConfigurationService;
use App\Features\Client\GamePassword\Services\GamePasswordGatewayService;
use App\Features\Client\GamePassword\Services\GamePasswordOrderService;
use App\Jobs\ChangeGamePassword;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->queueManager = Queue::getFacadeRoot();
    Queue::fake();
    $this->owner = User::factory()->create();
    $this->wallet = Wallet::factory()->create(['user_id' => $this->owner->id, 'balance' => 1000]);
    $this->orders = app(GamePasswordOrderService::class);
    app(GamePasswordConfigurationService::class)->configure(['price' => 100, 'captcha_api_key' => 'test-key']);
    $this->credentials = ['old_password' => 'old-secret', 'new_password' => 'new-secret'];
    $this->order = $this->orders->create($this->owner, [['username' => 'retry-account', 'server_code' => 3, ...$this->credentials]], (string) Str::uuid(), 100, ['key' => 'user-api-key', 'secret' => 'user-api-secret'])->first();
    $this->orders->finish($this->order->id, 'error', 'VERIFY_FAILED');
});

test('owner can queue an error again without a second charge and stale clicks are rejected', function (): void {
    $url = route('tools.game-password.retry', $this->order);
    $body = ['attempt_version' => 1];
    $this->actingAs(User::factory()->create())->postJson($url, $body)->assertForbidden();
    $this->actingAs($this->owner)->postJson($url, $body)->assertOk();
    $order = $this->order->fresh();
    expect($order->status)->toBe('pending')->and($order->attempt_version)->toBe(2)
        ->and($order->verify_before_change)->toBeTrue()->and($order->change_submitted_at)->toBeNull()
        ->and($order->old_password)->toBe('old-secret')->and($order->completed_at)->toBeNull()
        ->and($this->wallet->fresh()->balance)->toBe(900)->and(WalletTransaction::count())->toBe(1);
    expect(DB::table('game_password_orders')->where('id', $order->id)->value('old_password'))->not->toContain('old-secret');
    Queue::assertPushed(ChangeGamePassword::class, fn ($job): bool => $job->orderId === $order->id && $job->attemptVersion === 2 && ! str_contains(serialize($job), 'old-secret'));
    $this->postJson($url, $body)->assertConflict();
    Queue::assertPushed(ChangeGamePassword::class, 2);
});

test('old attempt jobs cannot start progress finish or refund a newer attempt', function (): void {
    $this->orders->retry($this->owner, $this->order->id, 1);
    $oldJob = new ChangeGamePassword($this->order->id);
    $gateway = $this->mock(GamePasswordGatewayService::class);
    $gateway->shouldNotReceive('change');
    $oldJob->handle($this->orders, $gateway);
    $oldJob->failed(null);
    expect($this->order->fresh()->status)->toBe('pending');
    $this->orders->start($this->order->id, 2);
    $this->orders->progress($this->order->id, 'submitting', 1);
    $this->orders->finish($this->order->id, 'error', 'OLD_JOB', attemptVersion: 1);
    $oldJob->failed(null);
    expect($this->order->fresh()->status)->toBe('processing')->and($this->order->fresh()->progress_code)->toBe('login')
        ->and($this->order->fresh()->change_submitted_at)->toBeNull()->and($this->wallet->fresh()->balance)->toBe(900);
});

test('retry worker verifies first then clears credentials on completion without another fee', function (): void {
    $this->orders->retry($this->owner, $this->order->id, 1);
    $gateway = $this->mock(GamePasswordGatewayService::class);
    $gateway->shouldReceive('change')->once()->withArgs(function ($username, $old, $new, $server, $progress, $verifyFirst): bool {
        $progress('verify');

        return $verifyFirst && $old === 'old-secret' && $new === 'new-secret';
    });
    (new ChangeGamePassword($this->order->id, 2))->handle($this->orders, $gateway);
    expect($this->order->fresh()->status)->toBe('success')->and($this->order->fresh()->old_password)->toBeNull()
        ->and($this->order->fresh()->new_password)->toBeNull()->and($this->wallet->fresh()->balance)->toBe(900);
});

test('interrupted retry attempts become retryable errors without refunding or charging again', function (): void {
    $this->orders->retry($this->owner, $this->order->id, 1);
    $job = new ChangeGamePassword($this->order->id, 2);
    $job->failed(null);
    expect($this->order->fresh()->status)->toBe('error')->and($this->wallet->fresh()->balance)->toBe(900);
    $this->orders->retry($this->owner, $this->order->id, 2);
    $this->orders->start($this->order->id, 3);
    $this->orders->progress($this->order->id, 'change', 3);
    $job = new ChangeGamePassword($this->order->id, 3);
    $job->failed(null);
    $job->failed(null);
    expect($this->order->fresh()->status)->toBe('error')->and($this->wallet->fresh()->balance)->toBe(900)
        ->and(WalletTransaction::where('direction', 'credit')->count())->toBe(0);
});

test('admin retry validates credentials role and status and never returns passwords', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $url = route('admin.game-password.retry', $this->order);
    $this->actingAs($this->owner)->postJson($url, ['attempt_version' => 1])->assertForbidden();
    $this->actingAs($admin)->postJson($url, [])->assertUnprocessable();
    $this->postJson($url, ['attempt_version' => 1, 'old_password' => 'same-password', 'new_password' => 'same-password'])->assertUnprocessable();
    $this->postJson($url, ['attempt_version' => 1])->assertOk()->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.attempt_version', 2)->assertDontSee('old-secret')->assertDontSee('new-secret');
    $this->postJson($url, ['attempt_version' => 2])->assertConflict();
    $admin->update(['status' => 'banned']);
    $this->postJson($url, ['attempt_version' => 2])->assertForbidden();
});

test('retry queue insertion and status update commit atomically', function (): void {
    Queue::shouldReceive('connection')->andThrow(new RuntimeException('Queue unavailable'));
    expect(fn () => $this->orders->retry($this->owner, $this->order->id, 1))->toThrow(RuntimeException::class);
    expect($this->order->fresh()->status)->toBe('error')->and($this->order->fresh()->attempt_version)->toBe(1)
        ->and($this->order->fresh()->old_password)->toBe('old-secret')->and($this->wallet->fresh()->balance)->toBe(900);
});

test('retry creates a real database queue task containing only its id and attempt version', function (): void {
    Queue::swap($this->queueManager);
    $this->orders->retry($this->owner, $this->order->id, 1);
    $payload = DB::table('jobs')->where('queue', 'game-password')->sole()->payload;
    expect($payload)->toContain('attemptVersion')->not->toContain('old-secret', 'new-secret', 'test-key');
    expect($this->order->fresh()->status)->toBe('pending');
});

test('retry is allowed only for errors or pending older than thirty minutes', function (string $status, int $ageSeconds, bool $allowed): void {
    $this->freezeTime();
    $this->order->forceFill(['status' => $status, 'created_at' => now()->subDays(2), 'queued_at' => now()->subSeconds($ageSeconds)])->save();
    $response = $this->actingAs($this->owner)->postJson(route('tools.game-password.retry', $this->order), ['attempt_version' => 1]);
    if ($allowed) {
        $response->assertOk();
        expect($this->order->fresh()->status)->toBe('pending')->and($this->order->fresh()->queued_at->getTimestamp())->toBe(now()->getTimestamp())
            ->and($this->order->fresh()->canRetry())->toBeFalse();
        Queue::assertPushed(ChangeGamePassword::class, 2);
    } else {
        $response->assertConflict();
        expect($this->order->fresh()->attempt_version)->toBe(1);
        Queue::assertPushed(ChangeGamePassword::class, 1);
    }
    expect($this->wallet->fresh()->balance)->toBe(900)->and(WalletTransaction::count())->toBe(1);
})->with([
    'fresh pending' => ['pending', 0, false],
    'pending 29 minutes' => ['pending', 1740, false],
    'pending exactly 30 minutes' => ['pending', 1800, false],
    'pending older than 30 minutes' => ['pending', 1801, true],
    'error immediately' => ['error', 0, true],
    'processing even when old' => ['processing', 7200, false],
    'review is not retryable' => ['needs_review', 7200, false],
    'success is not retryable' => ['success', 7200, false],
]);

test('the thirty minute wait starts again after every queue attempt and is reflected in history', function (): void {
    $this->freezeTime();
    $this->order->forceFill(['status' => 'pending', 'queued_at' => now()->subMinutes(30)])->save();
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->getJson(route('admin.game-password.history'))->assertOk()->assertJsonPath('data.0.can_retry', false);
    $this->travel(1)->seconds();
    $this->getJson(route('admin.game-password.history'))->assertOk()->assertJsonPath('data.0.can_retry', true);
    $this->orders->retry($this->owner, $this->order->id, 1);
    $this->getJson(route('admin.game-password.history'))->assertOk()->assertJsonPath('data.0.can_retry', false);
    $this->postJson(route('admin.game-password.retry', $this->order), ['attempt_version' => 2])->assertConflict();
});

test('legacy refunded errors can retry for free and their refund records remain intact', function (): void {
    $this->order->forceFill(['status' => 'needs_review'])->save();
    $this->orders->finish($this->order->id, 'error', 'ADMIN_REFUND', true);
    $refundId = $this->order->fresh()->refund_transaction_id;
    expect($this->wallet->fresh()->balance)->toBe(1000);
    $this->orders->retry($this->owner, $this->order->id, 1);
    $this->orders->finish($this->order->id, 'success', attemptVersion: 2);
    expect($this->wallet->fresh()->balance)->toBe(1000)->and(WalletTransaction::count())->toBe(2)
        ->and($this->order->fresh()->refund_transaction_id)->toBe($refundId)->and($this->order->fresh()->refunded_at)->not->toBeNull();
});

test('retry reacquires the account lock and refuses a competing active order', function (): void {
    $other = $this->orders->create($this->owner, [['username' => 'retry-account', 'server_code' => 3, ...$this->credentials]], (string) Str::uuid(), 100, ['key' => 'user-api-key', 'secret' => 'user-api-secret'])->first();
    $this->actingAs($this->owner)->postJson(route('tools.game-password.retry', $this->order), ['attempt_version' => 1])->assertConflict();
    expect($this->order->fresh()->status)->toBe('error')->and($other->fresh()->status)->toBe('pending');
});

test('the database worker deletes duplicate completed jobs without calling the gateway or failing them', function (): void {
    $this->order->forceFill(['status' => 'pending'])->save();
    $this->orders->finish($this->order->id, 'success');
    Queue::swap($this->queueManager);
    $gateway = $this->mock(GamePasswordGatewayService::class);
    $gateway->shouldNotReceive('change');
    ChangeGamePassword::dispatch($this->order->id, 1);
    ChangeGamePassword::dispatch($this->order->id, 1);
    expect(DB::table('jobs')->where('queue', 'game-password')->count())->toBe(2);
    for ($index = 0; $index < 2; $index++) {
        $this->artisan('queue:work', ['connection' => 'game_password', '--queue' => 'game-password', '--once' => true, '--tries' => 1])->assertSuccessful();
    }
    expect(DB::table('jobs')->where('queue', 'game-password')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0)->and($this->order->fresh()->status)->toBe('success')
        ->and($this->wallet->fresh()->balance)->toBe(900)->and(WalletTransaction::count())->toBe(1);
});

test('the database worker drops jobs whose orders were already cleared instead of recording failures', function (): void {
    $id = $this->order->id;
    $this->order->delete();
    Queue::swap($this->queueManager);
    $gateway = $this->mock(GamePasswordGatewayService::class);
    $gateway->shouldNotReceive('change');
    ChangeGamePassword::dispatch($id, 1);
    $this->artisan('queue:work', ['connection' => 'game_password', '--queue' => 'game-password', '--once' => true, '--tries' => 1])->assertSuccessful();
    expect(DB::table('jobs')->where('queue', 'game-password')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0)->and(WalletTransaction::count())->toBe(1);
});

test('retries preserve and reuse encrypted saved credentials without accepting replacements from the client', function (): void {
    $before = DB::table('game_password_orders')->where('id', $this->order->id)->first(['old_password', 'new_password']);
    expect($before->old_password)->not->toContain('old-secret');
    expect($before->new_password)->not->toContain('new-secret');
    $url = route('tools.game-password.retry', $this->order);
    $this->actingAs($this->owner)->postJson($url, ['attempt_version' => 1, 'old_password' => 'replacement-old', 'new_password' => 'replacement-new'])
        ->assertUnprocessable()->assertJsonValidationErrors(['old_password', 'new_password']);
    $this->postJson($url, ['attempt_version' => 1])->assertOk()->assertDontSee('old-secret')->assertDontSee('new-secret');
    $after = DB::table('game_password_orders')->where('id', $this->order->id)->first(['old_password', 'new_password']);
    expect($after->old_password)->toBe($before->old_password)->and($after->new_password)->toBe($before->new_password);
    $this->orders->finish($this->order->id, 'error', 'REMOTE_TIMEOUT', attemptVersion: 2);
    $this->postJson($url, ['attempt_version' => 2])->assertOk();
    expect($this->order->fresh()->old_password)->toBe('old-secret')->and($this->order->fresh()->new_password)->toBe('new-secret')
        ->and($this->wallet->fresh()->balance)->toBe(900)->and(WalletTransaction::count())->toBe(1);
});

test('legacy orders with erased credentials do not enqueue an empty task or charge the owner', function (): void {
    $this->order->forceFill(['old_password' => null, 'new_password' => null])->save();
    $this->actingAs($this->owner)->postJson(route('tools.game-password.retry', $this->order), ['attempt_version' => 1])->assertUnprocessable()->assertJsonValidationErrors('order');
    Queue::assertPushed(ChangeGamePassword::class, 1);
    expect($this->order->fresh()->status)->toBe('error')->and($this->order->fresh()->attempt_version)->toBe(1)
        ->and($this->wallet->fresh()->balance)->toBe(900);
    $this->get(route('tools.game-password'))->assertOk()->assertSee('Đơn cũ không còn mật khẩu đã lưu.');
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->getJson(route('admin.game-password.history'))->assertOk()->assertJsonPath('data.0.has_saved_credentials', false)
        ->assertJsonMissingPath('data.0.old_password')->assertJsonMissingPath('data.0.new_password');
});
