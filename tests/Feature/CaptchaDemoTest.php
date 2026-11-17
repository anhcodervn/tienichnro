<?php

use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Models\CaptchaWallet;
use App\Models\User;
use App\Support\SettingStore;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Storage::fake('local');
    Http::preventStrayRequests();
    app(ToolAvailabilityService::class)->create([
        'code' => 'giai-captcha-nro', 'name' => 'Giải captcha NRO', 'url' => '/giai-captcha-nro',
        'description' => '', 'icon' => 'bx-scan', 'is_enabled' => true, 'maintenance_message' => '',
    ]);
    $settings = app(SettingStore::class);
    $settings->putMany(['turnstile_enabled' => true, 'turnstile_site_key' => 'demo-site-key']);
    $settings->putEncryptedString('turnstile_secret_key', 'demo-secret-key');
    Http::fake([
        'challenges.cloudflare.com/*' => fn () => config('captcha_demo_test.connection') ? Http::failedConnection()
            : Http::response(config('captcha_demo_test.verification', ['success' => true, 'action' => 'captcha_demo', 'hostname' => 'localhost'])),
        'nro.giaicaptcha.vn/solve' => fn () => Http::response(config('captcha_demo_test.provider', ['success' => true, 'result' => '93371'])),
    ]);
});

function captchaDemoPayload(): array
{
    return ['image' => base64_encode(file_get_contents(__DIR__.'/../Fixtures/captcha.png')), 'cf-turnstile-response' => (string) Str::uuid()];
}

test('guests see a free demo card upload dropzone and Cloudflare widget', function (): void {
    $response = $this->get('/giai-captcha-nro')->assertOk()->assertSee('Thử giải captcha')
        ->assertSee('Giải captcha miễn phí')->assertSee('demo-site-key')->assertDontSee('demo-secret-key')
        ->assertSee('data-action="captcha_demo"', false)->assertSee('border-dotted', false)
        ->assertSee('challenges.cloudflare.com/turnstile/v0/api.js', false);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//form[@data-captcha-solve]//input[@name="image"]')->length)->toBe(1)
        ->and($xpath->query('//section[@aria-labelledby="captcha-demo-title"]/header')->length)->toBe(1);
});

test('a guest can solve free after server side Cloudflare verification without a wallet', function (): void {
    $this->postJson('/giai-captcha-nro/try', captchaDemoPayload())->assertOk()
        ->assertJsonPath('status', true)->assertJsonPath('code', '93371')->assertJsonStructure(['time']);
    $this->assertDatabaseCount('captcha_wallets', 0);
    $this->assertDatabaseCount('captcha_transactions', 0);
    $this->assertDatabaseCount('captcha_solves', 0);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'siteverify') && $request['secret'] === 'demo-secret-key');
});

test('an authenticated user with zero tokens can try free and retain history without a debit', function (): void {
    $user = User::factory()->create();
    $wallet = CaptchaWallet::factory()->create(['user_id' => $user->id, 'balance' => 0, 'reserved' => 0]);
    $this->actingAs($user)->postJson('/giai-captcha-nro/try', captchaDemoPayload())->assertOk();
    expect($wallet->fresh()->balance)->toBe(0)->and($wallet->fresh()->reserved)->toBe(0);
    $this->assertDatabaseCount('captcha_transactions', 0);
    $this->assertDatabaseHas('captcha_solves', ['user_id' => $user->id, 'source' => 'demo', 'result' => '93371', 'status' => 'success']);
});

test('free demo rejects invalid images and missing verification without calling providers', function (array $overrides): void {
    $this->postJson('/giai-captcha-nro/try', [...captchaDemoPayload(), ...$overrides])->assertUnprocessable();
    Http::assertNothingSent();
})->with([
    [['cf-turnstile-response' => '']], [['cf-turnstile-response' => ['invalid']]], [['image' => 'bad-base64']], [['image' => '']],
]);

test('free demo rejects invalid expired cross action and cross host Cloudflare tokens', function (array $verification): void {
    config()->set('captcha_demo_test.verification', $verification);
    $this->postJson('/giai-captcha-nro/try', captchaDemoPayload())->assertUnprocessable()->assertJsonPath('status', false);
    Http::assertSentCount(1);
    $this->assertDatabaseCount('captcha_solves', 0);
})->with([
    [['success' => false]],
    [['success' => true, 'action' => 'nro_realtime', 'hostname' => 'localhost']],
    [['success' => true, 'action' => 'captcha_demo', 'hostname' => 'other.example']],
]);

test('free demo consumes each verification token once even if Cloudflare repeats success', function (): void {
    $payload = captchaDemoPayload();
    $this->postJson('/giai-captcha-nro/try', $payload)->assertOk();
    $this->postJson('/giai-captcha-nro/try', $payload)->assertUnprocessable();
    Http::assertSentCount(3);
});

test('free demo fails closed when verification is unavailable or unconfigured', function (string $scenario): void {
    if ($scenario === 'unconfigured') {
        app(SettingStore::class)->putMany(['turnstile_enabled' => false]);
        $this->get('/giai-captcha-nro')->assertOk()->assertSee('đang chờ cấu hình')->assertDontSee('data-captcha-turnstile', false);
    } else {
        config()->set('captcha_demo_test.connection', true);
    }
    $this->postJson('/giai-captcha-nro/try', captchaDemoPayload())->assertStatus(503)->assertJsonPath('status', false);
    $this->assertDatabaseCount('captcha_transactions', 0);
})->with(['unconfigured', 'connection']);

test('failed free provider requests do not create a debit or expose provider responses', function (): void {
    config()->set('captcha_demo_test.provider', ['success' => false, 'error' => 'provider-secret-detail']);
    $this->postJson('/giai-captcha-nro/try', captchaDemoPayload())->assertStatus(502)
        ->assertJsonPath('status', false)->assertJsonPath('code', null)->assertDontSee('provider-secret-detail');
    $this->assertDatabaseCount('captcha_transactions', 0);
});

test('free demo respects tool maintenance and throttles requests', function (): void {
    app(SettingStore::class)->putMany(['tool_giai-captcha-nro_enabled' => false]);
    $this->postJson('/giai-captcha-nro/try', captchaDemoPayload())->assertStatus(503);
    for ($index = 0; $index < 4; $index++) {
        $this->postJson('/giai-captcha-nro/try', [])->assertUnprocessable();
    }
    $this->postJson('/giai-captcha-nro/try', [])->assertTooManyRequests();
    $this->assertDatabaseCount('captcha_solves', 0);
});
