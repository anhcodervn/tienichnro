<?php

namespace App\Utils;

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Support\SettingStore;
use App\Support\TenantContext;
use Closure;

final class Site
{
    public static function mySite(): ?Tenant
    {
        $context = app(TenantContext::class);

        if ($context->current() instanceof Tenant) {
            return $context->current();
        }

        if (! $context->isActive()) {
            return $context->mainTenant();
        }

        return null;
    }

    public static function id(): ?int
    {
        return self::mySite()?->id ?? (app(TenantContext::class)->isActive() ? null : 0);
    }

    public static function isMain(): bool
    {
        return app(TenantContext::class)->isMain();
    }

    public static function isChild(): bool
    {
        return self::mySite() !== null && ! self::isMain();
    }

    public static function domain(): ?string
    {
        return self::mySite()?->domains()->where('is_primary', true)->value('domain');
    }

    public static function billingUser(): ?User
    {
        return self::mySite()?->billingUser()->first();
    }

    public static function global(): ?Tenant
    {
        return app(TenantContext::class)->mainTenant();
    }

    public static function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingStore::class)->get($key, $default);
    }

    /** @param array<string, mixed> $defaults */
    public static function settings(array $defaults): array
    {
        return app(SettingStore::class)->getMany($defaults);
    }

    public static function globalSetting(string $key, mixed $default = null): mixed
    {
        $setting = Setting::query()->where('key', $key)->first();

        return $setting === null ? $default : app(SettingStore::class)->decode($setting, $default);
    }

    public static function putSetting(string $key, mixed $value): void
    {
        app(SettingStore::class)->putMany([$key => $value]);
    }

    public static function for(Tenant $tenant, Closure $callback): mixed
    {
        return app(TenantContext::class)->run($tenant, $callback);
    }
}
