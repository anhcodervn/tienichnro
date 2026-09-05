<?php

namespace App\Features\Affiliate\Services;

use App\Models\AffiliateProfile;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class AffiliateReferralService
{
    public const COOKIE_NAME = 'affiliate_referral';

    public const SESSION_KEY = 'affiliate.referral';

    public const COOKIE_MINUTES = 43200;

    private const PERSIST_COOKIE_ATTRIBUTE = 'affiliate.persist_cookie';

    public function __construct(private readonly AffiliateProgramService $programService) {}

    public function capture(Request $request, string $code): bool
    {
        if ($this->restore($request)) {
            return false;
        }

        return $this->captureFresh($request, $code);
    }

    public function restore(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        if ($this->referrer($request) instanceof User) {
            return true;
        }

        $cookieValue = $request->cookie(self::COOKIE_NAME);

        if (! is_string($cookieValue) || $cookieValue === '') {
            return false;
        }

        $payload = json_decode($cookieValue, true);

        if (! is_array($payload)) {
            return $this->captureFresh($request, $cookieValue);
        }

        $program = $this->programService->enabled();

        if ($program === null
            || (int) ($payload['tenant_id'] ?? 0) !== $program->tenant_id
            || ! $this->isTimestamp($payload['captured_at'] ?? null)
            || ! $this->isFutureTimestamp($payload['expires_at'] ?? null)) {
            return false;
        }

        $referrer = $this->activeReferrerByCode($program->tenant_id, (string) ($payload['code'] ?? ''));

        if (! $referrer instanceof User || $request->user()?->is($referrer)) {
            return false;
        }

        $this->store($request, $referrer, (string) $payload['captured_at'], (string) $payload['expires_at']);

        return true;
    }

    public function referrer(Request $request): ?User
    {
        if (! $request->hasSession()) {
            return null;
        }

        $payload = $request->session()->get(self::SESSION_KEY);
        $program = $this->programService->enabled();

        if (! is_array($payload)
            || $program === null
            || (int) ($payload['tenant_id'] ?? 0) !== $program->tenant_id
            || ! $this->isFutureTimestamp($payload['expires_at'] ?? null)) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        $referrer = $this->activeReferrerByCode($program->tenant_id, (string) ($payload['code'] ?? ''));

        if (! $referrer instanceof User || $request->user()?->is($referrer)) {
            return null;
        }

        return $referrer;
    }

    /** @return array{referrer_id: int, referral_code: string, source: string, attributed_at: string}|null */
    public function attribution(Request $request, ?User $buyer, string $customerEmail): ?array
    {
        $program = $this->programService->enabled();

        if ($program === null) {
            return null;
        }

        if ($buyer instanceof User && $buyer->referred_by) {
            $referrer = $this->activeReferrerById($program->tenant_id, (int) $buyer->referred_by);
            $source = Order::AFFILIATE_SOURCE_REGISTERED;
            $attributedAt = now()->toISOString();
        } else {
            if (! $request->hasSession()) {
                return null;
            }

            $this->restore($request);
            $referrer = $this->referrer($request);
            $payload = $request->session()->get(self::SESSION_KEY);
            $source = Order::AFFILIATE_SOURCE_COOKIE;
            $attributedAt = is_array($payload) ? (string) ($payload['captured_at'] ?? now()->toISOString()) : now()->toISOString();
        }

        if (! $referrer instanceof User || $this->isSelfReferral($referrer, $buyer, $customerEmail)) {
            return null;
        }

        return [
            'referrer_id' => $referrer->id,
            'referral_code' => (string) $referrer->referral_code,
            'source' => $source,
            'attributed_at' => $attributedAt,
        ];
    }

    /**
     * @param  array{referrer_id?: mixed, referral_code?: mixed, source?: mixed, attributed_at?: mixed}|null  $attribution
     * @return array{referrer: User, referral_code: string, source: string, attributed_at: string}|null
     */
    public function validateForOrder(?array $attribution, ?User $buyer, string $customerEmail, int $tenantId): ?array
    {
        if ($attribution === null
            || ! in_array($attribution['source'] ?? null, [Order::AFFILIATE_SOURCE_REGISTERED, Order::AFFILIATE_SOURCE_COOKIE], true)) {
            return null;
        }

        $program = $this->programService->enabled();

        if ($program === null || $program->tenant_id !== $tenantId) {
            return null;
        }

        $referrer = User::query()
            ->whereKey((int) ($attribution['referrer_id'] ?? 0))
            ->where('tenant_id', $tenantId)
            ->where('referral_code', (string) ($attribution['referral_code'] ?? ''))
            ->where('status', 'active')
            ->whereDoesntHave('affiliateProfile', fn ($query) => $query->where('status', 'suspended'))
            ->lockForUpdate()
            ->first();

        if (! $referrer instanceof User || $this->isSelfReferral($referrer, $buyer, $customerEmail)) {
            return null;
        }

        return [
            'referrer' => $referrer,
            'referral_code' => (string) $referrer->referral_code,
            'source' => (string) $attribution['source'],
            'attributed_at' => $this->isTimestamp($attribution['attributed_at'] ?? null)
                ? (string) $attribution['attributed_at']
                : now()->toISOString(),
        ];
    }

    public function cookieValue(Request $request): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        $payload = $request->session()->get(self::SESSION_KEY);

        if (! is_array($payload)) {
            return null;
        }

        $encoded = json_encode($payload);

        return is_string($encoded) ? $encoded : null;
    }

    public function shouldPersistCookie(Request $request): bool
    {
        return $request->attributes->getBoolean(self::PERSIST_COOKIE_ATTRIBUTE);
    }

    public function forget(Request $request): void
    {
        if ($request->hasSession()) {
            $request->session()->forget(self::SESSION_KEY);
        }
    }

    private function captureFresh(Request $request, string $code): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        $program = $this->programService->enabled();
        $code = Str::upper(trim($code));

        if ($program === null || $code === '' || $request->user()?->referral_code === $code) {
            return false;
        }

        $referrer = $this->activeReferrerByCode($program->tenant_id, $code);

        if (! $referrer instanceof User) {
            return false;
        }

        AffiliateProfile::query()->firstOrCreate(
            ['user_id' => $referrer->id],
            ['tenant_id' => $program->tenant_id, 'status' => 'active'],
        );

        $capturedAt = CarbonImmutable::now();
        $this->store(
            $request,
            $referrer,
            $capturedAt->toISOString(),
            $capturedAt->addMinutes(self::COOKIE_MINUTES)->toISOString(),
        );
        $request->attributes->set(self::PERSIST_COOKIE_ATTRIBUTE, true);

        return true;
    }

    private function activeReferrerByCode(int $tenantId, string $code): ?User
    {
        return User::query()
            ->where('tenant_id', $tenantId)
            ->where('referral_code', Str::upper(trim($code)))
            ->where('status', 'active')
            ->whereDoesntHave('affiliateProfile', fn ($query) => $query->where('status', 'suspended'))
            ->first();
    }

    private function activeReferrerById(int $tenantId, int $referrerId): ?User
    {
        return User::query()
            ->whereKey($referrerId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereDoesntHave('affiliateProfile', fn ($query) => $query->where('status', 'suspended'))
            ->first();
    }

    private function isSelfReferral(User $referrer, ?User $buyer, string $customerEmail): bool
    {
        if ($buyer?->is($referrer)) {
            return true;
        }

        $referrerEmail = Str::lower(trim((string) $referrer->email));
        $customerEmail = Str::lower(trim($customerEmail));

        return $referrerEmail !== '' && $customerEmail !== '' && $referrerEmail === $customerEmail;
    }

    private function isFutureTimestamp(mixed $value): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }

        try {
            return CarbonImmutable::parse($value)->isFuture();
        } catch (Throwable) {
            return false;
        }
    }

    private function isTimestamp(mixed $value): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }

        try {
            CarbonImmutable::parse($value);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function store(Request $request, User $referrer, string $capturedAt, string $expiresAt): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'code' => $referrer->referral_code,
            'tenant_id' => $referrer->tenant_id,
            'captured_at' => $capturedAt,
            'expires_at' => $expiresAt,
        ]);
    }
}
