<?php

namespace App\Features\Client\Topup\Services;

use App\Support\SettingStore;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class TurnstileService
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    private const CHECKOUT_ACTION = 'guest_checkout';

    public function __construct(private readonly SettingStore $settingStore) {}

    public function isEnabled(): bool
    {
        return (bool) $this->settingStore->get('turnstile_enabled', false);
    }

    public function siteKey(): string
    {
        return $this->settingStore->getString('turnstile_site_key');
    }

    public function verifyOrFail(?string $token, ?string $ipAddress, string $idempotencyKey): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $secretKey = $this->settingStore->getString('turnstile_secret_key');
        $normalizedToken = trim((string) $token);

        if ($secretKey === '' || $normalizedToken === '') {
            $this->throwValidationException();
        }

        try {
            $response = Http::asForm()
                ->connectTimeout(3)
                ->timeout(8)
                ->post(self::VERIFY_URL, array_filter([
                    'secret' => $secretKey,
                    'response' => $normalizedToken,
                    'remoteip' => $ipAddress,
                    'idempotency_key' => $idempotencyKey,
                ]));
        } catch (\Throwable) {
            $this->throwValidationException('Không thể xác minh captcha lúc này. Vui lòng thử lại.');
        }

        if (! $response->successful()
            || $response->json('success') !== true
            || $response->json('action') !== self::CHECKOUT_ACTION) {
            $this->throwValidationException();
        }
    }

    private function throwValidationException(string $message = 'Xác minh captcha không thành công. Vui lòng thử lại.'): never
    {
        throw ValidationException::withMessages([
            'cf-turnstile-response' => $message,
        ]);
    }
}
