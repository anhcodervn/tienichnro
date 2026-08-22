<?php

use App\Models\ConfigRecharge;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function createApiBankRechargeConfig(array $overrides = []): ConfigRecharge
{
    return ConfigRecharge::query()->create(array_merge([
        'provider' => 'apibankvn_api',
        'bank_name' => 'MBBank',
        'account_name' => 'NGUYEN VAN A',
        'account_number' => '0123456789',
        'qr_template' => 'https://img.vietqr.io/image/{bank_name}-{account_number}-compact2.png?amount={amount}&addInfo={nd}',
        'transfer_prefix' => 'NAP',
        'api_base_url' => 'https://apibankvn.com',
        'api_key' => 'private-api-key',
        'api_secret' => 'private-api-secret',
        'webhook_secret' => 'private-webhook-secret',
        'api_bank_id' => 99,
        'is_active' => true,
    ], $overrides));
}

test('guest is redirected to login when opening wallet deposit page', function (): void {
    $this->get(route('wallet.deposit.index'))
        ->assertRedirect(route('login'));
});

test('authenticated user can open wallet deposit page without exposing recharge credentials', function (): void {
    $user = User::factory()->create();
    createApiBankRechargeConfig();

    $this->actingAs($user)
        ->get(route('wallet.deposit.index'))
        ->assertSuccessful()
        ->assertSee('Nạp tiền vào ví')
        ->assertSee('Lịch sử nạp tiền')
        ->assertSee('Tạo yêu cầu nạp')
        ->assertSee('Số dư ví hiện tại')
        ->assertSee('MBBank')
        ->assertDontSee('Thông tin chuyển khoản')
        ->assertDontSee('0123456789')
        ->assertDontSee('private-api-key')
        ->assertDontSee('private-api-secret')
        ->assertDontSee('private-webhook-secret')
        ->assertSee(route('client/wallet.deposit-requests.store'), false);
});

test('deposit page links the latest pending wallet request without exposing payment details', function (): void {
    $user = User::factory()->create();
    createApiBankRechargeConfig();
    $transaction = PaymentTransaction::query()->create([
        'user_id' => $user->id,
        'bank_code' => 'MBBank',
        'account_number' => 'PRIVATE-ACCOUNT-123',
        'transaction_code' => 'DEPPENDING123',
        'amount' => 500000,
        'content' => 'NAPSECRET123',
        'status' => 'pending',
        'raw_data' => [
            'provider' => 'apibankvn_api',
            'expires_at' => now()->addHour()->toISOString(),
        ],
    ]);

    $this->actingAs($user)
        ->get(route('wallet.deposit.index'))
        ->assertSuccessful()
        ->assertSee('Bạn có yêu cầu nạp đang chờ')
        ->assertSee('500.000đ')
        ->assertSee(route('wallet.deposit.payment', $transaction->transaction_code), false)
        ->assertDontSee('PRIVATE-ACCOUNT-123')
        ->assertDontSee('NAPSECRET123');
});

test('legacy topup index redirects to the home checkout', function (): void {
    $this->get('/nap-game')
        ->assertRedirect(route('home'));
});

test('wallet payment page is only visible to the deposit owner', function (): void {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $config = createApiBankRechargeConfig();
    $transaction = PaymentTransaction::query()->create([
        'user_id' => $owner->id,
        'bank_code' => 'MBBank',
        'account_number' => '0123456789',
        'transaction_code' => 'DEPPAYMENT123',
        'amount' => 500000,
        'content' => 'NAP123456',
        'status' => 'pending',
        'raw_data' => [
            'provider' => 'apibankvn_api',
            'recharge_config_id' => $config->id,
            'account_name' => 'NGUYEN VAN A',
            'qr_url' => 'https://example.com/payment-qr.png',
            'expires_at' => now()->addHour()->toISOString(),
        ],
    ]);

    $paymentUrl = route('wallet.deposit.payment', $transaction->transaction_code);

    $this->actingAs($otherUser)
        ->get($paymentUrl)
        ->assertNotFound();

    $this->actingAs($owner)
        ->get($paymentUrl)
        ->assertSuccessful()
        ->assertSee('Thanh toán chuyển khoản')
        ->assertSee('Quét mã để chuyển khoản')
        ->assertSee('500.000đ')
        ->assertSee('NAP123456')
        ->assertSee('https://example.com/payment-qr.png', false)
        ->assertSee('data-payment-countdown', false)
        ->assertSee('data-wallet-channel="users.'.$owner->id.'.wallet"', false)
        ->assertDontSee('private-api-secret');
});

