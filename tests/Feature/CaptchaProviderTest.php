<?php

use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Features\Captcha\Services\CaptchaProviderConfigurationService;
use App\Features\Captcha\Services\CaptchaWalletService;
use App\Models\CaptchaProvider;
use App\Models\CaptchaProviderAttempt;
use App\Models\CaptchaSolve;
use App\Models\User;
use Database\Seeders\CaptchaProviderSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Http::preventStrayRequests();
    app(ToolAvailabilityService::class)->create([
        'code' => 'giai-captcha-nro', 'name' => 'Giải captcha NRO', 'url' => '/giai-captcha-nro',
        'description' => '', 'icon' => 'bx-scan', 'is_enabled' => true, 'maintenance_message' => 'Bảo trì.',
    ]);
});

function providerPayload(array $overrides = []): array
{
    return array_replace([
        'name' => 'PlusDino 2', 'driver' => 'plusdino',
        'endpoint' => 'http://captcha.plusdino.com/SolveCaptcha/API.php',
        'api_key' => 'private-token/+?', 'priority' => 20, 'timeout' => 10, 'is_enabled' => true,
    ], $overrides);
}

function providerImage(): string
{
    return base64_encode(file_get_contents(__DIR__.'/../Fixtures/captcha.png'));
}

function providerUser(): User
{
    $user = User::factory()->create();
    app(CaptchaWalletService::class)->wallet($user)->update(['balance' => 10]);

    return $user;
}

test('default providers are available after migration and reseeding preserves admin configuration', function (): void {
    $nro = CaptchaProvider::query()->where('code', 'nro-giaicaptcha')->firstOrFail();
    $plus = CaptchaProvider::query()->where('code', 'plusdino')->firstOrFail();
    expect($nro->name)->toBe('nro.giaicaptcha')->and($nro->is_enabled)->toBeTrue()
        ->and($plus->driver)->toBe('plusdino')->and($plus->is_enabled)->toBeFalse();
    $nro->update(['endpoint' => 'https://custom.example/solve', 'is_enabled' => false]);
    $this->seed(CaptchaProviderSeeder::class);
    expect($nro->fresh()->endpoint)->toBe('https://custom.example/solve')->and($nro->fresh()->is_enabled)->toBeFalse();
    $phamgiang = CaptchaProvider::query()->where('code', 'phamgiang')->firstOrFail();
    expect($phamgiang->name)->toBe('api.phamgiang.net')->and($phamgiang->is_enabled)->toBeFalse();
    $this->assertDatabaseCount('captcha_providers', 3);
    CaptchaProviderAttempt::factory()->create();
});

test('provider administration requires an administrator for every action', function (): void {
    $id = CaptchaProvider::query()->firstOrFail()->id;
    $this->getJson('/api/admin-api/captcha/providers')->assertUnauthorized();
    $this->actingAs(User::factory()->create());
    $this->getJson('/api/admin-api/captcha/providers')->assertForbidden();
    $this->postJson('/api/admin-api/captcha/providers', providerPayload())->assertForbidden();
    $this->putJson('/api/admin-api/captcha/providers/'.$id, providerPayload())->assertForbidden();
    $this->deleteJson('/api/admin-api/captcha/providers/'.$id)->assertForbidden();
});

test('admin can manage multiple sources with encrypted keys without exposing them', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $created = $this->postJson('/api/admin-api/captcha/providers', providerPayload())
        ->assertCreated()->assertJsonPath('data.has_api_key', true)->assertJsonMissingPath('data.api_key');
    $id = $created->json('data.id');
    $provider = CaptchaProvider::query()->findOrFail($id);
    expect($provider->api_key)->toBe('private-token/+?')
        ->and(DB::table('captcha_providers')->where('id', $id)->value('api_key'))->not->toContain('private-token');
    $this->getJson('/api/admin-api/captcha/providers')->assertOk()->assertDontSee('private-token')->assertDontSee('"api_key"', false);
    $this->putJson('/api/admin-api/captcha/providers/'.$id, providerPayload(['api_key' => '']))->assertOk();
    expect($provider->fresh()->api_key)->toBe('private-token/+?');
    $this->putJson('/api/admin-api/captcha/providers/'.$id, providerPayload(['api_key' => 'replaced']))->assertOk();
    expect($provider->fresh()->api_key)->toBe('replaced');
    $this->putJson('/api/admin-api/captcha/providers/'.$id, providerPayload(['api_key' => '', 'clear_api_key' => true, 'is_enabled' => false]))
        ->assertOk()->assertJsonPath('data.has_api_key', false);
    expect($provider->fresh()->api_key)->toBeNull();
    $this->postJson('/api/admin-api/captcha/providers', providerPayload(['name' => 'PlusDino 3']))->assertCreated();
    $this->deleteJson('/api/admin-api/captcha/providers/'.$id)->assertNoContent();
    $this->assertDatabaseMissing('captcha_providers', ['id' => $id]);
});

