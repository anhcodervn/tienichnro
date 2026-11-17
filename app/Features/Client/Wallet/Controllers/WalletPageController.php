<?php

namespace App\Features\Client\Wallet\Controllers;

use App\Features\Client\Wallet\Services\WalletService;
use App\Features\Recharge\Services\RechargeConfigurationService;
use App\Http\Controllers\Controller;
use App\Models\WalletRechargeOrder;
use App\Models\WalletTransaction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class WalletPageController extends Controller
{
    public function index(Request $request, WalletService $service, RechargeConfigurationService $recharge): View
    {
        $wallet = $service->wallet($request->user());

        return view('client.wallet.index', [
            'wallet' => $wallet,
            'recharge' => $recharge->snapshot(),
            'rechargeOrders' => WalletRechargeOrder::query()->where('user_id', $request->user()->id)->select(['id', 'code', 'amount', 'status', 'created_at'])->latest('id')->paginate(20, ['*'], 'recharge_page')->withQueryString(),
            'transactions' => WalletTransaction::query()->where('wallet_id', $wallet->id)->latest('id')->paginate(20)->withQueryString(),
        ]);
    }
}
