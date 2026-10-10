<?php

namespace App\Features\Client\Wallet\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalletService
{
    public function wallet(User $user): Wallet
    {
        return DB::transaction(function () use ($user): Wallet {
            User::query()->lockForUpdate()->findOrFail($user->id);

            return Wallet::query()->firstOrCreate(['user_id' => $user->id], ['balance' => 0]);
        }, 3);
    }

    public function apply(User $user, int $amount, string $direction, string $key, string $description, ?int $actorId = null): WalletTransaction
    {
        if ($amount < 0 || $amount > 100000000000 || ! in_array($direction, ['credit', 'debit'], true)) {
            throw ValidationException::withMessages(['amount' => 'Số tiền hoặc loại giao dịch không hợp lệ.']);
        }

        return DB::transaction(function () use ($user, $amount, $direction, $key, $description, $actorId): WalletTransaction {
            $wallet = $this->wallet($user);
            $wallet = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);
            $existing = WalletTransaction::query()->where('wallet_id', $wallet->id)->where('idempotency_key', $key)->first();
            if ($existing) {
                if ($existing->amount !== $amount || $existing->direction !== $direction || $existing->description !== $description || $existing->actor_id !== $actorId) {
                    throw ValidationException::withMessages(['request_id' => 'Mã giao dịch đã được dùng cho nội dung khác.']);
                }

                return $existing;
            }
            $next = $direction === 'credit' ? $wallet->balance + $amount : $wallet->balance - $amount;
            if ($next < 0) {
                throw ValidationException::withMessages(['balance' => 'Số dư ví không đủ.']);
            }
            if ($next > 1000000000000000) {
                throw ValidationException::withMessages(['balance' => 'Số dư vượt quá giới hạn cho phép.']);
            }
            $transaction = WalletTransaction::query()->create([
                'wallet_id' => $wallet->id, 'actor_id' => $actorId, 'direction' => $direction, 'amount' => $amount,
                'balance_before' => $wallet->balance, 'balance_after' => $next, 'idempotency_key' => $key, 'description' => $description,
            ]);
            $wallet->update(['balance' => $next]);

            return $transaction;
        }, 3);
    }
}
