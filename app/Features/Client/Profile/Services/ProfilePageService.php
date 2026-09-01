<?php

namespace App\Features\Client\Profile\Services;

use App\Features\MemberLevel\Services\MemberLevelService;
use App\Features\Topup\Services\OrderRecipientService;
use App\Models\User;
use App\Models\WalletTransaction;

class ProfilePageService
{
    public function __construct(private readonly MemberLevelService $memberLevelService) {}

    /**
     * @return array<string, mixed>
     */
    public function data(User $user, string $activeTab): array
    {
        abort_unless(in_array($activeTab, ['profile', 'password', 'api', 'api-docs', 'logs', 'wallet'], true), 404);

        $wallet = $user->wallet()->first(['id', 'user_id', 'balance']);

        return [
            'user' => $user,
            'activeTab' => $activeTab,
            'wallet' => $wallet,
            'memberLevelStatus' => $this->memberLevelService->status($user),
            'memberLevelHistories' => $activeTab === 'profile'
                ? $user->memberLevelHistories()
                    ->with(['fromLevel:id,name,color', 'toLevel:id,name,color'])
                    ->latest('id')
                    ->limit(8)
                    ->get()
                : collect(),
            'apiKeys' => $activeTab === 'api'
                ? $user->apiKeys()
                    ->where('key_type', 'topup')
                    ->where('status', 'active')
                    ->latest('id')
                    ->get(['id', 'name', 'api_key', 'permissions', 'last_used_at', 'expired_at', 'created_at'])
                : collect(),
            'apiDocumentation' => $activeTab === 'api-docs'
                ? [
                    'base_url' => url('/api/v1'),
                    'balance_endpoint' => route('api.v1.balance'),
                    'catalog_endpoint' => route('api.v1.catalog'),
                    'create_order_endpoint' => route('api.v1.orders.store'),
                    'order_status_endpoint' => route('api.v1.orders.show', ['order' => 'ORDER_ID']),
                    'max_recipients' => OrderRecipientService::MAX_RECIPIENTS,
                    'max_quantity_per_recipient' => OrderRecipientService::MAX_QUANTITY_PER_RECIPIENT,
                ]
                : null,
            'userLogs' => $activeTab === 'logs'
                ? $user->userLogs()->latest('id')->paginate(12, ['id', 'action', 'description', 'ip', 'user_agent', 'created_at'], 'logs_page')->withQueryString()
                : null,
            'walletTransactions' => $activeTab === 'wallet' && $wallet
                ? WalletTransaction::query()
                    ->where('wallet_id', $wallet->id)
                    ->latest('id')
                    ->paginate(12, ['id', 'wallet_id', 'type', 'amount', 'balance_before', 'balance_after', 'description', 'status', 'created_at'], 'wallet_page')
                    ->withQueryString()
                : null,
        ];
    }
}
