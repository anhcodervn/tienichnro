<?php

use App\Features\Client\GamePassword\Exceptions\GamePasswordFailure;
use App\Features\Client\GamePassword\Services\GamePasswordConfigurationService;
use App\Features\Client\GamePassword\Services\GamePasswordFeedService;
use App\Features\Client\GamePassword\Services\GamePasswordGatewayService;
use App\Features\Client\GamePassword\Services\GamePasswordOrderService;
use App\Jobs\ChangeGamePassword;
use App\Models\GamePasswordOrder;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Queue::fake();
    app(GamePasswordConfigurationService::class)->configure(['price' => 100, 'captcha_api_key' => 'test-key']);
    $this->owner = User::factory()->create();
    Wallet::factory()->create(['user_id' => $this->owner->id, 'balance' => 1000]);
    $this->orders = app(GamePasswordOrderService::class);
    $this->order = $this->orders->create($this->owner, [[
        'username' => 'log-account', 'server_code' => 3, 'old_password' => 'old-secret', 'new_password' => 'new-secret',
    ]], (string) Str::uuid(), 100, ['key' => 'user-api-key', 'secret' => 'user-api-secret'])->first();
});

test('worker records timestamped steps and terminal outcome without exposing secrets', function (bool $fails): void {
    $gateway = $this->mock(GamePasswordGatewayService::class);
    $gateway->shouldReceive('change')->once()->andReturnUsing(function ($username, $old, $new, $server, $progress) use ($fails): void {
        foreach (['home', 'old_check', 'login_page', 'login_captcha', 'login', $fails ? 'login_failed' : 'login_success'] as $phase) {
            $progress($phase);
        }
        if ($fails) {
            throw new GamePasswordFailure('LOGIN_FAILED');
        }
        foreach (['change', 'change_captcha', 'submitting', 'verify', 'verify_success'] as $phase) {
            $progress($phase);
        }
    });
    (new ChangeGamePassword($this->order->id))->handle($this->orders, $gateway);
    $order = $this->order->fresh();
    $codes = array_column($order->progress_logs, 'code');
    expect($codes[0])->toBe('queued')->and(array_slice($codes, -2))->toBe([$fails ? 'error' : 'success', 'done']);
    expect(array_count_values($codes)['done'])->toBe(1);
    expect($order->progress_logs[0]['at'])->toMatch('/^\d{2}:\d{2}:\d{2}$/');
    $html = app(GamePasswordFeedService::class)->snapshot($this->owner, [])['html'];
    expect($html)->toContain('Log xử lý', 'textarea readonly', 'data-password-view-log="'.$order->id.'"', 'Xem log', 'data-password-log="'.$order->id.'"', 'Đang mở trang chủ', 'Done')
        ->not->toContain('old-secret', 'new-secret', 'test-key');
    expect($html)->toContain($fails ? 'Đăng nhập thất bại' : 'Đổi mật khẩu thành công');
})->with([true, false]);

test('retry retains attempts while stale workers and unknown messages cannot append logs', function (): void {
    $this->orders->finish($this->order->id, 'error', 'LOGIN_FAILED');
    $this->orders->retry($this->owner, $this->order->id, 1);
    $this->orders->start($this->order->id, 2);
    $before = $this->order->fresh()->progress_logs;
    $this->orders->progress($this->order->id, 'login_success', 1);
    $this->orders->finish($this->order->id, 'success', attemptVersion: 1);
    $this->orders->progress($this->order->id, 'raw-password-or-token', 2);
    expect($this->order->fresh()->progress_logs)->toBe($before);
    $this->orders->progress($this->order->id, 'verify', 2);
    $logs = $this->order->fresh()->progress_logs;
    expect(end($logs)['attempt'])->toBe(2)->and(array_column($logs, 'code'))->toContain('retry');
    for ($index = 0; $index < 110; $index++) {
        $this->orders->progress($this->order->id, 'verify', 2);
    }
    expect($this->order->fresh()->progress_logs)->toHaveCount(100);
});

test('SSE sends log changes even when phase stays the same and scopes them to the owner', function (): void {
    GamePasswordOrder::factory()->create(['username' => 'other-owner']);
    $this->orders->start($this->order->id, 1);
    $events = app(GamePasswordFeedService::class)->events($this->owner, [], durationSeconds: 10, pollMicroseconds: 0);
    expect($events->current()->event)->toBe('orders');
    $this->orders->progress($this->order->id, 'login', 1);
    $events->next();
    expect($events->current()->event)->toBe('orders');
    expect($events->current()->data['html'])->toContain('Đang đăng nhập bằng mật khẩu cũ')->not->toContain('other-owner');
    $this->orders->progress($this->order->id, 'login', 1);
    $events->next();
    expect($events->current()->event)->toBe('orders');
});
