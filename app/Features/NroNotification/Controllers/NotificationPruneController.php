<?php

namespace App\Features\NroNotification\Controllers;

use App\Features\NroNotification\Services\NotificationRetentionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class NotificationPruneController extends Controller
{
    public function __invoke(Request $request, NotificationRetentionService $retention): JsonResponse
    {
        $key = (string) config('services.internal_cron.key', '');
        if (trim($key) === '') {
            return response()->json(['message' => 'Chưa cấu hình AUTOCRON_INTERNAL_KEY.'], 503);
        }
        $provided = (string) ($request->header('X-Cron-Key') ?? $request->bearerToken() ?? '');
        if ($provided === '' || ! hash_equals($key, $provided)) {
            return response()->json(['message' => 'Khóa cron không hợp lệ.'], 401);
        }

        $lock = Cache::lock('nro:notification-retention', 300);
        if (! $lock->get()) {
            return response()->json(['message' => 'Tác vụ xoá thông báo đang chạy.'], 409, ['Retry-After' => '60']);
        }
        try {
            return response()->json(['status' => true, 'data' => $retention->prune()], 200, ['Cache-Control' => 'no-store']);
        } finally {
            $lock->release();
        }
    }
}
