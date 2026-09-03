<?php

namespace App\Utils;

use App\Support\SettingStore;

final class Setting
{
    public static function get(string $key, mixed $default = null): mixed
    {
        return app(SettingStore::class)->get($key, $default);
    }

    /** @param array<string, mixed> $defaults */
    public static function many(array $defaults): array
    {
        return app(SettingStore::class)->getMany($defaults);
    }

    public static function put(string $key, mixed $value): void
    {
        app(SettingStore::class)->putMany([$key => $value]);
    }

    /** @param array<string, mixed> $values */
    public static function putMany(array $values): void
    {
        app(SettingStore::class)->putMany($values);
    }

    public static function global(string $key, mixed $default = null): mixed
    {
        return Site::globalSetting($key, $default);
    }
}
