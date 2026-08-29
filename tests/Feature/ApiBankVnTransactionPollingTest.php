<?php

use App\Enums\PaymentStatus;
use App\Features\Recharge\Services\ApiBankVnPartnerService;
use App\Features\Topup\Jobs\ProcessTopupOrder;
use App\Mail\Orders\PaymentReceivedMail;
use App\Models\ConfigRecharge;
use App\Models\Game;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    config(['services.internal_cron.key' => 'private-cron-key']);
    Cache::flush();
    Http::preventStrayRequests();
    Mail::fake();
    Queue::fake();
});

test('apibankvn transaction cron rejects missing wrong and query string keys', function (): void {
    $this->postJson(route('api.cron.apibankvn.transactions'))->assertForbidden();
    $this->withHeader('X-Cron-Key', 'wrong-key')
        ->postJson(route('api.cron.apibankvn.transactions'))
        ->assertForbidden();
    $this->postJson(route('api.cron.apibankvn.transactions', ['key' => 'private-cron-key']))
        ->assertForbidden();

    Http::assertNothingSent();
});

test('apibankvn transaction cron fetches the configured bank and matches incoming transfers idempotently', function (): void {
    $this->travelTo(now()->setDate(2026, 8, 28)->setTime(9, 30));

    ConfigRecharge::query()->create([
        'provider' => 'apibankvn_api',
        'bank_name' => 'MBBank',
        'account_name' => 'NAP CAROT',
        'account_number' => '0123456789',
        'qr_template' => 'https://qr.test/{account_number}',
        'transfer_prefix' => 'NAP',
        'api_base_url' => 'https://apibankvn.test',
        'api_key' => 'api-key',
        'api_secret' => 'api-secret',
        'webhook_secret' => 'webhook-secret',
        'api_bank_id' => 12,
        'is_active' => true,
    ]);

    $order = Order::factory()->create([
        'game_id' => Game::factory(),
        'topup_package_id' => null,
        'total_amount' => 10000,
        'payment_status' => PaymentStatus::Pending,
    ]);
    PaymentTransaction::query()->create([
        'user_id' => $order->user_id,
        'order_id' => $order->id,
        'transaction_code' => $order->code,
        'amount' => 10000,
        'content' => 'NAPABC12345',
        'transfer_reference' => 'NAPABC12345',
        'status' => 'pending',
    ]);

    Http::fake([
        'https://apibankvn.test/api/v1/transactions' => Http::response([
            'status' => true,
            'data' => [
                'transactions' => [
                    [
                        'transaction_id' => 'BANK-CREDIT-001',
                        'type' => 'credit',
                        'amount' => 10000,
                        'description' => 'Thanh toan napabc12345',
                        'transaction_time' => '2026-08-28 09:00:00',
                        'polling_marker' => 'first-winner',
                    ],
                    [
                        'transaction_id' => 'BANK-DEBIT-001',
                        'type' => 'debit',
                        'amount' => 10000,
                        'description' => 'NAPABC12345',
                    ],
                ],
            ],
        ]),
    ]);

    $firstResponse = $this->withToken('private-cron-key')
        ->postJson(route('api.cron.apibankvn.transactions'));

    $firstResponse->assertSuccessful()
        ->assertJsonPath('data.configs', 1)
        ->assertJsonPath('data.fetched', 2)
        ->assertJsonPath('data.matched', 1)
        ->assertJsonPath('data.ignored', 1)
        ->assertJsonPath('data.failed', 0);

    $this->withHeader('X-Cron-Key', 'private-cron-key')
        ->postJson(route('api.cron.apibankvn.transactions'))
        ->assertSuccessful();

    $this->withHeader('X-Webhook-Secret', 'webhook-secret')
        ->postJson(route('api.recharge.callbacks.apibankvn'), [
            'bank_id' => 12,
            'sign' => md5('webhook-secret12'),
            'transaction_id' => 'BANK-CREDIT-001',
            'transaction_type' => 'credit',
            'transfer_content' => 'Thanh toan napabc12345',
            'amount' => 10000,
        ])
        ->assertSuccessful();

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://apibankvn.test/api/v1/transactions'
            && $request->method() === 'POST'
            && $request->hasHeader('X-API-KEY', 'api-key')
            && $request->hasHeader('X-API-SECRET', 'api-secret')
            && $request->data() === [
                'bank_id' => 12,
                'limit' => 20,
                'force_refresh' => true,
                'start_date' => '2026-08-27',
                'end_date' => '2026-08-28',
            ];
    });

    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and(PaymentTransaction::query()->count())->toBe(1)
        ->and(PaymentTransaction::query()->sole()->provider_transaction_id)->toBe('BANK-CREDIT-001')
        ->and(data_get(PaymentTransaction::query()->sole()->raw_data, 'callback_payload.polling_marker'))->toBe('first-winner');
    Mail::assertQueued(PaymentReceivedMail::class, 1);
    Queue::assertPushed(ProcessTopupOrder::class, 1);
});

