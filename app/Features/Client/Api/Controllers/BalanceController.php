<?php

namespace App\Features\Client\Api\Controllers;

use App\Exceptions\ApiException;
use App\Features\Client\Wallet\Services\WalletService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BalanceController extends Controller
{
    public function __construct(private readonly WalletService $walletService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        if ($user->status !== 'active') {
            throw new ApiException('Tài khoản không thể sử dụng API.', 403);
        }

        $wallet = $this->walletService->getWallet($user);

        return response()->json([
            'status' => true,
            'data' => [
                'balance' => (int) $wallet->balance,
                'currency' => 'VND',
            ],
        ]);
    }
}
