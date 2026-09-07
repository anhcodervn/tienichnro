<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Features\Topup\Jobs\ProcessTopupOrder;
use App\Features\Topup\Services\OrderStatusService;
use App\Mail\Orders\OrderCreatedMail;
use App\Models\ConfigRecharge;
use App\Models\Game;
use App\Models\GameServer;
use App\Models\GlobalTopupPackageGameSetting;
use App\Models\Order;
use App\Models\OrderRecipient;
use App\Models\PaymentTransaction;
use App\Models\TopupPackage;
use App\Models\TopupProvider;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Mail::fake();
    Queue::fake();
});

test('guest home renders the purchase layout reward table and seo content without mounting vue', function (): void {
    [$game, $server, $package] = topupCatalog([
        'denomination' => 100000,
        'carot_amount' => 195,
        'reward_x2_amount' => 345,
        'reward_x3_amount' => 495,
        'first_topup_reward_amount' => 390,
        'provider_price' => 76543,
    ]);
    GlobalTopupPackageGameSetting::factory()->for($game)->create([
        'denomination' => 100000,
        'receives' => [
            [
                'code' => 'GM',
                'label' => 'Gem mở',
                'base_amount' => 195,
                'reward_x2_amount' => 345,
                'reward_x3_amount' => 495,
                'first_topup_reward_amount' => 390,
            ],
            [
                'code' => 'GK',
                'label' => 'Gem khóa',
                'base_amount' => 95,
                'reward_x2_amount' => 145,
                'reward_x3_amount' => 195,
                'first_topup_reward_amount' => 190,
            ],
        ],
    ]);
    GlobalTopupPackageGameSetting::factory()->for($game)->create([
        'denomination' => 20000,
        'receives' => [[
            'code' => 'DOC_LAP',
            'label' => 'Thực nhận độc lập',
            'base_amount' => 777,
            'reward_x2_amount' => null,
            'reward_x3_amount' => null,
            'first_topup_reward_amount' => null,
        ]],
    ]);
    $inactivePackage = TopupPackage::factory()->for($game)->inactive()->create(['name' => 'Gói đã tạm dừng']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee($game->name.' - ID: '.$game->id)
        ->assertSee($server->name.' - ID: '.$server->id)
        ->assertSee('data-authenticated="false"', false)
        ->assertDontSee('data-guest-order-history', false)
        ->assertSee('assets/icon/boxicons/fonts/basic/boxicons.min.css', false)
        ->assertSee('bx bx-joystick', false)
        ->assertSee('data-topup-form', false)
        ->assertSee('data-topup-confirmation-modal', false)
        ->assertSee('data-topup-confirmation-checkbox', false)
        ->assertSee('data-confirm-recipients', false)
        ->assertDontSee('data-affiliate-referrer', false)
        ->assertSee('Xác nhận thông tin nạp')
        ->assertSee('Tôi đã kiểm tra kỹ thông tin')
        ->assertSee('data-purchase-tab="single"', false)
        ->assertSee('data-purchase-tab="bulk"', false)
        ->assertSee('data-server-picker="single"', false)
        ->assertSee('data-server-picker="bulk"', false)
        ->assertSeeInOrder([
            'id="topup-server-single"',
            'data-server-picker="single"',
            'required',
        ], false)
        ->assertSee('data-package-button="'.$package->id.'"', false)
        ->assertSee('data-order-total', false)
        ->assertSee('data-summary-reward', false)
        ->assertSee('name="email"', false)
        ->assertSee('CHỌN GÓI NẠP')
        ->assertSee('name="recipient_fields[game_account]"', false)
        ->assertSee('name="bulk_recipients"', false)
        ->assertSee('data-game-reward-tab="'.$game->id.'"', false)
        ->assertSee('data-game-reward="'.$game->id.'"', false)
        ->assertSee($game->name)
        ->assertSee($package->name)
        ->assertSee('90.000đ')
        ->assertSee('Bảng thực nhận theo từng game')
        ->assertSee('100.000đ')
        ->assertSee('20.000đ')
        ->assertSee('Thực nhận độc lập')
        ->assertSee('777')
        ->assertSee('Gem mở')
        ->assertSee('Gem khóa')
        ->assertSee('195')
        ->assertSee('345')
        ->assertSee('495')
        ->assertSee('390')
        ->assertDontSee('76543')
        ->assertDontSee($inactivePackage->name)
        ->assertSee('Cách mua Carot trong 3 bước')
        ->assertDontSee('Lịch sử nạp game gần đây')
        ->assertDontSee('id="app"', false);
});

test('authenticated home keeps order history on its dedicated page', function (): void {
    [$game, $server, $package] = topupCatalog();
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $order = Order::factory()->for($user)->create([
        'game_id' => $game->id,
        'game_server_id' => $server->id,
        'topup_package_id' => $package->id,
        'email' => $user->email,
        'normalized_email' => Str::lower($user->email),
        'code' => 'TOPHOME001',
    ]);
    $otherOrder = Order::factory()->for($otherUser)->create([
        'game_id' => $game->id,
        'game_server_id' => $server->id,
        'topup_package_id' => $package->id,
        'code' => 'TOPPRIVATE1',
    ]);

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('data-authenticated="true"', false)
        ->assertDontSee('name="email"', false)
        ->assertSee('Theo dõi trực tiếp trong lịch sử đơn hàng')
        ->assertDontSee('data-guest-order-history', false)
        ->assertDontSee('Lịch sử nạp game gần đây')
        ->assertDontSee($order->code)
        ->assertDontSee($otherOrder->code)
        ->assertDontSee('Cách mua Carot trong 3 bước')
        ->assertViewMissing('userOrders');
});

test('home automatically selects wallet when its balance covers the current order', function (): void {
    [$game, $server, $package] = topupCatalog();
    $user = User::factory()->create();
    $user->wallet()->update(['balance' => 180000]);

    $this->actingAs($user)
        ->withSession(['_old_input' => [
            'game_id' => $game->id,
            'server_id' => $server->id,
            'package_id' => $package->id,
            'purchase_mode' => 'single',
            'single_quantity' => 2,
        ]])
        ->get(route('home'))
        ->assertOk()
        ->assertSee('name="payment_method"', false)
        ->assertSee('data-payment-explicit="false"', false)
        ->assertSee('value="wallet" data-wallet-balance="180000" selected', false)
        ->assertSee('Số dư ví đủ nên hệ thống đang ưu tiên thanh toán bằng ví. Bạn vẫn có thể chọn ATM.');
});

test('home renders the checkout fields configured for each game', function (): void {
    [$game] = topupCatalog();
    $game->update(['checkout_fields' => [
        ['key' => 'player_id', 'label' => 'ID người chơi', 'placeholder' => 'Nhập ID số', 'required' => true],
        ['key' => 'zone', 'label' => 'Khu vực', 'placeholder' => 'Ví dụ: Asia', 'required' => true],
    ]]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('name="recipient_fields[player_id]"', false)
        ->assertSee('name="recipient_fields[zone]"', false)
        ->assertSee('Mỗi dòng theo thứ tự:', false)
        ->assertSee('ID người chơi | Khu vực | Số lượng thẻ')
        ->assertSee('data-bulk-placeholder="Nhập ID số|Ví dụ: Asia|Số lượng thẻ"', false);
});

function topupCatalog(array $packageAttributes = []): array
{
    $game = Game::factory()->create();
    $server = GameServer::factory()->for($game)->create();
    $package = TopupPackage::factory()->for($game)->create([
        'price' => 90000,
        'original_price' => 100000,
        'min_quantity' => 1,
        'max_quantity' => 10,
        ...$packageAttributes,
    ]);

    return [$game, $server, $package];
}

function checkoutPayload(Game $game, GameServer $server, TopupPackage $package, array $overrides = []): array
{
    return [
        'idempotency_key' => (string) Str::uuid(),
        'game_id' => $game->id,
        'server_id' => $server->id,
        'package_id' => $package->id,
        'purchase_mode' => 'single',
        'single_quantity' => 2,
        'recipient_fields' => [
            'game_account' => 'ninja-player',
            'game_character' => '',
        ],
        'email' => 'Guest@Example.com',
        'payment_method' => PaymentMethod::BankTransfer->value,
        ...$overrides,
    ];
}

test('guest can create a bank transfer order and backend recalculates price', function (): void {
    [$game, $server, $package] = topupCatalog(['provider_price' => 75000]);

    $response = $this->post(route('checkout.store'), checkoutPayload($game, $server, $package, [
        'price' => 1,
        'total_amount' => 1,
    ]));

    $order = Order::query()->sole();

    $response->assertRedirect(route('orders.payment', $order));
    $this->get(route('orders.payment', $order))
        ->assertOk()
        ->assertSee('data-guest-order-code="'.$order->code.'"', false)
        ->assertSee('data-guest-order-created-at=', false)
        ->assertSee('data-order-realtime-channel="orders.', false)
        ->assertDontSee('data-order-status-url=', false);
    expect($order->user_id)->toBeNull()
        ->and($order->normalized_email)->toBe('guest@example.com')
        ->and($order->game_server_id)->toBe($server->id)
        ->and($order->purchase_mode)->toBe('single')
        ->and($order->recipients()->count())->toBe(1)
        ->and($order->recipients()->first()->recipient_data['game_account'])->toBe('ninja-player')
        ->and($order->recipients()->first()->quantity)->toBe(2)
        ->and((int) $order->subtotal)->toBe(200000)
        ->and((int) $order->discount_amount)->toBe(20000)
        ->and((int) $order->total_amount)->toBe(180000)
        ->and((int) $order->sale_unit_price)->toBe(90000)
        ->and((int) $order->provider_unit_cost)->toBe(75000)
        ->and((int) $order->provider_total_cost)->toBe(150000)
        ->and((int) $order->gross_profit)->toBe(30000)
        ->and($order->metadata['package'])->not->toHaveKey('provider_price');

    $package->update(['provider_price' => 88000, 'price' => 99000]);
    expect((int) $order->refresh()->sale_unit_price)->toBe(90000)
        ->and((int) $order->provider_unit_cost)->toBe(75000)
        ->and((int) $order->gross_profit)->toBe(30000);
    Mail::assertQueued(OrderCreatedMail::class, function (OrderCreatedMail $mail) use ($order): bool {
        return $mail->order->is($order)
            && $mail->queue === 'mails'
            && $mail->afterCommit === true;
    });
});

test('checkout snapshots direct provider field names without provider-specific mapping', function (): void {
    [$game, $server, $package] = topupCatalog();
    $provider = TopupProvider::factory()->create([
        'slug' => 'accnrovn',
        'connection_config' => [
            'base_url' => 'https://accnro.vn/api/v1/partner/recharge',
            'partner_id' => 'pk_test',
            'secret_key' => 'sk_test',
        ],
    ]);
    $game->update([
        'provider_service_code' => 'nr',
        'checkout_fields' => [
            ['key' => 'account', 'label' => 'Email/Số điện thoại', 'placeholder' => '', 'required' => true],
        ],
    ]);
    $server->update(['code' => '16']);
    $package->update([
        'provider_id' => $provider->id,
    ]);

    $this->post(route('checkout.store'), checkoutPayload($game, $server, $package, [
        'recipient_fields' => ['account' => 'Anh200@Gmail.COM'],
    ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $order = Order::query()->sole();
    expect($order->checkout_fields_snapshot[0]['key'])->toBe('account')
        ->and($order->recipients()->firstOrFail()->recipient_data)->toBe(['account' => 'anh200@gmail.com'])
        ->and(data_get($order->metadata, 'provider'))->toBe([
            'slug' => 'accnrovn',
            'service_code' => 'nr',
            'server_code' => '16',
        ]);
});

test('guest order stays unclaimed when its email belongs to an existing user', function (): void {
    [$game, $server, $package] = topupCatalog();
    $existingUser = User::factory()->create(['email' => 'Existing@Example.com']);

    $this->post(route('checkout.store'), checkoutPayload($game, $server, $package, [
        'email' => 'existing@example.com',
    ]))->assertRedirect();

    $order = Order::query()->sole();

    expect($order->user_id)->toBeNull()
        ->and($order->normalized_email)->toBe('existing@example.com')
        ->and(User::query()->count())->toBe(1)
        ->and(User::query()->sole()->is($existingUser))->toBeTrue();
});

test('bank transfer order creates one shared payment request through the configured gateway', function (): void {
    [$game, $server, $package] = topupCatalog();
    $config = ConfigRecharge::query()->create([
        'provider' => 'apibankvn_api',
        'bank_name' => 'MBBank',
        'account_name' => 'NGUYEN VAN A',
        'account_number' => '0123456789',
        'qr_template' => 'https://img.vietqr.io/image/{bank_code}-{account_number}-compact2.png?amount={amount}&addInfo={nd}',
        'transfer_prefix' => 'NAP',
        'api_base_url' => 'https://apibankvn.com',
        'api_key' => 'private-api-key',
        'api_secret' => 'private-api-secret',
        'webhook_secret' => 'private-webhook-secret',
        'api_bank_id' => 99,
        'is_active' => true,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://apibankvn.com/api/v1/recharge-orders' => Http::response([
            'status' => true,
            'message' => 'Created',
            'data' => [
                'order' => [
                    'order_code' => 'ABV-TOPUP-001',
                    'bank_name' => 'MBBank',
                    'account_number' => '0123456789',
                    'account_name' => 'NGUYEN VAN A',
                    'status' => 'pending',
                    'expires_at' => now()->addHour()->toISOString(),
                ],
            ],
        ], 201),
    ]);

    $response = $this->post(route('checkout.store'), checkoutPayload($game, $server, $package));
    $order = Order::query()->sole();
    $paymentTransaction = PaymentTransaction::query()->sole();

    $response->assertRedirect(route('orders.payment', $order));
    expect($paymentTransaction->order_id)->toBe($order->id)
        ->and($paymentTransaction->transaction_code)->toBe($order->code)
        ->and($paymentTransaction->content)->toMatch('/^NAP[A-Z0-9]{7}$/')
        ->and(Str::length($paymentTransaction->content))->toBe(10)
        ->and($paymentTransaction->transfer_reference)->toBe($paymentTransaction->content)
        ->and($paymentTransaction->raw_data['remote_order_code'])->toBe('ABV-TOPUP-001')
        ->and($paymentTransaction->raw_data['recharge_config_id'])->toBe($config->id)
        ->and($paymentTransaction->raw_data['qr_url'])->not->toContain('{bank_code}')
        ->and($paymentTransaction->raw_data['qr_url'])->toContain('MBBank-0123456789-compact2.png');

    $this->get(route('orders.payment', $order))
        ->assertSuccessful()
        ->assertSee('MBBank')
        ->assertSee('0123456789')
        ->assertSee($paymentTransaction->content)
        ->assertDontSee('{bank_code}', false)
        ->assertSee('data-order-realtime-channel="orders.', false)
        ->assertDontSee('private-api-key')
        ->assertDontSee('private-api-secret')
        ->assertDontSee('apibankvn');

    expect(PaymentTransaction::query()->count())->toBe(1);
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://apibankvn.com/api/v1/recharge-orders'
        && $request->data()['client_order_code'] === $order->code
        && $request->data()['transfer_prefix'] === 'NAP'
        && $request->data()['transfer_content'] === $paymentTransaction->content
        && $request->data()['amount'] === 180000);
});

test('payment page rebuilds a stored QR URL that still contains a template placeholder', function (): void {
    [$game, $server, $package] = topupCatalog();
    $user = User::factory()->create();
    $config = ConfigRecharge::query()->create([
        'provider' => 'apibankvn_api',
        'bank_name' => 'MBBank',
        'account_name' => 'NGUYEN VAN A',
        'account_number' => '0123456789',
        'qr_template' => 'https://img.vietqr.io/image/{bank_code}-{account_number}-compact2.png?amount={amount}&addInfo={nd}',
        'transfer_prefix' => 'NAP',
        'api_base_url' => 'https://apibankvn.com',
        'api_key' => 'private-api-key',
        'api_secret' => 'private-api-secret',
        'webhook_secret' => 'private-webhook-secret',
        'api_bank_id' => 99,
        'is_active' => true,
    ]);
    $order = Order::factory()->for($user)->forServer($server)->create([
        'game_id' => $game->id,
        'topup_package_id' => $package->id,
        'payment_method' => PaymentMethod::BankTransfer,
        'payment_status' => PaymentStatus::Pending,
        'order_status' => OrderStatus::Pending,
        'total_amount' => 180000,
    ]);
    PaymentTransaction::query()->create([
        'user_id' => $user->id,
        'order_id' => $order->id,
        'bank_code' => 'MBBank',
        'account_number' => '0123456789',
        'transaction_code' => $order->code,
        'amount' => 180000,
        'content' => 'NAP '.$order->code,
        'status' => 'pending',
        'raw_data' => [
            'provider' => 'apibankvn_api',
            'recharge_config_id' => $config->id,
            'account_name' => 'NGUYEN VAN A',
            'qr_url' => 'https://img.vietqr.io/image/{bank_code}-0123456789-compact2.png?amount=180000&addInfo=NAP%20'.$order->code,
            'gateway_unavailable' => true,
        ],
    ]);

    $this->actingAs($user)
        ->get(route('orders.payment', $order))
        ->assertSuccessful()
        ->assertSee('https://img.vietqr.io/image/MBBank-0123456789-compact2.png', false)
        ->assertDontSee('{bank_code}', false);
});

test('order detail presents status progress recipients and payment summary', function (): void {
    [$game, $server, $package] = topupCatalog();
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->forServer($server)->create([
        'game_id' => $game->id,
        'topup_package_id' => $package->id,
        'package_name' => $package->name,
        'checkout_fields_snapshot' => $game->checkoutFields(),
        'quantity' => 2,
        'subtotal' => 200000,
        'discount_amount' => 20000,
        'total_amount' => 180000,
        'payment_method' => PaymentMethod::BankTransfer,
        'payment_status' => PaymentStatus::Pending,
        'order_status' => OrderStatus::Pending,
    ]);
    OrderRecipient::factory()->for($order)->create([
        'recipient_data' => ['game_account' => 'ninja-player', 'game_character' => 'Ninja Hero'],
        'quantity' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('Chi tiết đơn hàng')
        ->assertSee('Tiến trình đơn hàng')
        ->assertSee('Thông tin nhận hàng')
        ->assertSee('Tóm tắt thanh toán')
        ->assertSee('data-copy="'.$order->code.'"', false)
        ->assertSee($game->name)
        ->assertSee($server->name)
        ->assertSee('ninja-player')
        ->assertSee('Ninja Hero')
        ->assertSee('180.000đ')
        ->assertSee('Thanh toán đơn hàng')
        ->assertSee('lg:sticky lg:top-24', false)
        ->assertSee('sm:hidden', false)
        ->assertSee('home-table-scroll hidden sm:block', false)
        ->assertSee('data-order-realtime-channel="orders.', false)
        ->assertSee('data-order-realtime-connection', false);
});

test('order detail supports legacy recipients and prioritizes failure support', function (): void {
    [$game, $server, $package] = topupCatalog();
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->forServer($server)->create([
        'game_id' => $game->id,
        'topup_package_id' => $package->id,
        'game_account' => 'legacy-player',
        'game_character' => 'Legacy Hero',
        'payment_method' => PaymentMethod::BankTransfer,
        'payment_status' => PaymentStatus::Pending,
        'order_status' => OrderStatus::Failed,
        'failed_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('legacy-player')
        ->assertSee('Legacy Hero')
        ->assertSee('Đơn hàng chưa thể hoàn tất.')
        ->assertSee('Liên hệ hỗ trợ')
        ->assertDontSee('Thanh toán đơn hàng');
});

test('bulk checkout derives quantity and total from recipient card quantities', function (): void {
    [$game, $server, $package] = topupCatalog();

    $response = $this->post(route('checkout.store'), checkoutPayload($game, $server, $package, [
        'purchase_mode' => 'bulk',
        'bulk_recipients' => "Account-1|Hero-1|2\r\n\r\nACCOUNT-2|HERO-2|3\nAccount-3|1",
        'quantity' => 1,
        'single_quantity' => 1,
        'recipient_fields' => ['game_account' => 'forged-account'],
    ]));

    $order = Order::query()->sole();

    $response->assertRedirect(route('orders.payment', $order));
    expect($order->purchase_mode)->toBe('bulk')
        ->and($order->quantity)->toBe(6)
        ->and((int) $order->subtotal)->toBe(600000)
        ->and((int) $order->discount_amount)->toBe(60000)
        ->and((int) $order->total_amount)->toBe(540000)
        ->and($order->checkout_fields_snapshot)->toHaveCount(2)
        ->and($order->recipients()->count())->toBe(3)
        ->and($order->recipients()->orderBy('position')->pluck('quantity')->all())->toBe([2, 3, 1])
        ->and($order->recipients()->orderBy('position')->get()->pluck('recipient_data')->all())->toBe([
            ['game_account' => 'account-1', 'game_character' => 'hero-1'],
            ['game_account' => 'account-2', 'game_character' => 'hero-2'],
            ['game_account' => 'account-3', 'game_character' => ''],
        ]);

    $mailHtml = (new OrderCreatedMail($order->fresh()))->render();
    expect($mailHtml)->toContain('3 tài khoản')->toContain('× 6');
});

test('bulk checkout rejects malformed rows and rolls back the order', function (): void {
    [$game, $server, $package] = topupCatalog();

    $this->from(route('topup.game', $game))
        ->post(route('checkout.store'), checkoutPayload($game, $server, $package, [
            'purchase_mode' => 'bulk',
            'bulk_recipients' => "valid-account|hero|1\n|missing-account|1",
        ]))
        ->assertRedirect(route('topup.game', $game))
        ->assertSessionHasErrors('bulk_recipients');

    expect(Order::query()->count())->toBe(0);
});

test('bulk checkout rejects an account line without the quantity delimiter', function (): void {
    [$game, $server, $package] = topupCatalog();

    $this->from(route('topup.game', $game))
        ->post(route('checkout.store'), checkoutPayload($game, $server, $package, [
            'purchase_mode' => 'bulk',
            'bulk_recipients' => "valid-account|hero|2\naccount-without-quantity",
        ]))
        ->assertRedirect(route('topup.game', $game))
        ->assertSessionHasErrors([
            'bulk_recipients' => 'Tài khoản account-without-quantity định dạng không hợp lệ. Vui lòng nhập đúng định dạng param|số lượng.',
        ]);

    expect(Order::query()->count())->toBe(0)
        ->and(OrderRecipient::query()->count())->toBe(0);
});

test('bulk checkout rejects an invalid card quantity', function (string $quantity): void {
    [$game, $server, $package] = topupCatalog();

    $this->from(route('topup.game', $game))
        ->post(route('checkout.store'), checkoutPayload($game, $server, $package, [
            'purchase_mode' => 'bulk',
            'bulk_recipients' => "account|hero|{$quantity}",
        ]))
        ->assertRedirect(route('topup.game', $game))
        ->assertSessionHasErrors('bulk_recipients');

    expect(Order::query()->count())->toBe(0);
})->with(['zero' => '0', 'negative' => '-1', 'decimal' => '1.5', 'text' => 'abc', 'over per-account limit' => '11']);

test('bulk checkout applies the package limit to each account instead of the summed quantity', function (): void {
    [$game, $server, $package] = topupCatalog();

    $response = $this->from(route('topup.game', $game))
        ->post(route('checkout.store'), checkoutPayload($game, $server, $package, [
            'purchase_mode' => 'bulk',
            'bulk_recipients' => "account-1|hero-1|6\naccount-2|hero-2|5",
        ]));

    $order = Order::query()->sole();

    $response->assertRedirect(route('orders.payment', $order));
    expect($order->quantity)->toBe(11)
        ->and($order->recipients()->orderBy('position')->pluck('quantity')->all())->toBe([6, 5])
        ->and((int) $order->total_amount)->toBe(990000);
});

test('recipient quantity backfill preserves historical single and bulk order semantics', function (): void {
    $singleOrder = Order::factory()->create(['purchase_mode' => 'single', 'quantity' => 4]);
    $singleRecipient = OrderRecipient::factory()->for($singleOrder)->create(['quantity' => 1]);
    $bulkOrder = Order::factory()->create(['purchase_mode' => 'bulk', 'quantity' => 2]);
    $bulkRecipient = OrderRecipient::factory()->for($bulkOrder)->create(['quantity' => 1]);

    $migration = require database_path('migrations/2026_08_21_112006_backfill_order_recipient_quantities.php');
    $migration->up();

    expect($singleRecipient->refresh()->quantity)->toBe(4)
        ->and($bulkRecipient->refresh()->quantity)->toBe(1);
});

test('single checkout validates and stores the selected games custom schema', function (): void {
    [$game, $server, $package] = topupCatalog();
    $game->update(['checkout_fields' => [
        ['key' => 'player_id', 'label' => 'ID người chơi', 'placeholder' => '', 'required' => true],
        ['key' => 'zone', 'label' => 'Khu vực', 'placeholder' => '', 'required' => true],
    ]]);

    $this->post(route('checkout.store'), checkoutPayload($game, $server, $package, [
        'single_quantity' => 1,
        'recipient_fields' => ['player_id' => '123456', 'zone' => 'Asia'],
    ]))->assertRedirect();

    $order = Order::query()->sole();
    expect($order->game_account)->toBe('123456')
        ->and($order->recipients()->sole()->recipient_data)->toBe([
            'player_id' => '123456',
            'zone' => 'asia',
        ]);
});

test('order transitions keep recipient processing statuses in sync', function (): void {
    $order = Order::factory()->create();
    $recipient = OrderRecipient::factory()->for($order)->create();
    $statusService = app(OrderStatusService::class);

    $statusService->transition($order, OrderStatus::Processing);
    expect($recipient->refresh()->status)->toBe('processing');

    $statusService->transition($order->refresh(), OrderStatus::Completed);
    expect($recipient->refresh()->status)->toBe('completed');
});

test('guest email is required and disabled packages are rejected', function (): void {
    [$game, $server, $package] = topupCatalog(['status' => 'inactive']);

    $this->from(route('topup.index'))
        ->post(route('checkout.store'), checkoutPayload($game, $server, $package, ['email' => '']))
        ->assertRedirect(route('topup.index'))
        ->assertSessionHasErrors('email');

    $this->from(route('topup.index'))
        ->post(route('checkout.store'), checkoutPayload($game, $server, $package))
        ->assertRedirect(route('topup.index'))
        ->assertSessionHasErrors('package_id');
});

test('authenticated checkout does not require an email field and uses the account email', function (): void {
    [$game, $server, $package] = topupCatalog();
    $user = User::factory()->create(['email' => 'Account@Example.com']);
    $payload = checkoutPayload($game, $server, $package, [
        'email' => 'forged@example.com',
    ]);

    unset($payload['email']);

    $this->actingAs($user)
        ->post(route('checkout.store'), $payload)
        ->assertRedirect();

    $order = Order::query()->sole();

    expect($order->user_id)->toBe($user->id)
        ->and($order->email)->toBe('Account@Example.com')
        ->and($order->normalized_email)->toBe('account@example.com');
});

test('checkout requires an active server belonging to the selected game', function (): void {
    [$game, $server, $package] = topupCatalog();
    $sameGameServer = GameServer::factory()->for($game)->create();
    $otherGame = Game::factory()->create();
    $otherServer = GameServer::factory()->for($otherGame)->create();
    $missingServerPayload = checkoutPayload($game, $server, $package);
    unset($missingServerPayload['server_id']);

    $this->from(route('topup.index'))
        ->post(route('checkout.store'), $missingServerPayload)
        ->assertRedirect(route('topup.index'))
        ->assertSessionHasErrors('server_id');

    $this->from(route('topup.index'))
        ->post(route('checkout.store'), checkoutPayload($game, $server, $package, [
            'server_id' => $otherServer->id,
        ]))
        ->assertRedirect(route('topup.index'))
        ->assertSessionHasErrors('server_id');

    $this->from(route('topup.index'))
        ->post(route('checkout.store'), checkoutPayload($game, $server, $package, [
            'server_id' => $sameGameServer->id,
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $server->update(['status' => 'inactive']);

    $this->from(route('topup.index'))
        ->post(route('checkout.store'), checkoutPayload($game, $server, $package))
        ->assertRedirect(route('topup.index'))
        ->assertSessionHasErrors('server_id');

    expect(Order::query()->count())->toBe(1)
        ->and(Order::query()->sole()->game_server_id)->toBe($sameGameServer->id);
});

test('guest checkout always falls back to bank transfer', function (): void {
    [$game, $server, $package] = topupCatalog();

    $response = $this->post(route('checkout.store'), checkoutPayload($game, $server, $package, [
        'payment_method' => PaymentMethod::Wallet->value,
    ]));

    $order = Order::query()->sole();

    $response->assertRedirect(route('orders.payment', $order));
    expect($order->payment_method)->toBe(PaymentMethod::BankTransfer)
        ->and($order->payment_status)->toBe(PaymentStatus::Pending);
    Queue::assertNotPushed(ProcessTopupOrder::class);
});

test('sufficient wallet balance is selected and deducted once for duplicate request', function (): void {
    [$game, $server, $package] = topupCatalog();
    $user = User::factory()->create();
    $user->wallet()->update(['balance' => 180000]);
    $payload = checkoutPayload($game, $server, $package, [
        'email' => $user->email,
        'payment_method' => PaymentMethod::Wallet->value,
    ]);

    $this->actingAs($user)->post(route('checkout.store'), $payload)->assertRedirect();
    $this->actingAs($user)->post(route('checkout.store'), $payload)->assertRedirect();

    expect(Order::query()->count())->toBe(1)
        ->and(WalletTransaction::query()->where('idempotency_key', $payload['idempotency_key'])->count())->toBe(1)
        ->and((int) $user->wallet()->value('balance'))->toBe(0)
        ->and(Order::query()->sole()->payment_method)->toBe(PaymentMethod::Wallet)
        ->and(Order::query()->sole()->payment_status)->toBe(PaymentStatus::Paid);
    Queue::assertPushed(ProcessTopupOrder::class, 1);
});

test('user with sufficient wallet balance can still choose bank transfer', function (): void {
    [$game, $server, $package] = topupCatalog();
    $user = User::factory()->create();
    $user->wallet()->update(['balance' => 500000]);

    $response = $this->actingAs($user)->post(route('checkout.store'), checkoutPayload($game, $server, $package, [
        'email' => $user->email,
        'payment_method' => PaymentMethod::BankTransfer->value,
    ]));

    $order = Order::query()->sole();

    $response->assertRedirect(route('orders.payment', $order));
    $this->actingAs($user)
        ->get(route('orders.payment', $order))
        ->assertOk()
        ->assertDontSee('data-guest-order-code=', false);
    expect($order->payment_method)->toBe(PaymentMethod::BankTransfer)
        ->and($order->payment_status)->toBe(PaymentStatus::Pending)
        ->and((int) $user->wallet()->value('balance'))->toBe(500000)
        ->and(WalletTransaction::query()->count())->toBe(0);
    Queue::assertNotPushed(ProcessTopupOrder::class);
});

test('wallet checkout rejects an idempotency key already used by another ledger operation', function (): void {
    [$game, $server, $package] = topupCatalog();
    $user = User::factory()->create();
    $user->wallet()->update(['balance' => 180000]);
    $payload = checkoutPayload($game, $server, $package, [
        'email' => $user->email,
        'payment_method' => PaymentMethod::Wallet->value,
    ]);
    $wallet = $user->wallet()->firstOrFail();

    WalletTransaction::query()->create([
        'wallet_id' => $wallet->id,
        'type' => 'credit',
        'amount' => 1,
        'balance_before' => 180000,
        'balance_after' => 180000,
        'reference_type' => User::class,
        'reference_id' => $user->id,
        'idempotency_key' => $payload['idempotency_key'],
        'description' => 'Giao dịch không liên quan',
        'status' => 'success',
    ]);

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($user)->post(route('checkout.store'), $payload))
        ->toThrow(ApiException::class);
    expect(Order::query()->count())->toBe(0)
        ->and((int) $user->wallet()->value('balance'))->toBe(180000)
        ->and(WalletTransaction::query()->count())->toBe(1);
});

test('another authenticated user cannot replay an existing users checkout key', function (): void {
    [$game, $server, $package] = topupCatalog();
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $payload = checkoutPayload($game, $server, $package, ['email' => $owner->email]);

    Order::factory()->for($owner)->create([
        'idempotency_key' => $payload['idempotency_key'],
        'email' => $owner->email,
        'normalized_email' => Str::lower($owner->email),
        'game_id' => $game->id,
        'game_server_id' => $server->id,
        'topup_package_id' => $package->id,
    ]);

    $this->actingAs($attacker)
        ->from(route('topup.index'))
        ->post(route('checkout.store'), $payload)
        ->assertRedirect(route('topup.index'))
        ->assertSessionHasErrors('idempotency_key');
});

test('insufficient wallet balance falls back to bank transfer without a debit', function (): void {
    [$game, $server, $package] = topupCatalog();
    $user = User::factory()->create();
    $user->wallet()->update(['balance' => 1000]);
    $response = $this->actingAs($user)->post(route('checkout.store'), checkoutPayload($game, $server, $package, [
        'email' => $user->email,
        'payment_method' => PaymentMethod::Wallet->value,
    ]));

    $order = Order::query()->sole();

    $response->assertRedirect(route('orders.payment', $order));
    expect($order->payment_method)->toBe(PaymentMethod::BankTransfer)
        ->and($order->payment_status)->toBe(PaymentStatus::Pending)
        ->and((int) $user->wallet()->value('balance'))->toBe(1000)
        ->and(WalletTransaction::query()->count())->toBe(0);
    Queue::assertNotPushed(ProcessTopupOrder::class);
});
