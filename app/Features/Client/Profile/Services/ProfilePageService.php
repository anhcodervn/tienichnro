<?php

namespace App\Features\Client\Profile\Services;

use App\Models\User;
use App\Models\WalletTransaction;

class ProfilePageService
{
    /**
     * @return array<string, mixed>
     */
    public function data(User $user, string $activeTab): array
    {
        abort_unless(in_array($activeTab, ['profile', 'password', 'api', 'logs', 'wallet'], true), 404);

        $wallet = $user->wallet()->first(['id', 'user_id', 'balance']);

        return [
            'user' => $user,
            'activeTab' => $activeTab,
            'wallet' => $wallet,
            'apiKeys' => $activeTab === 'api'
                ? $user->apiKeys()
                    ->where('key_type', 'topup')
                    ->where('status', 'active')
                    ->latest('id')
                    ->get(['id', 'name', 'api_key', 'permissions', 'last_used_at', 'expired_at', 'created_at'])
                : collect(),
            'userLogs' => $activeTab === 'logs'
                ? $user->userLogs()->latest('id')->paginate(12, ['id', 'action', 'description', 'ip', 'user_agent', 'created_at'], 'logs_page')->withQueryString()
                : null,
            'walletTransactions' => $activeTab === 'wallet' && $wallet
                ? WalletTransaction::query()
                    ->where('wallet_id', $wallet->id)
                    ->latest('id')
                    ->paginate(12, ['id', 'wallet_id', 'type', 'amount', 'balance_after', 'description', 'status', 'created_at'], 'wallet_page')
                    ->withQueryString()
                : null,
        ];
    }
}
