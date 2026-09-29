<?php

namespace App\Features\Affiliate\Services;

use App\Exceptions\ApiException;
use App\Models\AffiliateCommission;
use App\Models\AffiliateConversion;
use App\Models\AffiliateProfile;
use App\Models\AffiliateProgram;
use App\Models\AffiliateWithdrawal;
use App\Models\GameServiceOrder;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AffiliateWalletService
{
    public const MINIMUM_CONVERSION = 1000;

    public const MINIMUM_COLLABORATOR_WITHDRAWAL = 100000;

    public function wallet(User $user, string $type): Wallet
    {
        return $this->ensureWallet($user, $type, (int) $user->tenant_id);
    }

    public function creditCommission(AffiliateCommission $commission, User $referrer): Wallet
    {
        $wallet = $this->lockedWallet($referrer, Wallet::TYPE_AFFILIATE, $commission->tenant_id);
        $before = (int) $wallet->balance;
        $after = $before + $commission->amount;
        $wallet->forceFill(['balance' => $after])->save();
        $this->record($wallet, 'credit', $commission->amount, $before, $after, $commission, 'Hoa hồng đơn '.$commission->order_id);

        return $wallet;
    }

    public function settleGameServiceOrder(GameServiceOrder $order): ?WalletTransaction
    {
        if ($order->collaborator_id === null || $order->collaborator_total_cost === null || $order->collaborator_settled_at !== null) {
            return null;
        }

        $collaborator = User::query()->withoutGlobalScope(TenantScope::class)->findOrFail($order->collaborator_id);
        $wallet = $this->lockedWallet($collaborator, Wallet::TYPE_COLLABORATOR, (int) $collaborator->tenant_id);
        $amount = (int) $order->collaborator_total_cost;
        $before = (int) $wallet->balance;
        $wallet->forceFill(['balance' => $before + $amount])->save();
        $transaction = $this->record($wallet, 'credit', $amount, $before, $before + $amount, $order, 'Kết toán đơn dịch vụ '.$order->code);
        $order->forceFill([
            'collaborator_settlement_amount' => $amount,
            'collaborator_wallet_transaction_id' => $transaction->id,
            'collaborator_settled_at' => now(),
        ])->save();

        return $transaction;
    }

    public function reverseGameServiceOrderSettlement(GameServiceOrder $order): ?WalletTransaction
    {
        if ($order->collaborator_id === null || $order->collaborator_settled_at === null) {
            return null;
        }

        $collaborator = User::query()->withoutGlobalScope(TenantScope::class)->findOrFail($order->collaborator_id);
        $wallet = $this->lockedWallet($collaborator, Wallet::TYPE_COLLABORATOR, (int) $collaborator->tenant_id);
        $amount = (int) $order->collaborator_settlement_amount;
        $before = (int) $wallet->balance;

        if ($before < $amount) {
            throw new ApiException('Không thể thu hồi kết toán vì số dư khả dụng của CTV không đủ.', 422);
        }

        $wallet->forceFill(['balance' => $before - $amount])->save();
        $transaction = $this->record($wallet, 'debit', $amount, $before, $before - $amount, $order, 'Thu hồi kết toán đơn dịch vụ '.$order->code);
        $order->forceFill([
            'collaborator_settlement_amount' => null,
            'collaborator_wallet_transaction_id' => null,
            'collaborator_settled_at' => null,
        ])->save();

        return $transaction;
    }

    public function reverseCommission(AffiliateCommission $commission): Wallet
    {
        $referrer = User::query()->withoutGlobalScope(TenantScope::class)->findOrFail($commission->referrer_id);
        $wallet = $this->lockedWallet($referrer, Wallet::TYPE_AFFILIATE, $commission->tenant_id);
        $before = (int) $wallet->balance;
        $after = $before - $commission->amount;
        $wallet->forceFill(['balance' => $after])->save();
        $this->record($wallet, 'debit', $commission->amount, $before, $after, $commission, 'Thu hồi hoa hồng đơn '.$commission->order_id);

        return $wallet;
    }

    public function convert(User $user, int $amount, string $idempotencyKey): AffiliateConversion
    {
        if ($amount < self::MINIMUM_CONVERSION) {
            throw new ApiException('Số tiền quy đổi tối thiểu là 1.000đ.', 422);
        }

        $this->assertActiveProgram($user);

        return DB::transaction(function () use ($user, $amount, $idempotencyKey): AffiliateConversion {
            $existing = AffiliateConversion::query()->withoutGlobalScope(TenantScope::class)
                ->where('idempotency_key', $idempotencyKey)->first();

            if ($existing instanceof AffiliateConversion) {
                if ($existing->user_id !== $user->id || $existing->amount !== $amount) {
                    throw new ApiException('Mã chống trùng không khớp với yêu cầu ban đầu.', 409);
                }

                return $existing;
            }

            $affiliateWallet = $this->ensureWallet($user, Wallet::TYPE_AFFILIATE, (int) $user->tenant_id);
            $mainWallet = $this->ensureWallet($user, Wallet::TYPE_MAIN, (int) $user->tenant_id);
            $wallets = Wallet::query()->withoutGlobalScope(TenantScope::class)
                ->whereIn('id', [$affiliateWallet->id, $mainWallet->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('type');
            $affiliateWallet = $wallets->get(Wallet::TYPE_AFFILIATE);
            $mainWallet = $wallets->get(Wallet::TYPE_MAIN);

            if (! $affiliateWallet instanceof Wallet || ! $mainWallet instanceof Wallet || (int) $affiliateWallet->balance < $amount) {
                throw new ApiException('Số dư hoa hồng khả dụng không đủ.', 422);
            }

            $conversion = AffiliateConversion::query()->create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'amount' => $amount,
                'idempotency_key' => $idempotencyKey,
            ]);
            $affiliateBefore = (int) $affiliateWallet->balance;
            $mainBefore = (int) $mainWallet->balance;
            $affiliateWallet->forceFill(['balance' => $affiliateBefore - $amount])->save();
            $mainWallet->forceFill(['balance' => $mainBefore + $amount])->save();
            $this->record($affiliateWallet, 'debit', $amount, $affiliateBefore, $affiliateBefore - $amount, $conversion, 'Quy đổi hoa hồng sang ví chính');
            $this->record($mainWallet, 'credit', $amount, $mainBefore, $mainBefore + $amount, $conversion, 'Nhận tiền quy đổi từ ví hoa hồng');

            return $conversion;
        }, 3);
    }

    public function requestWithdrawal(
        User $user,
        int $amount,
        string $idempotencyKey,
        string $walletType = Wallet::TYPE_AFFILIATE,
    ): AffiliateWithdrawal {
        $minimumWithdrawal = $walletType === Wallet::TYPE_COLLABORATOR
            ? $this->assertActiveCollaborator($user)
            : (int) $this->assertActiveProgram($user)->minimum_withdrawal;

        return DB::transaction(function () use ($user, $amount, $idempotencyKey, $minimumWithdrawal, $walletType): AffiliateWithdrawal {
            $existing = AffiliateWithdrawal::query()->withoutGlobalScope(TenantScope::class)
                ->where('idempotency_key', $idempotencyKey)->first();

            if ($existing instanceof AffiliateWithdrawal) {
                if ($existing->user_id !== $user->id || $existing->amount !== $amount || $existing->wallet_type !== $walletType) {
                    throw new ApiException('Mã chống trùng không khớp với yêu cầu ban đầu.', 409);
                }

                return $existing;
            }

            $profile = AffiliateProfile::query()->withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $user->tenant_id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->first();

            if ($amount < $minimumWithdrawal) {
                throw new ApiException('Số tiền chưa đạt mức rút tối thiểu của website.', 422);
            }

            if (! $profile instanceof AffiliateProfile
                || blank($profile->bank_name)
                || blank($profile->bank_account_name)
                || blank($profile->bank_account_number)) {
                throw new ApiException('Vui lòng cập nhật đầy đủ tài khoản nhận tiền trước khi rút.', 422);
            }

            if (! $user->hasVerifiedEmail()) {
                throw new ApiException('Bạn cần xác minh email trước khi rút hoa hồng.', 422);
            }

            $wallet = $this->lockedWallet($user, $walletType, (int) $user->tenant_id);

            if ((int) $wallet->balance < $amount) {
                throw new ApiException('Số dư hoa hồng khả dụng không đủ.', 422);
            }

            $withdrawal = AffiliateWithdrawal::query()->create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'amount' => $amount,
                'wallet_type' => $walletType,
                'bank_name' => $profile->bank_name,
                'bank_account_name' => $profile->bank_account_name,
                'bank_account_number' => $profile->bank_account_number,
                'idempotency_key' => $idempotencyKey,
            ]);
            $before = (int) $wallet->balance;
            $holdBefore = (int) $wallet->hold_balance;
            $wallet->forceFill(['balance' => $before - $amount, 'hold_balance' => $holdBefore + $amount])->save();
            $this->record($wallet, 'hold', $amount, $before, $before - $amount, $withdrawal, 'Tạm giữ tiền cho yêu cầu rút', [
                'hold_before' => $holdBefore,
                'hold_after' => $holdBefore + $amount,
            ]);

            return $withdrawal;
        }, 3);
    }

    public function releaseWithdrawal(AffiliateWithdrawal $withdrawal): void
    {
        $user = User::query()->withoutGlobalScope(TenantScope::class)->findOrFail($withdrawal->user_id);
        $wallet = $this->lockedWallet($user, $withdrawal->wallet_type, $withdrawal->tenant_id);
        $before = (int) $wallet->balance;
        $holdBefore = (int) $wallet->hold_balance;

        if ($holdBefore < $withdrawal->amount) {
            throw new ApiException('Số dư tạm giữ không khớp với yêu cầu rút.', 409);
        }

        $wallet->forceFill(['balance' => $before + $withdrawal->amount, 'hold_balance' => $holdBefore - $withdrawal->amount])->save();
        $this->record($wallet, 'release', $withdrawal->amount, $before, $before + $withdrawal->amount, $withdrawal, 'Hoàn lại yêu cầu rút bị từ chối', [
            'hold_before' => $holdBefore,
            'hold_after' => $holdBefore - $withdrawal->amount,
        ]);
    }

    public function settleWithdrawal(AffiliateWithdrawal $withdrawal): void
    {
        $user = User::query()->withoutGlobalScope(TenantScope::class)->findOrFail($withdrawal->user_id);
        $wallet = $this->lockedWallet($user, $withdrawal->wallet_type, $withdrawal->tenant_id);
        $holdBefore = (int) $wallet->hold_balance;

        if ($holdBefore < $withdrawal->amount) {
            throw new ApiException('Số dư tạm giữ không khớp với yêu cầu rút.', 409);
        }

        $wallet->forceFill(['hold_balance' => $holdBefore - $withdrawal->amount])->save();
        $this->record($wallet, 'debit', $withdrawal->amount, (int) $wallet->balance, (int) $wallet->balance, $withdrawal, 'Đã thanh toán yêu cầu rút hoa hồng', [
            'hold_before' => $holdBefore,
            'hold_after' => $holdBefore - $withdrawal->amount,
        ]);
    }

    private function lockedWallet(User $user, string $type, int $tenantId): Wallet
    {
        $wallet = $this->ensureWallet($user, $type, $tenantId);

        return Wallet::query()->withoutGlobalScope(TenantScope::class)->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
    }

    private function ensureWallet(User $user, string $type, int $tenantId): Wallet
    {
        return Wallet::query()->withoutGlobalScope(TenantScope::class)->firstOrCreate(
            ['user_id' => $user->id, 'type' => $type],
            ['tenant_id' => $tenantId, 'balance' => 0, 'hold_balance' => 0, 'total_recharge' => 0, 'total_spent' => 0],
        );
    }

    private function assertActiveProgram(User $user): AffiliateProgram
    {
        $program = AffiliateProgram::query()->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $user->tenant_id)
            ->where('is_enabled', true)
            ->first();
        $isSuspended = AffiliateProfile::query()->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('status', 'suspended')
            ->exists();

        if (! $program instanceof AffiliateProgram) {
            throw new ApiException('Chương trình cộng tác viên hiện chưa được bật trên website này.', 422);
        }

        if ($isSuspended) {
            throw new ApiException('Tài khoản cộng tác viên đang bị tạm khóa.', 403);
        }

        return $program;
    }

    private function assertActiveCollaborator(User $user): int
    {
        $isActive = $user->status === 'active' && AffiliateProfile::query()->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        if (! $isActive) {
            throw new ApiException('Tài khoản cộng tác viên chưa hoạt động hoặc đang bị tạm khóa.', 403);
        }

        return self::MINIMUM_COLLABORATOR_WITHDRAWAL;
    }

    /** @param array<string, mixed> $metadata */
    private function record(
        Wallet $wallet,
        string $type,
        int $amount,
        int $balanceBefore,
        int $balanceAfter,
        object $reference,
        string $description,
        array $metadata = [],
    ): WalletTransaction {
        return WalletTransaction::query()->withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $wallet->tenant_id,
            'wallet_id' => $wallet->id,
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'reference_type' => $reference::class,
            'reference_id' => $reference->id,
            'idempotency_key' => (string) Str::uuid(),
            'description' => $description,
            'metadata' => $metadata,
            'status' => 'success',
        ]);
    }
}
