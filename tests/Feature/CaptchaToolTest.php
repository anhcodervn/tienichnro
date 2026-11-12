<?php

use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Features\Captcha\Services\CaptchaSolverService;
use App\Features\Captcha\Services\CaptchaWalletService;
use App\Features\Client\Wallet\Services\WalletService;
use App\Models\CaptchaApiKey;
use App\Models\CaptchaSolve;
use App\Models\CaptchaTransaction;
use App\Models\CaptchaWallet;
use App\Models\User;
use App\Support\SettingStore;
use Database\Seeders\CaptchaExchangeRateSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    Storage::fake('local');
    Http::preventStrayRequests();
    app(ToolAvailabilityService::class)->create([
        'code' => 'giai-captcha-nro', 'name' => 'Giải captcha NRO', 'url' => '/giai-captcha-nro',
        'description' => '', 'icon' => 'bx-scan', 'is_enabled' => true, 'maintenance_message' => 'Bảo trì.',
    ]);
});

test('captcha factories create valid records and seeding preserves configured rates', function (): void {
    CaptchaWallet::factory()->create();
    CaptchaApiKey::factory()->create();
    CaptchaSolve::factory()->create();
    CaptchaTransaction::factory()->create();
    $this->seed(CaptchaExchangeRateSeeder::class);
    expect(app(CaptchaWalletService::class)->quote(1000)['total'])->toBe(3000);
    app(SettingStore::class)->putArray('captcha.exchange_rates', [['min_tokens' => 1, 'unit_price' => 4]]);
    $this->seed(CaptchaExchangeRateSeeder::class);
    expect(app(CaptchaWalletService::class)->quote(1000)['total'])->toBe(4000);
});

function captchaTestImage(): string
{
    return base64_encode(file_get_contents(__DIR__.'/../Fixtures/captcha.png'));
}

function fundCaptchaUser(User $user, int $tokens = 10): void
{
    app(CaptchaWalletService::class)->wallet($user)->update(['balance' => $tokens]);
}

test('captcha tool can be added manually and its public page includes curl documentation', function (): void {
    $this->get('/giai-captcha-nro')->assertOk()->assertSee('Tài liệu cURL')->assertDontSee('request_id')->assertSee('Đăng nhập để demo');
    $this->get('/')->assertOk()->assertSee('Giải captcha NRO');
    expect(collect(config('tools.items'))->contains('code', 'giai-captcha-nro'))->toBeFalse();
    $this->postJson('/giai-captcha-nro/purchase', [])->assertUnauthorized();
    $this->postJson('/api/v1/giai-captcha-nro/solve', [])->assertUnauthorized();
});

test('captcha is hidden from home until an administrator adds its link using any tool code', function (): void {
    app(ToolAvailabilityService::class)->delete('giai-captcha-nro');
    $this->get('/')->assertOk()->assertDontSee('Giải captcha NRO');
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->postJson('/api/admin-api/settings/services', [
        'code' => 'custom_captcha', 'name' => 'Giải captcha NRO', 'url' => '/giai-captcha-nro',
        'description' => 'Giải ảnh captcha NRO', 'icon_type' => 'icon', 'icon' => 'bx-scan',
        'image_url' => null, 'sort_order' => 5, 'is_enabled' => true, 'maintenance_message' => 'Bảo trì.',
    ])->assertSuccessful();
    $this->get('/')->assertOk()->assertSee('Giải captcha NRO');
    fundCaptchaUser($admin);
    Http::fake(['*' => Http::response(['success' => true, 'result' => '93371'])]);
    $this->postJson('/giai-captcha-nro/solve', ['image' => captchaTestImage()])
        ->assertOk()->assertJsonPath('status', true);
});

