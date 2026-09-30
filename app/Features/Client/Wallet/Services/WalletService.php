<?php

namespace App\Features\Client\Wallet\Services;

use App\Events\WalletBalanceChanged;
use App\Exceptions\ApiException;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\TenantContext;

class WalletService
{
    public function createWallet(User $user, string $type = Wallet::TYPE_MAIN): Wallet
    {
        $identity = ['type' => $type];

        if (app(TenantContext::class)->isActive()) {
            $identity['tenant_id'] = $user->tenant_id;
        }

        return $user->wallets()->firstOrCreate($identity, [
            'balance' => 0,
            'hold_balance' => 0,
            'total_recharge' => 0,
            'total_spent' => 0,
        ]);
    }

    /**
     * @return array{id:int,user_id:int,type:string,balance:string,hold_balance:string,total_recharge:string,total_spent:string,created_at:?string,updated_at:?string}
     */
    public function getWalletInfo(User $user, string $type = Wallet::TYPE_MAIN): array
    {
        $wallet = $this->getWallet($user, $type);

        return [
            'id' => $wallet->id,
            'user_id' => $wallet->user_id,
            'type' => $wallet->type,
            'balance' => (string) $wallet->balance,
            'hold_balance' => (string) $wallet->hold_balance,
            'total_recharge' => (string) $wallet->total_recharge,
            'total_spent' => (string) $wallet->total_spent,
            'created_at' => $wallet->created_at?->toISOString(),
            'updated_at' => $wallet->updated_at?->toISOString(),
        ];
    }

    public function getWallet(User $user, string $type = Wallet::TYPE_MAIN): Wallet
    {
        $wallet = $user->relationLoaded('wallet') && $type === Wallet::TYPE_MAIN
            ? $user->wallet
            : $user->wallets()->where('type', $type)->first();

        if (! $wallet instanceof Wallet) {
            $wallet = $this->createWallet($user, $type);
        }

        return $wallet;
    }

