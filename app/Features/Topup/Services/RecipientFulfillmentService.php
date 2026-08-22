<?php

namespace App\Features\Topup\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Features\Topup\DTOs\TopupProviderResultDto;
use App\Features\Topup\Enums\TopupProviderStatus;
use App\Features\Topup\Jobs\SyncTopupRecipientStatus;
use App\Mail\Orders\OrderCompletedMail;
use App\Mail\Orders\OrderFailedMail;
use App\Models\Order;
use App\Models\OrderRecipient;
use App\Models\TopupProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class RecipientFulfillmentService
{
    public function __construct(
        private readonly TopupProviderResolver $providerResolver,
        private readonly OrderStatusService $orderStatusService,
    ) {}

    public function submit(int $recipientId, int $unit = 1): void
    {
        $recipient = $this->prepareSubmission($recipientId, $unit);

        if (! $recipient instanceof OrderRecipient) {
            return;
        }

        $order = $recipient->order;
        $adapter = $this->providerResolver->resolveForOrder($order);
        $requestId = $this->unitRequestId($recipient, $unit);
        $item = $this->providerItem($recipient, $unit);

        if (filled($item['reference'] ?? null)) {
            if ($adapter->supportsStatusChecks()) {
                SyncTopupRecipientStatus::dispatch($recipient->id, $unit, 1)->delay(now()->addSeconds(15));
            }

            return;
        }

        $result = $adapter->submit($order, $recipient, $order->provider, $requestId);
        $this->storeUnitResult($recipient->id, $unit, $requestId, $result, true);

        if (! $result->status->isTerminal() && $adapter->supportsStatusChecks()) {
            SyncTopupRecipientStatus::dispatch($recipient->id, $unit, 1)->delay(now()->addSeconds(15));
        }

        $this->aggregateOrder($order->id);
    }

    public function syncStatus(int $recipientId, int $unit, int $attempt): void
    {
        $recipient = OrderRecipient::query()->with(['order.provider', 'order.server'])->find($recipientId);

        if (! $recipient instanceof OrderRecipient || in_array($recipient->status, ['completed', 'failed', 'cancelled'], true)) {
            return;
        }

        $order = $recipient->order;
        $provider = $order->provider;
        $item = $this->providerItem($recipient, $unit);
        $reference = trim((string) ($item['reference'] ?? ''));
        $requestId = trim((string) ($item['request_id'] ?? $this->unitRequestId($recipient, $unit)));

        if (! $provider instanceof TopupProvider || $reference === '') {
            return;
        }

        $adapter = $this->providerResolver->resolveForOrder($order);
        if (! $adapter->supportsStatusChecks()) {
            return;
        }

        $maxChecks = min(max((int) data_get($provider->connection_config, 'max_status_checks', 20), 1), 100);
        if ($attempt > $maxChecks) {
            $this->markForManualReview($recipient->id, 'Đã đạt giới hạn kiểm tra tự động; cần đối soát thủ công.', $unit);

            return;
        }

        $result = $adapter->status($order, $recipient, $provider, $requestId, $reference);
        $this->storeUnitResult($recipient->id, $unit, $requestId, $result, false, $attempt);
        $this->aggregateOrder($order->id);

        if (! $result->status->isTerminal() && $attempt >= $maxChecks) {
            $this->markForManualReview($recipient->id, 'Đã đạt giới hạn kiểm tra tự động; cần đối soát thủ công.', $unit);

            return;
        }

        if (! $result->status->isTerminal()) {
            $delay = min(15 * (2 ** min($attempt - 1, 3)), 120);
            SyncTopupRecipientStatus::dispatch($recipient->id, $unit, $attempt + 1)->delay(now()->addSeconds($delay));
        }
    }

    public function markForManualReview(int $recipientId, string $reason, ?int $unit = null): void
    {
        $recipient = DB::transaction(function () use ($recipientId, $reason, $unit): ?OrderRecipient {
            $recipient = OrderRecipient::query()->lockForUpdate()->find($recipientId);

            if (! $recipient instanceof OrderRecipient || in_array($recipient->status, ['completed', 'failed', 'cancelled'], true)) {
                return null;
            }

            $providerResponse = $recipient->provider_response ?? [];
            if ($unit !== null) {
                $items = is_array($providerResponse['items'] ?? null) ? $providerResponse['items'] : [];
                $item = is_array($items[(string) $unit] ?? null) ? $items[(string) $unit] : [];
                $items[(string) $unit] = [
                    ...$item,
                    'unit' => $unit,
                    'request_id' => $item['request_id'] ?? $this->unitRequestId($recipient, $unit),
                    'status' => TopupProviderStatus::Processing->value,
                    'failure_reason' => $reason,
                    'last_checked_at' => now()->toISOString(),
                ];
                $providerResponse['items'] = $items;
            }

            $recipient->forceFill([
                'status' => 'processing',
                'provider_status' => TopupProviderStatus::Processing->value,
                'provider_response' => $providerResponse,
                'failure_reason' => $reason,
                'last_checked_at' => now(),
            ])->save();

            return $recipient;
        }, 3);

        if ($recipient instanceof OrderRecipient) {
            $this->aggregateOrder($recipient->order_id);
        }
    }

    private function prepareSubmission(int $recipientId, int $unit): ?OrderRecipient
    {
        return DB::transaction(function () use ($recipientId, $unit): ?OrderRecipient {
            $recipient = OrderRecipient::query()->lockForUpdate()->find($recipientId);

            if (! $recipient instanceof OrderRecipient
                || $unit < 1
                || $unit > $recipient->quantity
                || in_array($recipient->status, ['completed', 'failed', 'cancelled'], true)) {
                return null;
            }

            $existingItem = $this->providerItem($recipient, $unit);
            if (in_array($existingItem['status'] ?? null, ['completed', 'failed'], true)) {
                return null;
            }

            $order = Order::query()->lockForUpdate()->find($recipient->order_id);
            if (! $order instanceof Order || $order->payment_status !== PaymentStatus::Paid || $order->order_status === OrderStatus::Cancelled) {
                return null;
            }

            $recipient->forceFill([
                'provider_request_id' => $recipient->provider_request_id ?: $this->baseRequestId($order, $recipient),
                'status' => 'processing',
                'provider_status' => $recipient->provider_status ?: TopupProviderStatus::Processing->value,
                'failure_reason' => null,
            ])->save();

            return $recipient->load(['order.provider', 'order.server']);
        }, 3);
    }

    private function storeUnitResult(
        int $recipientId,
        int $unit,
        string $requestId,
        TopupProviderResultDto $result,
        bool $submitted,
        int $attempt = 0,
    ): void {
        DB::transaction(function () use ($recipientId, $unit, $requestId, $result, $submitted, $attempt): void {
            $recipient = OrderRecipient::query()->lockForUpdate()->findOrFail($recipientId);

            if (in_array($recipient->status, ['completed', 'cancelled'], true)) {
                return;
            }

            $providerResponse = $recipient->provider_response ?? [];
            $items = is_array($providerResponse['items'] ?? null) ? $providerResponse['items'] : [];
            $previousItem = is_array($items[(string) $unit] ?? null) ? $items[(string) $unit] : [];
            $items[(string) $unit] = [
                'unit' => $unit,
                'request_id' => $requestId,
                'reference' => $result->reference ?: ($previousItem['reference'] ?? null),
                'status' => $result->status->value,
                'message' => $result->message,
                'response' => $result->response,
                'submitted_at' => $submitted
                    ? ($previousItem['submitted_at'] ?? now()->toISOString())
                    : ($previousItem['submitted_at'] ?? null),
                'last_checked_at' => $submitted ? ($previousItem['last_checked_at'] ?? null) : now()->toISOString(),
                'check_attempts' => max((int) ($previousItem['check_attempts'] ?? 0), $attempt),
            ];
            $providerResponse['items'] = $items;

            $recipientStatus = $this->recipientStatus($recipient->quantity, $items);
            $recipient->forceFill([
                'status' => $recipientStatus,
                'provider_reference' => $recipient->provider_reference ?: $result->reference,
                'provider_status' => match ($recipientStatus) {
                    'completed' => TopupProviderStatus::Completed->value,
                    'failed' => TopupProviderStatus::Failed->value,
                    default => TopupProviderStatus::Processing->value,
                },
                'provider_response' => $providerResponse,
                'status_check_attempts' => max($recipient->status_check_attempts, $attempt),
                'failure_reason' => $recipientStatus === 'failed' ? 'Một hoặc nhiều lượt nạp không thành công.' : null,
                'submitted_at' => $submitted ? ($recipient->submitted_at ?? now()) : $recipient->submitted_at,
                'last_checked_at' => $submitted ? $recipient->last_checked_at : now(),
                'completed_at' => $recipientStatus === 'completed' ? now() : null,
                'failed_at' => $recipientStatus === 'failed' ? now() : null,
            ])->save();

            if ($recipient->quantity === 1 && $result->reference !== null && $recipient->order()->firstOrFail()->recipients()->count() === 1) {
                Order::query()
                    ->whereKey($recipient->order_id)
                    ->whereNull('provider_reference')
                    ->update(['provider_reference' => $result->reference]);
            }
        }, 3);
    }

    /** @param array<int|string, mixed> $items */
    private function recipientStatus(int $quantity, array $items): string
    {
        $statuses = collect(range(1, $quantity))
            ->map(fn (int $unit): string => (string) data_get($items, $unit.'.status', 'pending'));

        if ($statuses->every(fn (string $status): bool => $status === TopupProviderStatus::Completed->value)) {
            return 'completed';
        }

        if ($statuses->every(fn (string $status): bool => in_array($status, ['completed', 'failed'], true))) {
            return 'failed';
        }

        return 'processing';
    }

    /** @return array<string, mixed> */
    private function providerItem(OrderRecipient $recipient, int $unit): array
    {
        $item = data_get($recipient->provider_response, 'items.'.$unit, []);

        return is_array($item) ? $item : [];
    }

    private function unitRequestId(OrderRecipient $recipient, int $unit): string
    {
        $baseRequestId = $recipient->provider_request_id
            ?: $this->baseRequestId($recipient->order, $recipient);

        return $recipient->quantity === 1
            ? $baseRequestId
            : $baseRequestId.'-U'.str_pad((string) $unit, 3, '0', STR_PAD_LEFT);
    }

    private function baseRequestId(Order $order, OrderRecipient $recipient): string
    {
        return $order->code.'-R'.str_pad((string) $recipient->position, 3, '0', STR_PAD_LEFT);
    }

    private function aggregateOrder(int $orderId): void
    {
        $mail = DB::transaction(function () use ($orderId): ?string {
            $order = Order::query()->lockForUpdate()->find($orderId);
            if (! $order instanceof Order || in_array($order->order_status, [OrderStatus::Completed, OrderStatus::Cancelled], true)) {
                return null;
            }

            $counts = $order->recipients()
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status');
            $total = (int) $counts->sum();
            $completed = (int) ($counts['completed'] ?? 0);
            $failed = (int) ($counts['failed'] ?? 0);

            if ($total > 0 && $completed === $total) {
                $this->orderStatusService->transition($order, OrderStatus::Completed);

                return 'completed';
            }

            if ($total > 0 && $completed + $failed === $total && $failed > 0) {
                $this->orderStatusService->transition($order, OrderStatus::Failed, 'Một hoặc nhiều tài khoản nạp thất bại.');

                return 'failed';
            }

            return null;
        }, 3);

        if ($mail === null) {
            return;
        }

        $order = Order::query()->findOrFail($orderId);
        Mail::to($order->email)->queue(
            $mail === 'completed' ? new OrderCompletedMail($order) : new OrderFailedMail($order),
        );
    }
}