test('captcha purchases apply tier price on server and retries do not debit twice', function (): void {
    $user = User::factory()->create();
    app(WalletService::class)->apply($user, 10000, 'credit', 'fund', 'Fund');
    $this->actingAs($user)->get('/giai-captcha-nro')->assertOk()->assertSee('Mua token');
    $id = (string) Str::uuid();
    $payload = ['tokens' => 1000, 'unit_price' => 3, 'request_id' => $id];
    $this->postJson('/giai-captcha-nro/purchase', $payload)->assertOk()->assertJsonPath('balance', 1000)->assertJsonPath('data.cost', 3000);
    $this->postJson('/giai-captcha-nro/purchase', $payload)->assertOk()->assertJsonPath('balance', 1000);
    expect(app(WalletService::class)->wallet($user)->balance)->toBe(7000);
    expect(app(CaptchaWalletService::class)->quote(999))->toBe(['tokens' => 999, 'unit_price' => 5, 'total' => 4995]);
    $this->postJson('/giai-captcha-nro/purchase', [...$payload, 'tokens' => 1001])->assertUnprocessable();
    $this->assertDatabaseCount('captcha_transactions', 1);
    $this->assertDatabaseCount('wallet_transactions', 2);
});

test('captcha purchase rejects forged prices insufficient funds and invalid quantities atomically', function (): void {
    $user = User::factory()->create();
    app(WalletService::class)->apply($user, 100, 'credit', 'fund', 'Fund');
    $this->actingAs($user);
    foreach ([
        ['tokens' => 999, 'unit_price' => 3],
        ['tokens' => 1000, 'unit_price' => 3],
        ['tokens' => 0, 'unit_price' => 5],
        ['tokens' => 1.5, 'unit_price' => 5],
        ['tokens' => 10000001, 'unit_price' => 3],
    ] as $payload) {
        $this->postJson('/giai-captcha-nro/purchase', [...$payload, 'request_id' => (string) Str::uuid()])->assertUnprocessable();
    }
    expect(app(WalletService::class)->wallet($user)->balance)->toBe(100);
    $this->assertDatabaseCount('captcha_transactions', 0);
});

test('new conversion configuration is applied and stale quote is rejected', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $rates = [['min_tokens' => 1, 'unit_price' => 6], ['min_tokens' => 1000, 'unit_price' => 2]];
    $this->actingAs($admin)->putJson('/api/admin-api/captcha/rates', ['rates' => $rates])->assertOk();
    $this->getJson('/api/admin-api/captcha/rates')->assertOk()->assertJsonPath('rates.1.unit_price', 2);
    $user = User::factory()->create();
    app(WalletService::class)->apply($user, 5000, 'credit', 'fund', 'Fund');
    $payload = ['tokens' => 1000, 'unit_price' => 3, 'request_id' => (string) Str::uuid()];
    $this->actingAs($user)->postJson('/giai-captcha-nro/purchase', $payload)->assertUnprocessable();
    $this->postJson('/giai-captcha-nro/purchase', [...$payload, 'unit_price' => 2])->assertOk()->assertJsonPath('data.cost', 2000);
});

test('captcha api key is separate hashed rotatable and revocable', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $first = $this->postJson('/giai-captcha-nro/api-token')->assertOk()->json('token');
    expect(CaptchaApiKey::query()->first()->token_hash)->toBe(hash('sha256', $first));
    $second = $this->postJson('/giai-captcha-nro/api-token')->assertOk()->json('token');
    expect($second)->not->toBe($first);
    auth()->forgetGuards();
    $this->postJson('/api/v1/giai-captcha-nro/solve', [], ['Authorization' => 'Bearer '.$first])->assertUnauthorized();
    $this->postJson('/api/v1/giai-captcha-nro/solve', [], ['Authorization' => 'Bearer '.$second])->assertUnprocessable();
    $this->getJson('/api/user', ['Authorization' => 'Bearer '.$second])->assertUnauthorized();
    $this->actingAs($user)->deleteJson('/giai-captcha-nro/api-token')->assertOk();
    auth()->forgetGuards();
    $this->postJson('/api/v1/giai-captcha-nro/solve', [], ['Authorization' => 'Bearer '.$second])->assertUnauthorized();
});

