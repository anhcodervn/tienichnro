<?php

namespace App\Features\Admin\Topup\Actions;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Features\Topup\Exceptions\TopupProviderConnectionException;
use App\Features\Topup\Services\ProviderBalanceFallbackService;
use App\Features\Topup\Services\TopupProviderBalanceService;
use App\Features\Topup\Services\TopupProviderResolver;
use App\Models\Order;
use App\Models\OrderRecipient;
use App\Models\Scopes\TenantScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RetryProviderBalanceOrderAction
{
    public function __construct(private readonly TopupProviderBalanceService $providerBalanceService) {}

    public function handle(Order $order): Order
    {
        $order->loadMissing(['provider', 'package', 'recipients']);

        if ($order->payment_status !== PaymentStatus::Paid
            || $order->order_status !== OrderStatus::Processing
            || ! TopupProviderResolver::supportsBalance($order->provider?->slug)
            || ! $this->isSafelyRetryable($order)) {
            throw ValidationException::withMessages([
                'retry_provider_submission' => 'Chỉ có thể đẩy lại đơn đang chờ do provider không đủ số dư.',
            ]);
        }

        $providerUnitCost = (int) ($order->provider_unit_cost ?? $order->package?->provider_price ?? 0);
        $requiredBalance = $providerUnitCost * (int) $order->recipients->sum('quantity');

        if ($requiredBalance <= 0) {
            throw ValidationException::withMessages([
                'retry_provider_submission' => 'Đơn chưa có dữ liệu cost provider hợp lệ để kiểm tra số dư.',
            ]);
        }

        try {
            $balance = $this->providerBalanceService->forOrder($order);
        } catch (TopupProviderConnectionException $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'retry_provider_submission' => "[{$exception->errorCode}] {$exception->getMessage()}",
            ]);
        }

        if ($balance->balance < $requiredBalance) {
            throw ValidationException::withMessages([
                'retry_provider_submission' => sprintf(
                    'Provider vẫn không đủ số dư: hiện có %s %s, cần %s %s.',
                    number_format($balance->balance, 0, ',', '.'),
                    $balance->currency,
                    number_format($requiredBalance, 0, ',', '.'),
                    $balance->currency,
                ),
            ]);
        }

        return DB::transaction(function () use ($order): Order {
            $lockedOrder = Order::query()->withoutGlobalScope(TenantScope::class)->lockForUpdate()->findOrFail($order->id);
            $recipients = $lockedOrder->recipients()->lockForUpdate()->get();

            $lockedOrder->setRelation('recipients', $recipients);

            if (! $this->isSafelyRetryable($lockedOrder)) {
                throw ValidationException::withMessages([
                    'retry_provider_submission' => 'Đơn không còn ở trạng thái chờ số dư provider.',
                ]);
            }

            foreach ($recipients as $recipient) {
                $this->clearRecipientMarker($recipient);
            }

            $metadata = $lockedOrder->metadata ?? [];
            $manualReview = $metadata['provider_manual_review'] ?? null;
            $history = is_array($metadata['provider_manual_review_history'] ?? null)
                ? $metadata['provider_manual_review_history']
                : [];

            if (is_array($manualReview)) {
                $history[] = [...$manualReview, 'retried_at' => now()->toISOString()];
            }

            unset($metadata['provider_manual_review']);
            $metadata['provider_manual_review_history'] = array_slice($history, -10);

            $lockedOrder->forceFill([
                'failure_reason' => null,
                'metadata' => $metadata,
            ])->save();

            return $lockedOrder->refresh();
        }, 3);
    }

    /** @param array<int, OrderRecipient>|null $recipients */
    private function hasBalanceFallbackMarker(Order $order, ?array $recipients = null): bool
    {
        if (data_get($order->metadata, 'provider_manual_review.code') === ProviderBalanceFallbackService::REASON_CODE) {
            return true;
        }

        $recipients ??= $order->relationLoaded('recipients') ? $order->recipients->all() : [];

        return collect($recipients)->contains(
            fn (OrderRecipient $recipient): bool => data_get($recipient->provider_response, 'manual_review.code') === ProviderBalanceFallbackService::REASON_CODE,
        );
    }

    private function isSafelyRetryable(Order $order): bool
    {
        if (! $this->hasBalanceFallbackMarker($order)) {
            return false;
        }

        $recipients = $order->recipients;

        if ($recipients->isEmpty()) {
            return false;
        }

        return $recipients->every(function (OrderRecipient $recipient): bool {
            if (data_get($recipient->provider_response, 'manual_review.code') !== ProviderBalanceFallbackService::REASON_CODE
                || $recipient->submitted_at !== null
                || filled($recipient->provider_reference)) {
                return false;
            }

            return collect(data_get($recipient->provider_response, 'items', []))->every(
                fn (mixed $item): bool => ! is_array($item)
                    || (blank($item['reference'] ?? null) && ! is_array($item['submission'] ?? null)),
            );
        });
    }

    private function clearRecipientMarker(OrderRecipient $recipient): void
    {
        $providerResponse = $recipient->provider_response ?? [];
        $manualReview = $providerResponse['manual_review'] ?? null;
        $history = is_array($providerResponse['manual_review_history'] ?? null)
            ? $providerResponse['manual_review_history']
            : [];

        if (is_array($manualReview)) {
            $history[] = [...$manualReview, 'retried_at' => now()->toISOString()];
        }

        unset($providerResponse['manual_review']);
        $providerResponse['manual_review_history'] = array_slice($history, -10);
        $items = is_array($providerResponse['items'] ?? null) ? $providerResponse['items'] : [];

        foreach ($items as $key => $item) {
            if (! is_array($item) || filled($item['reference'] ?? null)) {
                continue;
            }

            unset($item['failure_reason'], $item['message'], $item['last_checked_at']);
            $items[$key] = [...$item, 'status' => 'pending', 'retry_queued_at' => now()->toISOString()];
        }

        $providerResponse['items'] = $items;
        $recipient->forceFill([
            'status' => 'processing',
            'provider_status' => 'processing',
            'provider_response' => $providerResponse,
            'failure_reason' => null,
            'last_checked_at' => null,
        ])->save();
    }
}
