<?php

namespace App\Features\Topup\Services;

use App\Features\Reporting\Services\DiscordReportService;
use App\Features\Topup\DTOs\TopupProviderBalanceDto;
use App\Features\Topup\Exceptions\The9pBalanceUnavailableException;
use App\Features\Topup\Providers\The9pTopupProvider;
use App\Models\TopupProvider;
use Illuminate\Support\Facades\Cache;
use Throwable;

class The9pBalanceService
{
    private const DEFAULT_WARNING_THRESHOLD = 1_000_000;

    public function __construct(
        private readonly The9pTopupProvider $the9pProvider,
        private readonly DiscordReportService $discordReportService,
    ) {}

    public function check(): TopupProviderBalanceDto
    {
        try {
            $provider = TopupProvider::query()
                ->where('slug', 'the9p')
                ->firstOrFail();

            $balance = $this->the9pProvider->balance($provider);
        } catch (Throwable $exception) {
            throw new The9pBalanceUnavailableException(previous: $exception);
        }

        $warningThreshold = $this->warningThreshold($provider);
        $isBelowWarningThreshold = $warningThreshold > 0 && $balance->balance < $warningThreshold;

        try {
            $this->reportBalanceState($provider, $balance, $warningThreshold, $isBelowWarningThreshold);
        } catch (Throwable $exception) {
            report($exception);
        }

        return new TopupProviderBalanceDto(
            balance: $balance->balance,
            currency: $balance->currency,
            warningThreshold: $warningThreshold,
            isBelowWarningThreshold: $isBelowWarningThreshold,
        );
    }

    private function warningThreshold(TopupProvider $provider): int
    {
        $configuredThreshold = data_get($provider->connection_config, 'balance_warning_threshold');

        if (! is_numeric($configuredThreshold) || (int) $configuredThreshold < 0) {
            return self::DEFAULT_WARNING_THRESHOLD;
        }

        return (int) $configuredThreshold;
    }

    private function reportBalanceState(
        TopupProvider $provider,
        TopupProviderBalanceDto $balance,
        int $warningThreshold,
        bool $isBelowWarningThreshold,
    ): void {
        $cacheKey = "topup:provider-balance-alert:{$provider->id}";

        if ($warningThreshold === 0) {
            Cache::forget($cacheKey);

            return;
        }

        $balanceState = Cache::get($cacheKey);
        $balanceState = is_array($balanceState) ? $balanceState : [];

        if ($isBelowWarningThreshold) {
            $currentHour = now()->format('Y-m-d-H');
            $lastAlertHour = is_string($balanceState['last_alert_hour'] ?? null)
                ? $balanceState['last_alert_hour']
                : null;

            if ($lastAlertHour !== $currentHour) {
                $queued = $this->discordReportService->queue(
                    channel: 'alerts',
                    title: 'Cảnh báo số dư cổng nạp thấp',
                    details: [
                        'Số dư hiện tại' => $this->formatMoney($balance->balance),
                        'Ngưỡng cảnh báo' => $this->formatMoney($warningThreshold),
                        'Số tiền cần bổ sung' => $this->formatMoney($warningThreshold - $balance->balance),
                        'Đơn vị' => $balance->currency,
                        'Kiểm tra lúc' => now(),
                    ],
                    dedupeKey: "provider-balance:{$provider->id}:low:{$currentHour}",
                );

                if ($queued) {
                    $lastAlertHour = $currentHour;
                }
            }

            Cache::put($cacheKey, [
                'status' => 'low',
                'last_alert_hour' => $lastAlertHour,
            ], now()->addDays(30));

            return;
        }

        if (($balanceState['status'] ?? null) !== 'low') {
            return;
        }

        Cache::forget($cacheKey);

        $this->discordReportService->queue(
            channel: 'recovered',
            title: 'Số dư cổng nạp đã phục hồi',
            details: [
                'Số dư hiện tại' => $this->formatMoney($balance->balance),
                'Ngưỡng cảnh báo' => $this->formatMoney($warningThreshold),
                'Đơn vị' => $balance->currency,
                'Kiểm tra lúc' => now(),
            ],
            dedupeKey: "provider-balance:{$provider->id}:recovered:".now()->format('Y-m-d-H'),
        );
    }

    private function formatMoney(int $amount): string
    {
        return number_format($amount, 0, ',', '.').'đ';
    }
}
