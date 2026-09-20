<?php

namespace App\Features\Topup\Actions;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Scopes\TenantScope;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ExpireUnpaidOrdersAction
{
    private const UNPAID_EXPIRATION_HOURS = 8;

    private const EXPIRED_RETENTION_HOURS = 24;

    /**
     * @return array{expired_deposits: int, expired_orders: int, deleted_deposits: int, deleted_orders: int}
     */
    public function execute(int $chunkSize = 200): array
    {
        $now = now();
        $expirationCutoff = $now->copy()->subHours(self::UNPAID_EXPIRATION_HOURS);
        $deletionCutoff = $now->copy()->subHours(self::EXPIRED_RETENTION_HOURS);

        return [
            'expired_deposits' => $this->expireWalletDeposits($expirationCutoff, $now, $chunkSize),
            'expired_orders' => $this->expireGameOrders($expirationCutoff, $now, $chunkSize),
            'deleted_deposits' => $this->deleteExpiredWalletDeposits($deletionCutoff, $chunkSize),
            'deleted_orders' => $this->deleteExpiredGameOrders($deletionCutoff, $chunkSize),
        ];
    }

    private function expireWalletDeposits(CarbonInterface $cutoff, CarbonInterface $expiredAt, int $chunkSize): int
    {
        $expired = 0;

        PaymentTransaction::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereNull('order_id')
            ->whereNull('expired_at')
            ->whereIn('status', ['pending', 'matched'])
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById($chunkSize, function ($transactions) use (&$expired, $cutoff, $expiredAt): void {
                foreach ($transactions as $transaction) {
                    $didExpire = DB::transaction(function () use ($transaction, $cutoff, $expiredAt): bool {
                        $lockedTransaction = PaymentTransaction::query()
                            ->withoutGlobalScope(TenantScope::class)
                            ->lockForUpdate()
                            ->find($transaction->id);

                        if (! $lockedTransaction instanceof PaymentTransaction
                            || $lockedTransaction->order_id !== null
                            || $lockedTransaction->expired_at !== null
                            || ! in_array($lockedTransaction->status, ['pending', 'matched'], true)
                            || $lockedTransaction->created_at->isAfter($cutoff)) {
                            return false;
                        }

                        $rawData = is_array($lockedTransaction->raw_data) ? $lockedTransaction->raw_data : [];
                        $rawData['cancel_reason'] = 'expired';
                        $rawData['expires_at'] = $expiredAt->toISOString();

                        $lockedTransaction->forceFill([
                            'status' => 'cancelled',
                            'expired_at' => $expiredAt,
                            'raw_data' => $rawData,
                        ])->save();

                        return true;
                    }, 3);

                    $expired += (int) $didExpire;
                }
            });

        return $expired;
    }

    private function expireGameOrders(CarbonInterface $cutoff, CarbonInterface $expiredAt, int $chunkSize): int
    {
        $expired = 0;

        Order::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('payment_status', PaymentStatus::Pending)
            ->whereNull('expired_at')
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById($chunkSize, function ($orders) use (&$expired, $cutoff, $expiredAt): void {
                foreach ($orders as $order) {
                    $didExpire = DB::transaction(function () use ($order, $cutoff, $expiredAt): bool {
                        $lockedOrder = Order::query()
                            ->withoutGlobalScope(TenantScope::class)
                            ->lockForUpdate()
                            ->find($order->id);

                        if (! $lockedOrder instanceof Order
                            || $lockedOrder->payment_status !== PaymentStatus::Pending
                            || $lockedOrder->expired_at !== null
                            || $lockedOrder->created_at->isAfter($cutoff)) {
                            return false;
                        }

                        $lockedOrder->forceFill([
                            'payment_status' => PaymentStatus::Expired,
                            'expired_at' => $expiredAt,
                        ])->save();

                        $lockedOrder->paymentTransactions()
                            ->whereIn('status', ['pending', 'matched'])
                            ->get()
                            ->each(function (PaymentTransaction $transaction) use ($expiredAt): void {
                                $rawData = is_array($transaction->raw_data) ? $transaction->raw_data : [];
                                $rawData['cancel_reason'] = 'expired';
                                $rawData['expires_at'] = $expiredAt->toISOString();

                                $transaction->forceFill([
                                    'status' => 'cancelled',
                                    'expired_at' => $expiredAt,
                                    'raw_data' => $rawData,
                                ])->save();
                            });

                        return true;
                    }, 3);

                    $expired += (int) $didExpire;
                }
            });

        return $expired;
    }

    private function deleteExpiredWalletDeposits(CarbonInterface $cutoff, int $chunkSize): int
    {
        $deleted = 0;

        PaymentTransaction::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereNull('order_id')
            ->where('status', 'cancelled')
            ->where('raw_data->cancel_reason', 'expired')
            ->where('expired_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById($chunkSize, function ($transactions) use (&$deleted, $cutoff): void {
                foreach ($transactions as $transaction) {
                    $didDelete = DB::transaction(function () use ($transaction, $cutoff): bool {
                        $lockedTransaction = PaymentTransaction::query()
                            ->withoutGlobalScope(TenantScope::class)
                            ->lockForUpdate()
                            ->find($transaction->id);

                        if (! $lockedTransaction instanceof PaymentTransaction
                            || $lockedTransaction->order_id !== null
                            || $lockedTransaction->status !== 'cancelled'
                            || data_get($lockedTransaction->raw_data, 'cancel_reason') !== 'expired'
                            || $lockedTransaction->expired_at === null
                            || $lockedTransaction->expired_at->isAfter($cutoff)) {
                            return false;
                        }

                        return (bool) $lockedTransaction->delete();
                    }, 3);

                    $deleted += (int) $didDelete;
                }
            });

        return $deleted;
    }

    private function deleteExpiredGameOrders(CarbonInterface $cutoff, int $chunkSize): int
    {
        $deleted = 0;

        Order::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('payment_status', PaymentStatus::Expired)
            ->where('expired_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById($chunkSize, function ($orders) use (&$deleted, $cutoff): void {
                foreach ($orders as $order) {
                    $didDelete = DB::transaction(function () use ($order, $cutoff): bool {
                        $lockedOrder = Order::query()
                            ->withoutGlobalScope(TenantScope::class)
                            ->lockForUpdate()
                            ->find($order->id);

                        if (! $lockedOrder instanceof Order
                            || $lockedOrder->payment_status !== PaymentStatus::Expired
                            || $lockedOrder->expired_at === null
                            || $lockedOrder->expired_at->isAfter($cutoff)) {
                            return false;
                        }

                        $lockedOrder->affiliateCommission()->delete();
                        $lockedOrder->paymentTransactions()->delete();

                        return (bool) $lockedOrder->delete();
                    }, 3);

                    $deleted += (int) $didDelete;
                }
            });

        return $deleted;
    }
}
