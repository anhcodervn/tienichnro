<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TopupProviderType;
use App\Mail\Orders\OrderCompletedMail;
use App\Models\AdminAuditLog;
use App\Models\Game;
use App\Models\GameServer;
use App\Models\Order;
use App\Models\OrderRecipient;
use App\Models\PaymentTransaction;
use App\Models\TopupPackage;
use App\Models\TopupProvider;
use App\Models\User;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

test('topup admin api rejects guests and ordinary users', function (): void {
    $provider = TopupProvider::factory()->create();

    $this->getJson('/api/admin-api/games')->assertUnauthorized();
    $this->getJson('/api/admin-api/topup-providers')->assertUnauthorized();
    $this->postJson('/api/admin-api/topup-providers/refresh-balances', ['provider_ids' => [1]])->assertUnauthorized();
    $this->postJson('/api/admin-api/topup-providers/1/services')->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->getJson('/api/admin-api/games')
        ->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->getJson('/api/admin-api/topup-providers')
        ->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->postJson("/api/admin-api/topup-providers/{$provider->id}/services")
        ->assertForbidden();
});

test('admin fetches and copies a sanitized provider services response through the server', function (): void {
    Http::preventStrayRequests();

    $admin = User::factory()->create(['role' => 'admin']);
    $provider = TopupProvider::factory()->create([
        'type' => TopupProviderType::MerchantPartnerCard,
        'connection_config' => [
            'base_url' => 'https://provider.test/api/rechargews',
            'partner_id' => 'partner-123',
            'partner_key' => 'secret-key',
        ],
    ]);

    Http::fake([
        'https://provider.test/api/rechargews' => Http::response([
            'status' => 'success',
            'message' => 'OK',
            'partner_key' => 'echoed-secret',
            'data' => [[
                'name' => 'Hiệp Sĩ Online',
                'service_code' => 'HSO',
                'metadata' => ['access_token' => 'echoed-token'],
                'items' => [[
                    'name' => 'Gói 100.000đ',
                    'value' => 100000,
                    'price' => 100000,
                    'discount' => 19.7,
                ]],
            ]],
        ]),
    ]);

    $response = $this->actingAs($admin)
        ->postJson("/api/admin-api/topup-providers/{$provider->id}/services")
        ->assertSuccessful()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.provider.id', $provider->id)
        ->assertJsonPath('data.response.status', 'success')
        ->assertJsonPath('data.response.data.0.service_code', 'HSO')
        ->assertJsonPath('data.response.data.0.items.0.value', 100000)
        ->assertJsonPath('data.response.partner_key', '[REDACTED]')
        ->assertJsonPath('data.response.data.0.metadata.access_token', '[REDACTED]');

    expect($response->getContent())
        ->not->toContain('echoed-secret')
        ->not->toContain('echoed-token')
        ->not->toContain('secret-key');

    Http::assertSent(function (ClientRequest $request): bool {
        return $request->url() === 'https://provider.test/api/rechargews'
            && $request['command'] === 'productlist'
            && $request['partner_id'] === 'partner-123'
            && $request['sign'] === md5('secret-key'.'partner-123'.'productlist');
    });
});

test('manual provider cannot fetch services', function (): void {
    Http::preventStrayRequests();

    $admin = User::factory()->create(['role' => 'admin']);
    $provider = TopupProvider::factory()->create([
        'type' => TopupProviderType::Manual,
        'connection_config' => ['mode' => 'manual'],
    ]);

    $this->actingAs($admin)
        ->postJson("/api/admin-api/topup-providers/{$provider->id}/services")
        ->assertUnprocessable()
        ->assertJsonPath('status', false)
        ->assertJsonPath('message', '[catalog_unsupported] Provider này không hỗ trợ lấy danh sách services tự động.');

    Http::assertNothingSent();
});

