<?php

use App\Features\Client\GamePassword\Exceptions\GamePasswordFailure;
use App\Features\Client\GamePassword\Services\GamePasswordConfigurationService;
use App\Features\Client\GamePassword\Services\GamePasswordGatewayService;
use App\Features\Client\GamePassword\Services\GamePasswordOrderService;
use App\Jobs\ChangeGamePassword;
use App\Models\GamePasswordOrder;
use App\Models\ManagedServer;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function passwordEntry(string $username = 'my-game-user'): array
{
    return ['username' => $username, 'old_password' => 'old-secret', 'new_password' => 'new-secret', 'server_code' => 3];
}
function passwordRequest(): array
{
    return [...passwordEntry(), 'captcha_api_key' => 'user-api-key', 'captcha_api_secret' => 'user-api-secret', 'request_id' => (string) Str::uuid(), 'price' => 100];
}

beforeEach(function (): void {
    $this->queueManager = Queue::getFacadeRoot();
    Queue::fake();
    $this->user = User::factory()->create();
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->wallet = Wallet::factory()->create(['user_id' => $this->user->id, 'balance' => 1000]);
    ManagedServer::query()->firstOrCreate(['type' => 'website', 'server_id' => '3'], ['name' => 'Server 3', 'is_active' => true, 'sort_order' => 3])->update(['is_active' => true]);
    app(GamePasswordConfigurationService::class)->configure(['price' => 100, 'captcha_api_key' => 'private-solver-key']);
});

test('password tool uses active website servers and submits their configured ID', function (): void {
    $server = ManagedServer::query()->create(['type' => 'website', 'server_id' => '731', 'name' => 'Shared notification server', 'is_active' => true, 'sort_order' => 0]);
    ManagedServer::query()->create(['type' => 'website', 'server_id' => '732', 'name' => 'Disabled notification server', 'is_active' => false, 'sort_order' => 0]);
    $expected = ManagedServer::activeOptions('website');
    ManagedServer::factory()->create(['type' => 'game', 'server_id' => '733', 'name' => 'Game only server']);
    ManagedServer::factory()->create(['type' => 'carot', 'server_id' => '734', 'name' => 'Carot only server']);
    $this->actingAs($this->user)->get(route('tools.game-password'))->assertOk()
        ->assertViewHas('servers', fn ($servers): bool => $servers->modelKeys() === $expected->modelKeys())
        ->assertSee('<option value="731"', false)->assertSee('Shared notification server')->assertDontSee('Disabled notification server');
    expect($server->id)->not->toBe((int) $server->server_id);
    $this->post(route('tools.game-password.store'), [...passwordRequest(), 'server_code' => 731])->assertRedirect(route('tools.game-password'));
    expect(GamePasswordOrder::latest('id')->first()->server_code)->toBe(731);
    $this->postJson(route('tools.game-password.store'), [...passwordRequest(), 'server_code' => 732])->assertUnprocessable();
    $this->postJson(route('tools.game-password.store'), [...passwordRequest(), 'server_code' => 733])->assertUnprocessable();
    $this->postJson(route('tools.game-password.store'), [...passwordRequest(), 'server_code' => 734])->assertUnprocessable();
    expect(GamePasswordOrder::count())->toBe(1)->and($this->wallet->fresh()->balance)->toBe(900);
});

