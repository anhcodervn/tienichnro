<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $wallet = $user->wallet()->firstOrFail();
        $transactions = $wallet->transactions()->latest()->paginate(15);

        return view('client.account.wallet', compact('wallet', 'transactions'));
    }
}