test('provider configuration validates credentials endpoint driver and limits', function (array $overrides, string $field): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->postJson('/api/admin-api/captcha/providers', providerPayload($overrides))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['api_key' => ''], 'api_key'],
    [['name' => 'nro.giaicaptcha'], 'name'],
    [['endpoint' => 'file:///etc/passwd'], 'endpoint'],
    [['endpoint' => 'http://user:password@example.com/solve'], 'endpoint'],
    [['endpoint' => 'http://example.com/solve?token=secret'], 'endpoint'],
    [['endpoint' => 'http://example.com/solve#secret'], 'endpoint'],
    [['driver' => 'unknown'], 'driver'],
    [['priority' => -1], 'priority'],
    [['timeout' => 31], 'timeout'],
]);

test('clearing the key of an enabled PlusDino source requires disabling it', function (): void {
    $provider = CaptchaProvider::factory()->create(providerPayload());
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->putJson('/api/admin-api/captcha/providers/'.$provider->id, providerPayload(['api_key' => '', 'clear_api_key' => true]))
        ->assertUnprocessable()->assertJsonValidationErrors('api_key');
    expect($provider->fresh()->api_key)->toBe('private-token/+?');
});

test('selected PlusDino receives form image and query token and charges only one successful solve', function (): void {
    $provider = CaptchaProvider::factory()->create(providerPayload());
    app(CaptchaProviderConfigurationService::class)->save(['is_enabled' => true], $provider);
    Http::fake([
        'nro.giaicaptcha.vn/*' => Http::response(['success' => false], 503),
        'captcha.plusdino.com/*' => Http::response(['captcha' => 'Ab123', 'token' => 'private-token/+?', 'nested' => ['url' => 'token=private-token%2F%2B%3F']]),
    ]);
    $user = providerUser();
    $response = $this->actingAs($user)->postJson('/giai-captcha-nro/solve', ['image' => providerImage()])
        ->assertOk()->assertJsonPath('status', true)->assertJsonPath('code', 'Ab123')->assertJsonCount(3);
    expect(array_keys($response->json()))->toBe(['status', 'code', 'time']);
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->url() === $provider->endpoint.'?token=private-token%2F%2B%3F'
        && $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded')
        && $request->data() === ['image' => providerImage()]);
    $solve = CaptchaSolve::query()->firstOrFail();
    expect($solve->provider_name)->toBe('PlusDino 2')->and($solve->providerAttempts->pluck('status')->all())->toBe(['success'])
        ->and($solve->response['token'])->toBe('[REDACTED]')->and($solve->response['nested']['url'])->toBe('token=[REDACTED]');
    expect(app(CaptchaWalletService::class)->wallet($user)->balance)->toBe(9)
        ->and(app(CaptchaWalletService::class)->wallet($user)->reserved)->toBe(0);
    $this->assertDatabaseCount('captcha_transactions', 1);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/api/admin-api/captcha/solves/'.$solve->id)
        ->assertOk()->assertJsonCount(1, 'data.provider_attempts')->assertDontSee('private-token');
});

test('only the selected provider is used even when another source has higher priority or is incorrectly enabled', function (): void {
    CaptchaProvider::factory()->create(providerPayload(['priority' => 0, 'is_enabled' => false]));
    $first = CaptchaProvider::factory()->create(['name' => 'First', 'is_enabled' => true, 'priority' => 1, 'api_key' => 'bearer-secret']);
    app(CaptchaProviderConfigurationService::class)->save(['is_enabled' => true], $first);
    CaptchaProvider::factory()->create(['is_enabled' => true, 'priority' => 0]);
    Http::fake(['*' => Http::response(['success' => true, 'result' => '12345'])]);
    $this->actingAs(providerUser())->postJson('/giai-captcha-nro/solve', ['image' => providerImage()])->assertOk();
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->url() === $first->endpoint && $request->hasHeader('Authorization', 'Bearer bearer-secret')
        && $request->hasHeader('Content-Type', 'application/json'));
    expect(CaptchaSolve::query()->firstOrFail()->provider_name)->toBe('First');
});

