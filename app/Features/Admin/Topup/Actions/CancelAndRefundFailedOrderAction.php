<?php

namespace App\Features\Admin\Topup\Actions;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Features\Client\Wallet\Services\WalletService;
use App\Features\Topup\Services\OrderStatusService;
use App\Models\Order;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CancelAndRefundFailedOrderAction
{
    public const REASON = 'huỷ đơn hoàn tiền';

    public function __construct(
        private readonly WalletService $walletService,
        private readonly OrderStatusService $orderStatusService,
    ) {}

    public function handle(Order $order): Order
    {
        if ($order->order_status !== OrderStatus::Failed || $order->payment_status !== PaymentStatus::Paid) {
            throw ValidationException::withMessages([
                'cancel_refund' => 'Chỉ có thể huỷ hoàn tiền đơn hàng lỗi đã thanh toán.',
            ]);
        }

        $customer = User::query()
            ->withoutGlobalScope(TenantScope::class)
            ->when($order->tenant_id !== null, fn (Builder $query) => $query->where('tenant_id', $order->tenant_id))
            ->whereRaw('LOWER(email) = ?', [$order->normalized_email])
            ->first();

        if (! $customer instanceof User) {
            throw ValidationException::withMessages([
                'cancel_refund' => 'Email đơn hàng chưa đăng ký tài khoản, không thể huỷ hoàn tiền đơn này.',
            ]);
        }

        $this->walletService->getWallet($customer);
        $this->walletService->refund(
            user: $customer,
            amount: (string) $order->total_amount,
            referenceType: Order::class,
            referenceId: $order->id,
            description: "Hoàn tiền đơn {$order->code}",
            idempotencyKey: (string) Str::uuid(),
        );

        $order->forceFill(['payment_status' => PaymentStatus::Refunded])->save();

        return $this->orderStatusService->transition($order, OrderStatus::Cancelled, self::REASON);
    }
}
