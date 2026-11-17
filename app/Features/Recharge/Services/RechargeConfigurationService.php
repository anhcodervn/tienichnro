<?php

namespace App\Features\Recharge\Services;

use App\Support\SettingStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RechargeConfigurationService
{
    public function __construct(private readonly SettingStore $settings, private readonly ApiBankVnService $provider) {}

    public function credentials(array $draft = []): array
    {
        return ['key' => filled($draft['api_key'] ?? null) ? $draft['api_key'] : $this->settings->getString('recharge.api_key'), 'secret' => filled($draft['api_secret'] ?? null) ? $draft['api_secret'] : $this->settings->getString('recharge.api_secret')];
    }

    public function snapshot(): array
    {
        $credentials = $this->credentials();

        return ['enabled' => (bool) $this->settings->get('recharge.enabled', false), 'bank_id' => (int) $this->settings->get('recharge.bank_id', 0), 'bank' => $this->settings->getArray('recharge.bank'),
            'credentials_saved' => filled($credentials['key']) && filled($credentials['secret']), 'cron_key_saved' => filled($this->cronKey()), 'cron_url' => route('recharge.cron'),
            'webhook_secret_saved' => filled($this->webhookSecret()), 'webhook_url' => route('recharge.callback.apibankvn'), 'last_check' => Cache::get('recharge:last_check')];
    }

    public function cronKey(): string
    {
        return $this->settings->getString('recharge.cron_key');
    }

    public function webhookSecret(): string
    {
        return $this->settings->getString('recharge.webhook_secret');
    }

    public function banks(array $draft): array
    {
        $credentials = $this->credentials($draft);
        if (blank($credentials['key']) || blank($credentials['secret'])) {
            throw ValidationException::withMessages(['api_key' => 'Nhập API key và secret của APIBankVN.']);
        }
        try {
            return $this->provider->banks($credentials);
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['api_key' => 'Không lấy được ngân hàng từ APIBankVN. Kiểm tra key, secret và IP whitelist.']);
        }
    }

    public function save(array $data): array
    {
        $credentials = $this->credentials($data);
        $bank = $this->settings->getArray('recharge.bank');
        if ($data['enabled'] || filled($data['api_key'] ?? null)) {
            $bank = collect($this->banks($data))->firstWhere('bank_id', (int) ($data['bank_id'] ?? 0));
            if ($bank === null) {
                throw ValidationException::withMessages(['bank_id' => 'Chọn ngân hàng đang hoạt động từ APIBankVN.']);
            }
        }
        if ($data['enabled'] && blank($data['cron_key'] ?? $this->cronKey())) {
            throw ValidationException::withMessages(['cron_key' => 'Nhập khoá bảo vệ cron.']);
        }
        DB::transaction(function () use ($data, $credentials, $bank): void {
            $this->settings->putMany(['recharge.enabled' => $data['enabled'], 'recharge.bank_id' => $bank['bank_id'] ?? 0, 'recharge.bank' => $bank]);
            if (filled($data['api_key'] ?? null)) {
                $this->settings->putEncryptedString('recharge.api_key', $credentials['key']);
                $this->settings->putEncryptedString('recharge.api_secret', $credentials['secret']);
            }
            if (filled($data['cron_key'] ?? null)) {
                $this->settings->putEncryptedString('recharge.cron_key', $data['cron_key']);
            }
            if (filled($data['webhook_secret'] ?? null)) {
                $this->settings->putEncryptedString('recharge.webhook_secret', $data['webhook_secret']);
            }
        });

        return $this->snapshot();
    }
}
