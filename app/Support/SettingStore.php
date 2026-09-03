<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\TenantSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class SettingStore
{
    /**
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    public function getMany(array $defaults): array
    {
        $settings = $this->settingQuery()
            ->whereIn('key', array_keys($defaults))
            ->get()
            ->keyBy('key');
        $globalSettings = $this->usesTenantSettings()
            ? Setting::query()->whereIn('key', array_keys($defaults))->get()->keyBy('key')
            : collect();

        $resolved = [];

        foreach ($defaults as $key => $default) {
            /** @var Setting|TenantSetting|null $setting */
            $setting = $settings->get($key);
            $globalSetting = $globalSettings->get($key);
            $resolved[$key] = $setting !== null
                ? $this->decode($setting, $default)
                : ($globalSetting !== null ? $this->decode($globalSetting, $default) : $default);
        }

        return $resolved;
    }

    public function getString(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param  array<string, mixed>  $default
     * @return array<string, mixed>
     */
    public function getArray(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);

        return is_array($value) ? $value : $default;
    }

    public function putString(string $key, string $value): Setting|TenantSetting
    {
        return $this->settingQuery()->updateOrCreate(
            $this->settingIdentity($key),
            [
                'value' => $value,
                'type' => 'string',
            ],
        );
    }

    public function putEncryptedString(string $key, string $value): Setting|TenantSetting
    {
        return $this->settingQuery()->updateOrCreate(
            $this->settingIdentity($key),
            [
                'value' => Crypt::encryptString($value),
                'type' => 'encrypted',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public function putArray(string $key, array $value): Setting|TenantSetting
    {
        return $this->settingQuery()->updateOrCreate(
            $this->settingIdentity($key),
            [
                'value' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'type' => 'json',
            ],
        );
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $setting = $this->settingQuery()->where('key', $key)->first();

        if ($setting !== null) {
            return $this->decode($setting, $default);
        }

        if ($this->usesTenantSettings()) {
            $globalSetting = Setting::query()->where('key', $key)->first();

            return $globalSetting === null ? $default : $this->decode($globalSetting, $default);
        }

        return $default;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function putMany(array $values): void
    {
        DB::transaction(function () use ($values): void {
            collect($values)->each(function (mixed $value, string $key): void {
                $payload = $this->prepareValue($value);

                $this->settingQuery()->updateOrCreate(
                    $this->settingIdentity($key),
                    [
                        'value' => $payload['value'],
                        'type' => $payload['type'],
                    ],
                );
            });
        });
    }

    public function forgetMany(array $keys): void
    {
        $this->settingQuery()->whereIn('key', $keys)->delete();
    }

    public function decode(Setting|TenantSetting $setting, mixed $default = null): mixed
    {
        if ($setting->type === 'json') {
            $decoded = json_decode((string) $setting->value, true);

            return is_array($decoded) ? $decoded : $default;
        }

        if ($setting->type === 'boolean') {
            return filter_var($setting->value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $default;
        }

        if ($setting->type === 'encrypted') {
            return filled($setting->value) ? Crypt::decryptString((string) $setting->value) : $default;
        }

        return $setting->value ?? $default;
    }

    /**
     * @return array{value: string|null, type: string}
     */
    protected function prepareValue(mixed $value): array
    {
        if (is_array($value)) {
            return [
                'value' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'type' => 'json',
            ];
        }

        if (is_bool($value)) {
            return [
                'value' => $value ? '1' : '0',
                'type' => 'boolean',
            ];
        }

        return [
            'value' => $value === null ? null : (string) $value,
            'type' => 'string',
        ];
    }

    private function usesTenantSettings(): bool
    {
        $context = app(TenantContext::class);

        return $context->current() !== null && ! $context->isMain();
    }

    private function settingQuery(): Builder
    {
        return $this->usesTenantSettings() ? TenantSetting::query() : Setting::query();
    }

    /** @return array<string, int|string> */
    private function settingIdentity(string $key): array
    {
        if (! $this->usesTenantSettings()) {
            return ['key' => $key];
        }

        return ['tenant_id' => app(TenantContext::class)->id(), 'key' => $key];
    }
}
