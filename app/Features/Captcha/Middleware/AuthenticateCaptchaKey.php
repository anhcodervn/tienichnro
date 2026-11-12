<?php

namespace App\Features\Captcha\Middleware;

use App\Models\CaptchaApiKey;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCaptchaKey
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        abort_unless(is_string($token) && strlen($token) >= 40 && strlen($token) <= 100, 401, 'API token không hợp lệ.');
        $key = CaptchaApiKey::query()->where('token_hash', hash('sha256', $token))->first();
        $user = $key ? User::query()->find($key->user_id) : null;
        abort_unless($user && $user->status === 'active', 401, 'API token không hợp lệ hoặc đã thu hồi.');
        $request->setUserResolver(fn (): User => $user);
        $key->update(['last_used_at' => now()]);

        return $next($request);
    }
}