test('client wallet overview only returns safe recharge configuration fields', function (): void {
    $user = User::factory()->create();
    createApiBankRechargeConfig();

    $response = $this->actingAs($user)
        ->getJson(route('client/wallet.index'))
        ->assertSuccessful()
        ->assertJsonPath('data.recharge_config.bank_name', 'MBBank')
        ->assertJsonPath('data.recharge_config.account_number', '0123456789')
        ->assertJsonMissing(['api_key' => 'private-api-key'])
        ->assertJsonMissing(['api_secret' => 'private-api-secret'])
        ->assertJsonMissing(['webhook_secret' => 'private-webhook-secret']);

    expect(array_keys($response->json('data.recharge_config')))->toBe([
        'id',
        'bank_name',
        'account_name',
        'account_number',
        'is_active',
    ]);
});

test('authenticated user creates an apibank recharge order through the wallet feature', function (): void {
    $user = User::factory()->create();
    $config = createApiBankRechargeConfig();

    Http::preventStrayRequests();
    Http::fake([
        'https://apibankvn.com/api/v1/recharge-orders' => Http::response([
            'status' => true,
            'message' => 'Created',
            'data' => [
                'order' => [
                    'order_code' => 'ABV123456',
                    'client_order_code' => 'DEPCLIENT123',
                    'bank_name' => 'MBBank',
                    'account_number' => '0123456789',
                    'account_name' => 'NGUYEN VAN A',
                    'transfer_content' => 'NAP100220826',
                    'status' => 'pending',
                    'expires_at' => now()->addHour()->toISOString(),
                ],
            ],
        ], 201),
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('client/wallet.deposit-requests.store'), [
            'amount' => 100000,
            'config_id' => $config->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.deposit_request.amount', 100000)
        ->assertJsonPath('data.deposit_request.bank_name', 'MBBank')
        ->assertJsonPath('data.deposit_request.method.id', 'bank_transfer')
        ->assertJsonPath('data.deposit_request.method.name', 'Chuyển khoản ngân hàng');

    $transaction = PaymentTransaction::query()->sole();

    expect($transaction->user_id)->toBe($user->id)
        ->and($transaction->amount)->toBe('100000.00')
        ->and($transaction->content)->toMatch('/^NAP[A-Z0-9]{8}$/')
        ->and($transaction->transfer_reference)->toBe($transaction->content)
        ->and($transaction->raw_data['provider'])->toBe('apibankvn_api')
        ->and($transaction->raw_data['remote_order_code'])->toBe('ABV123456')
        ->and($response->content())->not->toContain('private-api-key')
        ->not->toContain('private-api-secret');

    Http::assertSent(function (Request $request) use ($transaction): bool {
        return $request->url() === 'https://apibankvn.com/api/v1/recharge-orders'
            && $request->hasHeader('X-API-KEY', 'private-api-key')
            && $request->data()['bank_id'] === 99
            && $request->data()['amount'] === 100000
            && $request->data()['transfer_prefix'] === 'NAP'
            && $request->data()['transfer_content'] === $transaction->content;
    });
});

test('deposit request rejects invalid amount and inactive recharge config', function (): void {
    $user = User::factory()->create();
    $config = createApiBankRechargeConfig(['is_active' => false]);

    Http::preventStrayRequests();

    $this->actingAs($user)
        ->postJson(route('client/wallet.deposit-requests.store'), [
            'amount' => 999.5,
            'config_id' => $config->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount', 'config_id']);

    expect(PaymentTransaction::query()->count())->toBe(0);
});

test('wallet deposit scripts redirect to payment and use realtime without network polling', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $script = file_get_contents($projectRoot.'/resources/js/client.js');
    $depositView = file_get_contents($projectRoot.'/resources/views/client/wallet/deposit.blade.php');
    $paymentView = file_get_contents($projectRoot.'/resources/views/client/wallet/payment.blade.php');
    $orderPaymentView = file_get_contents($projectRoot.'/resources/views/client/orders/payment.blade.php');
    $sharedPaymentView = file_get_contents($projectRoot.'/resources/views/components/client/bank-transfer-payment.blade.php');

    expect($script)
        ->toContain("paymentUrlTemplate.replace('__CODE__', encodeURIComponent(deposit.code))")
        ->toContain("echo.private(walletChannel).listen('.wallet.deposit.credited'")
        ->toContain('countdownTimer = window.setTimeout(scheduleCountdown, 1000)')
        ->not->toContain('window.setInterval')
        ->not->toContain('setInterval(confirmPayment')
        ->and($depositView)
        ->toContain('data-payment-url-template')
        ->toContain('Lịch sử nạp tiền')
        ->toContain('data-deposit-mobile-submit')
        ->not->toContain('data-deposit-preview')
        ->and($paymentView)
        ->toContain('<x-client.bank-transfer-payment')
        ->toContain('data-wallet-payment')
        ->and($orderPaymentView)
        ->toContain('<x-client.bank-transfer-payment')
        ->toContain('data-order-realtime-channel')
        ->and($sharedPaymentView)
        ->toContain('data-payment-countdown')
        ->toContain('Quét mã để chuyển khoản');
});