test('missing user captcha credentials rejects orders before charging even when admin has a key', function (): void {
    app(GamePasswordConfigurationService::class)->configure(['price' => 100, 'captcha_provider' => 'captcha69', 'captcha_api_key' => 'point_key']);
    $this->actingAs($this->user)->postJson(route('tools.game-password.store'), array_diff_key(passwordRequest(), array_flip(['captcha_api_key', 'captcha_api_secret'])))->assertUnprocessable();
    expect($this->wallet->fresh()->balance)->toBe(1000)->and(GamePasswordOrder::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('public tool uses shared hero and authenticated form with safe configuration', function (): void {
    $this->get(route('tools.game-password'))->assertOk()->assertSee('data-tool-page', false)->assertDontSee('name="old_password"', false)->assertDontSee('private-solver-key');
    $this->actingAs($this->user)->get(route('tools.game-password'))->assertOk()->assertSee('data-password-bulk-toggle', false)
        ->assertSee('name="old_password"', false)->assertSee('name="new_password"', false)->assertSee('data-password-history', false)->assertDontSee('private-solver-key')
        ->assertSee('class="client-button w-full gap-2 sm:w-auto"', false)->assertSee('data-password-submit-label', false)->assertSee('Đổi mật khẩu · 1 tài khoản')->assertDontSee('client-button-primary')
        ->assertSee('class="grid w-full min-w-0 max-w-full grid-cols-1 gap-4"', false)
        ->assertSee('class="grid min-w-0 grid-cols-1 items-start gap-4 sm:grid-cols-2 xl:grid-cols-4"', false)
        ->assertSee('id="game-password-old"', false)->assertSee('data-password-visibility="game-password-new"', false)
        ->assertSee('placeholder="Nhập tài khoản game"', false)->assertSee('placeholder="Nhập mật khẩu hiện tại"', false)
        ->assertSee('placeholder="Mật khẩu mới từ 6 ký tự"', false)
        ->assertSee('stroke="currentColor"', false)->assertSee('group-aria-pressed:block', false)
        ->assertDontSee('bx bx-show text-lg', false)->assertDontSee('bx bx-hide text-lg', false);
});

test('single requests charge once and enqueue only the order ID on an asynchronous connection', function (): void {
    $payload = passwordRequest();
    $this->actingAs($this->user)->postJson(route('tools.game-password.store'), $payload)->assertRedirect(route('tools.game-password'));
    $order = GamePasswordOrder::query()->sole();
    expect($order->status)->toBe('pending')->and($order->charged_amount)->toBe(100)->and($this->wallet->fresh()->balance)->toBe(900)
        ->and($order->old_password)->toBe('old-secret')->and(DB::table('game_password_orders')->value('old_password'))->not->toBe('old-secret');
    Queue::assertPushed(ChangeGamePassword::class, fn ($job): bool => $job->orderId === $order->id && $job->connection === 'game_password' && $job->queue === 'game-password' && $job->timeout < config('queue.connections.game_password.retry_after') && ! str_contains(serialize($job), 'old-secret'));
    $this->postJson(route('tools.game-password.store'), $payload)->assertRedirect();
    Queue::assertPushed(ChangeGamePassword::class, 1);
    expect($this->wallet->fresh()->balance)->toBe(900)->and(GamePasswordOrder::count())->toBe(1);
    $this->postJson(route('tools.game-password.store'), [...$payload, 'new_password' => 'different-secret'])->assertConflict();
});

test('bulk parser supports server prefixes and creates one independent job per account', function (): void {
    $payload = ['captcha_api_key' => 'user-api-key', 'captcha_api_secret' => 'user-api-secret', 'bulk' => true, 'accounts_text' => "first-user|old-one|sv3|new-one\r\nsecond-user|old-two|3|new-two", 'price' => 100, 'request_id' => (string) Str::uuid()];
    $this->actingAs($this->user)->postJson(route('tools.game-password.store'), $payload)->assertRedirect();
    expect(GamePasswordOrder::count())->toBe(2)->and($this->wallet->fresh()->balance)->toBe(800)
        ->and(GamePasswordOrder::orderBy('row_index')->pluck('server_code')->all())->toBe([3, 3]);
    Queue::assertPushed(ChangeGamePassword::class, 2);
});

test('a bulk payment failure rolls back all accounts wallet entries and jobs', function (): void {
    $entries = array_map(fn ($i) => passwordEntry('user-'.$i), range(1, 11));
    expect(fn () => app(GamePasswordOrderService::class)->create($this->user, $entries, (string) Str::uuid(), 100, ['key' => 'user-api-key', 'secret' => 'user-api-secret']))->toThrow(ValidationException::class);
    expect(GamePasswordOrder::count())->toBe(0)->and(WalletTransaction::count())->toBe(0)->and($this->wallet->fresh()->balance)->toBe(1000);
    Queue::assertNothingPushed();
});

test('invalid bulk accounts are rejected without charge or secret flashes', function (): void {
    $payload = ['captcha_api_key' => 'user-api-key', 'captcha_api_secret' => 'user-api-secret', 'bulk' => 1, 'accounts_text' => 'secret-user|old-secret|sv999|new-secret', 'price' => 100, 'request_id' => (string) Str::uuid()];
    $this->actingAs($this->user)->post(route('tools.game-password.store'), $payload)->assertSessionHasErrors();
    expect(session()->getOldInput('accounts_text'))->toBeNull()->and(session()->getOldInput('entries'))->toBeNull();
    $this->postJson(route('tools.game-password.store'), [...$payload, 'accounts_text' => "user|old-secret|3|new-secret\nUSER|old-secret|3|new-secret"])->assertUnprocessable();
    $this->postJson(route('tools.game-password.store'), [...$payload, 'accounts_text' => 'bad|line'])->assertUnprocessable();
    $this->postJson(route('tools.game-password.store'), [...passwordRequest(), 'new_password' => 'old-secret'])->assertUnprocessable();
    expect(GamePasswordOrder::count())->toBe(0)->and($this->wallet->fresh()->balance)->toBe(1000);
});

test('credentials retain their whitespace and active account locks cover different users', function (): void {
    $data = passwordRequest();
    $data['old_password'] = ' old-secret ';
    $data['new_password'] = ' new-secret ';
    $this->actingAs($this->user)->postJson(route('tools.game-password.store'), $data)->assertRedirect();
    expect(GamePasswordOrder::query()->sole()->old_password)->toBe(' old-secret ');
    $other = User::factory()->create();
    Wallet::factory()->create(['user_id' => $other->id, 'balance' => 1000]);
    $this->actingAs($other)->postJson(route('tools.game-password.store'), passwordRequest())->assertUnprocessable();
});

test('worker confirms success clears credentials and duplicate delivery does not call the gateway twice', function (): void {
    $order = app(GamePasswordOrderService::class)->create($this->user, [passwordEntry()], (string) Str::uuid(), 100, ['key' => 'user-api-key', 'secret' => 'user-api-secret'])->first();
    $gateway = $this->mock(GamePasswordGatewayService::class);
    $gateway->shouldReceive('change')->once()->withArgs(function ($username, $old, $new, $server, $progress): bool {
        $progress('submitting');
        $progress('verify');

        return $username === 'my-game-user' && $old === 'old-secret' && $new === 'new-secret' && $server === 3;
    });
    $job = new ChangeGamePassword($order->id);
    $job->handle(app(GamePasswordOrderService::class), $gateway);
    $job->handle(app(GamePasswordOrderService::class), $gateway);
    expect($order->fresh()->status)->toBe('success')->and($order->fresh()->old_password)->toBeNull()->and($order->fresh()->new_password)->toBeNull()
        ->and($order->fresh()->active_key)->toBeNull()->and($this->wallet->fresh()->balance)->toBe(900);
});

test('failed tasks keep the original fee and remain retryable after prices change', function (): void {
    $orders = app(GamePasswordOrderService::class);
    $order = $orders->create($this->user, [passwordEntry()], (string) Str::uuid(), 100, ['key' => 'user-api-key', 'secret' => 'user-api-secret'])->first();
    app(GamePasswordConfigurationService::class)->configure(['price' => 300]);
    $gateway = $this->mock(GamePasswordGatewayService::class);
    $gateway->shouldReceive('change')->once()->andThrow(new GamePasswordFailure('LOGIN_FAILED'));
    (new ChangeGamePassword($order->id))->handle($orders, $gateway);
    $orders->finish($order->id, 'error', 'LOGIN_FAILED');
    expect($order->fresh()->status)->toBe('error')->and($this->wallet->fresh()->balance)->toBe(900)
        ->and(WalletTransaction::where('direction', 'credit')->count())->toBe(0)->and($order->fresh()->old_password)->toBe('old-secret')
        ->and($order->fresh()->canRetry())->toBeTrue();
});

test('ambiguous outcomes become retryable errors while legacy reviews still require admin resolution', function (): void {
    $orders = app(GamePasswordOrderService::class);
    $order = $orders->create($this->user, [passwordEntry()], (string) Str::uuid(), 100, ['key' => 'user-api-key', 'secret' => 'user-api-secret'])->first();
    $gateway = $this->mock(GamePasswordGatewayService::class);
    $gateway->shouldReceive('change')->once()->andReturnUsing(function ($u, $o, $n, $s, $progress): void {
        $progress('submitting');
        throw new GamePasswordFailure('VERIFY_FAILED', true);
    });
    (new ChangeGamePassword($order->id))->handle($orders, $gateway);
    expect($order->fresh()->status)->toBe('error')->and($order->fresh()->canRetry())->toBeTrue()->and($this->wallet->fresh()->balance)->toBe(900);
    $order->forceFill(['status' => 'needs_review', 'active_key' => hash('sha256', 'legacy-review')])->save();
    $url = route('admin.game-password.resolve', $order);
    $this->actingAs($this->user)->patchJson($url, ['status' => 'error'])->assertForbidden();
    $this->actingAs($this->admin)->patchJson($url, ['status' => 'error'])->assertOk()->assertJsonPath('data.status', 'error');
    $this->patchJson($url, ['status' => 'error'])->assertConflict();
    expect($order->fresh()->active_key)->toBeNull()->and($this->wallet->fresh()->balance)->toBe(1000);
});

test('worker interruptions become errors and retain the original fee for free retries', function (): void {
    $orders = app(GamePasswordOrderService::class);
    $order = $orders->create($this->user, [passwordEntry()], (string) Str::uuid(), 100, ['key' => 'user-api-key', 'secret' => 'user-api-secret'])->first();
    $orders->start($order->id);
    (new ChangeGamePassword($order->id))->failed(new RuntimeException('secret should not be stored'));
    expect($order->fresh()->status)->toBe('error')->and($this->wallet->fresh()->balance)->toBe(900);
    $other = $orders->create($this->user, [passwordEntry()], (string) Str::uuid(), 100, ['key' => 'user-api-key', 'secret' => 'user-api-secret'])->first();
    $orders->start($other->id);
    $orders->progress($other->id, 'submitting');
    (new ChangeGamePassword($other->id))->failed(null);
    expect($other->fresh()->status)->toBe('error')->and($other->fresh()->error_code)->toBe('JOB_INTERRUPTED');
});

test('admin config encrypts solver key and only configured tools accept a quoted price', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'banned']))->getJson(route('admin.game-password.configuration'))->assertForbidden();
    $this->actingAs($this->user)->getJson(route('admin.game-password.configuration'))->assertForbidden();
    $this->actingAs($this->admin)->putJson(route('admin.game-password.configure'), ['price' => 0, 'captcha_api_key' => 'changed-key'])->assertOk()->assertJsonPath('data.has_api_key', true)->assertDontSee('changed-key');
    expect(Setting::where('key', 'game_password.captcha_api_key')->value('value'))->not->toContain('changed-key');
    $this->actingAs($this->user)->postJson(route('tools.game-password.store'), passwordRequest())->assertUnprocessable();
    $this->postJson(route('tools.game-password.store'), [...passwordRequest(), 'price' => 0])->assertRedirect();
    expect($this->wallet->fresh()->balance)->toBe(1000);
    $this->actingAs($this->admin)->putJson(route('admin.game-password.configure'), ['price' => 0, 'clear_api_key' => true])->assertOk()->assertJsonPath('data.has_api_key', false);
    $this->actingAs($this->user)->postJson(route('tools.game-password.store'), [...passwordRequest(), 'price' => 0, 'username' => 'second-user'])->assertRedirect();
});

