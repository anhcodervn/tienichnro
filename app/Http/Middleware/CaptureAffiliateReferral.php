<?php

namespace App\Http\Middleware;

use App\Features\Affiliate\Services\AffiliateReferralService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureAffiliateReferral
{
    public function __construct(private readonly AffiliateReferralService $referralService) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $referralCode = $request->query('ref');
        $captured = is_string($referralCode)
            ? $this->referralService->capture($request, $referralCode)
            : $this->referralService->restore($request);
        $response = $next($request);

        if ($captured) {
            $payload = $request->session()->get(AffiliateReferralService::SESSION_KEY);
            $code = is_array($payload) ? ($payload['code'] ?? null) : null;

            if (is_string($code) && $code !== '') {
                $response->headers->setCookie(cookie(
                    AffiliateReferralService::COOKIE_NAME,
                    $code,
                    AffiliateReferralService::COOKIE_MINUTES,
                    '/',
                    null,
                    $request->isSecure(),
                    true,
                    false,
                    'lax',
                ));
            }
        }

        return $response;
    }
}
