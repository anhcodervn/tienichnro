<?php

use App\Enums\PaymentMethod;
use App\Mail\Orders\OrderCreatedMail;
use App\Models\Game;
use App\Models\GameServer;
use App\Models\Order;
use App\Models\Setting;
use App\Models\TopupPackage;
use App\Models\User;
use App\Support\SettingStore;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Mail::fake();
    Queue::fake();
});

function turnstileCatalog(): array
{
    $game = Game::factory()->create();
    $server = GameServer::factory()->for($game)->create();
    $package = TopupPackage::factory()->for($game)->create([
        'game_server_id' => $server->id,
        'price' => 90000,
        'original_price' => 100000,
    ]);

    return [$game, $server, $package];
}

function turnstileCheckoutPayload(Game $game, GameServer $server, TopupPackage $package, array $overrides = []): array
{
    return [
        'idempotency_key' => (string) Str::uuid(),
        'game_id' => $game->id,
        'server_id' => $server->id,
        'package_id' => $package->id,
        'purchase_mode' => 'single',
        'single_quantity' => 1,
        'recipient_fields' => ['game_account' => 'captcha-player', 'character_name' => ''],
        'email' => 'captcha@example.com',
        'payment_method' => PaymentMethod::BankTransfer->value,
        ...$overrides,
    ];
}

function enableTurnstile(): void
{
    $settings = app(SettingStore::class);
    $settings->putMany([
        'turnstile_enabled' => true,
        'turnstile_site_key' => 'site-key-test',
    ]);
    $settings->putEncryptedString('turnstile_secret_key', 'secret-key-test');
}

test('admin can configure turnstile without exposing the secret key', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/security', [
            'turnstile_enabled' => true,
            'turnstile_site_key' => 'site-key-test',
            'turnstile_secret_key' => 'secret-key-test',
        ])
        ->assertOk()
        ->assertJsonPath('data.settings.turnstile_enabled', true)
        ->assertJsonPath('data.settings.turnstile_site_key', 'site-key-test')
        ->assertJsonPath('data.settings.turnstile_secret_key', '')
        ->assertJsonPath('data.settings.turnstile_secret_configured', true);

    $storedSecret = Setting::query()->where('key', 'turnstile_secret_key')->firstOrFail();

    expect($storedSecret->type)->toBe('encrypted')
        ->and($storedSecret->value)->not->toBe('secret-key-test')
        ->and(app(SettingStore::class)->getString('turnstile_secret_key'))->toBe('secret-key-test');

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/security', [
            'turnstile_enabled' => true,
            'turnstile_site_key' => 'site-key-updated',
            'turnstile_secret_key' => '',
        ])
        ->assertOk()
        ->assertJsonPath('data.settings.turnstile_site_key', 'site-key-updated')
        ->assertJsonPath('data.settings.turnstile_secret_configured', true);

    expect(app(SettingStore::class)->getString('turnstile_secret_key'))->toBe('secret-key-test');

    $this->actingAs($admin)
        ->getJson('/api/admin-api/settings/security')
        ->assertOk()
        ->assertJsonPath('data.settings.turnstile_secret_key', '')
        ->assertJsonPath('data.settings.turnstile_secret_configured', true);
});

test('admin cannot enable turnstile without complete keys', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/security', [
            'turnstile_enabled' => true,
            'turnstile_site_key' => 'site-key-test',
            'turnstile_secret_key' => '',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('turnstile_secret_key');
});

test('guest checkout renders turnstile above the submit button only when enabled', function (): void {
    [$game] = turnstileCatalog();
    enableTurnstile();

    $this->get(route('topup.game', ['game' => $game]))
        ->assertOk()
        ->assertSee('data-turnstile-checkout', false)
        ->assertSee('data-sitekey="site-key-test"', false)
        ->assertSeeInOrder(['data-payment-method', 'data-turnstile-checkout', 'data-submit-button'], false)
        ->assertSee('https://challenges.cloudflare.com/turnstile/v0/api.js', false);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('topup.game', ['game' => $game]))
        ->assertOk()
        ->assertDontSee('data-turnstile-checkout', false)
        ->assertDontSee('data-turnstile-script', false);
});

test('guest checkout requires a successfully verified turnstile token', function (): void {
    [$game, $server, $package] = turnstileCatalog();
    enableTurnstile();
    Http::fake([
        'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']]),
    ]);

    $this->from(route('home'))
        ->post(route('checkout.store'), turnstileCheckoutPayload($game, $server, $package, [
            'cf-turnstile-response' => 'invalid-token',
        ]))
        ->assertRedirect(route('home'))
        ->assertSessionHasErrors('cf-turnstile-response');

    expect(Order::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

test('guest checkout rejects a missing turnstile token before contacting cloudflare', function (): void {
    [$game, $server, $package] = turnstileCatalog();
    enableTurnstile();
    Http::preventStrayRequests();

    $this->from(route('home'))
        ->post(route('checkout.store'), turnstileCheckoutPayload($game, $server, $package))
        ->assertRedirect(route('home'))
        ->assertSessionHasErrors('cf-turnstile-response');

    expect(Order::query()->count())->toBe(0);
    Http::assertNothingSent();
});

test('guest can create an order after server-side turnstile verification', function (): void {
    [$game, $server, $package] = turnstileCatalog();
    enableTurnstile();
    Http::fake([
        'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true, 'action' => 'guest_checkout']),
    ]);

    $response = $this->post(route('checkout.store'), turnstileCheckoutPayload($game, $server, $package, [
        'cf-turnstile-response' => 'valid-token',
    ]));

    $order = Order::query()->sole();
    $response->assertRedirect(route('orders.payment', $order));
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
        && $request['secret'] === 'secret-key-test'
        && $request['response'] === 'valid-token'
        && Str::isUuid((string) $request['idempotency_key']));
    Mail::assertQueued(OrderCreatedMail::class);
});

test('authenticated checkout bypasses turnstile verification', function (): void {
    [$game, $server, $package] = turnstileCatalog();
    enableTurnstile();
    Http::preventStrayRequests();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('checkout.store'), turnstileCheckoutPayload($game, $server, $package, [
        'email' => null,
    ]));

    $response->assertRedirect(route('orders.payment', Order::query()->sole()));
    Http::assertNothingSent();
});
