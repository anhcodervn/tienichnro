<?php

namespace App\Features\Client\Affiliate\Services;

use App\Features\Affiliate\Services\AffiliateProgramService;
use App\Features\Affiliate\Services\AffiliateWalletService;
use App\Models\AffiliateCommission;
use App\Models\AffiliateConversion;
use App\Models\AffiliateProfile;
use App\Models\AffiliateWithdrawal;
use App\Models\User;
use App\Models\Wallet;

class AffiliateDashboardService
{
    public function __construct(
        private readonly AffiliateProgramService $programService,
        private readonly AffiliateWalletService $walletService,
    ) {}

    /** @return array<string, mixed> */
    public function data(User $user): array
    {
        $program = $this->programService->enabled();
        abort_unless($program !== null && $program->tenant_id === $user->tenant_id, 404);
        $profile = AffiliateProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['tenant_id' => $user->tenant_id, 'status' => 'active'],
        );
        $affiliateWallet = $this->walletService->wallet($user, Wallet::TYPE_AFFILIATE);
        $mainWallet = $this->walletService->wallet($user, Wallet::TYPE_MAIN);
        $commissions = AffiliateCommission::query()
            ->where('referrer_id', $user->id)
            ->with(['order:id,code', 'referredUser:id,username', 'package:id,name'])
            ->latest('id')->limit(20)->get();
        $withdrawals = AffiliateWithdrawal::query()->where('user_id', $user->id)->latest('id')->limit(20)->get();
        $conversions = AffiliateConversion::query()->where('user_id', $user->id)->latest('id')->limit(20)->get();

        return [
            'program' => [
                'minimum_withdrawal' => $program->minimum_withdrawal,
                'minimum_conversion' => AffiliateWalletService::MINIMUM_CONVERSION,
                'holding_days' => 7,
            ],
            'profile' => [
                'status' => $profile->status,
                'bank_name' => $profile->bank_name,
                'bank_account_name' => $profile->bank_account_name,
                'bank_account_number_masked' => $this->mask((string) $profile->bank_account_number),
                'has_payout_account' => filled($profile->bank_name) && filled($profile->bank_account_name) && filled($profile->bank_account_number),
            ],
            'referral' => [
                'code' => $user->referral_code,
                'url' => url('/dang-ky?ref='.urlencode((string) $user->referral_code)),
                'referrals_count' => $user->referrals()->count(),
            ],
            'wallets' => [
                'affiliate' => ['balance' => (int) $affiliateWallet->balance, 'hold_balance' => (int) $affiliateWallet->hold_balance],
                'main' => ['balance' => (int) $mainWallet->balance],
            ],
            'stats' => [
                'pending' => (int) AffiliateCommission::query()->where('referrer_id', $user->id)
                    ->where('status', 'pending')->whereNotNull('earned_at')->sum('amount'),
                'available_earned' => (int) AffiliateCommission::query()->where('referrer_id', $user->id)->where('status', 'available')->sum('amount'),
                'reversed' => (int) AffiliateCommission::query()->where('referrer_id', $user->id)->where('status', 'reversed')->sum('amount'),
                'revenue' => (int) AffiliateCommission::query()->where('referrer_id', $user->id)
                    ->where(fn ($query) => $query->where('status', 'available')
                        ->orWhere(fn ($pendingQuery) => $pendingQuery->where('status', 'pending')->whereNotNull('earned_at')))
                    ->sum('base_amount'),
            ],
            'commissions' => $commissions->map(fn (AffiliateCommission $commission): array => [
                'id' => $commission->id,
                'amount' => $commission->amount,
                'base_amount' => $commission->base_amount,
                'status' => $commission->status,
                'available_at' => $commission->available_at?->toISOString(),
                'created_at' => $commission->created_at?->toISOString(),
                'order' => $commission->order?->only(['code']),
                'referred_user' => $commission->referredUser?->only(['username']),
                'package' => $commission->package?->only(['name']),
            ]),
            'referrals' => $user->referrals()->select(['id', 'username', 'created_at'])->latest('id')->limit(20)->get(),
            'withdrawals' => $withdrawals->map(fn (AffiliateWithdrawal $withdrawal): array => [
                'id' => $withdrawal->id, 'amount' => $withdrawal->amount, 'status' => $withdrawal->status,
                'bank_name' => $withdrawal->bank_name, 'account_number' => $this->mask((string) $withdrawal->bank_account_number),
                'created_at' => $withdrawal->created_at?->toISOString(),
            ]),
            'conversions' => $conversions->map(fn (AffiliateConversion $conversion): array => [
                'id' => $conversion->id,
                'amount' => $conversion->amount,
                'status' => $conversion->status,
                'created_at' => $conversion->created_at?->toISOString(),
            ]),
        ];
    }

    /** @param array<string, mixed> $payload */
    public function updatePayout(User $user, array $payload): AffiliateProfile
    {
        abort_if($this->programService->enabled() === null, 404);
        $profile = AffiliateProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['tenant_id' => $user->tenant_id, 'status' => 'active'],
        );
        abort_if($profile->status !== 'active', 403, 'Tài khoản cộng tác viên đang bị tạm khóa.');
        $profile->fill($payload)->save();

        return $profile->refresh();
    }

    private function mask(string $value): string
    {
        return $value === '' ? '' : str_repeat('*', max(0, mb_strlen($value) - 4)).mb_substr($value, -4);
    }
}
