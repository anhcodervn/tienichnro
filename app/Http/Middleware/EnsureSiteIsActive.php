<?php

namespace App\Http\Middleware;

use App\Support\SettingStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSiteIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var SettingStore $settingStore */
        $settingStore = app(SettingStore::class);
        if ($request->routeIs('maintenance') || $request->is(
            'admin',
            'admin/*',
            'dang-nhap',
            'dang-nhap/*',
            'login',
            'auth/login',
            'auth/google/*',
            'site-custom.css',
            'site-custom.js',
            'robots.txt',
            'ads.txt',
        )) {
            return $next($request);
        }

        $settings = $settingStore->getMany([
            'site_active' => true,
        ]);

        if ((bool) ($settings['site_active'] ?? true)) {
            return $next($request);
        }

        if ($request->expectsJson() || ! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return response()->json([
                'status' => false,
                'message' => 'Hệ thống đang bảo trì. Vui lòng thử lại sau.',
                'data' => [
                    'maintenance' => true,
                ],
            ], 503);
        }

        return redirect()->route('maintenance');
    }
}
