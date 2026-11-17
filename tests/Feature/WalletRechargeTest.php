<?php

use App\Features\Recharge\Services\RechargeConfigurationService;
use App\Features\Recharge\Services\RechargeOrderService;
use App\Features\Recharge\Services\RechargePollService;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletRechargeOrder;
use App\Models\WalletTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

function rechargeBank(): array
{
    return ['bank_id' => 25, 'bank_code' => 'mbbank', 'bank_name' => 'MB', 'account_number' => '123456', 'account_name' => 'TEST OWNER', 'status' => 'active', 'username' => 'must-not-return'];
}
function rechargeRemote(WalletRechargeOrder $order, string $status = 'paid', array $overrides = []): array
{
    return array_replace(['order_code' => $order->provider_code, 'client_order_code' => $order->code, 'bank_id' => 25, 'amount' => $order->amount,
        'account_number' => '123456', 'transfer_content' => $order->transfer_content, 'status' => $status,
        'matched_bank_transaction_id' => 991, 'paid_at' => now()->toIso8601String(), 'expires_at' => now()->addHour()->toIso8601String()], $overrides);
}
function rechargeRequest(): array
{
    return ['amount' => 10000, 'request_id' => (string) Str::uuid()];
}

beforeEach(function (): void {
    Http::preventStrayRequests();
    Http::fake(['https://apibankvn.com/api/v1/list-bank-accounts' => Http::response(['status' => true, 'data' => ['bank_accounts' => [rechargeBank()]]]),
        'https://apibankvn.com/api/v1/recharge-orders' => function (HttpRequest $request) {
            return Http::response(['status' => true, 'data' => ['order' => ['order_code' => 'RCL'.$request['client_order_code'], 'client_order_code' => $request['client_order_code'], 'bank_id' => $request['bank_id'], 'amount' => $request['amount'],
                'transfer_content' => $request['comment'], 'account_number' => '123456', 'status' => 'pending', 'expires_at' => now()->addHour()->toIso8601String()]]], 201);
        }]);
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->user = User::factory()->create();
    $this->wallet = Wallet::factory()->create(['user_id' => $this->user->id, 'balance' => 1000]);
    $this->config = app(RechargeConfigurationService::class);
    $this->config->save(['enabled' => true, 'bank_id' => 25, 'api_key' => 'ak_private', 'api_secret' => 'sk_private', 'cron_key' => str_repeat('z', 40)]);
    $this->orders = app(RechargeOrderService::class);
});

test('client recharge navigation and quick amount form render with balanced mobile columns', function (bool $services): void {
    config(['license.services_visible' => $services]);
    $response = $this->actingAs($this->user)->get(route('account.wallet'))->assertOk()
        ->assertSee('id="nap-tien"', false)->assertSee('data-recharge-form', false)
        ->assertSee('data-recharge-preset="50000"', false)->assertSee('data-recharge-summary', false)
        ->assertSee($services ? 'grid-cols-6' : 'grid-cols-5');
    $html = $response->getContent();
    expect(substr_count($html, 'href="'.route('account.wallet').'#nap-tien"'))->toBe(4);
})->with([false, true]);

test('disabled recharge keeps a navigation destination and support without a payment form', function (): void {
    $this->config->save(['enabled' => false]);
    $this->actingAs($this->user)->get(route('account.wallet'))->assertOk()
        ->assertSee('id="nap-tien"', false)->assertDontSee('data-recharge-form', false)->assertSee('Liên hệ hỗ trợ');
});

