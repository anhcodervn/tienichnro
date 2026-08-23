<?php

namespace App\Support;

final class SafeNavigationUrl
{
    public static function passes(mixed $value): bool
    {
        if (! is_string($value) || $value === '' || mb_strlen($value) > 2048) {
            return false;
        }

        $isSafePath = str_starts_with($value, '/')
            && ! str_starts_with($value, '//')
            && ! str_contains($value, '\\')
            && preg_match('/[\x00-\x20\x7F]/u', $value) !== 1;
        $scheme = parse_url($value, PHP_URL_SCHEME);
        $isSafeAbsoluteUrl = filter_var($value, FILTER_VALIDATE_URL) !== false
            && is_string($scheme)
            && in_array(strtolower($scheme), ['http', 'https'], true);

        return $isSafePath || $isSafeAbsoluteUrl;
    }
}
