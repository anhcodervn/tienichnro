<?php

use App\Features\License\Services\LicenseFailure;
use App\Http\Middleware\EnsureAdminUser;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureSiteIsActive;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\RecordAdminActivity;
use App\Models\LicenseEvent;
use App\Support\SettingStore;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['web', 'auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', EnsureSiteIsActive::class);
        $middleware->appendToGroup('web', RecordAdminActivity::class);
        $middleware->appendToGroup('api', RecordAdminActivity::class);
        $middleware->statefulApi();
        $middleware->trustProxies(at: '*');
        $middleware->trimStrings(except: ['content.*', '*_content.*', 'custom_css', 'custom_js', 'custom_head_tags', 'custom_script']);
        $middleware->convertEmptyStringsToNull(except: [
            fn (Request $request): bool => $request->is('api/admin-api/settings/custom-code'),
        ]);
        $middleware->alias([
            'admin' => EnsureAdminUser::class,
            'platform.admin' => EnsurePlatformAdmin::class,
            'site.active' => EnsureSiteIsActive::class,
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $throwable, Request $request) {
            if (! $request->is('api/v1/licenses/*')) {
                return null;
            }

            $status = 503;
            $code = 'SERVICE_UNAVAILABLE';
            $errors = [];
            if ($throwable instanceof LicenseFailure) {
                $status = $throwable->httpStatus;
                $code = $throwable->errorCode;
            } elseif ($throwable instanceof ValidationException) {
                $status = 422;
                $code = 'INVALID_REQUEST';
                $errors = $throwable->errors();
            } elseif ($throwable instanceof AuthenticationException) {
                $status = 401;
                $code = 'UNAUTHENTICATED';
            } elseif ($throwable instanceof AuthorizationException) {
                $status = 403;
                $code = 'FORBIDDEN';
            } elseif ($throwable instanceof HttpExceptionInterface && in_array($throwable->getStatusCode(), [401, 403, 429], true)) {
                $status = $throwable->getStatusCode();
                $code = match ($status) {
                    401 => 'UNAUTHENTICATED',
                    403 => 'FORBIDDEN',
                    429 => 'RATE_LIMITED',
                };
            }

            if ($throwable instanceof LicenseFailure) {
                try {
                    LicenseEvent::query()->create(['event' => $code, 'ip' => $request->ip()]);
                } catch (Throwable) {
                }
            }

            return response()->json(['success' => false, 'code' => $code, 'message' => $code, ...($errors ? ['errors' => $errors] : [])], $status)
                ->header('Cache-Control', 'no-store, private');
        });

        $exceptions->render(function (Throwable $throwable, Request $request) {
            if ($request->expectsJson() || $request->is('api/*') || $request->is('admin-api/*')) {
                return null;
            }

            if (! $throwable instanceof HttpExceptionInterface) {
                return null;
            }

            $statusCode = $throwable->getStatusCode();

            if (! in_array($statusCode, [401, 403, 404, 419, 429, 500, 503, 520, 524], true)) {
                return null;
            }

            $settingDefaults = [
                'site_name' => config('app.name', 'Nạp Carot'),
                'site_description' => '',
                'support_email' => '',
                'hotline' => '',
                'light_logo' => '',
                'dark_logo' => '',
                'favicon' => '',
            ];

            try {
                /** @var SettingStore $settingStore */
                $settingStore = app(SettingStore::class);
                $systemSettings = $settingStore->getMany($settingDefaults);
            } catch (Throwable) {
                $systemSettings = $settingDefaults;
            }

            $context = 'landing';

            if ($request->is('admin*')) {
                $context = 'admin';
            } elseif ($request->is('tai-khoan*', 'nap-tien*', 'don-hang*')) {
                $context = 'client';
            }

            $contextActions = [
                'landing' => [
                    'primary' => ['label' => 'Về trang chủ', 'href' => '/'],
                    'secondary' => ['label' => 'Liên hệ hỗ trợ', 'href' => '/lien-he'],
                ],
                'client' => [
                    'primary' => ['label' => 'Về tổng quan', 'href' => '/'],
                    'secondary' => ['label' => 'Hướng dẫn', 'href' => '/huong-dan'],
                ],
                'admin' => [
                    'primary' => ['label' => 'Về dashboard admin', 'href' => '/admin'],
                    'secondary' => ['label' => 'Quản lý queue', 'href' => '/admin/queues'],
                ],
            ];

            return response()->view("errors.{$statusCode}", [
                'errorContext' => $context,
                'errorActions' => Arr::get($contextActions, $context, $contextActions['landing']),
                'systemSettings' => $systemSettings,
            ], $statusCode);
        });
    })->create();