test('successful solves save private webp and full provider response and charge once', function (): void {
    $user = User::factory()->create();
    fundCaptchaUser($user);
    $response = ['success' => true, 'result' => '93371', 'needs_review' => true, 'verified' => false, 'confidence' => 0.0213];
    Http::fake(['nro.giaicaptcha.vn/solve' => Http::response($response)]);
    $this->actingAs($user);
    $payload = ['image' => captchaTestImage()];
    $result = $this->postJson('/giai-captcha-nro/solve', $payload)->assertOk()
        ->assertJsonPath('status', true)->assertJsonPath('code', '93371')->json();
    expect(array_keys($result))->toBe(['status', 'code', 'time'])
        ->and($result['time'])->toBeNumeric()->toBeGreaterThanOrEqual(0);
    $stored = CaptchaSolve::query()->firstOrFail();
    expect(app(CaptchaSolverService::class)->solve($user, $payload['image'], $stored->request_id, 'demo')->id)->toBe($stored->id);
    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => $request['image'] === captchaTestImage());
    $solve = CaptchaSolve::query()->firstOrFail();
    expect($solve->source)->toBe('demo')->and($solve->response)->toBe($response)
        ->and($solve->duration_ms)->toBe((int) round($result['time'] * 1000));
    Storage::disk('local')->assertExists($solve->image_path);
    expect(getimagesizefromstring(Storage::disk('local')->get($solve->image_path))['mime'])->toBe('image/webp');
    expect(CaptchaWallet::query()->first()->reserved)->toBe(0)
        ->and(CaptchaWallet::query()->first()->balance)->toBe(9);
    $this->assertDatabaseCount('captcha_transactions', 1);
    $this->assertDatabaseCount('wallet_transactions', 0);
});

test('failed captcha provider responses release reservation and never charge', function (string $failure): void {
    $user = User::factory()->create();
    fundCaptchaUser($user);
    Http::fake(['*' => match ($failure) {
        'connection' => Http::failedConnection(),
        'http' => Http::response(['success' => true, 'result' => '12345'], 500),
        'empty' => Http::response(['success' => true, 'result' => '']),
        'false' => Http::response(['success' => false, 'result' => '12345']),
        default => Http::response('not json', 200),
    }]);
    $this->actingAs($user)->postJson('/giai-captcha-nro/solve', [
        'image' => captchaTestImage(),
    ])->assertStatus(502)->assertJsonPath('status', false)->assertJsonPath('code', null)
        ->assertJsonStructure(['status', 'code', 'time'])->assertJsonCount(3);
    expect(CaptchaWallet::query()->first()->reserved)->toBe(0)
        ->and(CaptchaWallet::query()->first()->balance)->toBe(10);
    expect(CaptchaSolve::query()->first()->status)->toBe('failed');
    $this->assertDatabaseCount('captcha_transactions', 0);
})->with(['connection', 'http', 'empty', 'false', 'malformed']);

test('captcha rejects bad base64 images dimensions and insufficient tokens before provider call', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    foreach (['!!!', base64_encode('<svg/>'), base64_encode(str_repeat('x', 2100000))] as $image) {
        $this->postJson('/giai-captcha-nro/solve', ['image' => $image])->assertUnprocessable();
    }
    $this->postJson('/giai-captcha-nro/solve', ['image' => captchaTestImage()])->assertUnprocessable();
    Http::assertNothingSent();
    $this->assertDatabaseCount('captcha_solves', 0);
});

test('base64 data uri is supported and captcha api authenticates its owner', function (): void {
    $user = User::factory()->create();
    fundCaptchaUser($user);
    $token = $this->actingAs($user)->postJson('/giai-captcha-nro/api-token')->json('token');
    auth()->forgetGuards();
    Http::fake(['*' => Http::response(['success' => true, 'result' => '93371'])]);
    $this->postJson('/api/v1/giai-captcha-nro/solve', [
        'image' => 'data:image/png;base64,'.captchaTestImage(),
    ], ['Authorization' => 'Bearer '.$token])->assertOk()->assertJsonPath('status', true)
        ->assertJsonPath('code', '93371')->assertJsonStructure(['status', 'code', 'time'])->assertJsonCount(3);
    expect(CaptchaWallet::query()->first()->balance)->toBe(9);
    expect(CaptchaSolve::query()->first()->source)->toBe('api');
});