test('admin card statistics sum quantities from orders created today', function (): void {
    $this->travelTo(Carbon::parse('2026-09-01 12:00:00', config('app.timezone')));
    $admin = User::factory()->create(['role' => 'admin']);

    Order::factory()->create([
        'created_at' => now()->startOfDay(),
        'quantity' => 3,
        'payment_status' => PaymentStatus::Pending,
        'order_status' => OrderStatus::Pending,
    ]);
    Order::factory()->create([
        'created_at' => now()->subHours(2),
        'quantity' => 2,
        'payment_status' => PaymentStatus::Paid,
        'order_status' => OrderStatus::Processing,
    ]);
    Order::factory()->create([
        'created_at' => now()->subHour(),
        'quantity' => 4,
        'payment_status' => PaymentStatus::Paid,
        'order_status' => OrderStatus::Failed,
    ]);
    Order::factory()->create([
        'created_at' => now(),
        'quantity' => 5,
        'payment_status' => PaymentStatus::Paid,
        'order_status' => OrderStatus::Completed,
    ]);
    Order::factory()->create([
        'created_at' => now()->startOfDay()->subSecond(),
        'quantity' => 7,
        'payment_status' => PaymentStatus::Pending,
        'order_status' => OrderStatus::Failed,
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin-api/orders?order_status=failed')
        ->assertOk()
        ->assertJsonPath('data.statistics.total', 14)
        ->assertJsonPath('data.statistics.pending_payment', 3)
        ->assertJsonPath('data.statistics.processing', 2)
        ->assertJsonPath('data.statistics.failed', 4)
        ->assertJsonPath('data.meta.total', 2);
});

test('admin can find a completed order by topup id', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = Order::factory()->create([
        'order_status' => OrderStatus::Completed,
        'completed_at' => now(),
        'topup_id' => '1205643',
    ]);
    Order::factory()->create();

    $this->actingAs($admin)
        ->getJson('/api/admin-api/orders?search='.$order->topup_id)
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.data.0.code', $order->code)
        ->assertJsonPath('data.data.0.topup_id', $order->topup_id);
});

test('platform admin can manually complete a failed topup order', function (): void {
    Mail::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $order = Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'order_status' => OrderStatus::Failed,
        'failed_at' => now()->subMinute(),
        'failure_reason' => 'Provider trả về trạng thái failed sau khi đã nhận đơn.',
    ]);
    $recipient = OrderRecipient::factory()->for($order)->create([
        'status' => 'failed',
        'failure_reason' => 'Provider trả về failed.',
        'failed_at' => now()->subMinute(),
        'provider_response' => [
            'items' => [
                '1' => [
                    'status' => 'failed',
                    'response' => [
                        'body' => [
                            'status' => 'success',
                            'data' => ['request_id' => 'TOP260926Y25SS9-R001', 'status' => 'failed'],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $this->actingAs($admin)
        ->putJson("/api/admin-api/orders/{$order->code}", ['action' => 'complete'])
        ->assertSuccessful()
        ->assertJsonPath('data.order_status', 'completed')
        ->assertJsonPath('data.failure_reason', null)
        ->assertJsonPath('data.recipients.0.status', 'completed');

    $order->refresh();
    $recipient->refresh();

    expect($order->order_status)->toBe(OrderStatus::Completed)
        ->and($order->completed_at)->not->toBeNull()
        ->and($order->failure_reason)->toBeNull()
        ->and($recipient->status)->toBe('completed')
        ->and($recipient->failure_reason)->toBeNull()
        ->and(data_get($recipient->provider_response, 'items.1.status'))->toBe('failed')
        ->and(AdminAuditLog::query()->where([
            'admin_id' => $admin->id,
            'action' => 'order_complete',
            'subject_type' => Order::class,
            'subject_id' => $order->id,
        ])->exists())->toBeTrue();

    Mail::assertQueued(OrderCompletedMail::class, fn (OrderCompletedMail $mail): bool => $mail->hasTo($order->email));
});

test('admin order list exposes and searches bank transfer payment codes', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $targetOrder = Order::factory()->create(['payment_method' => 'bank_transfer']);
    $legacyOrder = Order::factory()->create(['payment_method' => 'bank_transfer']);
    PaymentTransaction::query()->create([
        'order_id' => $targetOrder->id,
        'transaction_code' => $targetOrder->code,
        'amount' => $targetOrder->total_amount,
        'content' => 'NAPSEARCH001',
        'transfer_reference' => 'NAPSEARCH001',
        'status' => 'pending',
    ]);
    PaymentTransaction::query()->create([
        'order_id' => null,
        'transaction_code' => $legacyOrder->code,
        'amount' => $legacyOrder->total_amount,
        'content' => 'NAPLEGACYSEARCH',
        'transfer_reference' => 'NAPLEGACYSEARCH',
        'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin-api/orders?search=NAPSEARCH001')
        ->assertSuccessful()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.data.0.code', $targetOrder->code)
        ->assertJsonPath('data.data.0.payment_method', 'bank_transfer')
        ->assertJsonPath('data.data.0.payment_transfer_content', 'NAPSEARCH001');

    $this->actingAs($admin)
        ->getJson('/api/admin-api/orders?search=NAPLEGACYSEARCH')
        ->assertSuccessful()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.data.0.code', $legacyOrder->code)
        ->assertJsonPath('data.data.0.payment_transfer_content', 'NAPLEGACYSEARCH');
});

test('admin order detail exposes QR reconciliation fields without raw callback payload', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = Order::factory()->create([
        'sale_unit_price' => 90000,
        'provider_unit_cost' => 75000,
        'provider_total_cost' => 75000,
        'gross_profit' => 15000,
        'tax_enabled' => true,
        'tax_calculation_type' => 'revenue',
        'vat_rate' => 1,
        'pit_rate' => 0.5,
        'estimated_vat' => 900,
        'estimated_pit' => 450,
        'estimated_tax' => 1350,
        'payment_fee' => 0,
        'other_cost' => 0,
        'net_profit' => 13650,
        'profit_margin' => 15.1667,
    ]);
    PaymentTransaction::query()->create([
        'order_id' => $order->id,
        'bank_code' => 'MBBank',
        'account_number' => '0123456789',
        'transaction_code' => 'ADMINQR001',
        'provider_transaction_id' => 'BANK-REF-001',
        'amount' => 90000,
        'content' => 'NAPADMIN001',
        'transfer_reference' => 'NAPADMIN001',
        'status' => 'success',
        'raw_data' => [
            'received_content' => 'napadmin001 thanh toan',
            'callback_payload' => ['api_secret' => 'must-not-leak'],
        ],
    ]);

    $response = $this->actingAs($admin)
        ->getJson("/api/admin-api/orders/{$order->code}")
        ->assertOk()
        ->assertJsonPath('data.topup_id', null)
        ->assertJsonPath('data.character_name', null)
        ->assertJsonMissingPath('data.game_character')
        ->assertJsonPath('data.pricing.sale_unit_price', 90000)
        ->assertJsonPath('data.pricing.sale_price', 450000)
        ->assertJsonPath('data.pricing.provider_total_cost', 75000)
        ->assertJsonPath('data.pricing.cost_price', 75000)
        ->assertJsonPath('data.pricing.gross_profit', 15000)
        ->assertJsonPath('data.pricing.tax_snapshot_available', true)
        ->assertJsonPath('data.pricing.estimated_vat', 900)
        ->assertJsonPath('data.pricing.estimated_pit', 450)
        ->assertJsonPath('data.pricing.estimated_tax', 1350)
        ->assertJsonPath('data.pricing.net_profit', 13650)
        ->assertJsonPath('data.pricing.profit_status', 'profit')
        ->assertJsonPath('data.payment_method', 'bank_transfer')
        ->assertJsonPath('data.payment_transfer_content', 'NAPADMIN001')
        ->assertJsonPath('data.payment_transaction.expected_content', 'NAPADMIN001')
        ->assertJsonPath('data.payment_transaction.received_content', 'napadmin001 thanh toan')
        ->assertJsonPath('data.payment_transaction.provider_transaction_id', 'BANK-REF-001');

    expect($response->json('data.payment_transaction'))->not->toHaveKey('raw_data')
        ->and($response->getContent())->not->toContain('must-not-leak');
});

test('admin order detail finds transfer content from a legacy unlinked bank transaction', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = Order::factory()->create();
    PaymentTransaction::query()->create([
        'order_id' => null,
        'transaction_code' => $order->code,
        'amount' => $order->total_amount,
        'content' => 'NAPLEGACY001',
        'transfer_reference' => null,
        'status' => 'pending',
        'raw_data' => ['transfer_content' => 'NAPLEGACY001'],
    ]);

    $this->actingAs($admin)
        ->getJson("/api/admin-api/orders/{$order->code}")
        ->assertSuccessful()
        ->assertJsonPath('data.payment_method', 'bank_transfer')
        ->assertJsonPath('data.payment_transfer_content', 'NAPLEGACY001')
        ->assertJsonPath('data.payment_transaction.expected_content', 'NAPLEGACY001');
});

test('admin provider page refreshes and stores a valid low balance response', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $provider = TopupProvider::factory()->create(['slug' => 'the9p']);
    Http::preventStrayRequests();
    Http::fake([
        'https://the9p.com/api/rechargews' => Http::response([
            'status' => 'success',
            'message' => 'Thành công',
            'data' => ['balance' => 200, 'currency' => 'VND'],
        ]),
    ]);

    $this->actingAs($admin)
        ->postJson('/api/admin-api/topup-providers/refresh-balances', ['provider_ids' => [$provider->id]])
        ->assertOk()
        ->assertJsonPath('data.0.id', $provider->id)
        ->assertJsonPath('data.0.balance', 200)
        ->assertJsonPath('data.0.balance_currency', 'VND')
        ->assertJsonPath('data.0.balance_status', 'success')
        ->assertJsonPath('data.0.balance_error_code', null);

    expect($provider->refresh()->balance)->toBe(200)
        ->and($provider->balance_status)->toBe('success')
        ->and($provider->balance_checked_at)->not->toBeNull();
});

test('failed provider balance refresh keeps last balance and exposes a safe diagnostic', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $provider = TopupProvider::factory()->create([
        'slug' => 'the9p',
        'balance' => 500000,
        'balance_currency' => 'VND',
        'balance_status' => 'success',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://the9p.com/api/rechargews' => Http::response(['message' => 'secret-key must not leak'], 403),
    ]);

    $response = $this->actingAs($admin)
        ->postJson('/api/admin-api/topup-providers/refresh-balances', ['provider_ids' => [$provider->id]])
        ->assertOk()
        ->assertJsonPath('data.0.balance', 500000)
        ->assertJsonPath('data.0.balance_status', 'failed')
        ->assertJsonPath('data.0.balance_error_code', 'authentication_failed');

    expect($response->getContent())->not->toContain('secret-key')
        ->and($provider->refresh()->balance)->toBe(500000)
        ->and($provider->balance_error_message)->toContain('IP whitelist');
});

test('catalog lists support server side search filters and pagination', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $targetGame = Game::factory()->create([
        'name' => 'Ngọc Rồng Online',
        'slug' => 'ngoc-rong-online',
        'status' => 'active',
        'sort_order' => 1,
    ]);
    Game::factory()->inactive()->count(11)->create(['sort_order' => 2]);
    $targetServer = GameServer::factory()->for($targetGame)->create([
        'name' => 'Vũ trụ 1',
        'code' => 'NRO-01',
        'status' => 'active',
    ]);
    GameServer::factory()->create(['name' => 'Máy chủ khác']);
    $targetProvider = TopupProvider::factory()->create(['name' => 'The9p', 'slug' => 'the9p']);
    TopupProvider::factory()->create(['name' => 'Provider khác']);
    TopupPackage::factory()->for($targetGame)->create([
        'provider_id' => $targetProvider->id,
        'name' => 'Gói Ngọc Rồng 100k',
        'provider_price' => 81000,
        'original_price' => 100000,
        'price' => 85000,
        'status' => 'active',
    ]);
    TopupPackage::factory()->create(['name' => 'Gói game khác', 'status' => 'inactive']);

    $this->actingAs($admin)->getJson('/api/admin-api/games?status=inactive&per_page=10&page=2')
        ->assertOk()
        ->assertJsonPath('data.meta.current_page', 2)
        ->assertJsonPath('data.meta.per_page', 10)
        ->assertJsonPath('data.meta.total', 11)
        ->assertJsonCount(1, 'data.data');

    $this->actingAs($admin)->getJson('/api/admin-api/game-servers?search=NRO-01&game_id='.$targetGame->id)
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.id', $targetServer->id);

    $this->actingAs($admin)->getJson('/api/admin-api/topup-packages?search=Ngọc&game_id='.$targetGame->id.'&provider_id='.$targetProvider->id.'&status=active&min_price=80000&max_price=90000')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.name', 'Gói Ngọc Rồng 100k');

    $this->actingAs($admin)->getJson('/api/admin-api/topup-providers?search=the9p')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.id', $targetProvider->id);
});

test('catalog list endpoints reject invalid filters', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->getJson('/api/admin-api/games?status=archived&per_page=11')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status', 'per_page']);

    $this->actingAs($admin)->getJson('/api/admin-api/game-servers?game_id=999999')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('game_id');

    $this->actingAs($admin)->getJson('/api/admin-api/topup-packages?min_price=90000&max_price=80000')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('max_price');

    $this->actingAs($admin)->getJson('/api/admin-api/topup-providers?per_page=5')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');
});

