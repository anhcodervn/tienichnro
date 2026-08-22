<?php

namespace App\Features\Admin\User\Actions;

use App\Events\WalletBalanceChanged;
use App\Exceptions\ApiException;
use App\Models\AdminAuditLog;
use App\Models\Notification;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdjustUserWalletAction
{
    /**
     * @param  array{type:string,amount:int,note?:string}  $payload
     * @return array<string, mixed>
     */
    public function handle(User $user, array $payload, User $actor, Request $request): array
    {
        return DB::transaction(function () use ($user, $payload, $actor, $request): array {
            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->where('type', Wallet::TYPE_MAIN)
                ->lockForUpdate()
                ->first();

            if (! $wallet instanceof Wallet) {
                $wallet = $user->wallets()->create([
                    'type' => Wallet::TYPE_MAIN,
                    'balance' => 0,
                    'hold_balance' => 0,
                    'total_recharge' => 0,
                    'total_spent' => 0,
                ]);
                $wallet = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            }

            $amount = (int) $payload['amount'];
            $signedAmount = $payload['type'] === 'subtract' ? -1 * $amount : $amount;
            $balanceBefore = (int) $wallet->balance;
            $balanceAfter = $balanceBefore + $signedAmount;

            if ($balanceAfter < 0) {
                throw new ApiException('Số dư ví không đủ để thực hiện thao tác trừ.', 422);
            }

            $wallet->forceFill([
                'balance' => $balanceAfter,
            ])->save();

            $transaction = WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'type' => 'adjustment',
                'amount' => $signedAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => User::class,
                'reference_id' => $actor->id,
                'description' => $payload['note'] ?? 'Admin wallet adjustment',
                'status' => 'success',
            ]);

            $isCredit = $signedAmount > 0;
            $notification = Notification::query()->create([
                'user_id' => $user->id,
                'scope' => Notification::SCOPE_USER,
                'title' => $isCredit ? 'Tài khoản được cộng tiền' : 'Số dư tài khoản được điều chỉnh',
                'content' => sprintf(
                    'Admin đã %s %sđ. Số dư hiện tại: %sđ.%s',
                    $isCredit ? 'cộng' : 'trừ',
                    number_format(abs($signedAmount), 0, ',', '.'),
                    number_format($balanceAfter, 0, ',', '.'),
                    filled($payload['note'] ?? null) ? ' Nội dung: '.trim((string) $payload['note']) : '',
                ),
                'redirect_url' => '/tai-khoan/so-du',
                'type' => $isCredit ? 'success' : 'warning',
                'is_read' => false,
            ]);

            WalletBalanceChanged::dispatch(
                userId: $user->id,
                walletType: $wallet->type,
                balance: (string) $wallet->balance,
                holdBalance: (string) $wallet->hold_balance,
                totalRecharge: (string) $wallet->total_recharge,
                totalSpent: (string) $wallet->total_spent,
                changeType: 'adjustment',
                amount: number_format($signedAmount, 2, '.', ''),
                transactionId: $transaction->id,
                description: (string) $transaction->description,
                changedAt: $transaction->created_at?->toISOString() ?? now()->toISOString(),
                notification: [
                    'id' => $notification->id,
                    'scope' => $notification->scope,
                    'title' => $notification->title,
                    'content' => $notification->content,
                    'redirect_url' => $notification->redirect_url,
                    'type' => $notification->type,
                    'is_read' => false,
                    'created_at' => $notification->created_at?->toDateTimeString(),
                ],
            );

            AdminAuditLog::query()->create([
                'admin_id' => $actor->id,
                'action' => 'wallet_adjusted',
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'old_values' => ['balance' => $balanceBefore],
                'new_values' => [
                    'balance' => $balanceAfter,
                    'amount' => $signedAmount,
                    'note' => $payload['note'] ?? null,
                ],
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return [
                'wallet' => [
                    'id' => $wallet->id,
                    'balance' => (float) $wallet->fresh()->balance,
                    'hold_balance' => (float) $wallet->hold_balance,
                    'total_recharge' => (float) $wallet->total_recharge,
                    'total_spent' => (float) $wallet->total_spent,
                ],
                'transaction' => [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount' => (float) $transaction->amount,
                    'balance_before' => (float) $transaction->balance_before,
                    'balance_after' => (float) $transaction->balance_after,
                    'description' => $transaction->description,
                    'status' => $transaction->status,
                    'created_at' => $transaction->created_at?->toISOString(),
                ],
            ];
        });
    }
}
