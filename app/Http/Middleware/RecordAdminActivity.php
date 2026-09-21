<?php

namespace App\Http\Middleware;

use App\Features\Admin\AuditLog\Services\AdminAuditLogService;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class RecordAdminActivity
{
    public function __construct(private readonly AdminAuditLogService $auditLogService) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $requestId = (string) Str::uuid();
        $previousAuditId = $this->latestAuditId($request);

        try {
            $response = $next($request);
            $this->record($request, $response->getStatusCode(), $startedAt, $requestId, $previousAuditId);

            return $response;
        } catch (Throwable $throwable) {
            $statusCode = $throwable instanceof HttpExceptionInterface ? $throwable->getStatusCode() : 500;
            $this->record($request, $statusCode, $startedAt, $requestId, $previousAuditId);

            throw $throwable;
        }
    }

    private function record(Request $request, int $statusCode, int $startedAt, string $requestId, int $previousAuditId): void
    {
        $admin = $request->user();

        if (! $admin instanceof User || $admin->role !== 'admin' || ! $this->isAdminRoute($request)) {
            return;
        }

        try {
            $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
            $this->auditLogService->recordRequest($request, $admin, $statusCode, $durationMs, $requestId, $previousAuditId);
        } catch (Throwable) {
            // Audit persistence must never alter the outcome of the admin request.
        }
    }

    private function latestAuditId(Request $request): int
    {
        if (! $this->isAdminRoute($request)) {
            return 0;
        }

        try {
            return $this->auditLogService->latestId();
        } catch (Throwable) {
            return 0;
        }
    }

    private function isAdminRoute(Request $request): bool
    {
        $middleware = $request->route()?->gatherMiddleware() ?? [];

        return in_array('admin', $middleware, true)
            || in_array(EnsureAdminUser::class, $middleware, true)
            || $request->is('admin', 'admin/*', 'api/admin-api/*', 'admin-api/*');
    }
}
