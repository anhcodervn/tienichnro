<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Features\Admin\Topup\Actions\CancelAndRefundFailedOrderAction;
use App\Models\AdminAuditLog;
use App\Models\Order;
use App\Models\OrderRecipient;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;

test('platform admin can cancel a paid failed topup order and refund its registered email wallet', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $customer = User::factory()->create(['tenant_id' => $admin->tenant_id, 'email' => 'customer@example.com']);
    $order = Order::factory()->create([
        'tenant_id' => $admin->tenant_id,
        'user_id' => null,
        'email' => 'Customer@Example.com',
        'normalized_email' => 'customer@example.com',
        'total_amount' => 450000,
        'payment_status' => PaymentStatus::Paid,
        'order_status' => OrderStatus::Failed,
        'failed_at' => now(),
        'failure_reason' => 'Provider không thể xử lý.',
    ]);
    $recipient = OrderRecipient::factory()->for($order)->create([
        'status' => 'failed',
        'failure_reason' => 'Provider không thể xử lý.',
        'failed_at' => now(),
    ]);
    $wallet = Wallet::query()->where('user_id', $customer->id)->where('type', Wallet::TYPE_MAIN)->firstOrFail();

    $this->actingAs($admin)
        ->putJson("/api/admin-api/orders/{$order->code}", ['action' => 'cancel_refund'])
        ->assertSuccessful()
        ->assertJsonPath('data.payment_status', PaymentStatus::Refunded->value)
        ->assertJsonPath('data.order_status', OrderStatus::Cancelled->value)
        ->assertJsonPath('data.failure_reason', CancelAndRefundFailedOrderAction::REASON)
        ->assertJsonPath('data.can_cancel_refund', false);

    $transaction = WalletTransaction::query()->where([
        'wallet_id' => $wallet->id,
        'type' => 'refund',
        'reference_type' => Order::class,
        'reference_id' => $order->id,
    ])->sole();

    expect((int) $wallet->refresh()->balance)->toBe(450000)
        ->and((int) $transaction->amount)->toBe(450000)
        ->and($transaction->description)->toBe("Hoàn tiền đơn {$order->code}")
        ->and($order->refresh()->payment_status)->toBe(PaymentStatus::Refunded)
        ->and($order->order_status)->toBe(OrderStatus::Cancelled)
        ->and($order->failure_reason)->toBe(CancelAndRefundFailedOrderAction::REASON)
        ->and($recipient->refresh()->status)->toBe('cancelled')
        ->and($recipient->failure_reason)->toBe(CancelAndRefundFailedOrderAction::REASON)
        ->and(AdminAuditLog::query()->where([
            'admin_id' => $admin->id,
            'action' => 'order_cancel_refund',
            'subject_type' => Order::class,
            'subject_id' => $order->id,
        ])->exists())->toBeTrue();
});

test('platform admin cannot cancel and refund when the order email is not registered', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $order = Order::factory()->create([
        'tenant_id' => $admin->tenant_id,
        'email' => 'guest@example.com',
        'normalized_email' => 'guest@example.com',
        'payment_status' => PaymentStatus::Paid,
        'order_status' => OrderStatus::Failed,
        'failed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->putJson("/api/admin-api/orders/{$order->code}", ['action' => 'cancel_refund'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cancel_refund');

    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->order_status)->toBe(OrderStatus::Failed)
        ->and(WalletTransaction::query()->where('reference_type', Order::class)->where('reference_id', $order->id)->exists())->toBeFalse();
});

test('cancel and refund rejects an order that is not both paid and failed', function (PaymentStatus $paymentStatus, OrderStatus $orderStatus): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $customer = User::factory()->create(['tenant_id' => $admin->tenant_id]);
    $order = Order::factory()->for($customer)->create([
        'tenant_id' => $admin->tenant_id,
        'email' => $customer->email,
        'normalized_email' => strtolower($customer->email),
        'payment_status' => $paymentStatus,
        'order_status' => $orderStatus,
    ]);

    $this->actingAs($admin)
        ->putJson("/api/admin-api/orders/{$order->code}", ['action' => 'cancel_refund'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cancel_refund');

    expect($order->refresh()->payment_status)->toBe($paymentStatus)
        ->and($order->order_status)->toBe($orderStatus)
        ->and(WalletTransaction::query()->where('reference_type', Order::class)->where('reference_id', $order->id)->exists())->toBeFalse();
})->with([
    'unpaid failed order' => [PaymentStatus::Pending, OrderStatus::Failed],
    'paid processing order' => [PaymentStatus::Paid, OrderStatus::Processing],
]);

test('cancel and refund never refunds an order twice', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $customer = User::factory()->create(['tenant_id' => $admin->tenant_id]);
    $order = Order::factory()->for($customer)->create([
        'tenant_id' => $admin->tenant_id,
        'email' => $customer->email,
        'normalized_email' => strtolower($customer->email),
        'total_amount' => 125000,
        'payment_status' => PaymentStatus::Paid,
        'order_status' => OrderStatus::Failed,
        'failed_at' => now(),
    ]);
    $wallet = Wallet::query()->where('user_id', $customer->id)->where('type', Wallet::TYPE_MAIN)->firstOrFail();

    $this->actingAs($admin)
        ->putJson("/api/admin-api/orders/{$order->code}", ['action' => 'cancel_refund'])
        ->assertSuccessful();

    $this->actingAs($admin)
        ->putJson("/api/admin-api/orders/{$order->code}", ['action' => 'cancel_refund'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cancel_refund');

    expect((int) $wallet->refresh()->balance)->toBe(125000)
        ->and(WalletTransaction::query()->where([
            'wallet_id' => $wallet->id,
            'type' => 'refund',
            'reference_type' => Order::class,
            'reference_id' => $order->id,
        ])->count())->toBe(1);
});
