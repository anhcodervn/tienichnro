<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\GameServiceSecondaryAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGameServiceSecondaryAuth
{
    public function __construct(private readonly GameServiceSecondaryAuth $secondaryAuth) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        if (! $this->secondaryAuth->isConfigured($user)) {
            return response()->json([
                'status' => false,
                'code' => 'SECONDARY_PASSWORD_NOT_CONFIGURED',
                'message' => $user->role === User::ROLE_COLLABORATOR
                    ? 'Tài khoản CTV chưa được quản trị viên cấu hình mật khẩu C2.'
                    : 'Mật khẩu C2 global của admin chưa được cấu hình trên hệ thống.',
            ], 503);
        }

        if (! $this->secondaryAuth->isUnlocked($user, $request)) {
            return response()->json([
                'status' => false,
                'code' => 'SECONDARY_PASSWORD_REQUIRED',
                'message' => 'Vui lòng nhập mật khẩu C2 để tiếp tục.',
            ], 423);
        }

        return $next($request);
    }
}