test('failure of the selected source never calls another provider and releases reserved balance', function (string $failure): void {
    $provider = CaptchaProvider::factory()->create(providerPayload());
    app(CaptchaProviderConfigurationService::class)->save(['is_enabled' => true], $provider);
    CaptchaProvider::query()->where('driver', 'nro_giaicaptcha')->update(['is_enabled' => true]);
    Http::fake([
        'nro.giaicaptcha.vn/*' => Http::failedConnection(),
        'captcha.plusdino.com/*' => match ($failure) {
            'short' => Http::response(['captcha' => '123']),
            'false' => Http::response(['captcha' => '12345', 'status' => false]),
            'http' => Http::response(['captcha' => '12345'], 500),
            'malformed' => Http::response('private-token/+? not json'),
        },
    ]);
    $user = providerUser();
    $this->actingAs($user)->postJson('/giai-captcha-nro/solve', ['image' => providerImage()])
        ->assertStatus(502)->assertJsonPath('status', false)->assertJsonStructure(['message']);
    Http::assertSentCount(1);
    expect(app(CaptchaWalletService::class)->wallet($user)->balance)->toBe(10)
        ->and(app(CaptchaWalletService::class)->wallet($user)->reserved)->toBe(0)
        ->and(CaptchaProviderAttempt::query()->pluck('status')->all())->toBe(['failed'])
        ->and(CaptchaProviderAttempt::query()->latest('id')->first()->toJson())->not->toContain('private-token');
    $this->assertDatabaseCount('captcha_transactions', 0);
})->with(['short', 'false', 'http', 'malformed']);

test('no enabled sources returns a clear error without reserving tokens or calling providers', function (): void {
    CaptchaProvider::query()->update(['is_enabled' => false]);
    $user = providerUser();
    $this->actingAs($user)->postJson('/giai-captcha-nro/solve', ['image' => providerImage()])
        ->assertStatus(503)->assertJsonPath('status', false)
        ->assertJsonPath('message', 'Chưa chọn nguồn giải captcha chính. Vui lòng liên hệ quản trị viên.');
    Http::assertNothingSent();
    $this->assertDatabaseCount('captcha_solves', 0);
    expect(app(CaptchaWalletService::class)->wallet($user)->reserved)->toBe(0);
});

test('deleting a source while solving preserves its history and result', function (): void {
    $provider = CaptchaProvider::query()->where('driver', 'nro_giaicaptcha')->firstOrFail();
    Http::fake(function () use ($provider) {
        $provider->delete();

        return Http::response(['success' => true, 'result' => '12345']);
    });
    $this->actingAs(providerUser())->postJson('/giai-captcha-nro/solve', ['image' => providerImage()])->assertOk();
    $attempt = CaptchaProviderAttempt::query()->firstOrFail();
    expect($attempt->captcha_provider_id)->toBeNull()->and($attempt->provider_name)->toBe('nro.giaicaptcha')
        ->and($attempt->status)->toBe('success')->and(CaptchaSolve::query()->firstOrFail()->provider_name)->toBe('nro.giaicaptcha');
});

test('Pham Giang sends a multipart image and encoded query token and charges a successful result once', function (): void {
    CaptchaProvider::query()->update(['is_enabled' => false]);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $provider = CaptchaProvider::query()->where('code', 'phamgiang')->firstOrFail();
    $payload = providerPayload(['name' => $provider->name, 'driver' => 'phamgiang', 'endpoint' => $provider->endpoint]);
    $this->putJson('/api/admin-api/captcha/providers/'.$provider->id, $payload)
        ->assertOk()->assertJsonPath('data.has_api_key', true)->assertJsonMissingPath('data.api_key');
    Http::fake(['api.phamgiang.net/*' => Http::response([
        'status' => 0, 'captcha' => '39151', 'confidence' => 0.9999996, 'time' => 148, 'message' => '',
    ])]);
    $user = providerUser();
    $this->actingAs($user)->postJson('/giai-captcha-nro/solve', ['image' => providerImage()])
        ->assertOk()->assertJsonPath('status', true)->assertJsonPath('code', '39151')->assertJsonCount(3);
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === $provider->endpoint.'?token=private-token%2F%2B%3F'
        && $request->isMultipart() && str_contains($request->body(), providerImage())
        && str_contains($request->body(), 'name="image"'));
    $solve = CaptchaSolve::query()->firstOrFail();
    expect($solve->provider_name)->toBe('api.phamgiang.net')->and($solve->response['time'])->toBe(148)
        ->and($solve->providerAttempts->first()->status)->toBe('success')
        ->and(app(CaptchaWalletService::class)->wallet($user)->balance)->toBe(9)
        ->and(app(CaptchaWalletService::class)->wallet($user)->reserved)->toBe(0);
    $this->assertDatabaseCount('captcha_transactions', 1);
});