test('apibankvn polling does not process a transaction already handled by webhook', function (): void {
    ConfigRecharge::query()->create([
        'provider' => 'apibankvn_api',
        'bank_name' => 'MBBank',
        'account_name' => 'NAP CAROT',
        'account_number' => '0123456789',
        'qr_template' => 'https://qr.test/{account_number}',
        'transfer_prefix' => 'NAP',
        'api_base_url' => 'https://apibankvn.test',
        'api_key' => 'api-key',
        'api_secret' => 'api-secret',
        'webhook_secret' => 'webhook-secret',
        'api_bank_id' => 12,
        'is_active' => true,
    ]);

    $order = Order::factory()->create([
        'game_id' => Game::factory(),
        'topup_package_id' => null,
        'total_amount' => 20000,
        'payment_status' => PaymentStatus::Pending,
    ]);
    PaymentTransaction::query()->create([
        'user_id' => $order->user_id,
        'order_id' => $order->id,
        'transaction_code' => $order->code,
        'amount' => 20000,
        'content' => 'NAPWEBHOOK1',
        'transfer_reference' => 'NAPWEBHOOK1',
        'status' => 'pending',
    ]);

    Http::fake([
        'https://apibankvn.test/api/v1/transactions' => Http::response([
            'status' => true,
            'data' => [
                'transactions' => [[
                    'transaction_id' => 'BANK-WEBHOOK-FIRST',
                    'type' => 'credit',
                    'amount' => 20000,
                    'description' => 'NAPWEBHOOK1',
                ]],
            ],
        ]),
    ]);

    $callbackPayload = [
        'bank_id' => 12,
        'sign' => md5('webhook-secret12'),
        'transaction_id' => 'BANK-WEBHOOK-FIRST',
        'transaction_type' => 'credit',
        'event_keyword' => 'webhook-first-winner',
        'transfer_content' => 'NAPWEBHOOK1',
        'amount' => 20000,
    ];

    $this->withHeader('X-Webhook-Secret', 'webhook-secret')
        ->postJson(route('api.recharge.callbacks.apibankvn'), $callbackPayload)
        ->assertSuccessful();

    $this->withHeader('X-Cron-Key', 'private-cron-key')
        ->postJson(route('api.cron.apibankvn.transactions'))
        ->assertSuccessful()
        ->assertJsonPath('data.matched', 1);

    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and(PaymentTransaction::query()->count())->toBe(1)
        ->and(PaymentTransaction::query()->sole()->provider_transaction_id)->toBe('BANK-WEBHOOK-FIRST')
        ->and(data_get(PaymentTransaction::query()->sole()->raw_data, 'callback_payload.event_keyword'))->toBe('webhook-first-winner');
    Mail::assertQueued(PaymentReceivedMail::class, 1);
    Queue::assertPushed(ProcessTopupOrder::class, 1);
});

test('apibankvn webhook never treats an outgoing transaction as payment', function (): void {
    ConfigRecharge::query()->create([
        'provider' => 'apibankvn_api',
        'bank_name' => 'MBBank',
        'account_name' => 'NAP CAROT',
        'account_number' => '0123456789',
        'qr_template' => 'https://qr.test/{account_number}',
        'transfer_prefix' => 'NAP',
        'api_base_url' => 'https://apibankvn.test',
        'api_key' => 'api-key',
        'api_secret' => 'api-secret',
        'webhook_secret' => 'webhook-secret',
        'api_bank_id' => 12,
        'is_active' => true,
    ]);
    $order = Order::factory()->create([
        'game_id' => Game::factory(),
        'topup_package_id' => null,
        'total_amount' => 30000,
        'payment_status' => PaymentStatus::Pending,
    ]);
    $paymentTransaction = PaymentTransaction::query()->create([
        'user_id' => $order->user_id,
        'order_id' => $order->id,
        'transaction_code' => $order->code,
        'amount' => 30000,
        'content' => 'NAPOUTGOING',
        'transfer_reference' => 'NAPOUTGOING',
        'status' => 'pending',
    ]);

    $this->withHeader('X-Webhook-Secret', 'webhook-secret')
        ->postJson(route('api.recharge.callbacks.apibankvn'), [
            'bank_id' => 12,
            'sign' => md5('webhook-secret12'),
            'transaction_id' => 'BANK-DEBIT-CALLBACK',
            'transaction_type' => 'debit',
            'transfer_content' => 'NAPOUTGOING',
            'amount' => 30000,
        ])
        ->assertNotFound();

    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Pending)
        ->and($paymentTransaction->refresh()->status)->toBe('pending')
        ->and($paymentTransaction->provider_transaction_id)->toBeNull();
    Mail::assertNothingQueued();
    Queue::assertNotPushed(ProcessTopupOrder::class);
});