test('UTC provider timestamps survive database reload and show a payable QR after redirect', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-17T16:00:00+07:00'));
    Http::fake(['https://apibankvn.com/api/v1/recharge-orders' => function (HttpRequest $request) {
        return Http::response(['status' => true, 'data' => ['order' => [
            'order_code' => 'UTC'.$request['client_order_code'], 'client_order_code' => $request['client_order_code'],
            'bank_id' => $request['bank_id'], 'amount' => $request['amount'], 'transfer_content' => $request['comment'],
            'account_number' => '123456', 'status' => 'pending', 'expires_at' => '2026-11-17T10:00:00.000000Z',
        ]]], 201);
    }]);
    $response = $this->actingAs($this->user)->post(route('account.recharge.store'), rechargeRequest())->assertRedirect();
    $order = WalletRechargeOrder::query()->sole();
    expect($order->expires_at->toIso8601String())->toBe('2026-11-17T17:00:00+07:00')
        ->and($order->expires_at->isFuture())->toBeTrue();
    $this->get($response->headers->get('Location'))->assertOk()->assertSee('data-recharge-qr-image', false)
        ->assertSee('data-recharge-countdown', false)->assertSee('Tôi đã chuyển khoản');
    Http::fake(['https://apibankvn.com/api/v1/recharge-orders/*' => Http::response(['status' => true, 'data' => ['order' => rechargeRemote($order, 'paid', ['paid_at' => '2026-11-17T09:30:00Z'])]])]);
    $this->orders->check($order);
    expect($order->fresh()->paid_at->toIso8601String())->toBe('2026-11-17T16:30:00+07:00');
});

test('repair corrects legacy UTC deadlines once without extending valid or truly expired deadlines', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-17T16:00:00+07:00'));
    $broken = WalletRechargeOrder::factory()->create(['expires_at' => '2026-11-17 10:00:00']);
    $valid = WalletRechargeOrder::factory()->create(['expires_at' => '2026-11-17 17:00:00']);
    $old = WalletRechargeOrder::factory()->create(['expires_at' => '2026-11-16 10:00:00']);
    $migration = require database_path('migrations/2026_11_17_165103_normalize_recharge_order_expiration_timezone.php');
    $migration->up();
    $migration->up();
    expect($broken->fresh()->expires_at->format('H:i'))->toBe('17:00')
        ->and($valid->fresh()->expires_at->format('H:i'))->toBe('17:00')
        ->and($old->fresh()->getRawOriginal('expires_at'))->toBe('2026-11-16 10:00:00');
    $this->actingAs($broken->user)->get(route('account.recharge.show', $broken))->assertOk()->assertSee('data-recharge-qr-image', false);
});

test('transfer content uses a unique ten character TNR code throughout provider QR and payment matching', function (): void {
    WalletRechargeOrder::factory()->create(['code' => 'TNRDUP0000']);
    Str::createRandomStringsUsingSequence(['DUP0000', 'NEW0000']);
    try {
        $requestId = (string) Str::uuid();
        $order = $this->orders->create($this->user, 10000, $requestId);
        expect($order->code)->toBe('TNRNEW0000')->toMatch('/\ATNR[A-Z0-9]{7}\z/')
            ->and($order->transfer_content)->toBe($order->code);
        expect($this->orders->create($this->user, 10000, $requestId)->code)->toBe($order->code);
        Http::assertSent(fn (HttpRequest $request): bool => $request->method() === 'POST'
            && $request['comment'] === 'TNRNEW0000' && $request['client_order_code'] === 'TNRNEW0000');
        parse_str(parse_url($order->paymentQrUrl(), PHP_URL_QUERY), $query);
        expect($query['addInfo'])->toBe('TNRNEW0000');
        Http::fake(['https://apibankvn.com/api/v1/recharge-orders/*' => Http::response(['status' => true, 'data' => ['order' => rechargeRemote($order)]])]);
        $this->orders->check($order);
        expect($order->fresh()->status)->toBe('paid')->and($this->wallet->fresh()->balance)->toBe(11000);
    } finally {
        Str::createRandomStringsNormally();
    }
});

test('formatted deposit amounts submit as exact integer amounts', function (): void {
    $this->actingAs($this->user)->post(route('account.recharge.store'), ['amount' => '50.000', 'request_id' => (string) Str::uuid()])->assertRedirect();
    expect(WalletRechargeOrder::query()->sole()->amount)->toBe(50000);
    $this->postJson(route('account.recharge.store'), ['amount' => '50.00', 'request_id' => (string) Str::uuid()])->assertUnprocessable();
});

