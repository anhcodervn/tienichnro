<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Features\Admin\RechargeConfig\Requests\UpdateRechargeConfigRequest;
use App\Features\Client\Wallet\Services\WalletDepositService;
use App\Features\Recharge\Services\BankTransferContentService;
use App\Features\Topup\Jobs\ProcessTopupOrder;
use App\Features\Topup\Services\Payments\BankPaymentService;
use App\Features\Topup\Services\Payments\OrderBankPaymentService;
use App\Mail\Orders\PaymentReceivedMail;
use App\Models\ConfigRecharge;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;

function sharedTransferContentConfig(array $overrides = []): ConfigRecharge
{
    return ConfigRecharge::query()->create(array_merge([
        'provider' => 'manual',
        'bank_name' => 'MBBank',
        'account_name' => 'NGUYEN VAN A',
        'account_number' => '0123456789',
        'qr_template' => 'https://img.vietqr.io/image/{bank_code}-{account_number}-compact2.png?amount={amount}&addInfo={nd}',
        'transfer_prefix' => 'nap',
        'is_active' => true,
    ], $overrides));
}

beforeEach(function (): void {
    Mail::fake();
    Queue::fake();
});

test('guest orders and wallet deposits use the same compact transfer content format', function (): void {
    $config = sharedTransferContentConfig();
    $order = Order::factory()->create([
        'user_id' => null,
        'payment_method' => PaymentMethod::BankTransfer,
        'total_amount' => 450000,
    ]);
    $user = User::factory()->create();

    $orderPayment = app(OrderBankPaymentService::class)->prepare($order);
    $walletDeposit = app(WalletDepositService::class)->createRequest($user, 200000, $config->id);

    expect($orderPayment)->toBeInstanceOf(PaymentTransaction::class)
        ->and($orderPayment->content)->toMatch('/^NAP[A-Z0-9]{8}$/')
        ->and($orderPayment->transfer_reference)->toBe($orderPayment->content)
        ->and($walletDeposit->content)->toMatch('/^NAP[A-Z0-9]{8}$/')
        ->and($walletDeposit->transfer_reference)->toBe($walletDeposit->content)
        ->and($walletDeposit->content)->not->toBe($orderPayment->content)
        ->and($orderPayment->content)->not->toContain(' ')
        ->and($walletDeposit->content)->not->toContain(' ');
});

test('bank callback matches a compact order reference embedded in the description', function (): void {
    sharedTransferContentConfig();
    $order = Order::factory()->create([
        'user_id' => null,
        'payment_method' => PaymentMethod::BankTransfer,
        'total_amount' => 450000,
    ]);
    $preparedTransaction = app(OrderBankPaymentService::class)->prepare($order);
    $expectedContent = $preparedTransaction->content;
    $payload = [
        'transaction_id' => 'BANK-COMPACT-REFERENCE-001',
        'transaction_description' => 'MBVCB.'.$expectedContent.'.THANH TOAN',
        'amount' => '450000.00',
        'bank_name' => 'MBBank',
    ];

    $matchedTransaction = app(BankPaymentService::class)->match($payload, $payload);

    expect($matchedTransaction?->is($preparedTransaction))->toBeTrue()
        ->and($preparedTransaction->refresh()->status)->toBe('success')
        ->and($preparedTransaction->content)->toBe($expectedContent)
        ->and($order->refresh()->payment_status)->toBe(PaymentStatus::Paid);
    Mail::assertQueued(PaymentReceivedMail::class);
    Queue::assertPushed(ProcessTopupOrder::class, 1);
});

test('transfer reference suffix only accepts lengths from six to eight characters', function (): void {
    $config = sharedTransferContentConfig();
    $service = app(BankTransferContentService::class);

    expect($service->generate($config, 6))->toMatch('/^NAP[A-Z0-9]{6}$/')
        ->and($service->generate($config, 8))->toMatch('/^NAP[A-Z0-9]{8}$/');

    expect(fn () => $service->generate($config, 5))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->generate($config, 9))->toThrow(InvalidArgumentException::class);
});

test('recharge prefix only accepts letters and numbers', function (): void {
    $rules = (new UpdateRechargeConfigRequest)->rules()['transfer_prefix'];

    expect(Validator::make(['transfer_prefix' => 'NAPCAROT'], ['transfer_prefix' => $rules])->passes())->toBeTrue()
        ->and(Validator::make(['transfer_prefix' => 'NAP-CAROT'], ['transfer_prefix' => $rules])->fails())->toBeTrue()
        ->and(Validator::make(['transfer_prefix' => 'NAP CAROT'], ['transfer_prefix' => $rules])->fails())->toBeTrue();
});
