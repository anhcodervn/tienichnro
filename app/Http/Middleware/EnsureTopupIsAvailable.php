<?php

namespace App\Http\Middleware;

use App\Support\SettingStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTopupIsAvailable
{
    public function __construct(private readonly SettingStore $settingStore) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $settings = $this->settingStore->getMany([
            'topup_maintenance_enabled' => false,
            'topup_maintenance_message' => 'Cổng nạp game đang bảo trì. Vui lòng quay lại sau.',
        ]);

        if (! (bool) $settings['topup_maintenance_enabled']) {
            return $next($request);
        }

        $message = trim((string) $settings['topup_maintenance_message'])
            ?: 'Cổng nạp game đang bảo trì. Vui lòng quay lại sau.';

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => false,
                'message' => $message,
                'data' => ['maintenance' => true],
            ], 503);
        }

        return back()->withErrors(['topup' => $message]);
    }
}