test('payment page shows a snapshot QR with exact amount content and copy actions', function (): void {
    $order = $this->orders->create($this->user, 20000, (string) Str::uuid());
    expect($order->bank_code)->toBe('mbbank');
    $url = $order->paymentQrUrl();
    expect($url)->toStartWith('https://img.vietqr.io/image/mbbank-123456-compact2.png?');
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    expect($query)->toBe(['amount' => '20000', 'addInfo' => $order->transfer_content, 'accountName' => 'TEST OWNER']);
    $this->actingAs($this->user)->get(route('account.recharge.show', $order))->assertOk()
        ->assertSee('Thanh toán chuyển khoản')->assertSee('data-recharge-countdown', false)
        ->assertSee('data-recharge-copy="123456"', false)->assertSee('Tôi đã chuyển khoản')
        ->assertDontSee('ak_private')->assertDontSee('sk_private');
    $this->config->save(['enabled' => false]);
    expect($order->fresh()->paymentQrUrl())->toBe($url);
});

test('terminal and locally expired orders cannot show payment QR or transfer instructions', function (string $status): void {
    $order = $this->orders->create($this->user, 10000, (string) Str::uuid());
    $order->forceFill(['status' => $status, 'expires_at' => now()->subMinute()])->save();
    expect($order->paymentQrUrl())->toBeNull();
    $this->actingAs($this->user)->get(route('account.recharge.show', $order))->assertOk()
        ->assertDontSee('data-recharge-qr-image', false)->assertDontSee('data-recharge-copy=', false);
})->with(['paid', 'expired', 'failed', 'cancelled', 'creation_failed', 'pending']);

test('recharge history is paginated and private on its own tab', function (): void {
    WalletRechargeOrder::factory()->count(21)->create(['user_id' => $this->user->id]);
    $other = WalletRechargeOrder::factory()->create();
    $this->actingAs($this->user)->get(route('account.wallet', ['tab' => 'history']))->assertOk()
        ->assertViewHas('rechargeOrders', fn ($orders) => $orders->total() === 21 && $orders->count() === 20)
        ->assertDontSee($other->code)->assertDontSee('data-recharge-form', false);
    $this->get(route('account.wallet', ['tab' => 'history', 'recharge_page' => 2]))->assertOk()
        ->assertViewHas('rechargeOrders', fn ($orders) => $orders->count() === 1);
});

test('admin config masks encrypted credentials and the bank response strips provider login metadata', function (): void {
    $this->actingAs($this->user)->getJson(route('admin.recharge.configuration'))->assertForbidden();
    $response = $this->actingAs($this->admin)->getJson(route('admin.recharge.configuration'))->assertOk()->assertJsonPath('data.credentials_saved', true)->assertJsonPath('data.cron_key_saved', true);
    expect($response->getContent())->not->toContain('ak_private', 'sk_private', str_repeat('z', 40));
    foreach (['api_key', 'api_secret', 'cron_key'] as $key) {
        $setting = Setting::query()->where('key', 'recharge.'.$key)->firstOrFail();
        expect($setting->type)->toBe('encrypted')->and($setting->value)->not->toContain('private');
    }
    $this->postJson(route('admin.recharge.banks'))->assertOk()->assertJsonMissingPath('data.0.username');
    $this->putJson(route('admin.recharge.configure'), ['enabled' => true, 'bank_id' => 25])->assertOk();
    expect($this->config->credentials()['secret'])->toBe('sk_private');
});

