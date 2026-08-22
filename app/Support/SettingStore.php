<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class SettingStore
{
    /**
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    public function getMany(array $defaults): array
    {
        $settings = Setting::query()
            ->whereIn('key', array_keys($defaults))
            ->get()
            ->keyBy('key');

        $resolved = [];

        foreach ($defaults as $key => $default) {
            /** @var Setting|null $setting */
            $setting = $settings->get($key);
            $resolved[$key] = $setting !== null ? $this->decodeValue($setting, $default) : $default;
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

    public function putString(string $key, string $value): Setting
    {
        return Setting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => 'string',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public function putArray(string $key, array $value): Setting
    {
        return Setting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'type' => 'json',
            ],
        );
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $setting = Setting::query()->where('key', $key)->first();

        if ($setting === null) {
            return $default;
        }

        return $this->decodeValue($setting, $default);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function putMany(array $values): void
    {
        DB::transaction(function () use ($values): void {
            collect($values)->each(function (mixed $value, string $key): void {
                $payload = $this->prepareValue($value);

                Setting::query()->updateOrCreate(
                    ['key' => $key],
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
        Setting::query()->whereIn('key', $keys)->delete();
    }

    protected function decodeValue(Setting $setting, mixed $default = null): mixed
    {
        if ($setting->type === 'json') {
            $decoded = json_decode((string) $setting->value, true);

            return is_array($decoded) ? $decoded : $default;
        }

        if ($setting->type === 'boolean') {
            return filter_var($setting->value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $default;
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
}
