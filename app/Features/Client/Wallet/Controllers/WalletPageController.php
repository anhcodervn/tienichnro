<?php

namespace App\Features\Client\Wallet\Controllers;

use App\Features\Client\Wallet\Services\WalletService;
use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class WalletPageController extends Controller
{
    public function index(Request $request, WalletService $service): View
    {
        $wallet = $service->wallet($request->user());

        return view('client.wallet.index', [
            'wallet' => $wallet,
            'transactions' => WalletTransaction::query()->where('wallet_id', $wallet->id)->latest('id')->paginate(20),
        ]);
    }
}