test('processing request reserves token against concurrent solve or admin debit and expires safely', function (): void {
    $user = User::factory()->create();
    fundCaptchaUser($user, 1);
    $wallet = CaptchaWallet::query()->first();
    $wallet->update(['reserved' => 1]);
    $id = (string) Str::uuid();
    CaptchaSolve::factory()->create([
        'user_id' => $user->id, 'request_id' => $id, 'image_hash' => hash('sha256', base64_decode(captchaTestImage())),
        'image_path' => 'captcha/test.webp', 'source' => 'api',
    ]);
    expect(app(CaptchaSolverService::class)->solve($user, captchaTestImage(), $id, 'api')->status)->toBe('processing');
    $this->actingAs($user)->postJson('/giai-captcha-nro/solve', ['image' => captchaTestImage()])->assertUnprocessable();
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->postJson('/api/admin-api/captcha/wallets/'.$user->id.'/adjust', [
        'amount' => 1, 'direction' => 'debit', 'request_id' => (string) Str::uuid(), 'description' => 'Test',
    ])->assertUnprocessable();
    $this->travel(6)->minutes();
    app(CaptchaSolverService::class)->expire($user);
    expect($wallet->fresh()->reserved)->toBe(0)->and($wallet->fresh()->balance)->toBe(1);
    expect(CaptchaSolve::query()->first()->response['error'])->toBe('processing_expired');
    Http::assertNothingSent();
});

test('same solve request cannot be reused with a different image', function (): void {
    $user = User::factory()->create();
    fundCaptchaUser($user);
    $id = (string) Str::uuid();
    CaptchaSolve::factory()->create([
        'user_id' => $user->id, 'request_id' => $id, 'image_hash' => str_repeat('0', 64),
        'image_path' => 'captcha/test.webp', 'source' => 'api', 'status' => 'failed',
    ]);
    expect(fn () => app(CaptchaSolverService::class)->solve($user, captchaTestImage(), $id, 'api'))
        ->toThrow(ValidationException::class);
    Http::assertNothingSent();
});

test('each image only API request creates a new solve even when a client supplies the same request id', function (): void {
    $user = User::factory()->create();
    fundCaptchaUser($user);
    $token = $this->actingAs($user)->postJson('/giai-captcha-nro/api-token')->json('token');
    auth()->forgetGuards();
    Http::fake(['*' => Http::response(['success' => true, 'result' => '93371'])]);
    $headers = ['Authorization' => 'Bearer '.$token];
    $this->postJson('/api/v1/giai-captcha-nro/solve', ['image' => captchaTestImage()], $headers)
        ->assertOk()->assertJsonPath('status', true);
    foreach (range(1, 2) as $attempt) {
        $this->postJson('/api/v1/giai-captcha-nro/solve', [
            'image' => captchaTestImage(), 'request_id' => 'ignored-client-value',
        ], $headers)->assertOk()->assertJsonPath('code', '93371');
    }
    expect(CaptchaSolve::query()->pluck('request_id')->unique())->toHaveCount(3);
    expect(CaptchaWallet::query()->first()->balance)->toBe(7);
    $this->assertDatabaseCount('captcha_transactions', 3);
    Http::assertSentCount(3);
});

