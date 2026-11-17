<?php

use App\Features\Client\GamePassword\Exceptions\GamePasswordFailure;
use App\Features\Client\GamePassword\Services\GamePasswordCaptchaService;
use App\Features\Client\GamePassword\Services\GamePasswordConfigurationService;
use App\Features\Client\GamePassword\Services\GamePasswordGatewayService;
use App\Features\Client\GamePassword\Services\GamePasswordOrderService;
use App\Jobs\ChangeGamePassword;
use App\Models\GamePasswordOrder;
use App\Models\User;
use App\Models\Wallet;
use App\Support\EventStreamRuntime;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->app->instance(EventStreamRuntime::class, new EventStreamRuntime('cli-server'));
    Queue::fake();
    Sleep::fake();
    Http::preventStrayRequests();
    app(GamePasswordConfigurationService::class)->configure(['price' => 100, 'captcha_api_key' => 'admin-secret']);
    $this->owner = User::factory()->create();
    $this->wallet = Wallet::factory()->create(['user_id' => $this->owner->id, 'balance' => 1000]);
    $this->credentials = ['key' => 'ak_user', 'secret' => 'sk_user'];
    $this->entries = [['username' => 'captcha-user', 'old_password' => 'old-secret', 'new_password' => 'new-secret', 'server_code' => 3]];
    $this->orders = app(GamePasswordOrderService::class);
});

test('user captcha API creates once and polls with the users headers and exact task payload', function (): void {
    Http::fake([
        'giaicaptcha.vn/api/v1/create' => Http::response(['status' => true, 'data' => ['task_code' => 'ct_task']]),
        'giaicaptcha.vn/api/v1/result' => Http::sequence()->push(['status' => true, 'data' => ['task_code' => 'ct_task']])->push(['status' => true, 'data' => ['captcha' => 'solved-token']]),
    ]);
    expect(app(GamePasswordCaptchaService::class)->solveWithUserCredentials('site-key', 'https://forum.ngocrongonline.com/app/dang-nhap', microtime(true) + 60, $this->credentials))->toBe('solved-token');
    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/create') && $request->method() === 'POST'
        && $request->header('X-API-KEY') === ['ak_user'] && $request->header('X-API-SECRET') === ['sk_user']
        && $request->data() === ['service_code' => 'cloudflare-turnstile', 'task' => ['website_url' => 'https://forum.ngocrongonline.com/app/dang-nhap', 'website_key' => 'site-key']]);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/result') && $request->data() === ['task_code' => 'ct_task']);
});

test('missing user keys never fall back to the configured website solver', function (): void {
    expect(fn () => app(GamePasswordGatewayService::class)->change('user', 'old', 'new', 3, fn () => null))->toThrow(GamePasswordFailure::class, 'USER_SOLVER_NOT_CONFIGURED');
    expect(fn () => $this->orders->create($this->owner, $this->entries, (string) Str::uuid(), 100))->toThrow(ValidationException::class);
    Http::assertNothingSent();
    expect($this->wallet->fresh()->balance)->toBe(1000)->and(GamePasswordOrder::count())->toBe(0);
});

test('personal keys accept new orders without a website solver key configured', function (): void {
    app(GamePasswordConfigurationService::class)->configure(['price' => 100, 'clear_api_key' => true]);
    $order = $this->orders->create($this->owner, $this->entries, (string) Str::uuid(), 100, $this->credentials)->first();
    expect($order->captcha_api_key)->toBe('ak_user')->and($this->wallet->fresh()->balance)->toBe(900);
    $this->actingAs($this->owner)->get(route('tools.game-password'))->assertOk()
        ->assertSee('name="captcha_api_key"', false)->assertSee('name="captcha_api_secret"', false)
        ->assertSee('placeholder="ak_xxx"', false)->assertSee('placeholder="sk_xxx"', false)->assertSee('giaicaptcha.vn');
});

test('API failures malformed responses and deadlines stop without fallback or leaked provider text', function (array $body, int $status, string $reason): void {
    Http::fake(['giaicaptcha.vn/*' => Http::response($body, $status)]);
    try {
        app(GamePasswordCaptchaService::class)->solveWithUserCredentials('site', 'https://example.test', microtime(true) + 60, $this->credentials);
        test()->fail('Expected solver failure');
    } catch (GamePasswordFailure $failure) {
        expect($failure->reason)->toBe($reason)->and($failure->getMessage())->not->toContain('private-upstream');
    }
    Http::assertSentCount(1);
})->with([
    'invalid keys' => [['message' => 'private-upstream'], 401, 'USER_SOLVER_AUTH_FAILED'],
    'service rejected' => [['status' => false, 'message' => 'private-upstream'], 200, 'SOLVER_FAILED'],
    'missing task' => [['status' => true], 200, 'SOLVER_FAILED'],
    'HTTP failure' => [['status' => true], 500, 'SOLVER_FAILED'],
]);