test('creating recharge is owner bound idempotent and does not credit or leak credentials', function (): void {
    $request = rechargeRequest();
    $this->actingAs($this->user)->post(route('account.recharge.store'), $request)->assertRedirect();
    $this->post(route('account.recharge.store'), $request)->assertRedirect();
    $order = WalletRechargeOrder::query()->sole();
    expect($order->status)->toBe('pending')->and($order->api_secret)->toBe('sk_private')->and($order->getRawOriginal('api_secret'))->not->toBe('sk_private')
        ->and($this->wallet->fresh()->balance)->toBe(1000)->and(WalletTransaction::count())->toBe(0);
    Http::assertSentCount(2);
    Http::assertSent(fn (HttpRequest $request): bool => $request->method() === 'POST' && $request->hasHeader('X-API-KEY', 'ak_private') && $request['comment'] === $order->code);
    $this->get(route('account.recharge.show', $order))->assertOk()->assertSee($order->transfer_content)->assertDontSee('sk_private');
    $this->actingAs(User::factory()->create())->getJson(route('account.recharge.status', $order))->assertNotFound();
});

test('cron requires its secret and credits a matching paid transaction exactly once', function (): void {
    $order = $this->orders->create($this->user, 10000, (string) Str::uuid());
    Http::fake(['https://apibankvn.com/api/v1/recharge-orders/*' => Http::response(['status' => true, 'data' => ['order' => rechargeRemote($order)]])]);
    $this->postJson(route('recharge.cron'))->assertUnauthorized();
    $this->withHeader('X-Cron-Key', 'incorrect')->postJson(route('recharge.cron'))->assertUnauthorized();
    $this->withHeader('X-Cron-Key', str_repeat('z', 40))->postJson(route('recharge.cron'))->assertOk()->assertJsonPath('data.credited', 1);
    $this->postJson(route('recharge.cron'))->assertOk()->assertJsonPath('data.checked', 0);
    expect($this->wallet->fresh()->balance)->toBe(11000)->and(WalletTransaction::query()->where('direction', 'credit')->count())->toBe(1)->and($order->fresh()->status)->toBe('paid');
    $this->orders->check($order);
    expect($this->wallet->fresh()->balance)->toBe(11000);
});

test('unmatched or incomplete paid responses never credit the wallet', function (array $override): void {
    $order = $this->orders->create($this->user, 10000, (string) Str::uuid());
    Http::fake(['https://apibankvn.com/api/v1/recharge-orders/*' => Http::response(['status' => true, 'data' => ['order' => rechargeRemote($order, 'paid', $override)]])]);
    $result = app(RechargePollService::class)->run();
    expect($result['errors'])->toBe(1)->and($order->fresh()->status)->toBe('pending')->and($this->wallet->fresh()->balance)->toBe(1000)->and(WalletTransaction::count())->toBe(0);
})->with(array_map(fn (array $override): array => [$override], [['amount' => 20000], ['bank_id' => 26], ['client_order_code' => 'OTHER'], ['order_code' => 'OTHER'], ['account_number' => 'OTHER'], ['transfer_content' => 'OTHER'], ['matched_bank_transaction_id' => null], ['paid_at' => null]]));

test('the same bank transaction cannot fund two different deposit orders', function (): void {
    $first = $this->orders->create($this->user, 10000, (string) Str::uuid());
    $second = $this->orders->create($this->user, 10000, (string) Str::uuid());
    Http::fake(['https://apibankvn.com/api/v1/recharge-orders/*' => function (HttpRequest $request) use ($first, $second) {
        $order = str_ends_with($request->url(), $first->provider_code) ? $first : $second;

        return Http::response(['status' => true, 'data' => ['order' => rechargeRemote($order)]]);
    }]);
    $result = app(RechargePollService::class)->run();
    expect($result['credited'])->toBe(1)->and($result['errors'])->toBe(1)->and($this->wallet->fresh()->balance)->toBe(11000)->and(WalletTransaction::count())->toBe(1);
});

test('pending expired and failed provider states never add wallet balance', function (string $status): void {
    $order = $this->orders->create($this->user, 10000, (string) Str::uuid());
    Http::fake(['https://apibankvn.com/api/v1/recharge-orders/*' => Http::response(['status' => true, 'data' => ['order' => rechargeRemote($order, $status)]])]);
    $this->orders->check($order);
    expect($this->wallet->fresh()->balance)->toBe(1000)->and($order->fresh()->status)->toBe($status);
})->with(['pending', 'processing', 'expired', 'failed', 'cancelled']);

