<?php

use App\Features\Client\GamePassword\Services\GamePasswordConfigurationService;
use App\Models\GamePasswordOrder;
use App\Models\ManagedServer;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function passwordCaptchaProfileResponse(string $name = 'demo_user', string $balance = '1500'): array
{
    return ['status' => true, 'data' => ['user' => ['id' => 12, 'username' => $name, 'status' => 'active', 'email' => 'private@example.test'],
        'wallet' => ['balance' => $balance, 'hold_balance' => '0'], 'api_key' => ['secret' => 'upstream-secret']]];
}

beforeEach(function (): void {
    Http::preventStrayRequests();
    Queue::fake();
    $this->owner = User::factory()->create();
    $this->url = route('tools.game-password.captcha.save');
    $this->keys = ['captcha_api_key' => 'ak_owner', 'captcha_api_secret' => 'sk_owner'];
    $this->remoteResponse = Http::response(passwordCaptchaProfileResponse());
    Http::fake(['giaicaptcha.vn/api/v1/user' => fn () => is_callable($this->remoteResponse) ? ($this->remoteResponse)() : $this->remoteResponse]);
});

test('saving verifies personal keys stores encrypted fields and returns only a safe profile', function (): void {
    $this->actingAs($this->owner)->postJson($this->url, $this->keys)->assertOk()
        ->assertJsonPath('data.saved', true)->assertJsonPath('data.username', 'demo_user')->assertJsonPath('data.balance', '1500')
        ->assertDontSee('ak_owner')->assertDontSee('sk_owner')->assertDontSee('private@example.test')->assertDontSee('upstream-secret');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET' && $request->url() === 'https://giaicaptcha.vn/api/v1/user'
        && $request->header('X-API-KEY') === ['ak_owner'] && $request->header('X-API-SECRET') === ['sk_owner']);
    $owner = $this->owner->fresh();
    expect($owner->game_password_captcha_key)->toBe('ak_owner')->and($owner->game_password_captcha_secret)->toBe('sk_owner');
    $raw = DB::table('users')->where('id', $owner->id)->first();
    expect($raw->game_password_captcha_key)->not->toContain('ak_owner')->and($raw->game_password_captcha_secret)->not->toContain('sk_owner');
    expect(json_encode($owner))->not->toContain('ak_owner', 'sk_owner', 'game_password_captcha_profile');
    $this->actingAs($owner)->get(route('tools.game-password'))->assertOk()->assertSee('demo_user')->assertSee('1.500 đ')->assertSee('placeholder="***"', false)
        ->assertDontSee('ak_owner')->assertDontSee('sk_owner');
    Http::assertSentCount(1);
});

test('saved credentials are owner scoped and new orders snapshot them without requesting them again', function (): void {
    app(GamePasswordConfigurationService::class)->configure(['price' => 100]);
    Wallet::factory()->create(['user_id' => $this->owner->id, 'balance' => 1000]);
    ManagedServer::query()->firstOrCreate(['type' => 'website', 'server_id' => '3'], ['name' => 'Server 3', 'is_active' => true, 'sort_order' => 3])->update(['is_active' => true]);
    $other = User::factory()->create();
    $this->actingAs($this->owner)->postJson($this->url, [...$this->keys, 'user_id' => $other->id])->assertOk();
    $this->actingAs($this->owner->fresh());
    expect($other->fresh()->game_password_captcha_key)->toBeNull();
    $payload = ['username' => 'game-account', 'old_password' => 'old-secret', 'new_password' => 'new-secret', 'server_code' => 3, 'price' => 100, 'request_id' => (string) Str::uuid()];
    $this->postJson(route('tools.game-password.store'), $payload)->assertRedirect();
    $order = GamePasswordOrder::query()->sole();
    expect($order->captcha_api_key)->toBe('ak_owner')->and($order->captcha_api_secret)->toBe('sk_owner');
    $this->postJson($this->url, ['captcha_api_key' => 'ak_new', 'captcha_api_secret' => 'sk_new'])->assertOk();
    expect($order->fresh()->captcha_api_secret)->toBe('sk_owner');
    $this->actingAs($other)->postJson(route('tools.game-password.captcha.refresh'))->assertUnprocessable();
    $this->postJson(route('tools.game-password.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors(['captcha_api_key', 'captcha_api_secret']);
});

test('invalid replacement keys preserve the saved pair and profile without leaking provider responses', function (bool $networkFailure): void {
    $this->actingAs($this->owner)->postJson($this->url, $this->keys)->assertOk();
    $this->remoteResponse = function () use ($networkFailure) {
        if ($networkFailure) {
            throw new ConnectionException('private upstream sk_new');
        }

        return Http::response(['status' => false, 'message' => 'private upstream sk_new'], 401);
    };
    $this->postJson($this->url, ['captcha_api_key' => 'ak_new', 'captcha_api_secret' => 'sk_new'])->assertUnprocessable()->assertDontSee('private upstream')->assertDontSee('sk_new');
    expect($this->owner->fresh()->game_password_captcha_secret)->toBe('sk_owner')->and($this->owner->fresh()->game_password_captcha_profile['balance'])->toBe('1500');
})->with([true, false]);

test('refresh updates only the current users balance and rejects stale credentials', function (): void {
    $this->actingAs($this->owner)->postJson($this->url, $this->keys)->assertOk();
    $this->actingAs($this->owner->fresh());
    $this->remoteResponse = Http::response(passwordCaptchaProfileResponse('demo_user', '0'));
    $this->postJson(route('tools.game-password.captcha.refresh'))->assertOk()->assertJsonPath('data.balance', '0')->assertDontSee('sk_owner');
    $viewer = $this->owner->fresh();
    $this->remoteResponse = function () use ($viewer) {
        $viewer->forceFill(['game_password_captcha_key' => 'ak_changed', 'game_password_captcha_secret' => 'sk_changed',
            'game_password_captcha_profile' => ['username' => 'different-account', 'balance' => '500', 'checked_at' => now()->toISOString()]])->save();

        return Http::response(passwordCaptchaProfileResponse('old-account', '999'));
    };
    $this->postJson(route('tools.game-password.captcha.refresh'))->assertConflict();
    expect($this->owner->fresh()->game_password_captcha_profile['username'])->toBe('different-account');
});

test('profile requests require login active accounts and both valid keys', function (): void {
    $this->postJson($this->url, $this->keys)->assertUnauthorized();
    $this->actingAs($this->owner)->postJson($this->url, ['captcha_api_key' => 'ak_only'])->assertUnprocessable();
    $this->postJson($this->url, [...$this->keys, 'captcha_api_secret' => "bad\r\nheader"])->assertUnprocessable();
    $this->owner->update(['status' => 'banned']);
    $this->postJson($this->url, $this->keys)->assertForbidden();
    Http::assertNothingSent();
});

test('malformed profile data cannot save keys or leak untrusted response content', function (array $body): void {
    $this->remoteResponse = Http::response($body);
    $this->actingAs($this->owner)->postJson($this->url, $this->keys)->assertUnprocessable();
    expect($this->owner->fresh()->game_password_captcha_key)->toBeNull();
})->with([
    'missing data' => [['status' => true]],
    'inactive account' => [['status' => true, 'data' => ['user' => ['username' => 'demo', 'status' => 'banned'], 'wallet' => ['balance' => '10']]]],
    'invalid balance' => [['status' => true, 'data' => ['user' => ['username' => 'demo', 'status' => 'active'], 'wallet' => ['balance' => true]]]],
]);