test('Pham Giang rejects nonzero missing or incorrectly typed status and empty captcha without charge', function (array $payload): void {
    CaptchaProvider::query()->update(['is_enabled' => false]);
    app(CaptchaProviderConfigurationService::class)->save(['api_key' => 'pg-secret', 'is_enabled' => true], CaptchaProvider::query()->where('code', 'phamgiang')->firstOrFail());
    Http::fake(['api.phamgiang.net/*' => Http::response($payload)]);
    $user = providerUser();
    $this->actingAs($user)->postJson('/giai-captcha-nro/solve', ['image' => providerImage()])
        ->assertStatus(502)->assertJsonPath('status', false)->assertJsonStructure(['message']);
    expect(app(CaptchaWalletService::class)->wallet($user)->balance)->toBe(10)
        ->and(app(CaptchaWalletService::class)->wallet($user)->reserved)->toBe(0)
        ->and(CaptchaProviderAttempt::query()->firstOrFail()->status)->toBe('failed');
    $this->assertDatabaseCount('captcha_transactions', 0);
})->with([
    'incorrect' => [['status' => 1, 'captcha' => '39151']],
    'error' => [['status' => 2, 'captcha' => '39151']],
    'missing status' => [['captcha' => '39151']],
    'false status' => [['status' => false, 'captcha' => '39151']],
    'string status' => [['status' => '0', 'captcha' => '39151']],
    'empty result' => [['status' => 0, 'captcha' => ' ']],
]);

test('Pham Giang token is required when enabling a source or clearing its key', function (): void {
    $provider = CaptchaProvider::query()->where('code', 'phamgiang')->firstOrFail();
    $payload = providerPayload(['name' => $provider->name, 'driver' => 'phamgiang', 'endpoint' => $provider->endpoint, 'api_key' => '']);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->putJson('/api/admin-api/captcha/providers/'.$provider->id, $payload)->assertUnprocessable()->assertJsonValidationErrors('api_key');
    $provider->update(['api_key' => 'pg-secret', 'is_enabled' => true]);
    $this->putJson('/api/admin-api/captcha/providers/'.$provider->id, [...$payload, 'clear_api_key' => true])
        ->assertUnprocessable()->assertJsonValidationErrors('api_key');
});

test('admin selecting a source replaces the previous primary and invalid selection preserves it', function (): void {
    $configuration = app(CaptchaProviderConfigurationService::class);
    $originalId = $configuration->primaryId();
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->postJson('/api/admin-api/captcha/providers', providerPayload(['api_key' => '']))->assertUnprocessable();
    expect($configuration->primaryId())->toBe($originalId);
    $id = $this->postJson('/api/admin-api/captcha/providers', providerPayload())->assertCreated()->json('data.id');
    expect($configuration->primaryId())->toBe($id)
        ->and(CaptchaProvider::query()->where('is_enabled', true)->pluck('id')->all())->toBe([$id]);
    $otherId = $this->postJson('/api/admin-api/captcha/providers', providerPayload(['name' => 'Another source']))->assertCreated()->json('data.id');
    expect($configuration->primaryId())->toBe($otherId)
        ->and(CaptchaProvider::query()->where('is_enabled', true)->pluck('id')->all())->toBe([$otherId]);
    $this->deleteJson('/api/admin-api/captcha/providers/'.$id)->assertNoContent();
    expect($configuration->primaryId())->toBe($otherId);
});

test('disabling or deleting the primary never automatically selects another source', function (string $action): void {
    $configuration = app(CaptchaProviderConfigurationService::class);
    $id = $configuration->primaryId();
    $provider = CaptchaProvider::query()->findOrFail($id);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    if ($action === 'delete') {
        $this->deleteJson('/api/admin-api/captcha/providers/'.$id)->assertNoContent();
    } else {
        $this->putJson('/api/admin-api/captcha/providers/'.$id, providerPayload([
            'name' => $provider->name, 'driver' => $provider->driver, 'endpoint' => $provider->endpoint, 'is_enabled' => false,
        ]))->assertOk();
    }
    expect($configuration->primaryId())->toBe(0)->and(CaptchaProvider::query()->where('is_enabled', true)->count())->toBe(0);
    $this->actingAs(providerUser())->postJson('/giai-captcha-nro/solve', ['image' => providerImage()])
        ->assertStatus(503)->assertJsonPath('status', false)->assertJsonPath('message', 'Chưa chọn nguồn giải captcha chính. Vui lòng liên hệ quản trị viên.');
    Http::assertNothingSent();
})->with(['disable', 'delete']);
