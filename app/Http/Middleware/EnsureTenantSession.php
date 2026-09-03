<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantSession
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->tenantContext->isActive()) {
            return $next($request);
        }

        $user = $request->user();

        if ($user instanceof User && $user->tenant_id !== $this->tenantContext->id()) {
            Auth::guard('web')->logout();
            abort(403, 'Phiên đăng nhập không thuộc website hiện tại.');
        }

        return $next($request);
    }
}