test('admin manages encrypted provider connection config without leaking secrets', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $connectionConfig = [
        'base_url' => 'https://api.provider.example/v1',
        'api_key' => 'public-key-123',
        'api_secret' => 'very-secret-value',
        'proxy' => 'http://proxy-user:proxy-pass@proxy.example:8080',
        'timeout' => 30,
        'balance_warning_threshold' => 1000000,
        'minimum_profit_percent' => 7.5,
    ];
    $payloadFieldMapping = [
        'default' => ['character' => 'charactor'],
        'services' => ['hso' => ['username' => 'user_account']],
    ];

    $created = $this->actingAs($admin)->postJson('/api/admin-api/topup-providers', [
        'name' => 'Provider Carot',
        'slug' => 'provider-carot',
        'connection_config' => $connectionConfig,
        'payload_field_mapping' => $payloadFieldMapping,
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Provider Carot')
        ->assertJsonPath('data.slug', 'provider-carot')
        ->assertJsonPath('data.has_connection_config', true);

    expect($created->json('data'))->not->toHaveKey('connection_config');

    $provider = TopupProvider::query()->findOrFail($created->json('data.id'));
    $rawConfig = DB::table($provider->getTable())->where('id', $provider->id)->value('connection_config');

    expect($provider->connection_config)->toBe($connectionConfig)
        ->and($provider->payload_field_mapping)->toBe($payloadFieldMapping)
        ->and($provider->toArray())->not->toHaveKey('connection_config')
        ->and($rawConfig)->not->toContain('very-secret-value')
        ->and($rawConfig)->not->toContain('api.provider.example');

    $shown = $this->actingAs($admin)->getJson("/api/admin-api/topup-providers/{$provider->id}")
        ->assertOk()
        ->assertJsonPath('data.connection_config.base_url', 'https://api.provider.example/v1')
        ->assertJsonPath('data.connection_config.api_key', TopupProvider::SECRET_MASK)
        ->assertJsonPath('data.connection_config.api_secret', TopupProvider::SECRET_MASK)
        ->assertJsonPath('data.connection_config.proxy', TopupProvider::SECRET_MASK)
        ->assertJsonPath('data.connection_config.timeout', 30)
        ->assertJsonPath('data.connection_config.balance_warning_threshold', 1000000)
        ->assertJsonPath('data.connection_config.minimum_profit_percent', 7.5);
    $shown->assertJsonPath('data.payload_field_mapping.services.hso.username', 'user_account');

    expect($shown->getContent())->not->toContain('public-key-123')
        ->not->toContain('very-secret-value')
        ->not->toContain('proxy-pass');

    $this->actingAs($admin)->putJson("/api/admin-api/topup-providers/{$provider->id}", [
        'name' => 'Provider Carot mới',
        'slug' => 'provider-carot',
        'connection_config' => [
            'base_url' => 'https://new.provider.example/v2',
            'api_key' => TopupProvider::SECRET_MASK,
            'api_secret' => TopupProvider::SECRET_MASK,
            'proxy' => TopupProvider::SECRET_MASK,
            'timeout' => 45,
            'balance_warning_threshold' => 2000000,
            'minimum_profit_percent' => 10,
        ],
        'payload_field_mapping' => [
            'default' => ['character' => 'character_name'],
            'services' => [],
        ],
    ])->assertOk();

    expect($provider->refresh()->connection_config)->toBe([
        'base_url' => 'https://new.provider.example/v2',
        'api_key' => 'public-key-123',
        'api_secret' => 'very-secret-value',
        'proxy' => 'http://proxy-user:proxy-pass@proxy.example:8080',
        'timeout' => 45,
        'balance_warning_threshold' => 2000000,
        'minimum_profit_percent' => 10,
    ])->and($provider->payload_field_mapping)->toBe([
        'default' => ['character' => 'character_name'],
        'services' => [],
    ]);

    $this->actingAs($admin)->patchJson("/api/admin-api/topup-providers/{$provider->id}", [
        'name' => 'Provider chỉ đổi tên',
        'slug' => 'provider-carot',
    ])->assertOk();

    expect($provider->refresh()->connection_config['api_secret'])->toBe('very-secret-value');
    expect($provider->payload_field_mapping)->toBe([
        'default' => ['character' => 'character_name'],
        'services' => [],
    ]);

    $auditJson = AdminAuditLog::query()
        ->where('subject_type', TopupProvider::class)
        ->get(['old_values', 'new_values'])
        ->toJson();

    expect($auditJson)->not->toContain('public-key-123')
        ->not->toContain('very-secret-value')
        ->not->toContain('proxy-pass')
        ->not->toContain((string) $rawConfig);
});

test('provider editor appends defaults for new game services without changing saved mappings', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    Game::factory()->create([
        'provider_service_code' => 'HSO',
        'checkout_fields' => [
            ['key' => 'game_account', 'label' => 'Tài khoản', 'placeholder' => '', 'required' => true, 'regex' => ''],
        ],
    ]);
    Game::factory()->create([
        'provider_service_code' => 'AVATAR',
        'checkout_fields' => [
            ['key' => 'character_name', 'label' => 'Tên nhân vật', 'placeholder' => '', 'required' => true, 'regex' => ''],
        ],
    ]);
    Game::factory()->create([
        'provider_service_code' => 'NRO',
        'checkout_fields' => [
            ['key' => 'game_account', 'label' => 'Tài khoản', 'placeholder' => '', 'required' => true, 'regex' => ''],
            ['key' => 'game_character', 'label' => 'Tên nhân vật', 'placeholder' => '', 'required' => false, 'regex' => ''],
        ],
    ]);
    $savedMapping = [
        'default' => ['zone' => 'server_zone'],
        'services' => ['hso' => ['game_account' => 'player_id']],
    ];
    $provider = TopupProvider::factory()->create([
        'type' => TopupProviderType::MerchantPartnerCard,
        'payload_field_mapping' => $savedMapping,
    ]);

    $this->actingAs($admin)
        ->getJson("/api/admin-api/topup-providers/{$provider->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.payload_field_mapping', $savedMapping)
        ->assertJsonPath('data.payload_field_mapping_editor.default.zone', 'server_zone')
        ->assertJsonPath('data.payload_field_mapping_editor.services.hso', ['game_account' => 'player_id'])
        ->assertJsonPath('data.payload_field_mapping_editor.services.avatar', ['character_name' => 'username'])
        ->assertJsonPath('data.payload_field_mapping_editor.services.nro', [
            'game_account' => 'username',
            'character_name' => 'charname',
        ]);

    expect($provider->refresh()->payload_field_mapping)->toBe($savedMapping);
});

test('provider validation rejects unsafe connection config and duplicate slugs', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    TopupProvider::factory()->create(['slug' => 'duplicate-provider']);

    $this->actingAs($admin)->postJson('/api/admin-api/topup-providers', [
        'name' => 'Trùng slug',
        'slug' => 'duplicate-provider',
        'connection_config' => ['base_url' => 'https://provider.example'],
    ])->assertUnprocessable()->assertJsonValidationErrors('slug');

    $this->actingAs($admin)->postJson('/api/admin-api/topup-providers', [
        'name' => 'Private provider',
        'slug' => 'private-provider',
        'connection_config' => ['base_url' => 'http://127.0.0.1/admin'],
    ])->assertUnprocessable()->assertJsonValidationErrors('connection_config');

    $this->actingAs($admin)->postJson('/api/admin-api/topup-providers', [
        'name' => 'Sai phần trăm lợi nhuận',
        'slug' => 'invalid-profit-percent',
        'connection_config' => [
            'base_url' => 'https://provider.example',
            'minimum_profit_percent' => 100,
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors('connection_config');

    $this->actingAs($admin)->postJson('/api/admin-api/topup-providers', [
        'name' => 'Sai cấu trúc',
        'slug' => 'invalid-provider',
        'connection_config' => ['first', 'second'],
    ])->assertUnprocessable()->assertJsonValidationErrors('connection_config');

    $this->actingAs($admin)->postJson('/api/admin-api/topup-providers', [
        'name' => 'Sai ngưỡng cảnh báo',
        'slug' => 'invalid-balance-threshold',
        'connection_config' => [
            'base_url' => 'https://provider.example',
            'balance_warning_threshold' => -1,
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors('connection_config');

    $this->actingAs($admin)->postJson('/api/admin-api/topup-providers', [
        'name' => 'Proxy không hợp lệ',
        'slug' => 'invalid-proxy',
        'connection_config' => [
            'base_url' => 'https://provider.example',
            'proxy' => 'file://proxy.example:8080',
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors('connection_config');

    $this->actingAs($admin)->postJson('/api/admin-api/topup-providers', [
        'name' => 'Mapping trùng field đích',
        'slug' => 'invalid-field-mapping',
        'connection_config' => ['base_url' => 'https://provider.example'],
        'payload_field_mapping' => [
            'default' => ['username' => 'provider_account', 'character' => 'provider_account'],
            'services' => [],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors('payload_field_mapping');

    $this->actingAs($admin)->postJson('/api/admin-api/topup-providers', [
        'name' => 'Mapping ghi đè field hệ thống',
        'slug' => 'reserved-field-mapping',
        'connection_config' => ['base_url' => 'https://provider.example'],
        'payload_field_mapping' => [
            'default' => ['username' => 'server'],
            'services' => [],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors('payload_field_mapping');

    $this->actingAs($admin)->postJson('/api/admin-api/topup-providers', [
        'name' => 'Provider không cần mapping',
        'slug' => 'empty-field-mapping',
        'connection_config' => ['base_url' => 'https://provider.example'],
        'payload_field_mapping' => ['default' => [], 'services' => []],
    ])->assertCreated()
        ->assertJsonPath('data.payload_field_mapping.default', [])
        ->assertJsonPath('data.payload_field_mapping.services', []);
});

test('assigned provider cannot be deleted until packages are detached', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $provider = TopupProvider::factory()->create();
    $package = TopupPackage::factory()->create(['provider_id' => $provider->id]);

    $this->actingAs($admin)->deleteJson("/api/admin-api/topup-providers/{$provider->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('provider');

    expect($provider->fresh())->not->toBeNull()
        ->and($package->refresh()->provider_id)->toBe($provider->id);

    $package->forceFill(['provider_id' => null])->save();

    $this->actingAs($admin)->deleteJson("/api/admin-api/topup-providers/{$provider->id}")
        ->assertOk();

    expect($provider->fresh())->toBeNull();
});

test('admin can create update and delete an empty game with audit logs', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $created = $this->actingAs($admin)->postJson('/api/admin-api/games', [
        'name' => 'Ninja School Online',
        'slug' => 'ninja-school-online',
        'reward_label' => 'Xu',
        'provider_service_code' => 'nso',
        'min_quantity' => 2,
        'max_quantity' => 4,
        'checkout_fields' => [
            ['key' => 'account_id', 'label' => 'ID tài khoản', 'placeholder' => 'Nhập ID', 'required' => true, 'regex' => '^[0-9]{6,12}$'],
            ['key' => 'character_name', 'label' => 'Tên nhân vật', 'placeholder' => null, 'required' => false],
        ],
        'status' => 'active',
        'sort_order' => 1,
    ])->assertCreated()
        ->assertJsonPath('data.provider_service_code', 'nso')
        ->assertJsonPath('data.min_quantity', 2)
        ->assertJsonPath('data.max_quantity', 4);

    $game = Game::query()->findOrFail($created->json('data.id'));

    expect($game->reward_label)->toBe('Xu')
        ->and($game->provider_service_code)->toBe('nso')
        ->and($game->min_quantity)->toBe(2)
        ->and($game->max_quantity)->toBe(4)
        ->and($game->checkout_fields)->toHaveCount(2)
        ->and($game->checkout_fields[0]['key'])->toBe('account_id')
        ->and($game->checkout_fields[0]['regex'])->toBe('^[0-9]{6,12}$');

    $this->actingAs($admin)->patchJson("/api/admin-api/games/{$game->id}", [
        'name' => 'Ninja School',
        'slug' => $game->slug,
        'reward_label' => 'Xu',
        'provider_service_code' => 'nso-v2',
        'min_quantity' => 3,
        'max_quantity' => 5,
        'checkout_fields' => $game->checkout_fields,
        'status' => $game->status,
        'sort_order' => $game->sort_order,
    ])->assertOk()
        ->assertJsonPath('data.provider_service_code', 'nso-v2')
        ->assertJsonPath('data.min_quantity', 3)
        ->assertJsonPath('data.max_quantity', 5);

    $this->actingAs($admin)->deleteJson("/api/admin-api/games/{$game->id}")
        ->assertOk();

    expect($game->fresh())->toBeNull()
        ->and(AdminAuditLog::query()->where('admin_id', $admin->id)->count())->toBe(3);
});

test('game checkout field schema rejects unsafe and ambiguous definitions', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $basePayload = [
        'name' => 'Game schema test',
        'slug' => 'game-schema-test',
        'reward_label' => 'Xu',
        'status' => 'active',
        'sort_order' => 1,
    ];

    $this->actingAs($admin)->postJson('/api/admin-api/games', [
        ...$basePayload,
        'checkout_fields' => [
            ['key' => 'account', 'label' => 'Tài khoản', 'placeholder' => '', 'required' => false],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors('checkout_fields');

    $this->actingAs($admin)->postJson('/api/admin-api/games', [
        ...$basePayload,
        'checkout_fields' => [
            ['key' => 'email', 'label' => 'Ghi đè email', 'placeholder' => '', 'required' => true],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors('checkout_fields.0.key');

    $this->actingAs($admin)->postJson('/api/admin-api/games', [
        ...$basePayload,
        'checkout_fields' => [
            ['key' => 'amount', 'label' => 'Ghi đè số lượng API', 'placeholder' => '', 'required' => true],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors('checkout_fields.0.key');

    $this->actingAs($admin)->postJson('/api/admin-api/games', [
        ...$basePayload,
        'checkout_fields' => [
            ['key' => 'account', 'label' => 'Tài khoản', 'placeholder' => '', 'required' => true, 'regex' => '[a-z'],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors('checkout_fields.0.regex');
});

test('admin can configure text number and select checkout fields', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->postJson('/api/admin-api/games', [
        'name' => 'Game typed fields',
        'slug' => 'game-typed-fields',
        'reward_label' => 'Xu',
        'status' => 'active',
        'sort_order' => 1,
        'checkout_fields' => [
            ['key' => 'account', 'label' => 'Tài khoản', 'placeholder' => '', 'required' => true, 'type' => 'text'],
            [
                'key' => 'level',
                'label' => 'Cấp độ',
                'placeholder' => 'Chọn cấp',
                'required' => true,
                'type' => 'number',
                'min' => 10,
                'max' => 100,
                'step' => 5,
            ],
            [
                'key' => 'region',
                'label' => 'Khu vực',
                'placeholder' => 'Chọn khu vực',
                'required' => true,
                'type' => 'select',
                'options' => [
                    ['value' => 'VN-1', 'text' => 'Việt Nam 1'],
                    ['value' => 'SEA', 'text' => 'Đông Nam Á'],
                ],
            ],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.checkout_fields.0.type', 'text')
        ->assertJsonPath('data.checkout_fields.1.min', 10)
        ->assertJsonPath('data.checkout_fields.1.step', 5)
        ->assertJsonPath('data.checkout_fields.2.options.0.value', 'VN-1')
        ->assertJsonPath('data.checkout_fields.2.options.0.text', 'Việt Nam 1');

    $game = Game::query()->findOrFail($response->json('data.id'));

    expect($game->checkout_fields[0])->toMatchArray(['type' => 'text', 'options' => [], 'min' => null, 'max' => null, 'step' => null])
        ->and($game->checkout_fields[2]['options'])->toHaveCount(2);
});

test('typed checkout field schema rejects invalid options and number limits', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $basePayload = [
        'name' => 'Invalid typed fields',
        'slug' => 'invalid-typed-fields',
        'reward_label' => 'Xu',
        'status' => 'active',
        'sort_order' => 1,
    ];

    $this->actingAs($admin)->postJson('/api/admin-api/games', [
        ...$basePayload,
        'checkout_fields' => [[
            'key' => 'region', 'label' => 'Khu vực', 'placeholder' => '', 'required' => true, 'type' => 'select', 'options' => [],
        ]],
    ])->assertUnprocessable()->assertJsonValidationErrors('checkout_fields.0.options');

    $this->actingAs($admin)->postJson('/api/admin-api/games', [
        ...$basePayload,
        'checkout_fields' => [[
            'key' => 'region',
            'label' => 'Khu vực',
            'placeholder' => '',
            'required' => true,
            'type' => 'select',
            'options' => [['value' => 'VN|1', 'text' => 'Việt Nam']],
        ]],
    ])->assertUnprocessable()->assertJsonValidationErrors('checkout_fields.0.options.0.value');

    $this->actingAs($admin)->postJson('/api/admin-api/games', [
        ...$basePayload,
        'checkout_fields' => [[
            'key' => 'level', 'label' => 'Cấp độ', 'placeholder' => '', 'required' => true, 'type' => 'number', 'min' => 20, 'max' => 10,
        ]],
    ])->assertUnprocessable()->assertJsonValidationErrors('checkout_fields.0.max');
});

test('admin stores provider field names directly in the game checkout schema', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $payload = [
        'name' => 'Game direct provider fields',
        'slug' => 'game-direct-provider-fields',
        'reward_label' => 'Xu',
        'status' => 'active',
        'sort_order' => 1,
        'checkout_fields' => [
            ['key' => 'account', 'label' => 'Email', 'placeholder' => '', 'required' => true],
            ['key' => 'character', 'label' => 'Nhân vật', 'placeholder' => '', 'required' => false],
        ],
        'metadata' => [],
    ];

    $created = $this->actingAs($admin)
        ->postJson('/api/admin-api/games', $payload)
        ->assertCreated()
        ->assertJsonPath('data.checkout_fields.0.key', 'account')
        ->assertJsonPath('data.checkout_fields.1.key', 'character');
    $game = Game::query()->findOrFail($created->json('data.id'));

    $this->actingAs($admin)->postJson('/api/admin-api/game-servers', [
        'game_id' => $game->id,
        'name' => 'Server 16',
        'code' => '16',
        'status' => 'active',
        'sort_order' => 1,
    ])->assertCreated()
        ->assertJsonPath('data.code', '16');

    expect($game->metadata)->toBe([])
        ->and($game->checkout_fields[0]['key'])->toBe('account');
});

test('catalog deletion requires child records to be removed first', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $game = Game::factory()->create();
    $server = GameServer::factory()->for($game)->create();
    $package = TopupPackage::factory()->for($game)->create();

    $this->actingAs($admin)->deleteJson("/api/admin-api/games/{$game->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('game');

    $this->actingAs($admin)->deleteJson("/api/admin-api/game-servers/{$server->id}")
        ->assertOk();

    $this->actingAs($admin)->deleteJson("/api/admin-api/topup-packages/{$package->id}")
        ->assertOk();
    $this->actingAs($admin)->deleteJson("/api/admin-api/games/{$game->id}")
        ->assertOk();

    expect($package->fresh())->toBeNull()
        ->and($server->fresh())->toBeNull()
        ->and($game->fresh())->toBeNull()
        ->and(AdminAuditLog::query()->where('action', 'deleted')->count())->toBe(3);
});

test('catalog records with order history cannot be deleted', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $game = Game::factory()->create();
    $server = GameServer::factory()->for($game)->create();
    $package = TopupPackage::factory()->for($game)->create();
    Order::factory()->create([
        'game_id' => $game->id,
        'game_server_id' => $server->id,
        'topup_package_id' => $package->id,
        'package_name' => $package->name,
    ]);

    $this->actingAs($admin)->deleteJson("/api/admin-api/topup-packages/{$package->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('package');
    $this->actingAs($admin)->deleteJson("/api/admin-api/game-servers/{$server->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('server');
    $this->actingAs($admin)->deleteJson("/api/admin-api/games/{$game->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('game');

    expect($package->fresh())->not->toBeNull()
        ->and($server->fresh())->not->toBeNull()
        ->and($game->fresh())->not->toBeNull();
});

test('admin package pricing stores provider cost and calculates discount on the server', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $game = Game::factory()->create(['provider_service_code' => 'NRO']);
    $provider = TopupProvider::factory()->create();

    $created = $this->actingAs($admin)->postJson('/api/admin-api/topup-packages', [
        'game_id' => $game->id,
        'provider_id' => $provider->id,
        'name' => 'Gói 10.000đ',
        'denomination' => 10000,
        'provider_price' => 8100,
        'original_price' => 10000,
        'price' => 8500,
        'status' => 'active',
        'sort_order' => 1,
    ])->assertCreated()
        ->assertJsonPath('data.provider_price', '8100.00')
        ->assertJsonPath('data.original_price', '10000.00')
        ->assertJsonPath('data.price', '8500.00')
        ->assertJsonPath('data.discount_percent', '15.00')
        ->assertJsonPath('data.provider_id', $provider->id)
        ->assertJsonPath('data.provider_name', $provider->name);

    $package = TopupPackage::query()->findOrFail($created->json('data.id'));

    expect($package->provider_price)->toBe('8100.00')
        ->and($package->original_price)->toBe('10000.00')
        ->and($package->price)->toBe('8500.00')
        ->and($package->providerServiceCode())->toBe('NRO')
        ->and($package->discount_percent)->toBe('15.00');

    expect($created->json('data'))->not->toHaveKeys([
        'provider_service_code',
        'game_server_id',
        'carot_amount',
        'reward_x2_amount',
        'reward_x3_amount',
        'first_topup_reward_amount',
    ]);

    $this->actingAs($admin)
        ->postJson('/api/admin-api/topup-packages', [
            'game_id' => $game->id,
            'name' => 'Gói can thiệp chiết khấu',
            'provider_price' => 8100,
            'original_price' => 10000,
            'price' => 8500,
            'discount_percent' => 99,
            'status' => 'active',
            'sort_order' => 2,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('discount_percent');
});

test('admin package pricing rejects provider cost above sale price and original price below it', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $game = Game::factory()->create();
    $otherGameServer = GameServer::factory()->create();
    $payload = [
        'game_id' => $game->id,
        'name' => 'Gói 10.000đ',
        'provider_price' => 8100,
        'original_price' => 10000,
        'price' => 8500,
        'status' => 'active',
        'sort_order' => 1,
    ];

    $this->actingAs($admin)
        ->postJson('/api/admin-api/topup-packages', [...$payload, 'provider_price' => 9000])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('provider_price');

    $this->actingAs($admin)
        ->postJson('/api/admin-api/topup-packages', [...$payload, 'original_price' => 8100])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('original_price');

    $this->actingAs($admin)
        ->postJson('/api/admin-api/topup-packages', [
            ...$payload,
            'carot_amount' => 13,
            'reward_x2_amount' => 22,
            'reward_x3_amount' => 32,
            'first_topup_reward_amount' => 26,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'carot_amount',
            'reward_x2_amount',
            'reward_x3_amount',
            'first_topup_reward_amount',
        ]);

    $this->actingAs($admin)
        ->postJson('/api/admin-api/topup-packages', [...$payload, 'game_server_id' => $otherGameServer->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('game_server_id');
});
