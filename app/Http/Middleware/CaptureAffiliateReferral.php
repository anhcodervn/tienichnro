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
        $canCaptureQuery = in_array($request->method(), ['GET', 'HEAD'], true);

        if ($canCaptureQuery && is_string($referralCode)) {
            $this->referralService->capture($request, $referralCode);
        } else {
            $this->referralService->restore($request);
        }

        $response = $next($request);

        $cookieValue = $this->referralService->cookieValue($request);

        if ($this->referralService->shouldPersistCookie($request) && $cookieValue !== null) {
            $response->headers->setCookie(cookie(
                AffiliateReferralService::COOKIE_NAME,
                $cookieValue,
                AffiliateReferralService::COOKIE_MINUTES,
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                'lax',
            ));
        }

        return $response;
    }
}