test('history is paginated filtered owner scoped and never contains passwords', function (): void {
    GamePasswordOrder::factory()->count(21)->create(['user_id' => $this->user->id, 'old_password' => 'history-secret-old', 'new_password' => 'history-secret-new']);
    GamePasswordOrder::factory()->create(['username' => 'stranger-private']);
    $this->actingAs($this->user)->get(route('tools.game-password'))->assertOk()->assertViewHas('orders', fn ($rows): bool => $rows->total() === 21 && $rows->count() === 20)
        ->assertDontSee('history-secret-old')->assertDontSee('history-secret-new')->assertDontSee('stranger-private');
    $this->getJson(route('tools.game-password.history', ['status' => 'success']))->assertOk()->assertDontSee('history-secret-old');
    $this->actingAs($this->admin)->getJson(route('admin.game-password.history'))->assertOk()->assertJsonPath('meta.total', 22)->assertJsonCount(20, 'data')->assertJsonMissingPath('data.0.old_password');
});

test('database queue and orders are committed together without plaintext credentials in jobs', function (): void {
    Queue::swap($this->queueManager);
    $orders = app(GamePasswordOrderService::class);
    $order = $orders->create($this->user, [passwordEntry()], (string) Str::uuid(), 100, ['key' => 'user-api-key', 'secret' => 'user-api-secret'])->first();
    expect(DB::table('jobs')->where('queue', 'game-password')->count())->toBe(1);
    $payload = DB::table('jobs')->where('queue', 'game-password')->value('payload');
    expect($payload)->not->toContain('old-secret', 'new-secret', 'private-solver-key');
    $entries = array_map(fn ($i) => passwordEntry('queue-user-'.$i), range(1, 11));
    expect(fn () => $orders->create($this->user, $entries, (string) Str::uuid(), 100, ['key' => 'user-api-key', 'secret' => 'user-api-secret']))->toThrow(ValidationException::class);
    expect(DB::table('jobs')->where('queue', 'game-password')->count())->toBe(1)->and(GamePasswordOrder::count())->toBe(1);
});

