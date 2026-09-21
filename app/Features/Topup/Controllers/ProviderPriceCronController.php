<?php

namespace App\Features\Topup\Controllers;

use App\Features\Topup\Requests\RunProviderPriceCronRequest;
use App\Features\Topup\Services\ProviderPriceProtectionService;
use App\Http\Controllers\Controller;
use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ProviderPriceCronController extends Controller
{
    public function __invoke(
        RunProviderPriceCronRequest $request,
        ProviderPriceProtectionService $priceProtectionService,
    ): JsonResponse {
        $request->validated();
        $lock = Cache::lock('cron:provider-prices', 600);

        if (! $lock->get()) {
            return response()->json(ApiResponse::error('Tiến trình cập nhật giá provider đang chạy.'), 409);
        }

        try {
            $result = $priceProtectionService->run();
        } finally {
            $lock->release();
        }

        return response()->json(ApiResponse::success(
            message: 'Đã đồng bộ giá provider và kiểm tra lợi nhuận gói nạp.',
            data: [...$result, 'checked_at' => now()->toISOString()],
        ));
    }
}
