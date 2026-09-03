<?php

namespace App\Features\Client\Wallet\Controllers;

use App\Features\Client\Wallet\Resources\ClientRechargeConfigResource;
use App\Features\Client\Wallet\Services\WalletDepositService;
use App\Features\Client\Wallet\Services\WalletService;
use App\Features\Recharge\Services\RechargeBonusService;
use App\Http\Controllers\Controller;
use App\Models\RechargeBonusTier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepositPageController extends Controller
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly WalletDepositService $walletDepositService,
        private readonly RechargeBonusService $rechargeBonusService,
    ) {}

    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $rechargeConfigs = collect($this->walletDepositService->clientConfigs($user))
            ->map(fn (array $item): array => (new ClientRechargeConfigResource($item['config']))->resolve())
            ->values()
            ->all();

        $depositHistory = $user->paymentTransactions()
            ->whereNull('order_id')
            ->latest('id')
            ->paginate(10, [
                'id',
                'user_id',
                'transaction_code',
                'amount',
                'status',
                'raw_data',
                'created_at',
            ])
            ->withQueryString();

        return view('client.wallet.deposit', [
            'user' => $user,
            'wallet' => $this->walletService->getWalletInfo($user),
            'rechargeConfigs' => $rechargeConfigs,
            'depositHistory' => $depositHistory,
            'pendingDeposit' => $this->walletDepositService->latestPendingRequest($user),
            'bonusTiers' => $this->rechargeBonusService->active()
                ->map(fn (RechargeBonusTier $tier): array => $this->rechargeBonusService->serialize($tier))
                ->values()
                ->all(),
        ]);
    }
}
