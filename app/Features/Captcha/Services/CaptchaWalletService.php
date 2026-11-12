<?php

namespace App\Features\Captcha\Services;

use App\Features\Client\Wallet\Services\WalletService;
use App\Models\CaptchaTransaction;
use App\Models\CaptchaWallet;
use App\Models\User;
use App\Support\SettingStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CaptchaWalletService
{
    public function __construct(private readonly SettingStore $settings, private readonly WalletService $wallets) {}

    /** @return array<int, array{min_tokens: int, unit_price: int}> */
    public function rates(): array
    {
        return collect($this->settings->getArray('captcha.exchange_rates', [
            ['min_tokens' => 1, 'unit_price' => 5],
            ['min_tokens' => 1000, 'unit_price' => 3],
        ]))->sortBy('min_tokens')->values()->all();
    }

    /** @return array{tokens: int, unit_price: int, total: int} */
    public function quote(int $tokens): array
    {
        $price = 5;
        foreach ($this->rates() as $rate) {
            if ($tokens >= $rate['min_tokens']) {
                $price = (int) $rate['unit_price'];
            }
        }

        return ['tokens' => $tokens, 'unit_price' => $price, 'total' => $tokens * $price];
    }

    public function wallet(User $user): CaptchaWallet
    {
        return DB::transaction(function () use ($user): CaptchaWallet {
            User::query()->lockForUpdate()->findOrFail($user->id);

            return CaptchaWallet::query()->firstOrCreate(['user_id' => $user->id]);
        }, 3);
    }

    public function lock(User $user): CaptchaWallet
    {
        $wallet = $this->wallet($user);

        return CaptchaWallet::query()->lockForUpdate()->findOrFail($wallet->id);
    }

    public function purchase(User $user, int $tokens, int $unitPrice, string $requestId): CaptchaTransaction
    {
        return DB::transaction(function () use ($user, $tokens, $unitPrice, $requestId): CaptchaTransaction {
            $wallet = $this->lock($user);
            $key = 'purchase:'.$requestId;
            $existing = CaptchaTransaction::query()->where('user_id', $user->id)->where('request_key', $key)->first();
            if ($existing) {
                if ($existing->amount !== $tokens || $existing->cost !== $tokens * $unitPrice) {
                    throw ValidationException::withMessages(['request_id' => 'Mã giao dịch đã được dùng cho nội dung khác.']);
                }

                return $existing;
            }
            $quote = $this->quote($tokens);
            if ($quote['unit_price'] !== $unitPrice) {
                throw ValidationException::withMessages(['unit_price' => 'Giá quy đổi đã thay đổi. Vui lòng xem lại tổng tiền và thử lại.']);
            }
            $this->wallets->apply($user, $quote['total'], 'debit', 'captcha:'.$requestId, 'Mua '.$tokens.' token_captcha');

            return $this->record($wallet, $tokens, 'credit', $key, 'Mua token_captcha', $quote['total']);
        }, 3);
    }

    public function adjust(User $user, int $amount, string $direction, string $requestId, string $description, int $actorId): CaptchaTransaction
    {
        return DB::transaction(function () use ($user, $amount, $direction, $requestId, $description, $actorId): CaptchaTransaction {
            $wallet = $this->lock($user);
            $key = 'admin:'.$requestId;
            $existing = CaptchaTransaction::query()->where('user_id', $user->id)->where('request_key', $key)->first();
            if ($existing) {
                if ($existing->amount !== $amount || $existing->direction !== $direction || $existing->description !== $description || $existing->actor_id !== $actorId) {
                    throw ValidationException::withMessages(['request_id' => 'Mã giao dịch đã được dùng cho nội dung khác.']);
                }

                return $existing;
            }

            return $this->record($wallet, $amount, $direction, $key, $description, actorId: $actorId);
        }, 3);
    }

    public function record(CaptchaWallet $wallet, int $amount, string $direction, string $key, string $description, int $cost = 0, ?int $actorId = null): CaptchaTransaction
    {
        $next = $wallet->balance + ($direction === 'credit' ? $amount : -$amount);
        if ($next < $wallet->reserved || $next > 1000000000000) {
            throw ValidationException::withMessages(['balance' => 'Số dư token_captcha không đủ hoặc vượt giới hạn.']);
        }
        $transaction = CaptchaTransaction::query()->create([
            'user_id' => $wallet->user_id, 'actor_id' => $actorId, 'request_key' => $key,
            'direction' => $direction, 'amount' => $amount, 'cost' => $cost,
            'balance_before' => $wallet->balance, 'balance_after' => $next, 'description' => $description,
        ]);
        $wallet->update(['balance' => $next]);

        return $transaction;
    }
}
