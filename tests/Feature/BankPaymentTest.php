<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Features\Topup\Jobs\ProcessTopupOrder;
use App\Features\Topup\Services\Payments\BankPaymentService;
use App\Features\Topup\Services\TopupService;
use App\Mail\Orders\PaymentReceivedMail;
use App\Models\Game;
use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Mail::fake();
    Queue::fake();
});

test('bank payment uppercases callback content and duplicate provider transaction is idempotent', function (): void {
    $game = Game::factory()->create();
    $order = Order::factory()->create([
        'game_id' => $game->id,
        'topup_package_id' => null,
        'total_amount' => 450000,
    ]);
    $expectedContent = 'NAPABC12345';
    PaymentTransaction::query()->create([
        'user_id' => $order->user_id,
        'order_id' => $order->id,
        'transaction_code' => $order->code,
        'amount' => 450000,
        'content' => $expectedContent,
        'transfer_reference' => $expectedContent,
        'status' => 'pending',
    ]);
    $payload = [
        'transaction_id' => 'BANK-UNIQUE-001',
        'transfer_content' => Str::lower($expectedContent),
        'amount' => '450000.00',
        'bank_name' => 'VCB',
    ];

    $service = app(BankPaymentService::class);
    $service->match($payload, $payload);
    $service->match($payload, $payload);

    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and(PaymentTransaction::query()->count())->toBe(1)
        ->and(PaymentTransaction::query()->sole()->content)->toBe($expectedContent)
        ->and(PaymentTransaction::query()->sole()->transfer_reference)->toBe($expectedContent)
        ->and(data_get(PaymentTransaction::query()->sole()->raw_data, 'received_content'))->toBe(Str::lower($expectedContent));
    Mail::assertQueued(PaymentReceivedMail::class, function (PaymentReceivedMail $mail) use ($order): bool {
        return $mail->order->is($order)
            && $mail->queue === 'mails'
            && $mail->afterCommit === true;
    });
    Queue::assertPushed(ProcessTopupOrder::class, 1);
});

test('bank callback cannot bypass a wrong transfer reference with the client order code', function (): void {
    $order = Order::factory()->create([
        'game_id' => Game::factory(),
        'topup_package_id' => null,
        'total_amount' => 450000,
    ]);
    $preparedTransaction = PaymentTransaction::query()->create([
        'user_id' => $order->user_id,
        'order_id' => $order->id,
        'transaction_code' => $order->code,
        'amount' => 450000,
        'content' => 'NAPRIGHT123',
        'transfer_reference' => 'NAPRIGHT123',
        'status' => 'pending',
    ]);
    $payload = [
        'transaction_id' => 'BANK-WRONG-CONTENT',
        'client_order_code' => $order->code,
        'transfer_content' => 'napwrong123',
        'amount' => '450000.00',
    ];

    $matchedTransaction = app(BankPaymentService::class)->match($payload, $payload);

    expect($matchedTransaction)->toBeNull()
        ->and($preparedTransaction->refresh()->status)->toBe('pending')
        ->and($preparedTransaction->provider_transaction_id)->toBeNull()
        ->and($order->refresh()->payment_status)->toBe(PaymentStatus::Pending);
    Mail::assertNothingQueued();
    Queue::assertNotPushed(ProcessTopupOrder::class);
});

test('bank callback rejects the correct transfer reference with a wrong amount', function (): void {
    $order = Order::factory()->create([
        'game_id' => Game::factory(),
        'topup_package_id' => null,
        'total_amount' => 450000,
    ]);
    $preparedTransaction = PaymentTransaction::query()->create([
        'user_id' => $order->user_id,
        'order_id' => $order->id,
        'transaction_code' => $order->code,
        'amount' => 450000,
        'content' => 'NAPAMOUNT01',
        'transfer_reference' => 'NAPAMOUNT01',
        'status' => 'pending',
    ]);
    $payload = [
        'transaction_id' => 'BANK-WRONG-AMOUNT',
        'transfer_content' => 'napamount01',
        'amount' => '449999.00',
    ];

    expect(fn () => app(BankPaymentService::class)->match($payload, $payload))
        ->toThrow(ApiException::class);
    expect($preparedTransaction->refresh()->status)->toBe('pending')
        ->and($preparedTransaction->provider_transaction_id)->toBeNull()
        ->and($order->refresh()->payment_status)->toBe(PaymentStatus::Pending);
    Mail::assertNothingQueued();
    Queue::assertNotPushed(ProcessTopupOrder::class);
});

test('bank callback completes the prepared order transaction instead of creating a duplicate', function (): void {
    $game = Game::factory()->create();
    $order = Order::factory()->create([
        'game_id' => $game->id,
        'topup_package_id' => null,
        'total_amount' => 450000,
    ]);
    $preparedTransaction = PaymentTransaction::query()->create([
        'user_id' => $order->user_id,
        'order_id' => $order->id,
        'bank_code' => 'MBBank',
        'account_number' => '0123456789',
        'transaction_code' => $order->code,
        'amount' => 450000,
        'content' => 'NAP '.$order->code,
        'raw_data' => ['provider' => 'apibankvn_api', 'remote_order_code' => 'REMOTE-001'],
        'status' => 'pending',
    ]);
    $payload = [
        'transaction_id' => 'BANK-UNIQUE-PREPARED',
        'transfer_content' => 'NAP '.$order->code,
        'amount' => '450000.00',
        'bank_name' => 'MBBank',
    ];

    app(BankPaymentService::class)->match($payload, $payload);

    expect(PaymentTransaction::query()->count())->toBe(1)
        ->and($preparedTransaction->refresh()->status)->toBe('success')
        ->and($preparedTransaction->provider_transaction_id)->toBe('BANK-UNIQUE-PREPARED')
        ->and($preparedTransaction->transaction_code)->toBe($order->code)
        ->and($order->refresh()->payment_status)->toBe(PaymentStatus::Paid);
});

test('completed order cannot be processed twice', function (): void {
    $game = Game::factory()->create();
    $order = Order::factory()->create([
        'game_id' => $game->id,
        'topup_package_id' => null,
        'payment_status' => PaymentStatus::Paid,
        'order_status' => OrderStatus::Completed,
        'completed_at' => now(),
    ]);

    app(TopupService::class)->process($order->id);
    app(TopupService::class)->process($order->id);

    expect($order->refresh()->order_status)->toBe(OrderStatus::Completed)
        ->and($order->provider_reference)->toBeNull();
});
