<?php

namespace App\Features\Topup\Controllers;

use App\Features\Topup\Requests\CheckThe9pBalanceRequest;
use App\Features\Topup\Services\The9pBalanceService;
use App\Http\Controllers\Controller;
use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;

class The9pBalanceCronController extends Controller
{
    public function __invoke(CheckThe9pBalanceRequest $request, The9pBalanceService $balanceService): JsonResponse
    {
        $request->validated();
        $balance = $balanceService->check();

        return response()->json(ApiResponse::success(
            message: 'Đã kiểm tra số dư provider thành công.',
            data: [
                'balance' => $balance->balance,
                'currency' => $balance->currency,
                'warning_threshold' => $balance->warningThreshold,
                'is_below_warning_threshold' => $balance->isBelowWarningThreshold,
                'checked_at' => now()->toISOString(),
            ],
        ));
    }
}
