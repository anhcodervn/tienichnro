<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Utils\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->role !== 'admin') {
            return $this->forbiddenResponse('Chức năng này chỉ dành cho quản trị viên NapCarot.');
        }

        return $next($request);
    }

    private function forbiddenResponse(string $message): JsonResponse
    {
        return response()->json(ApiResponse::error($message), 403);
    }
}
