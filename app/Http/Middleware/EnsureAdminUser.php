<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\TenantContext;
use App\Utils\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminUser
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->forbiddenResponse('Bạn cần đăng nhập để truy cập API admin.');
        }

        if ($user->role !== 'admin'
            || ($this->tenantContext->isActive() && $user->tenant_id !== $this->tenantContext->id())) {
            return $this->forbiddenResponse('Bạn không có quyền truy cập API admin.');
        }

        return $next($request);
    }

    private function forbiddenResponse(string $message): JsonResponse
    {
        return response()->json(ApiResponse::error($message), 403);
    }
}