test('queue insertion failures roll back the charge and the order', function (): void {
    $queue = Mockery::mock(Illuminate\Contracts\Queue\Queue::class);
    $queue->shouldReceive('push')->once()->andThrow(new RuntimeException('Queue unavailable'));
    $manager = Mockery::mock(QueueManager::class);
    $manager->shouldReceive('connection')->with('game_password')->andReturn($queue);
    Queue::swap($manager);
    expect(fn () => app(GamePasswordOrderService::class)->create($this->user, [passwordEntry()], (string) Str::uuid(), 100, ['key' => 'user-api-key', 'secret' => 'user-api-secret']))->toThrow(RuntimeException::class, 'Queue unavailable');
    expect(GamePasswordOrder::count())->toBe(0)->and($this->wallet->fresh()->balance)->toBe(1000)->and(WalletTransaction::count())->toBe(0);
});

test('bulk mode accepts one hundred accounts and rejects larger batches and malformed scalar fields', function (): void {
    app(GamePasswordConfigurationService::class)->configure(['price' => 0]);
    $text = implode("\n", array_map(fn ($index) => 'bulk-user-'.$index.'|old-secret|3|new-secret', range(1, 101)));
    $payload = ['captcha_api_key' => 'user-api-key', 'captcha_api_secret' => 'user-api-secret', 'bulk' => 1, 'accounts_text' => $text, 'price' => 0, 'request_id' => (string) Str::uuid()];
    $this->actingAs($this->user)->postJson(route('tools.game-password.store'), $payload)->assertUnprocessable();
    $this->postJson(route('tools.game-password.store'), [...passwordRequest(), 'price' => 0, 'username' => ['invalid']])->assertUnprocessable();
    $this->postJson(route('tools.game-password.store'), [...$payload, 'accounts_text' => implode("\n", array_slice(explode("\n", $text), 0, 100))])->assertRedirect();
    expect(GamePasswordOrder::count())->toBe(100)->and($this->wallet->fresh()->balance)->toBe(1000);
    Queue::assertPushed(ChangeGamePassword::class, 100);
});
