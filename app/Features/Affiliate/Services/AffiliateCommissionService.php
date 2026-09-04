<?php

namespace App\Features\Affiliate\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePackageRate;
use App\Models\AffiliateProfile;
use App\Models\Order;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AffiliateCommissionService
{
    public const HOLDING_DAYS = 7;

    public function __construct(
        private readonly AffiliateProgramService $programService,
        private readonly AffiliateWalletService $walletService,
    ) {}

    public function snapshot(Order $order): ?AffiliateCommission
    {
        if (! $order->user_id || ! $order->topup_package_id) {
            return null;
        }

        $program = $this->programService->enabled();
        $buyer = User::query()->find($order->user_id);

        if ($program === null
            || ! $buyer instanceof User
            || $buyer->tenant_id !== $program->tenant_id
            || $order->tenant_id !== $program->tenant_id
            || ! $buyer->referred_by
            || $buyer->referred_by === $buyer->id) {
            return null;
        }

        $referrer = User::query()
            ->whereKey($buyer->referred_by)
            ->where('tenant_id', $program->tenant_id)
            ->where('status', 'active')
            ->first();

        if (! $referrer instanceof User) {
            return null;
        }

        $profile = AffiliateProfile::query()->firstOrCreate(
            ['user_id' => $buyer->referred_by],
            ['tenant_id' => $program->tenant_id, 'status' => 'active'],
        );
        $rate = AffiliatePackageRate::query()
            ->where('tenant_id', $program->tenant_id)
            ->where('topup_package_id', $order->topup_package_id)
            ->where('is_active', true)
            ->first();

        if ($profile->status === 'suspended' || ! $rate instanceof AffiliatePackageRate) {
            return null;
        }

        $baseAmount = (int) $order->total_amount;
        $quantity = max(1, (int) $order->quantity);
        $rateValue = $rate->commission_type === AffiliatePackageRate::TYPE_PERCENTAGE
            ? (int) $rate->percentage_basis_points
            : (int) $rate->fixed_amount;
        $amount = $rate->commission_type === AffiliatePackageRate::TYPE_PERCENTAGE
            ? intdiv($baseAmount * $rateValue, 10000)
            : $rateValue * $quantity;

        if ($amount < 1) {
            return null;
        }

        return AffiliateCommission::query()->firstOrCreate(
            ['order_id' => $order->id],
            [
                'tenant_id' => $program->tenant_id,
                'referrer_id' => $referrer->id,
                'referred_user_id' => $buyer->id,
                'topup_package_id' => $order->topup_package_id,
                'commission_type' => $rate->commission_type,
                'rate_value' => $rateValue,
                'base_amount' => $baseAmount,
                'quantity' => $quantity,
                'amount' => $amount,
                'holding_days' => self::HOLDING_DAYS,
            ],
        );
    }

    public function markOrderCompleted(Order $order): void
    {
        if ($order->payment_status !== PaymentStatus::Paid || $order->order_status !== OrderStatus::Completed) {
            return;
        }

        $commission = AffiliateCommission::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $order->tenant_id)
            ->where('order_id', $order->id)
            ->lockForUpdate()
            ->first();

        if ($commission instanceof AffiliateCommission && $commission->status === AffiliateCommission::STATUS_PENDING && $commission->earned_at === null) {
            $earnedAt = $order->completed_at ?? now();
            $commission->forceFill([
                'earned_at' => $earnedAt,
                'available_at' => $earnedAt->copy()->addDays($commission->holding_days),
            ])->save();
        }
    }

    public function releaseDue(int $chunkSize = 200): int
    {
        $released = 0;

        AffiliateCommission::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('status', AffiliateCommission::STATUS_PENDING)
            ->where('is_flagged', false)
            ->whereNotNull('available_at')
            ->where('available_at', '<=', now())
            ->orderBy('id')
            ->chunkById($chunkSize, function ($commissions) use (&$released): void {
                foreach ($commissions as $commission) {
                    if ($this->release((int) $commission->id)) {
                        $released++;
                    }
                }
            });

        return $released;
    }

    public function reverseForOrder(Order $order, string $reason): void
    {
        DB::transaction(function () use ($order, $reason): void {
            $commission = AffiliateCommission::query()
                ->withoutGlobalScope(TenantScope::class)
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->first();

            if (! $commission instanceof AffiliateCommission || $commission->status === AffiliateCommission::STATUS_REVERSED) {
                return;
            }

            if ($commission->status === AffiliateCommission::STATUS_AVAILABLE) {
                $this->walletService->reverseCommission($commission);
            }

            $commission->forceFill([
                'status' => AffiliateCommission::STATUS_REVERSED,
                'reversed_at' => now(),
                'reversal_reason' => $reason,
            ])->save();
        }, 3);
    }

    private function release(int $commissionId): bool
    {
        return DB::transaction(function () use ($commissionId): bool {
            $commission = AffiliateCommission::query()
                ->withoutGlobalScope(TenantScope::class)
                ->lockForUpdate()
                ->find($commissionId);

            if (! $commission instanceof AffiliateCommission
                || $commission->status !== AffiliateCommission::STATUS_PENDING
                || $commission->is_flagged
                || $commission->available_at?->isFuture()) {
                return false;
            }

            $order = Order::query()->withoutGlobalScope(TenantScope::class)->find($commission->order_id);
            $referrer = User::query()->withoutGlobalScope(TenantScope::class)->find($commission->referrer_id);
            $isSuspended = AffiliateProfile::query()->withoutGlobalScope(TenantScope::class)
                ->where('user_id', $commission->referrer_id)
                ->where('status', 'suspended')
                ->exists();

            if (! $order instanceof Order
                || $order->payment_status !== PaymentStatus::Paid
                || $order->order_status !== OrderStatus::Completed
                || ! $referrer instanceof User
                || $referrer->tenant_id !== $commission->tenant_id
                || $referrer->status !== 'active'
                || $isSuspended) {
                return false;
            }

            $this->walletService->creditCommission($commission, $referrer);
            $commission->forceFill(['status' => AffiliateCommission::STATUS_AVAILABLE])->save();

            return true;
        }, 3);
    }
}
