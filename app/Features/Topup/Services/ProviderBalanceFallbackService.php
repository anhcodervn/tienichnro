<?php

namespace App\Features\Topup\Services;

use App\Features\Reporting\Services\TopupDiscordReporterService;
use App\Models\Order;
use App\Models\OrderRecipient;
use App\Models\TopupPackage;
use App\Models\TopupProvider;
use Illuminate\Support\Facades\DB;

class ProviderBalanceFallbackService
{
    public const REASON_CODE = 'provider_balance_insufficient';

    public function __construct(
        private readonly TopupDiscordReporterService $discordReporter,
        private readonly TopupProviderBalanceService $providerBalanceService,
    ) {}

    public function divertIfInsufficient(int $orderId): bool
    {
        $candidate = Order::query()
            ->with(['provider', 'package', 'recipients'])
            ->find($orderId);

        if (! $candidate instanceof Order
            || ! $candidate->provider instanceof TopupProvider
            || ((int) ($candidate->provider_unit_cost ?? $candidate->package?->provider_price ?? 0)) <= 0
            || ! TopupProviderResolver::supportsBalance($candidate->provider->slug)) {
            return false;
        }

        if ($candidate->recipients->contains(fn (OrderRecipient $recipient): bool => data_get(
            $recipient->provider_response,
            'manual_review.code',
        ) === self::REASON_CODE)) {
            return true;
        }

        $this->providerBalanceService->forProvider($candidate->provider);

        $result = DB::transaction(function () use ($orderId): ?array {
            $order = Order::query()->lockForUpdate()->find($orderId);

            if (! $order instanceof Order || $order->topup_provider_id === null || $order->topup_package_id === null) {
                return null;
            }

            $recipients = $order->recipients()
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->lockForUpdate()
                ->get();

            if ($recipients->isEmpty()) {
                return null;
            }

            if ($recipients->contains(fn (OrderRecipient $recipient): bool => data_get(
                $recipient->provider_response,
                'manual_review.code',
            ) === self::REASON_CODE)) {
                return ['already_diverted' => true];
            }

            $provider = TopupProvider::query()->lockForUpdate()->find($order->topup_provider_id);
            $package = TopupPackage::query()->find($order->topup_package_id);
            $providerUnitCost = (int) ($order->provider_unit_cost ?? $package?->provider_price ?? 0);

            if (! $provider instanceof TopupProvider
                || $provider->balance_status !== 'success'
                || $provider->balance === null
                || $providerUnitCost <= 0) {
                return null;
            }

            $requiredBalance = $providerUnitCost * (int) $recipients->sum('quantity');
            $providerBalance = (int) $provider->balance;

            if ($providerBalance >= $requiredBalance) {
                return null;
            }

            $currency = $provider->balance_currency ?: 'VND';
            $reason = sprintf(
                'Provider không đủ số dư (%s/%s %s). Đơn đã chuyển sang xử lý thủ công và chưa gửi sang provider.',
                number_format($providerBalance, 0, ',', '.'),
                number_format($requiredBalance, 0, ',', '.'),
                strtoupper($currency),
            );
            $markedAt = now();

            foreach ($recipients as $recipient) {
                $providerResponse = $recipient->provider_response ?? [];
                $items = $providerResponse['items'] ?? [];

                foreach (range(1, $recipient->quantity) as $unit) {
                    $items[$unit] = [
                        ...($items[$unit] ?? []),
                        'unit' => $unit,
                        'status' => 'processing',
                        'failure_reason' => $reason,
                        'message' => 'Chờ admin xử lý thủ công; chưa gửi yêu cầu sang provider.',
                        'last_checked_at' => $markedAt->toISOString(),
                    ];
                }

                $recipient->forceFill([
                    'status' => 'processing',
                    'provider_status' => 'processing',
                    'provider_response' => [
                        ...$providerResponse,
                        'items' => $items,
                        'manual_review' => [
                            'code' => self::REASON_CODE,
                            'provider_balance' => $providerBalance,
                            'required_balance' => $requiredBalance,
                            'currency' => $currency,
                            'marked_at' => $markedAt->toISOString(),
                        ],
                    ],
                    'failure_reason' => $reason,
                    'last_checked_at' => $markedAt,
                ])->save();
            }

            $metadata = $order->metadata ?? [];
            $order->forceFill([
                'failure_reason' => $reason,
                'metadata' => [
                    ...$metadata,
                    'provider_manual_review' => [
                        'code' => self::REASON_CODE,
                        'provider_balance' => $providerBalance,
                        'required_balance' => $requiredBalance,
                        'currency' => $currency,
                        'marked_at' => $markedAt->toISOString(),
                    ],
                ],
            ])->save();

            return [
                'already_diverted' => false,
                'order' => $order,
                'provider' => $provider,
                'provider_balance' => $providerBalance,
                'required_balance' => $requiredBalance,
                'currency' => $currency,
            ];
        }, 3);

        if ($result === null) {
            return false;
        }

        if ($result['already_diverted'] === false) {
            $this->discordReporter->providerBalanceInsufficient(
                order: $result['order'],
                provider: $result['provider'],
                providerBalance: $result['provider_balance'],
                requiredBalance: $result['required_balance'],
                currency: $result['currency'],
            );
        }

        return true;
    }
}
