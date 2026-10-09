<?php

namespace App\Features\NroNotification\Services;

use App\Models\CodeNotify;

class NotificationTypeClassifierService
{
    public static function normalizeText(string $text): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text) ?? $text), 'UTF-8');
    }

    public function match(string $content): ?CodeNotify
    {
        $text = self::normalizeText($content);
        $selected = null;
        $longest = 0;
        foreach (CodeNotify::query()->whereNotNull('keywords')->orderBy('id')->get(['id', 'code', 'keywords']) as $type) {
            foreach ($type->keywords ?? [] as $keyword) {
                $keyword = self::normalizeText($keyword);
                $length = mb_strlen($keyword, 'UTF-8');
                if ($length > $longest && str_contains($text, $keyword)) {
                    $selected = $type;
                    $longest = $length;
                }
            }
        }

        return $selected;
    }
}
