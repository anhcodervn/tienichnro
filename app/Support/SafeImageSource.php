<?php

namespace App\Support;

class SafeImageSource
{
    public static function isRootRelative(string $source): bool
    {
        if ($source === '' || strlen($source) > 2048 || trim($source) !== $source || ! str_starts_with($source, '/')) {
            return false;
        }

        if (str_starts_with($source, '//') || preg_match('/[\x00-\x20\x7F\\\\]/', $source) === 1) {
            return false;
        }

        $parts = parse_url($source);

        if ($parts === false || isset($parts['scheme'], $parts['host'], $parts['user'], $parts['pass'])) {
            return false;
        }

        $decodedPath = (string) ($parts['path'] ?? '');

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $nextPath = rawurldecode($decodedPath);

            if ($nextPath === $decodedPath) {
                break;
            }

            $decodedPath = $nextPath;
        }

        if (! str_starts_with($decodedPath, '/') || str_contains($decodedPath, '\\') || preg_match('/[\x00-\x20\x7F]/', $decodedPath) === 1) {
            return false;
        }

        return collect(explode('/', $decodedPath))
            ->every(fn (string $segment): bool => ! in_array($segment, ['.', '..'], true));
    }
}
