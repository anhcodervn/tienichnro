<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->tenantContext->isActive()) {
            return $next($request);
        }

        $host = Str::lower($request->getHost());
        $tenantDomain = TenantDomain::query()
            ->with('tenant')
            ->where('domain', $host)
            ->first();
        $tenant = $tenantDomain?->tenant;

        if (! $tenant instanceof Tenant && in_array($host, config('tenancy.fallback_hosts', []), true)) {
            $tenant = Tenant::query()->where('is_main', true)->first();
        }

        if (! $tenant instanceof Tenant) {
            return $this->unavailableResponse(
                request: $request,
                statusCode: 404,
                state: 'unregistered',
                title: 'Tên miền đã được trỏ thành công nhưng website chưa được kích hoạt',
                description: 'Nếu bạn là chủ website, hãy liên hệ quản trị viên để kiểm tra tên miền và hoàn tất kích hoạt.',
            );
        }

        $this->tenantContext->set($tenant);
        $request->attributes->set('tenant', $tenant);
        $originalStatefulDomains = config('sanctum.stateful', []);
        $this->configureSanctumStatefulDomain($request);
        URL::forceRootUrl($request->getSchemeAndHttpHost());

        try {
            if ($tenantDomain instanceof TenantDomain && ! $tenantDomain->is_verified) {
                return $this->unavailableResponse(
                    request: $request,
                    statusCode: 503,
                    state: 'unverified',
                    title: 'Tên miền đã được trỏ thành công nhưng website chưa được kích hoạt',
                    description: 'Nếu bạn là chủ website, hãy liên hệ quản trị viên để kiểm tra tên miền và hoàn tất kích hoạt.',
                    siteName: $tenant->is_main ? null : $tenant->name,
                );
            }

            if ($tenant->status !== 'active') {
                return $this->unavailableResponse(
                    request: $request,
                    statusCode: 503,
                    state: $tenant->status === 'suspended' ? 'suspended' : 'pending',
                    title: $tenant->status === 'suspended'
                        ? 'Website đang tạm ngưng'
                        : 'Tên miền đã được trỏ thành công nhưng website chưa được kích hoạt',
                    description: $tenant->status === 'suspended'
                        ? 'Website hiện tạm ngưng phục vụ. Vui lòng liên hệ quản trị viên của website để biết thêm thông tin.'
                        : 'Nếu bạn là chủ website, hãy liên hệ quản trị viên để kiểm tra tên miền và hoàn tất kích hoạt.',
                    siteName: $tenant->is_main ? null : $tenant->name,
                );
            }

            return $next($request);
        } finally {
            $this->tenantContext->clear();
            config()->set('sanctum.stateful', $originalStatefulDomains);
            URL::forceRootUrl((string) config('app.url'));
        }
    }

    private function unavailableResponse(
        Request $request,
        int $statusCode,
        string $state,
        string $title,
        string $description,
        ?string $siteName = null,
    ): Response {
        $data = [
            'site_unavailable' => true,
            'state' => $state,
            'domain' => Str::lower($request->getHost()),
        ];

        if ($request->expectsJson() || $request->is('api/*') || ! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return response()->json([
                'status' => false,
                'message' => $description,
                'data' => $data,
            ], $statusCode);
        }

        return response()->view('pages.site-unavailable', [
            ...$data,
            'statusCode' => $statusCode,
            'title' => $title,
            'description' => $description,
            'siteName' => filled($siteName) ? $siteName : null,
        ], $statusCode);
    }

    private function configureSanctumStatefulDomain(Request $request): void
    {
        $statefulDomains = config('sanctum.stateful', []);

        if (! is_array($statefulDomains)) {
            $statefulDomains = [];
        }

        config()->set('sanctum.stateful', array_values(array_unique([
            ...$statefulDomains,
            Str::lower($request->getHost()),
            Str::lower($request->getHttpHost()),
        ])));
    }
}
