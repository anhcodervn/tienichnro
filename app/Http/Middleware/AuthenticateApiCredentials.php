<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\ApiKey;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiCredentials
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $providedKey = trim((string) $request->header('X-API-Key'));
        $providedSecret = trim((string) $request->header('X-API-Secret'));

        $apiKey = $providedKey !== ''
            ? ApiKey::query()->with('user')->where('api_key', $providedKey)->first()
            : null;

        $credentialsMatch = $apiKey instanceof ApiKey
            && hash_equals($apiKey->api_key, $providedKey)
            && $providedSecret !== ''
            && Hash::check($providedSecret, $apiKey->api_secret_hash);

        if (! $credentialsMatch || $apiKey->status !== 'active' || $apiKey->key_type !== 'topup') {
            throw new ApiException('API key hoặc API secret không hợp lệ.', 401);
        }

        if ($apiKey->expired_at?->isPast()) {
            throw new ApiException('API key đã hết hạn.', 401);
        }

        $user = $apiKey->user;
        if (! $user instanceof User || $user->status !== 'active') {
            throw new ApiException('Tài khoản không thể sử dụng API.', 403);
        }

        if (! in_array($permission, $apiKey->permissions ?? [], true)) {
            throw new ApiException('API key không có quyền thực hiện thao tác này.', 403);
        }

        $allowedIps = $apiKey->ip_whitelist ?? [];
        if ($allowedIps !== [] && ! in_array($request->ip(), $allowedIps, true)) {
            throw new ApiException('Địa chỉ IP không được phép sử dụng API key này.', 403);
        }

        $request->setUserResolver(fn (): User => $user);
        $request->attributes->set('api_key', $apiKey);

        if ($apiKey->last_used_at === null || $apiKey->last_used_at->lte(now()->subMinutes(5))) {
            ApiKey::query()->whereKey($apiKey->id)->update(['last_used_at' => now()]);
        }

        return $next($request);
    }
}
