<?php

namespace App\Features\Reporting\Services;

use App\Features\Reporting\Jobs\SendDiscordReport;
use App\Utils\SendMessage;
use DateTimeInterface;
use Illuminate\Support\Str;

class DiscordReportService
{
    private const CHANNELS = [
        'activity',
        'alerts',
        'feedback',
        'info',
        'ops',
        'provider',
        'queue',
        'recovered',
        'sales',
        'security',
        'staging',
        'support',
    ];

    private const SENSITIVE_LABEL_PATTERN = '/password|secret|token|api[_ -]?key|partner[_ -]?key|sign|webhook|credential|payload|response|recipient|game[_ -]?account|email|phone|mật.?khẩu|bí.?mật|mã.?ký|tài.?khoản.?game|người.?nhận|điện.?thoại/iu';

    /**
     * @param  array<string, mixed>  $details
     */
    public function queue(string $channel, string $title, array $details, string $dedupeKey): bool
    {
        if (! $this->isConfigured($channel)) {
            return false;
        }

        SendDiscordReport::dispatch(
            channel: $channel,
            title: Str::limit(trim($title), 150, ''),
            details: $this->sanitizeDetails($details),
            dedupeKey: Str::limit(trim($dedupeKey), 200, ''),
        )->afterCommit();

        return true;
    }

    /**
     * @param  array<string, string|int|float|bool|null>  $details
     */
    public function sendNow(string $channel, string $title, array $details): void
    {
        SendMessage::sendReport($channel, $title, $details);
    }

    public function isConfigured(string $channel): bool
    {
        if (! in_array($channel, self::CHANNELS, true)) {
            return false;
        }

        return filled(config("services.discord.channels.{$channel}"));
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, string|int|float|bool|null>
     */
    private function sanitizeDetails(array $details): array
    {
        $sanitized = [];

        foreach (array_slice($details, 0, 25, true) as $label => $value) {
            $label = Str::limit(trim((string) $label), 80, '');

            if ($label === '' || preg_match(self::SENSITIVE_LABEL_PATTERN, $label) === 1) {
                continue;
            }

            if ($value instanceof DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s T');
            } elseif (is_string($value)) {
                $value = Str::of($value)
                    ->replace(['@everyone', '@here', '`'], ['＠everyone', '＠here', 'ˋ'])
                    ->limit(300, '')
                    ->toString();
            } elseif (! is_int($value) && ! is_float($value) && ! is_bool($value) && $value !== null) {
                $value = '[redacted]';
            }

            $sanitized[$label] = $value;
        }

        return $sanitized;
    }
}