test('orders encrypt and hide personal solver credentials and retries can replace them without another charge', function (): void {
    $order = $this->orders->create($this->owner, $this->entries, (string) Str::uuid(), 100, $this->credentials)->first();
    expect($order->captcha_api_key)->toBe('ak_user')->and($order->captcha_api_secret)->toBe('sk_user');
    $stored = DB::table('game_password_orders')->where('id', $order->id)->first();
    expect($stored->captcha_api_key)->not->toContain('ak_user')->and($stored->captcha_api_secret)->not->toContain('sk_user');
    expect(json_encode($order))->not->toContain('ak_user', 'sk_user', 'captcha_api_secret');
    Queue::assertPushed(ChangeGamePassword::class, fn ($job): bool => ! str_contains(serialize($job), 'ak_user') && ! str_contains(serialize($job), 'sk_user'));
    $this->orders->finish($order->id, 'error', 'USER_SOLVER_AUTH_FAILED');
    $this->actingAs($this->owner)->postJson(route('tools.game-password.retry', $order), ['attempt_version' => 1, 'captcha_api_key' => 'ak_replaced', 'captcha_api_secret' => 'sk_replaced'])->assertOk()->assertDontSee('sk_replaced');
    expect($order->fresh()->captcha_api_secret)->toBe('sk_replaced')->and($this->wallet->fresh()->balance)->toBe(900);
    $gateway = $this->mock(GamePasswordGatewayService::class);
    $gateway->shouldReceive('change')->once()->withArgs(fn ($username, $old, $new, $server, $progress, $verify, $keys): bool => $keys === ['key' => 'ak_replaced', 'secret' => 'sk_replaced'] && $verify && $old === 'old-secret' && $new === 'new-secret');
    (new ChangeGamePassword($order->id, 2))->handle($this->orders, $gateway);
    expect($order->fresh()->status)->toBe('success')->and($order->fresh()->captcha_api_key)->toBeNull()->and($order->fresh()->captcha_api_secret)->toBeNull();
});

test('validation never flashes personal captcha keys back into the session', function (): void {
    $this->actingAs($this->owner)->from(route('tools.game-password'))->post(route('tools.game-password.store'), [
        'request_id' => (string) Str::uuid(), 'price' => 100, 'captcha_api_key' => 'ak_private', 'captcha_api_secret' => 'sk_private',
    ])->assertRedirect()->assertSessionHasErrors();
    expect(json_encode(session()->getOldInput()))->not->toContain('ak_private', 'sk_private');
});

test('polling rejects auth failures and never ready tasks expire within a bounded number of requests', function (bool $authFailure): void {
    Http::fake([
        'giaicaptcha.vn/api/v1/create' => Http::response(['status' => true, 'data' => ['task_code' => 'ct_task']]),
        'giaicaptcha.vn/api/v1/result' => Http::response($authFailure ? ['message' => 'private-provider'] : ['status' => true, 'data' => []], $authFailure ? 403 : 200),
    ]);
    expect(fn () => app(GamePasswordCaptchaService::class)->solveWithUserCredentials('site', 'https://example.test', microtime(true) + 300, $this->credentials))
        ->toThrow(GamePasswordFailure::class, $authFailure ? 'USER_SOLVER_AUTH_FAILED' : 'REMOTE_TIMEOUT');
    Http::assertSentCount($authFailure ? 2 : 46);
})->with([true, false]);

test('history and SSE exclude personal keys and only the owner can replace them on retry', function (): void {
    $order = $this->orders->create($this->owner, $this->entries, (string) Str::uuid(), 100, $this->credentials)->first();
    $this->orders->finish($order->id, 'error', 'USER_SOLVER_AUTH_FAILED');
    $url = route('tools.game-password.retry', $order);
    $replacement = ['attempt_version' => 1, 'captcha_api_key' => 'ak_replaced', 'captcha_api_secret' => 'sk_replaced'];
    $this->actingAs(User::factory()->create())->postJson($url, $replacement)->assertForbidden();
    $this->actingAs($this->owner)->postJson($url, ['attempt_version' => 1, 'captcha_api_key' => 'ak_only'])->assertUnprocessable();
    $this->getJson(route('tools.game-password.history'))->assertOk()->assertDontSee('sk_user')->assertDontSee('ak_user');
    $response = $this->get(route('tools.game-password.stream'));
    expect($response->streamedContent())->not->toContain('sk_user', 'ak_user', 'admin-secret');
    expect($order->fresh()->captcha_api_secret)->toBe('sk_user');
});
