<?php

namespace App\Features\Client\GamePassword\Services;

use App\Support\SettingStore;
use Illuminate\Support\Facades\DB;

class GamePasswordConfigurationService
{
    public function __construct(private readonly SettingStore $settings) {}

    public function configuration(): array
    {
        $price = $this->settings->get('game_password.price');

        return [
            'price' => $price === null ? null : (int) $price,
            'captcha_provider' => $this->provider(),
            'has_api_key' => filled($this->apiKey()) && ($this->provider() !== 'captcha69' || filled($this->proxy())),
            'providers' => collect(GamePasswordCaptchaService::PROVIDERS)->map(fn (string $label, string $id): array => [
                'id' => $id, 'label' => $label, 'has_api_key' => filled($this->apiKey($id)),
                'has_proxy' => $id === 'captcha69' && filled($this->proxy()),
            ])->values()->all(),
        ];
    }

    public function configure(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $provider = $data['captcha_provider'] ?? $this->provider();
            abort_unless(array_key_exists($provider, GamePasswordCaptchaService::PROVIDERS), 422);
            $this->settings->putMany(['game_password.price' => (int) $data['price'], 'game_password.captcha_provider' => $provider]);
            if (filled($data['captcha_api_key'] ?? null)) {
                $this->settings->putEncryptedString($this->keyName($provider), $data['captcha_api_key']);
            } elseif ($data['clear_api_key'] ?? false) {
                $this->settings->putEncryptedString($this->keyName($provider), '');
            }
            if ($provider === 'captcha69') {
                if (filled($data['captcha_proxy'] ?? null)) {
                    $this->settings->putEncryptedString('game_password.captcha69.proxy', $data['captcha_proxy']);
                } elseif ($data['clear_proxy'] ?? false) {
                    $this->settings->putEncryptedString('game_password.captcha69.proxy', '');
                }
            }

            return $this->configuration();
        });
    }

    public function provider(): string
    {
        return $this->settings->getString('game_password.captcha_provider', 'autocaptcha');
    }

    public function apiKey(?string $provider = null): string
    {
        return $this->settings->getString($this->keyName($provider ?? $this->provider()));
    }

    public function proxy(): string
    {
        return $this->settings->getString('game_password.captcha69.proxy');
    }

    private function keyName(string $provider): string
    {
        return $provider === 'autocaptcha' ? 'game_password.captcha_api_key' : 'game_password.'.$provider.'.api_key';
    }
}
