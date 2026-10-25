<?php

namespace App\Features\NroGameData\Services;

class GameEffectAssetNameService
{
    public static function basename(string $value): string
    {
        return strtolower(rawurldecode(basename(str_replace('\\', '/', parse_url($value, PHP_URL_PATH) ?: $value))));
    }

    /** @return array{name: string, effectId: int|null, sha256: string|null}|null */
    public static function identify(?string $value): ?array
    {
        if ($value === null) {
            return null;
        }
        $name = self::basename($value);
        if (! str_contains($name, 'effectdata')) {
            if (! preg_match('~/game-effects/raw/(\d{1,5})/([a-f0-9]{64})\.png$~i', parse_url($value, PHP_URL_PATH) ?: '', $legacy)) {
                return null;
            }
            $name = 'effectdata_'.$legacy[1].'_'.$legacy[2].'.png';
        }
        $id = preg_match('/effectdata[\s_.-]*(\d+)(?!\d)/i', $name, $match) && (int) $match[1] <= 99999 ? (int) $match[1] : null;
        $hash = preg_match('/(?:^|[_-])([a-f0-9]{64})\.png$/i', $name, $match) ? strtolower($match[1]) : null;

        return ['name' => $name, 'effectId' => $id, 'sha256' => $hash];
    }
}
