<?php

namespace App\Features\Captcha\Services;

use App\Models\CaptchaProvider;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class CaptchaProviderConfigurationService
{
    public const KEY = 'captcha.primary_provider_id';

    public function primaryId(): int
    {
        return (int) Setting::query()->where('key', self::KEY)->value('value');
    }

    /** @param array<string, mixed> $values */
    public function save(array $values, ?CaptchaProvider $provider = null): CaptchaProvider
    {
        return DB::transaction(function () use ($values, $provider): CaptchaProvider {
            $selection = Setting::query()->where('key', self::KEY)->lockForUpdate()->firstOrFail();
            $provider = $provider ? CaptchaProvider::query()->lockForUpdate()->findOrFail($provider->id) : new CaptchaProvider;
            $provider->fill($values)->save();
            if ($provider->is_enabled) {
                CaptchaProvider::query()->whereKeyNot($provider->id)->update(['is_enabled' => false]);
                $selection->update(['value' => (string) $provider->id]);
            } elseif ((int) $selection->value === $provider->id) {
                $selection->update(['value' => '']);
            }

            return $provider->refresh();
        }, 3);
    }

    public function delete(CaptchaProvider $provider): void
    {
        DB::transaction(function () use ($provider): void {
            $selection = Setting::query()->where('key', self::KEY)->lockForUpdate()->firstOrFail();
            if ((int) $selection->value === $provider->id) {
                $selection->update(['value' => '']);
            }
            $provider->delete();
        }, 3);
    }
}
