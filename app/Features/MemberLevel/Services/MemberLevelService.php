<?php

namespace App\Features\MemberLevel\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\MemberLevel;
use App\Models\MemberLevelAccount;
use App\Models\MemberLevelHistory;
use App\Models\MemberLevelOrderCredit;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MemberLevelService
{
    /** @return array<string, mixed>|null */
    public function status(?User $user, bool $synchronize = true): ?array
    {
        if (! $user instanceof User) {
            return null;
        }

        if ($synchronize) {
            $this->synchronizeOrders($user);
        }

        $account = MemberLevelAccount::query()
            ->with(['earnedLevel', 'manualLevel'])
            ->firstOrCreate(['user_id' => $user->id]);
        $manualIsActive = $account->manualLevel instanceof MemberLevel
            && $account->manualLevel->status === 'active'
            && ($account->manual_level_expires_at === null || $account->manual_level_expires_at->isFuture());
        $unlockedLevel = $manualIsActive ? $account->manualLevel : $account->earnedLevel;

        if ($unlockedLevel instanceof MemberLevel && $unlockedLevel->status !== 'active') {
            $unlockedLevel = MemberLevel::query()
                ->where('status', 'active')
                ->where('rank', '<=', $unlockedLevel->rank)
                ->orderByDesc('rank')
                ->first();
        }

        if (! $unlockedLevel instanceof MemberLevel) {
            $unlockedLevel = MemberLevel::query()->where('status', 'active')->orderBy('rank')->first();
        }

        if (! $unlockedLevel instanceof MemberLevel) {
            return $this->emptyStatus($account);
        }

        $maintenance = $manualIsActive
            ? $this->manualMaintenanceStatus($account)
            : $this->maintenanceStatus($user, $unlockedLevel);
        $effectiveLevel = $unlockedLevel;

        if (! $maintenance['is_maintained']) {
            $effectiveLevel = MemberLevel::query()
                ->where('status', 'active')
                ->where('rank', '<', $unlockedLevel->rank)
                ->orderByDesc('rank')
                ->first() ?? $unlockedLevel;
        }

        $nextLevel = MemberLevel::query()
            ->where('status', 'active')
            ->where('rank', '>', $unlockedLevel->rank)
            ->orderBy('rank')
            ->first();

        return [
            'unlocked_level' => $this->levelData($unlockedLevel),
            'effective_level' => $this->levelData($effectiveLevel),
            'next_level' => $nextLevel instanceof MemberLevel ? $this->levelData($nextLevel) : null,
            'lifetime_completed_amount' => $account->lifetime_completed_amount,
            'amount_to_next_level' => $nextLevel instanceof MemberLevel
                ? max(0, $nextLevel->lifetime_threshold - $account->lifetime_completed_amount)
                : 0,
            'rolling_completed_amount' => $maintenance['rolling_amount'],
            'maintenance_required_amount' => $unlockedLevel->maintenance_amount,
            'maintenance_remaining_amount' => $maintenance['remaining_amount'],
            'maintenance_expires_at' => $maintenance['expires_at']?->toISOString(),
            'is_maintained' => $maintenance['is_maintained'],
            'is_temporarily_downgraded' => $effectiveLevel->id !== $unlockedLevel->id,
            'is_manual' => $manualIsActive,
            'manual_level_expires_at' => $account->manual_level_expires_at?->toISOString(),
        ];
    }

    public function synchronizeOrder(Order $order): void
    {
        if ($order->user_id === null) {
            return;
        }

        $qualifies = $order->payment_status === PaymentStatus::Paid
            && $order->order_status === OrderStatus::Completed;
        $credit = MemberLevelOrderCredit::query()->where('order_id', $order->id)->first();

        if ($qualifies) {
            $credit = MemberLevelOrderCredit::query()->firstOrCreate([
                'order_id' => $order->id,
            ], [
                'user_id' => $order->user_id,
                'amount' => (int) $order->total_amount,
                'occurred_at' => $order->completed_at ?? now(),
            ]);

            if (! $credit->wasRecentlyCreated) {
                return;
            }
        } elseif ($order->payment_status === PaymentStatus::Refunded && $credit instanceof MemberLevelOrderCredit && $credit->reversed_at === null) {
            $credit->forceFill(['reversed_at' => now()])->save();
        } else {
            return;
        }

        $this->refreshAccount(User::query()->findOrFail($order->user_id));
    }

    public function synchronizeOrders(User $user): void
    {
        Order::query()
            ->whereBelongsTo($user)
            ->where('payment_status', PaymentStatus::Paid)
            ->where('order_status', OrderStatus::Completed)
            ->whereDoesntHave('memberLevelCredit')
            ->select(['id', 'user_id', 'total_amount', 'completed_at'])
            ->chunkById(100, function ($orders): void {
                foreach ($orders as $order) {
                    MemberLevelOrderCredit::query()->firstOrCreate(
                        ['order_id' => $order->id],
                        [
                            'user_id' => $order->user_id,
                            'amount' => (int) $order->total_amount,
                            'occurred_at' => $order->completed_at ?? now(),
                        ],
                    );
                }
            });

        MemberLevelOrderCredit::query()
            ->whereBelongsTo($user)
            ->whereNull('reversed_at')
            ->whereHas('order', fn ($query) => $query->where('payment_status', PaymentStatus::Refunded))
            ->update(['reversed_at' => now(), 'updated_at' => now()]);

        $this->refreshAccount($user);
    }

    public function assignManualLevel(User $user, ?MemberLevel $level, ?Carbon $expiresAt, User $actor, ?string $reason = null): void
    {
        DB::transaction(function () use ($user, $level, $expiresAt, $actor, $reason): void {
            $account = MemberLevelAccount::query()->lockForUpdate()->firstOrCreate(['user_id' => $user->id]);
            $fromLevelId = $account->manual_level_id;

            $account->forceFill([
                'manual_level_id' => $level?->id,
                'manual_level_expires_at' => $level instanceof MemberLevel ? $expiresAt : null,
            ])->save();

            MemberLevelHistory::query()->create([
                'user_id' => $user->id,
                'from_level_id' => $fromLevelId,
                'to_level_id' => $level?->id,
                'actor_id' => $actor->id,
                'type' => $level instanceof MemberLevel ? 'admin_assigned' : 'admin_cleared',
                'reason' => $reason,
                'lifetime_completed_amount' => $account->lifetime_completed_amount,
                'metadata' => ['expires_at' => $expiresAt?->toISOString()],
            ]);
        }, 3);
    }

    private function refreshAccount(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $account = MemberLevelAccount::query()->lockForUpdate()->firstOrCreate(['user_id' => $user->id]);
            $creditedLifetimeAmount = (int) MemberLevelOrderCredit::query()->whereBelongsTo($user)->sum('amount');
            $lifetimeAmount = max($account->lifetime_completed_amount, $creditedLifetimeAmount);
            $earnedLevel = MemberLevel::query()
                ->where('status', 'active')
                ->where('lifetime_threshold', '<=', $lifetimeAmount)
                ->orderByDesc('rank')
                ->first();
            $previousLevelId = $account->earned_level_id;
            $previousLevel = $previousLevelId !== null ? MemberLevel::query()->find($previousLevelId) : null;

            if ($previousLevel instanceof MemberLevel && (! $earnedLevel instanceof MemberLevel || $previousLevel->rank > $earnedLevel->rank)) {
                $earnedLevel = $previousLevel;
            }

            $account->forceFill([
                'earned_level_id' => $earnedLevel?->id,
                'lifetime_completed_amount' => $lifetimeAmount,
                'last_qualified_order_at' => MemberLevelOrderCredit::query()
                    ->whereBelongsTo($user)
                    ->whereNull('reversed_at')
                    ->max('occurred_at'),
            ])->save();

            if ($earnedLevel?->id !== $previousLevelId) {
                MemberLevelHistory::query()->create([
                    'user_id' => $user->id,
                    'from_level_id' => $previousLevelId,
                    'to_level_id' => $earnedLevel?->id,
                    'type' => 'level_unlocked',
                    'lifetime_completed_amount' => $lifetimeAmount,
                ]);
            }
        }, 3);
    }

    /** @return array{rolling_amount:int,remaining_amount:int,is_maintained:bool,expires_at:?Carbon} */
    private function maintenanceStatus(User $user, MemberLevel $level): array
    {
        if ($level->maintenance_amount === 0) {
            return ['rolling_amount' => 0, 'remaining_amount' => 0, 'is_maintained' => true, 'expires_at' => null];
        }

        $cutoff = now()->subDays($level->maintenance_days);
        $credits = MemberLevelOrderCredit::query()
            ->whereBelongsTo($user)
            ->whereNull('reversed_at')
            ->where('occurred_at', '>=', $cutoff)
            ->oldest('occurred_at')
            ->get(['amount', 'occurred_at']);
        $rollingAmount = (int) $credits->sum('amount');
        $isMaintained = $rollingAmount >= $level->maintenance_amount;
        $remaining = $rollingAmount;
        $expiresAt = null;

        if ($isMaintained) {
            foreach ($credits as $credit) {
                $remaining -= $credit->amount;

                if ($remaining < $level->maintenance_amount) {
                    $expiresAt = $credit->occurred_at->copy()->addDays($level->maintenance_days);
                    break;
                }
            }
        }

        return [
            'rolling_amount' => $rollingAmount,
            'remaining_amount' => max(0, $level->maintenance_amount - $rollingAmount),
            'is_maintained' => $isMaintained,
            'expires_at' => $expiresAt,
        ];
    }

    /** @return array{rolling_amount:int,remaining_amount:int,is_maintained:bool,expires_at:?Carbon} */
    private function manualMaintenanceStatus(MemberLevelAccount $account): array
    {
        return [
            'rolling_amount' => 0,
            'remaining_amount' => 0,
            'is_maintained' => true,
            'expires_at' => $account->manual_level_expires_at,
        ];
    }

    /** @return array<string, mixed> */
    private function levelData(MemberLevel $level): array
    {
        return [
            'id' => $level->id,
            'code' => $level->code,
            'name' => $level->name,
            'rank' => $level->rank,
            'lifetime_threshold' => $level->lifetime_threshold,
            'maintenance_amount' => $level->maintenance_amount,
            'maintenance_days' => $level->maintenance_days,
            'default_discount_bps' => $level->default_discount_bps,
            'discount_percent' => $level->default_discount_bps / 100,
            'minimum_profit' => $level->minimum_profit,
            'color' => $level->color,
            'icon' => $level->icon,
        ];
    }

    /** @return array<string, mixed> */
    private function emptyStatus(MemberLevelAccount $account): array
    {
        return [
            'unlocked_level' => null,
            'effective_level' => null,
            'next_level' => null,
            'lifetime_completed_amount' => $account->lifetime_completed_amount,
            'amount_to_next_level' => 0,
            'rolling_completed_amount' => 0,
            'maintenance_required_amount' => 0,
            'maintenance_remaining_amount' => 0,
            'maintenance_expires_at' => null,
            'is_maintained' => true,
            'is_temporarily_downgraded' => false,
            'is_manual' => false,
            'manual_level_expires_at' => null,
        ];
    }
}
