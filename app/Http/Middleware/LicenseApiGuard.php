<?php

namespace App\Http\Middleware;

use App\Features\License\Services\LicenseFailure;
use App\Models\LicenseEvent;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LicenseApiGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $limiter = new RateLimiter(Cache::store(config('license.cache_store')));
            $endpoint = $request->path();
            $buckets = ['ip:'.$request->ip() => 120];
            if ($request->filled('license_key')) {
                $buckets['key:'.hash('sha256', strtoupper(str_replace('-', '', (string) $request->input('license_key'))))] = 30;
            }
            if ($request->filled('device_uuid')) {
                $buckets['device:'.hash('sha256', (string) $request->input('device_uuid'))] = 60;
            }
            foreach ($buckets as $bucket => $limit) {
                $key = 'license:rate:'.$endpoint.':'.$bucket;
                if ($limiter->tooManyAttempts($key, $limit)) {
                    throw new LicenseFailure('RATE_LIMITED', 429);
                }
                $limiter->hit($key, 60);
            }
            $response = $next($request);
        } catch (LicenseFailure $failure) {
            try {
                LicenseEvent::query()->create(['event' => $failure->errorCode, 'ip' => $request->ip()]);
            } catch (Throwable) {
            }
            $response = response()->json(['success' => false, 'code' => $failure->errorCode, 'message' => $failure->errorCode], $failure->httpStatus);
        } catch (ValidationException $exception) {
            $response = response()->json(['success' => false, 'code' => 'INVALID_REQUEST', 'message' => 'Invalid request.', 'errors' => $exception->errors()], 422);
        } catch (AuthorizationException) {
            $response = response()->json(['success' => false, 'code' => 'FORBIDDEN', 'message' => 'Owner verification required.'], 403);
        } catch (Throwable) {
            $response = response()->json(['success' => false, 'code' => 'SERVICE_UNAVAILABLE', 'message' => 'License service unavailable.'], 503);
        }
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