test('apibankvn webhook cannot credit a wallet transaction already handled by polling twice', function (): void {
    $config = ConfigRecharge::query()->create([
        'provider' => 'apibankvn_api',
        'bank_name' => 'MBBank',
        'account_name' => 'NAP CAROT',
        'account_number' => '0123456789',
        'qr_template' => 'https://qr.test/{account_number}',
        'transfer_prefix' => 'NAP',
        'api_base_url' => 'https://apibankvn.test',
        'api_key' => 'api-key',
        'api_secret' => 'api-secret',
        'webhook_secret' => 'webhook-secret',
        'api_bank_id' => 12,
        'is_active' => true,
    ]);
    $user = User::factory()->create();
    $paymentTransaction = PaymentTransaction::query()->create([
        'user_id' => $user->id,
        'bank_code' => 'MBBank',
        'account_number' => '0123456789',
        'transaction_code' => 'DEPWINNER001',
        'amount' => 50000,
        'content' => 'NAPWALLET01',
        'transfer_reference' => 'NAPWALLET01',
        'status' => 'pending',
        'raw_data' => [
            'provider' => 'apibankvn_api',
            'recharge_config_id' => $config->id,
            'requested_transfer_content' => 'NAPWALLET01',
            'client_order_code' => 'DEPWINNER001',
        ],
    ]);

    Http::fake([
        'https://apibankvn.test/api/v1/transactions' => Http::response([
            'status' => true,
            'data' => [
                'transactions' => [[
                    'transaction_id' => 'BANK-WALLET-WINNER',
                    'type' => 'credit',
                    'amount' => 50000,
                    'description' => 'NAP WAL LET01',
                ]],
            ],
        ]),
    ]);

    $this->withHeader('X-Cron-Key', 'private-cron-key')
        ->postJson(route('api.cron.apibankvn.transactions'))
        ->assertSuccessful();

    $this->withHeader('X-Webhook-Secret', 'webhook-secret')
        ->postJson(route('api.recharge.callbacks.apibankvn'), [
            'bank_id' => 12,
            'sign' => md5('webhook-secret12'),
            'transaction_id' => 'BANK-WALLET-WINNER',
            'transaction_type' => 'credit',
            'transfer_content' => 'NAP WALL ET01',
            'amount' => 50000,
        ])
        ->assertSuccessful();

    expect($paymentTransaction->refresh()->status)->toBe('success')
        ->and($paymentTransaction->provider_transaction_id)->toBe('BANK-WALLET-WINNER')
        ->and(Wallet::query()->where('user_id', $user->id)->sole()->balance)->toBe('50000.00')
        ->and(WalletTransaction::query()->count())->toBe(1);
});

test('apibankvn bank account verification always exposes the provider bank id', function (): void {
    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/api/v1/list-bank-accounts')) {
            return Http::response([
                'status' => true,
                'data' => [
                    'bank_accounts' => [
                        ['id' => 12, 'bank_name' => 'MBBank', 'account_number' => '0123456789'],
                    ],
                ],
            ]);
        }

        return Http::response([
            'status' => true,
            'data' => ['user' => [], 'permissions' => [], 'endpoints' => []],
        ]);
    });

    $result = app(ApiBankVnPartnerService::class)->verifyCredentials(
        apiKey: 'api-key',
        apiSecret: 'api-secret',
        baseUrl: 'https://apibankvn.test',
    );

    expect($result['bank_accounts'])->toHaveCount(1)
        ->and($result['bank_accounts'][0]['bank_id'])->toBe(12);
});