test('captcha response converts saved duration to seconds without provider details', function (): void {
    $user = User::factory()->create();
    $requestId = (string) Str::uuid();
    $solve = CaptchaSolve::factory()->create([
        'user_id' => $user->id, 'request_id' => $requestId,
        'image_hash' => hash('sha256', base64_decode(captchaTestImage())),
        'status' => 'success', 'result' => '93371', 'duration_ms' => 842,
        'response' => ['success' => true, 'result' => '93371', 'needs_review' => true],
    ]);
    $this->mock(CaptchaSolverService::class)->shouldReceive('solve')->once()->andReturn($solve);
    $this->actingAs($user)->postJson('/giai-captcha-nro/solve', [
        'image' => captchaTestImage(),
    ])->assertOk()->assertExactJson(['status' => true, 'code' => '93371', 'time' => 0.842]);
    $this->getJson('/giai-captcha-nro/history')->assertOk()->assertJsonMissingPath('solves.data.0.response');
    Http::assertNothingSent();
    $this->assertDatabaseCount('captcha_transactions', 0);
});

test('captcha admin routes protect wallets history images and conversion settings', function (): void {
    $user = User::factory()->create();
    fundCaptchaUser($user);
    $admin = User::factory()->create(['role' => 'admin']);
    $solve = CaptchaSolve::factory()->create([
        'user_id' => $user->id, 'request_id' => (string) Str::uuid(), 'image_hash' => str_repeat('0', 64),
        'image_path' => 'captcha/test.webp', 'source' => 'demo', 'status' => 'failed', 'response' => ['error' => 'test'],
    ]);
    Storage::disk('local')->put($solve->image_path, 'private-image');
    foreach (['wallets', 'transactions', 'rates', 'solves', 'solves/'.$solve->id, 'solves/'.$solve->id.'/image'] as $path) {
        $this->actingAs($user)->getJson('/api/admin-api/captcha/'.$path)->assertForbidden();
        $this->actingAs($admin)->getJson('/api/admin-api/captcha/'.$path)->assertOk();
    }
    $this->actingAs($user)->putJson('/api/admin-api/captcha/rates', ['rates' => []])->assertForbidden();
    $this->actingAs($admin)->putJson('/api/admin-api/captcha/rates', [
        'rates' => [['min_tokens' => 2, 'unit_price' => 0]],
    ])->assertUnprocessable();
    $this->putJson('/api/admin-api/captcha/rates', [
        'rates' => [['min_tokens' => 1, 'unit_price' => 5], ['min_tokens' => 1, 'unit_price' => 3]],
    ])->assertUnprocessable();
});

test('captcha wallet adjustments are audited idempotent and enforce available balance', function (): void {
    $user = User::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);
    $url = '/api/admin-api/captcha/wallets/'.$user->id.'/adjust';
    $payload = ['amount' => 100, 'direction' => 'credit', 'request_id' => (string) Str::uuid(), 'description' => 'Manual credit'];
    $this->postJson($url, $payload)->assertOk()->assertJsonPath('data.balance_after', 100);
    $this->postJson($url, $payload)->assertOk()->assertJsonPath('data.balance_after', 100);
    $this->postJson($url, [...$payload, 'description' => 'Changed'])->assertUnprocessable();
    $this->postJson($url, [...$payload, 'request_id' => (string) Str::uuid(), 'direction' => 'debit', 'amount' => 101])->assertUnprocessable();
    expect(CaptchaTransaction::query()->first()->actor_id)->toBe($admin->id);
    $this->assertDatabaseCount('captcha_transactions', 1);
});

test('captcha history is private and paginated', function (): void {
    $mine = User::factory()->create();
    $other = User::factory()->create();
    foreach ([$mine, $other] as $user) {
        CaptchaSolve::factory()->create([
            'user_id' => $user->id, 'request_id' => (string) Str::uuid(), 'image_hash' => str_repeat('0', 64),
            'image_path' => 'captcha/test.webp', 'source' => 'demo', 'status' => 'success', 'result' => 'result-'.$user->id,
        ]);
    }
    $this->actingAs($mine)->getJson('/giai-captcha-nro/history')->assertOk()->assertJsonCount(1, 'solves.data')
        ->assertJsonPath('solves.data.0.result', 'result-'.$mine->id)->assertJsonMissing(['result' => 'result-'.$other->id]);
});