    public function debit(
        User $user,
        int|string $amount,
        string $referenceType,
        int $referenceId,
        string $description,
        string $type = Wallet::TYPE_MAIN,
        ?string $idempotencyKey = null,
    ): Wallet {
        $amount = $this->monetaryInteger($amount);

        $idempotentWallet = $this->resolveIdempotentWallet(
            user: $user,
            walletType: $type,
            transactionType: 'debit',
            amount: $amount,
            referenceType: $referenceType,
            referenceId: $referenceId,
            idempotencyKey: $idempotencyKey,
        );

        if ($idempotentWallet instanceof Wallet) {
            return $idempotentWallet;
        }

        $wallet = Wallet::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->lockForUpdate()
            ->first();

        if (! $wallet instanceof Wallet) {
            $wallet = $this->createWallet($user, $type);
            $wallet = Wallet::query()
                ->withoutGlobalScope(TenantScope::class)
                ->whereKey($wallet->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        if ($this->monetaryInteger((string) $wallet->balance) < $amount) {
            throw new ApiException('Số dư ví chính không đủ để thanh toán đơn hàng.', 422);
        }

        $balanceBefore = $this->monetaryInteger((string) $wallet->balance);
        $balanceAfter = $balanceBefore - $amount;

        $wallet->forceFill([
            'balance' => $balanceAfter,
            'total_spent' => $this->monetaryInteger((string) $wallet->total_spent) + $amount,
        ])->save();

        $transactionAttributes = [
            'wallet_id' => $wallet->id,
            'type' => 'debit',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'idempotency_key' => $idempotencyKey,
            'description' => $description,
            'status' => 'success',
        ];

        if (app(TenantContext::class)->isActive()) {
            $transactionAttributes['tenant_id'] = $wallet->tenant_id;
        }

        $transaction = WalletTransaction::query()->create($transactionAttributes);

        $wallet = $wallet->refresh();
        $this->broadcastBalanceChanged($user, $wallet, $transaction, -$amount);

        return $wallet;
    }

    public function credit(
        User $user,
        int|string $amount,
        string $referenceType,
        int $referenceId,
        string $description,
        string $type = Wallet::TYPE_MAIN,
        ?string $idempotencyKey = null,
    ): Wallet {
        return $this->increaseBalance(
            user: $user,
            amount: $amount,
            referenceType: $referenceType,
            referenceId: $referenceId,
            description: $description,
            transactionType: 'credit',
            walletType: $type,
            idempotencyKey: $idempotencyKey,
        );
    }

    public function refund(
        User $user,
        int|string $amount,
        string $referenceType,
        int $referenceId,
        string $description,
        string $type = Wallet::TYPE_MAIN,
        ?string $idempotencyKey = null,
    ): Wallet {
        return $this->increaseBalance(
            user: $user,
            amount: $amount,
            referenceType: $referenceType,
            referenceId: $referenceId,
            description: $description,
            transactionType: 'refund',
            walletType: $type,
            idempotencyKey: $idempotencyKey,
        );
    }

    private function increaseBalance(
        User $user,
        int|string $amount,
        string $referenceType,
        int $referenceId,
        string $description,
        string $transactionType,
        string $walletType,
        ?string $idempotencyKey,
    ): Wallet {
        $amount = $this->monetaryInteger($amount);

        $idempotentWallet = $this->resolveIdempotentWallet(
            user: $user,
            walletType: $walletType,
            transactionType: $transactionType,
            amount: $amount,
            referenceType: $referenceType,
            referenceId: $referenceId,
            idempotencyKey: $idempotencyKey,
        );

        if ($idempotentWallet instanceof Wallet) {
            return $idempotentWallet;
        }

        $wallet = Wallet::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('user_id', $user->id)
            ->where('type', $walletType)
            ->lockForUpdate()
            ->firstOrFail();

        $balanceBefore = $this->monetaryInteger((string) $wallet->balance);
        $balanceAfter = $balanceBefore + $amount;

        $wallet->forceFill([
            'balance' => $balanceAfter,
            'total_spent' => max(0, $this->monetaryInteger((string) $wallet->total_spent) - $amount),
        ])->save();

        $transactionAttributes = [
            'wallet_id' => $wallet->id,
            'type' => $transactionType,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'idempotency_key' => $idempotencyKey,
            'description' => $description,
            'status' => 'success',
        ];

        if (app(TenantContext::class)->isActive()) {
            $transactionAttributes['tenant_id'] = $wallet->tenant_id;
        }

        $transaction = WalletTransaction::query()->create($transactionAttributes);

        $wallet = $wallet->refresh();
        $this->broadcastBalanceChanged($user, $wallet, $transaction, $amount);

        return $wallet;
    }

    private function broadcastBalanceChanged(User $user, Wallet $wallet, WalletTransaction $transaction, int $signedAmount): void
    {
        WalletBalanceChanged::dispatch(
            userId: $user->id,
            walletType: $wallet->type,
            balance: (string) $wallet->balance,
            holdBalance: (string) $wallet->hold_balance,
            totalRecharge: (string) $wallet->total_recharge,
            totalSpent: (string) $wallet->total_spent,
            changeType: $transaction->type,
            amount: number_format($signedAmount, 2, '.', ''),
            transactionId: $transaction->id,
            description: (string) $transaction->description,
            changedAt: $transaction->created_at?->toISOString() ?? now()->toISOString(),
        );
    }

    private function resolveIdempotentWallet(
        User $user,
        string $walletType,
        string $transactionType,
        int $amount,
        string $referenceType,
        int $referenceId,
        ?string $idempotencyKey,
    ): ?Wallet {
        if ($idempotencyKey === null) {
            return null;
        }

        $transaction = WalletTransaction::query()
            ->withoutGlobalScope(TenantScope::class)
            ->with('wallet')
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if (! $transaction instanceof WalletTransaction) {
            return null;
        }

        $wallet = $transaction->wallet;
        $matchesOriginalOperation = $wallet instanceof Wallet
            && $wallet->user_id === $user->id
            && $wallet->type === $walletType
            && $transaction->type === $transactionType
            && $transaction->status === 'success'
            && $this->monetaryInteger((string) $transaction->amount) === $amount
            && $transaction->reference_type === $referenceType
            && $transaction->reference_id === $referenceId;

        if (! $matchesOriginalOperation) {
            throw new ApiException('Mã chống trùng giao dịch ví không khớp với thao tác ban đầu.', 409);
        }

        return $wallet;
    }

    private function monetaryInteger(int|string $amount): int
    {
        return (int) str($amount)->before('.')->toString();
    }
}