test('provider outages preserve a pending order for future cron retries and do not expose secrets', function (): void {
    $order = $this->orders->create($this->user, 10000, (string) Str::uuid());
    Http::fake(['https://apibankvn.com/api/v1/recharge-orders/*' => Http::response(['message' => 'raw sk_private'], 500)]);
    app(RechargePollService::class)->run();
    expect($order->fresh()->status)->toBe('pending')->and($order->fresh()->last_error)->toBe('PROVIDER_HTTP')->and(WalletTransaction::count())->toBe(0);
    $this->actingAs($this->admin)->getJson(route('admin.recharge.history'))->assertOk()->assertDontSee('sk_private')->assertDontSee('raw');
});

test('a timed out create is not retried with the same request id and never shows payment instructions', function (): void {
    $attempts = 0;
    Http::fake(['https://apibankvn.com/api/v1/recharge-orders' => function () use (&$attempts) {
        $attempts++;
        throw new ConnectionException('Secret response sk_private');
    }]);
    $id = (string) Str::uuid();
    $order = $this->orders->create($this->user, 10000, $id);
    $this->orders->create($this->user, 10000, $id);
    expect($attempts)->toBe(1)->and($order->status)->toBe('creation_failed')->and($order->provider_code)->toBeNull()->and(WalletTransaction::count())->toBe(0);
    $this->actingAs($this->user)->get(route('account.recharge.show', $order))->assertOk()->assertDontSee('sk_private')->assertDontSee('data-recharge-content');
});

test('cron batches at most ten pending orders and local status polling never calls the bank', function (): void {
    WalletRechargeOrder::factory()->count(11)->create(['user_id' => $this->user->id]);
    Http::fake(['https://apibankvn.com/api/v1/recharge-orders/*' => function (HttpRequest $request) {
        $order = WalletRechargeOrder::where('provider_code', basename($request->url()))->firstOrFail();

        return Http::response(['status' => true, 'data' => ['order' => rechargeRemote($order, 'pending')]]);
    }]);
    $result = app(RechargePollService::class)->run();
    expect($result['checked'])->toBe(10)->and(WalletRechargeOrder::whereNotNull('last_checked_at')->count())->toBe(10);
    $this->actingAs($this->user)->getJson(route('account.recharge.status', WalletRechargeOrder::first()))->assertOk();
    Http::assertSentCount(10);
});

test('a failed wallet credit rolls back the paid status and transaction claim', function (): void {
    $order = $this->orders->create($this->user, 10000, (string) Str::uuid());
    $this->wallet->update(['balance' => 1000000000000000]);
    Http::fake(['https://apibankvn.com/api/v1/recharge-orders/*' => Http::response(['status' => true, 'data' => ['order' => rechargeRemote($order)]])]);
    app(RechargePollService::class)->run();
    expect($order->fresh()->status)->toBe('pending')->and($order->fresh()->bank_transaction_id)->toBeNull()->and(WalletTransaction::count())->toBe(0);
    $this->wallet->update(['balance' => 1000]);
    $this->orders->check($order->fresh());
    expect($order->fresh()->status)->toBe('paid')->and($this->wallet->fresh()->balance)->toBe(11000);
});

test('cron prevents overlapping runs and old credentials stay attached to an existing deposit', function (): void {
    $order = $this->orders->create($this->user, 10000, (string) Str::uuid());
    $lock = Cache::lock('recharge:poll', 60);
    $lock->get();
    try {
        $this->withHeader('X-Cron-Key', str_repeat('z', 40))->postJson(route('recharge.cron'))->assertConflict();
    } finally {
        $lock->release();
    }
    Http::fake(['https://apibankvn.com/api/v1/recharge-orders/*' => Http::response(['status' => true, 'data' => ['order' => rechargeRemote($order)]])]);
    $this->config->save(['enabled' => false, 'api_key' => null, 'api_secret' => null]);
    $this->orders->check($order);
    Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/recharge-orders/') && $request->hasHeader('X-API-SECRET', 'sk_private'));
});