test('captcha history renders separate solve and wallet tabs with wallet initially hidden', function (): void {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->get('/giai-captcha-nro')->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@role="tablist"]/*[@role="tab"]')->length)->toBe(2)
        ->and($xpath->query('//*[@id="captcha-solves-tab"]')->item(0)->getAttribute('aria-selected'))->toBe('true')
        ->and($xpath->query('//*[@id="captcha-transactions-tab"]')->item(0)->getAttribute('aria-selected'))->toBe('false')
        ->and($xpath->query('//*[@id="captcha-solves-panel"]')->item(0)->hasAttribute('hidden'))->toBeFalse()
        ->and($xpath->query('//*[@id="captcha-transactions-panel"]')->item(0)->hasAttribute('hidden'))->toBeTrue();
});

test('disabled captcha tool and inactive users cannot spend tokens', function (): void {
    $user = User::factory()->create();
    fundCaptchaUser($user);
    app(SettingStore::class)->putMany(['tool_giai-captcha-nro_enabled' => false]);
    $this->actingAs($user)->postJson('/giai-captcha-nro/solve', ['image' => captchaTestImage()])->assertStatus(503);
    $user->update(['status' => 'inactive']);
    $this->postJson('/giai-captcha-nro/api-token')->assertForbidden();
    $this->postJson('/giai-captcha-nro/purchase', ['tokens' => 1, 'unit_price' => 5, 'request_id' => (string) Str::uuid()])->assertForbidden();
    Http::assertNothingSent();
});

test('disabled captcha returns only the public error fields even when debug is enabled', function (bool $debug): void {
    config()->set('app.debug', $debug);
    $user = User::factory()->create();
    fundCaptchaUser($user);
    $token = $this->actingAs($user)->postJson('/giai-captcha-nro/api-token')->json('token');
    app(SettingStore::class)->putMany(['tool_giai-captcha-nro_enabled' => false]);
    foreach (['/giai-captcha-nro/solve', '/api/v1/giai-captcha-nro/solve'] as $path) {
        $this->postJson($path, ['image' => captchaTestImage()], ['Authorization' => 'Bearer '.$token])
            ->assertStatus(503)->assertExactJson(['status' => false, 'code' => null, 'time' => 0])
            ->assertHeader('Cache-Control', 'no-store, private');
    }
    expect(app(CaptchaWalletService::class)->wallet($user)->balance)->toBe(10);
    Http::assertNothingSent();
    $this->assertDatabaseCount('captcha_transactions', 0);
})->with([true, false]);

test('captcha authentication and validation errors never expose debug details', function (): void {
    config()->set('app.debug', true);
    $error = ['status' => false, 'code' => null, 'time' => 0];
    $this->postJson('/api/v1/giai-captcha-nro/solve', [])->assertUnauthorized()->assertExactJson($error);
    $user = User::factory()->create();
    $token = $this->actingAs($user)->postJson('/giai-captcha-nro/api-token')->json('token');
    $this->postJson('/api/v1/giai-captcha-nro/solve', [], ['Authorization' => 'Bearer '.$token])
        ->assertUnprocessable()->assertExactJson($error);
    Http::assertNothingSent();
});

test('unexpected captcha errors and throttling preserve HTTP status without internal messages', function (int $httpStatus): void {
    config()->set('app.debug', true);
    $user = User::factory()->create();
    $exception = $httpStatus === 500
        ? new RuntimeException('Internal database path and secret must not be exposed')
        : new HttpException($httpStatus, 'Internal exception message');
    $this->mock(CaptchaSolverService::class)->shouldReceive('solve')->once()->andThrow($exception);
    $this->actingAs($user)->postJson('/giai-captcha-nro/solve', ['image' => captchaTestImage()])
        ->assertStatus($httpStatus)->assertExactJson(['status' => false, 'code' => null, 'time' => 0]);
})->with([500, 429]);
