<?php

namespace App\Features\Affiliate\Services;

use App\Models\AffiliateProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AffiliateReferralService
{
    public const COOKIE_NAME = 'affiliate_referral';

    public const SESSION_KEY = 'affiliate.referral';

    public const COOKIE_MINUTES = 43200;

    public function __construct(private readonly AffiliateProgramService $programService) {}

    public function capture(Request $request, string $code): bool
    {
        $program = $this->programService->enabled();
        $code = Str::upper(trim($code));

        if ($program === null || $code === '' || $request->user()?->referral_code === $code) {
            return false;
        }

        $referrer = User::query()
            ->where('tenant_id', $program->tenant_id)
            ->where('referral_code', $code)
            ->where('status', 'active')
            ->whereDoesntHave('affiliateProfile', fn ($query) => $query->where('status', 'suspended'))
            ->first();

        if (! $referrer instanceof User) {
            return false;
        }

        AffiliateProfile::query()->firstOrCreate(
            ['user_id' => $referrer->id],
            ['tenant_id' => $program->tenant_id, 'status' => 'active'],
        );

        $request->session()->put(self::SESSION_KEY, [
            'code' => $referrer->referral_code,
            'tenant_id' => $program->tenant_id,
            'captured_at' => now()->toISOString(),
        ]);

        return true;
    }

    public function restore(Request $request): bool
    {
        if ($request->session()->has(self::SESSION_KEY)) {
            return true;
        }

        $code = $request->cookie(self::COOKIE_NAME);

        return is_string($code) && $this->capture($request, $code);
    }

    public function referrer(Request $request): ?User
    {
        $payload = $request->session()->get(self::SESSION_KEY);

        if (! is_array($payload) || (int) ($payload['tenant_id'] ?? 0) !== $this->programService->tenantId()) {
            return null;
        }

        return User::query()
            ->where('tenant_id', $payload['tenant_id'])
            ->where('referral_code', $payload['code'] ?? '')
            ->where('status', 'active')
            ->whereDoesntHave('affiliateProfile', fn ($query) => $query->where('status', 'suspended'))
            ->first();
    }

    public function forget(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }
}
