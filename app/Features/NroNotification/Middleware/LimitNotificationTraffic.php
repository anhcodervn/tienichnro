<?php

namespace App\Features\NroNotification\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class LimitNotificationTraffic
{
    public function handle(Request $request, Closure $next, string $mode = 'read'): Response
    {
        $limits = match ($mode) {
            'stream' => [10, 30], 'verify' => [5, 20], default => [60, 120]
        };
        $identity = $request->user()?->getAuthIdentifier() ?? ($request->hasSession() ? $request->session()->getId() : $request->ip());
        $keys = ['nro:'.$mode.':identity:'.hash('sha256', (string) $identity), 'nro:'.$mode.':ip:'.hash('sha256', (string) $request->ip())];
        foreach ($keys as $index => $key) {
            if (RateLimiter::tooManyAttempts($key, $limits[$index])) {
                return response()->json(['message' => 'Bạn thao tác quá nhiều lần. Vui lòng đợi rồi thử lại.'], 429,
                    ['Retry-After' => (string) RateLimiter::availableIn($key)]);
            }
        }
        foreach ($keys as $key) {
            RateLimiter::hit($key, 60);
        }
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
