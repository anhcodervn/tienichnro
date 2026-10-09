<?php

namespace App\Features\NroNotification\Services;

class BossMessageParserService
{
    /** @return array{kind: string, boss_name: string, map_name?: string, zone?: int|null, zone_name?: string|null, killed_by?: string}|null */
    public function parse(string $content): ?array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $content) ?? $content);
        $normalized = NotificationTypeClassifierService::normalizeText($text);
        if (str_contains($normalized, 'xuất hiện tại') && preg_match('/\A(?:BOSS\s+)?(.+?) (?:vừa )?xuất hiện tại\s*:?\s*(.+?)(?:\s*[,;\-]?\s+khu(?:\s*:\s*|\s+)(.+?))?\s*[.!]?\z/iu', $text, $matches)) {
            $zoneName = isset($matches[3]) ? trim($matches[3]) : null;

            return ['kind' => 'spawn', 'boss_name' => trim($matches[1]), 'map_name' => trim($matches[2]),
                'zone' => $zoneName !== null && ctype_digit($zoneName) ? (int) $zoneName : null, 'zone_name' => $zoneName];
        }
        if (str_contains($normalized, 'đã tiêu diệt được') && preg_match('/\A([^:]+):\s*Đã tiêu diệt được (.+?) mọi người đều ngưỡng mộ\s*[.!]?\z/iu', $text, $matches)) {
            return ['kind' => 'death', 'boss_name' => trim($matches[2]), 'killed_by' => trim($matches[1])];
        }
        if (str_contains($normalized, 'vừa bị tiêu diệt bởi') && preg_match('/\A(.+?) vừa bị tiêu diệt bởi (.+?)\s*[.!]?\z/iu', $text, $matches)) {
            return ['kind' => 'death', 'boss_name' => trim($matches[1]), 'killed_by' => trim($matches[2])];
        }

        return null;
    }
}
