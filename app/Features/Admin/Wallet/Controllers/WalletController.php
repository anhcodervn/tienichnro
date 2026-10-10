<?php

namespace App\Features\Admin\Wallet\Controllers;

use App\Features\Admin\Wallet\Requests\AdjustWalletRequest;
use App\Features\Client\Wallet\Services\WalletService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;

class WalletController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Wallet::query()->join('users', 'users.id', '=', 'wallets.user_id')
            ->whereNull('users.deleted_at')->select(['wallets.*', 'users.username', 'users.full_name'])->latest('wallets.id')->get()]);
    }

    public function transactions(User $user, WalletService $service): JsonResponse
    {
        $wallet = $service->wallet($user);

        return response()->json(WalletTransaction::query()->where('wallet_id', $wallet->id)->latest('id')->paginate(20));
    }

    public function adjust(AdjustWalletRequest $request, User $user, WalletService $service): JsonResponse
    {
        $values = $request->validated();
        $transaction = $service->apply($user, $values['amount'], $values['direction'], 'admin:'.$values['request_id'], $values['description'], $request->user()->id);

        return response()->json(['data' => $transaction, 'balance' => $service->wallet($user)->balance]);
    }
}
