<?php

namespace App\Features\Support;

use App\Utils\ApiResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class SupportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $tooManyMessagesResponse = static function (Request $request, array $headers): JsonResponse {
            $retryAfter = max(1, (int) ($headers['Retry-After'] ?? 10));

            return response()->json(ApiResponse::error(
                "Bạn gửi tin nhắn quá nhanh. Vui lòng chờ {$retryAfter} giây.",
                ['retry_after' => $retryAfter],
            ), 429, $headers);
        };

        RateLimiter::for('support-user-message', function (Request $request) use ($tooManyMessagesResponse): array {
            $userKey = 'user:'.($request->user()?->getAuthIdentifier() ?? $request->ip());

            return [
                Limit::perSecond(1, 10)
                    ->by("{$userKey}:ten-seconds")
                    ->response($tooManyMessagesResponse),
                Limit::perMinute(6)
                    ->by("{$userKey}:minute")
                    ->response($tooManyMessagesResponse),
            ];
        });
    }
}
