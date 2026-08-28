<?php

namespace App\Features\Recharge\Controllers;

use App\Features\Recharge\Requests\PollApiBankVnTransactionsRequest;
use App\Features\Recharge\Services\ApiBankVnTransactionPollingService;
use App\Http\Controllers\Controller;
use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ApiBankVnTransactionCronController extends Controller
{
    public function __invoke(
        PollApiBankVnTransactionsRequest $request,
        ApiBankVnTransactionPollingService $pollingService,
    ): JsonResponse {
        $request->validated();
        $lock = Cache::lock('cron:apibankvn:transactions', 300);

        if (! $lock->get()) {
            return response()->json(ApiResponse::error('Tien trinh lay giao dich APIBankVN dang chay.'), 409);
        }

        try {
            $result = $pollingService->poll(
                $request->startDate(),
                $request->endDate(),
                $request->transactionLimit(),
                $request->shouldForceRefresh(),
            );
        } finally {
            $lock->release();
        }

        return response()->json(ApiResponse::success(
            message: 'Da kiem tra giao dich APIBankVN.',
            data: [...$result, 'checked_at' => now()->toISOString()],
        ));
    }
}
