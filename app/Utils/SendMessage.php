<?php

namespace App\Utils;

use DateTimeInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class SendMessage
{
    private const DISCORD_CHANNELS = [
        'queue' => 'queue',
        'info' => 'info',
        'ops' => 'ops',
        'security' => 'security',
        'alerts' => 'alerts',
        'recovered' => 'recovered',
        'staging' => 'staging',
        'sales' => 'sales',
        'daily_report' => 'daily_report',
        'provider' => 'provider',
        'feedback' => 'feedback',
        'activity' => 'activity',
    ];

    private static function tele(string $message, string $chatId, string $token): void
    {
        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        $data = [
            'chat_id' => $chatId,
            'text' => $message,
        ];
        $options = [
            'http' => [
                'header' => "Content-Type: application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
        ];
        $context = stream_context_create($options);
        file_get_contents($url, false, $context);
    }

    public static function sendTelegram(string $message, ?string $chatId = null): void
    {
        return;
        if (app()->environment('testing')) {
            return;
        }

        $token = (string) config('services.telegram.bot_token', '');
        $resolvedChatId = $chatId ?: (string) config('services.telegram.default_chat_id', '');

        if ($token === '' || $resolvedChatId === '') {
            return;
        }

        self::tele($message, $resolvedChatId, $token);
    }

    public static function sendDiscord(string $message, string $type): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $channelKey = self::DISCORD_CHANNELS[$type] ?? null;
        if ($channelKey === null) {
            throw new InvalidArgumentException(sprintf('Unsupported Discord channel type [%s].', $type));
        }

        $channels = config('services.discord.channels', []);
        $url = Arr::get($channels, $channelKey);

        if (! is_string($url) || trim($url) === '') {
            return;
        }

        Http::connectTimeout(5)
            ->timeout(10)
            ->post($url, [
                'username' => (string) config('services.discord.bot_name', 'Nạp Carot Monitor'),
                'avatar_url' => (string) config('services.discord.bot_avatar_url', ''),
                'content' => $message,
                'allowed_mentions' => ['parse' => []],
            ])
            ->throw();
    }

    /**
     * Send a report immediately and allow transport exceptions to bubble so a queue job can retry.
     *
     * @param  array<string, mixed>  $details
     */
    public static function sendReport(string $channel, string $title, array $details = []): void
    {
        if (! array_key_exists($channel, self::DISCORD_CHANNELS)) {
            throw new InvalidArgumentException(sprintf('Unsupported Discord channel type [%s].', $channel));
        }

        self::sendDiscord(
            self::formatDiscordReport(Str::upper($channel), $title, $details),
            $channel,
        );
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function sendInfoReport(string $title, array $details = []): void
    {
        self::safeSendDiscord(
            self::formatDiscordReport('INFO', $title, $details),
            'info',
        );
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function sendQueueReport(string $title, array $details = []): void
    {
        self::safeSendDiscord(
            self::formatDiscordReport('QUEUE', $title, $details),
            'queue',
        );
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function sendOpsReport(string $title, array $details = []): void
    {
        self::safeSendDiscord(
            self::formatDiscordReport('OPS', $title, $details),
            'ops',
        );
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function sendSecurityReport(string $title, array $details = []): void
    {
        self::safeSendDiscord(
            self::formatDiscordReport('SECURITY', $title, $details),
            'security',
        );
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function sendAlertReport(string $title, array $details = []): void
    {
        self::safeSendDiscord(
            self::formatDiscordReport('ALERT', $title, $details),
            'alerts',
        );
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function sendRecoveredReport(string $title, array $details = []): void
    {
        self::safeSendDiscord(
            self::formatDiscordReport('RECOVERED', $title, $details),
            'recovered',
        );
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function sendStagingReport(string $title, array $details = []): void
    {
        self::safeSendDiscord(
            self::formatDiscordReport('STAGING', $title, $details),
            'staging',
        );
    }

    /** @param array<string, mixed> $details */
    public static function sendSalesReport(string $title, array $details = []): void
    {
        self::safeSendDiscord(
            self::formatDiscordReport('SALES', $title, $details),
            'sales',
        );
    }

    /** @param array<string, mixed> $details */
    public static function sendProviderReport(string $title, array $details = []): void
    {
        self::safeSendDiscord(
            self::formatDiscordReport('PROVIDER', $title, $details),
            'provider',
        );
    }

    /** @param array<string, mixed> $details */
    public static function sendFeedbackReport(string $title, array $details = []): void
    {
        self::safeSendDiscord(
            self::formatDiscordReport('FEEDBACK', $title, $details),
            'feedback',
        );
    }

    /** @param array<string, mixed> $details */
    public static function sendActivityReport(string $title, array $details = []): void
    {
        self::safeSendDiscord(
            self::formatDiscordReport('ACTIVITY', $title, $details),
            'activity',
        );
    }

    private static function safeSendDiscord(string $message, string $type): void
    {
        try {
            self::sendDiscord($message, $type);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private static function formatDiscordReport(string $channel, string $title, array $details): string
    {
        $lines = [
            sprintf('**[%s] %s**', $channel, $title),
            sprintf('- Thời gian: `%s`', now()->format('Y-m-d H:i:s T')),
        ];

        foreach (self::contextDetails() as $label => $value) {
            $lines[] = sprintf('- %s: %s', $label, self::normalizeDiscordValue($value));
        }

        foreach ($details as $label => $value) {
            $lines[] = sprintf('- %s: %s', $label, self::normalizeDiscordValue($value));
        }

        return Str::limit(implode(PHP_EOL, $lines), 1900);
    }

    private static function normalizeDiscordValue(mixed $value): string
    {
        if ($value === null) {
            return '`null`';
        }

        if ($value instanceof DateTimeInterface) {
            return sprintf('`%s`', $value->format('Y-m-d H:i:s T'));
        }

        if (is_bool($value)) {
            return $value ? '`true`' : '`false`';
        }

        if (is_scalar($value)) {
            return sprintf('`%s`', Str::limit((string) $value, 300));
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return sprintf('`%s`', Str::limit($encoded ?: '[unserializable]', 300));
    }

    /**
     * @return array<string, string>
     */
    private static function contextDetails(): array
    {
        $context = config('services.discord.context', []);

        return array_filter([
            'App' => trim((string) Arr::get($context, 'app_name', '')),
            'Môi trường' => trim((string) Arr::get($context, 'app_env', '')),
            'URL' => trim((string) Arr::get($context, 'app_url', '')),
            'Server' => trim((string) Arr::get($context, 'server_name', '')),
            'IP' => trim((string) Arr::get($context, 'server_ip', '')),
            'Role' => trim((string) Arr::get($context, 'server_role', '')),
            'Region' => trim((string) Arr::get($context, 'server_region', '')),
        ], static fn (string $value): bool => $value !== '');
    }
}
