<?php

namespace App\Features\Admin\Topup\Resources;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Features\Topup\Services\TopupProviderResolver;
use App\Models\OrderRecipient;
use App\Models\PaymentTransaction;
use App\Support\TenantContext;
use App\Utils\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var PaymentTransaction|null $paymentTransaction */
        $paymentTransaction = $this->whenLoaded('latestPaymentTransaction');
        $legacyPaymentTransaction = $this->whenLoaded('legacyPaymentTransaction');

        if (! $paymentTransaction instanceof PaymentTransaction && $legacyPaymentTransaction instanceof PaymentTransaction) {
            $paymentTransaction = $legacyPaymentTransaction;
        }

        $paymentTransferContent = $paymentTransaction instanceof PaymentTransaction
            ? ($paymentTransaction->transfer_reference
                ?: data_get($paymentTransaction->raw_data, 'transfer_content')
                ?: $paymentTransaction->content)
            : null;

        return [
            'id' => $this->id, 'tenant_id' => $this->tenant_id, 'code' => $this->code, 'topup_id' => $this->topup_id, 'email' => $this->email,
            'site' => $this->when(app(TenantContext::class)->isActive() && Site::isMain(), fn (): ?array => $this->tenant === null ? null : [
                'id' => $this->tenant->id,
                'name' => $this->tenant->name,
                'slug' => $this->tenant->slug,
            ]),
            'user_id' => $this->user_id, 'game' => $this->game?->name, 'server' => $this->server?->name,
            'game_account' => $this->game_account, 'character_name' => $this->game_character,
            'package_name' => $this->package_name, 'quantity' => $this->quantity,
            'purchase_mode' => $this->purchase_mode,
            'provider' => $this->provider === null ? null : [
                'id' => $this->provider->id,
                'name' => $this->provider->name,
                'slug' => $this->provider->slug,
            ],
            'checkout_fields' => $this->checkout_fields_snapshot ?? [],
            'payment_transfer_content' => $paymentTransferContent,
            'pricing' => [
                'sale_unit_price' => (int) ($this->sale_unit_price ?? ($this->quantity > 0 ? (int) $this->total_amount / $this->quantity : 0)),
                'sale_total' => (int) $this->total_amount,
                'sale_price' => (int) $this->total_amount,
                'tenant_cost_unit_price' => $this->tenant_cost_unit_price === null ? null : (int) $this->tenant_cost_unit_price,
                'tenant_cost_total' => $this->tenant_cost_total === null ? null : (int) $this->tenant_cost_total,
                'tenant_profit' => $this->tenant_profit === null ? null : (int) $this->tenant_profit,
                'provider_unit_cost' => Site::isMain() && $this->provider_unit_cost !== null ? (int) $this->provider_unit_cost : null,
                'provider_total_cost' => Site::isMain() && $this->provider_total_cost !== null ? (int) $this->provider_total_cost : null,
                'cost_price' => Site::isMain() && $this->provider_total_cost !== null ? (int) $this->provider_total_cost : null,
                'gross_profit' => Site::isMain() && $this->gross_profit !== null ? (int) $this->gross_profit : null,
                'gross_margin_percent' => ! Site::isMain() || $this->gross_profit === null || (int) $this->total_amount <= 0
                    ? null
                    : round(((int) $this->gross_profit / (int) $this->total_amount) * 100, 1),
                'tax_snapshot_available' => Site::isMain() && $this->tax_enabled !== null,
                'tax_enabled' => Site::isMain() && $this->tax_enabled !== null ? (bool) $this->tax_enabled : null,
                'tax_calculation_type' => Site::isMain() ? $this->tax_calculation_type?->value : null,
                'vat_rate' => Site::isMain() && $this->vat_rate !== null ? (float) $this->vat_rate : null,
                'pit_rate' => Site::isMain() && $this->pit_rate !== null ? (float) $this->pit_rate : null,
                'estimated_vat' => Site::isMain() && $this->estimated_vat !== null ? (int) $this->estimated_vat : null,
                'estimated_pit' => Site::isMain() && $this->estimated_pit !== null ? (int) $this->estimated_pit : null,
                'estimated_tax' => Site::isMain() && $this->estimated_tax !== null ? (int) $this->estimated_tax : null,
                'payment_fee' => Site::isMain() && $this->payment_fee !== null ? (int) $this->payment_fee : null,
                'other_cost' => Site::isMain() && $this->other_cost !== null ? (int) $this->other_cost : null,
                'net_profit' => Site::isMain() && $this->net_profit !== null ? (int) $this->net_profit : null,
                'profit_margin' => Site::isMain() && $this->profit_margin !== null ? (float) $this->profit_margin : null,
                'profit_status' => ! Site::isMain() || $this->net_profit === null
                    ? null
                    : ((int) $this->net_profit < 0 ? 'loss' : 'profit'),
            ],
            'payment_transaction' => $paymentTransaction instanceof PaymentTransaction ? [
                'status' => $paymentTransaction->status,
                'bank_code' => $paymentTransaction->bank_code,
                'account_number' => $paymentTransaction->account_number,
                'amount' => (int) $paymentTransaction->amount,
                'expected_content' => $paymentTransferContent,
                'received_content' => data_get($paymentTransaction->raw_data, 'received_content')
                    ?: data_get($paymentTransaction->raw_data, 'callback_payload.transfer_content')
                    ?: data_get($paymentTransaction->raw_data, 'callback_payload.transaction_description')
                    ?: data_get($paymentTransaction->raw_data, 'callback_payload.data.order.transfer_content')
                    ?: data_get($paymentTransaction->raw_data, 'callback_payload.payload.transaction.description'),
                'provider_transaction_id' => $paymentTransaction->provider_transaction_id,
                'matched_at' => $paymentTransaction->status === 'success' ? $paymentTransaction->updated_at?->toISOString() : null,
            ] : null,
            'recipients' => $this->whenLoaded('recipients', fn (): array => $this->recipients
                ->map(fn (OrderRecipient $recipient): array => [
                    'position' => $recipient->position,
                    'data' => $recipient->recipient_data,
                    'quantity' => $recipient->quantity,
                    'status' => $recipient->status,
                    'provider_reference' => $recipient->provider_reference,
                    'failure_reason' => $recipient->failure_reason,
                    'provider_items' => collect(data_get($recipient->provider_response, 'items', []))
                        ->filter(fn (mixed $item): bool => is_array($item))
                        ->map(fn (array $item): array => [
                            'unit' => $item['unit'] ?? null,
                            'quantity' => $item['quantity'] ?? 1,
                            'request_id' => $item['request_id'] ?? null,
                            'reference' => $item['reference'] ?? null,
                            'status' => $item['status'] ?? null,
                            'current_step' => $this->currentProviderStep($item),
                            'message' => $item['message'] ?? null,
                            'http_status' => data_get($item, 'response.http_status'),
                            'provider_code' => data_get($item, 'response.provider_code'),
                            'provider_status' => data_get($item, 'response.provider_status'),
                            'provider_topup_id' => data_get($item, 'response.provider_topup_id'),
                            'envelope_status' => data_get($item, 'response.envelope_status'),
                            'check_attempts' => $item['check_attempts'] ?? 0,
                            'submitted_at' => $item['submitted_at'] ?? null,
                            'last_checked_at' => $item['last_checked_at'] ?? null,
                            'submission' => is_array($item['submission'] ?? null) ? $item['submission'] : null,
                            'last_status_check' => is_array($item['last_status_check'] ?? null) ? $item['last_status_check'] : null,
                            'last_error' => is_array($item['last_error'] ?? null) ? $item['last_error'] : null,
                        ])->values()->all(),
                ])->all()),
            'total_amount' => $this->total_amount, 'payment_method' => $this->payment_method->value,
            'payment_status' => $this->payment_status->value, 'order_status' => $this->order_status->value,
            'can_reorder' => Site::isMain()
                && $this->payment_status === PaymentStatus::Paid
                && $this->order_status === OrderStatus::Failed
                && TopupProviderResolver::supportsBalance($this->provider?->slug),
            'can_sync_provider' => Site::isMain()
                && $this->payment_status === PaymentStatus::Paid
                && in_array($this->order_status, [OrderStatus::Processing, OrderStatus::Completed], true)
                && TopupProviderResolver::supportsStatusChecks($this->provider?->type?->value ?? $this->provider?->slug)
                && $this->hasQueryableProviderItems(),
            'can_retry_provider_submission' => Site::isMain()
                && $this->payment_status === PaymentStatus::Paid
                && $this->order_status === OrderStatus::Processing
                && TopupProviderResolver::supportsBalance($this->provider?->slug)
                && ($this->isProviderBalanceManualReview() || str_contains((string) $this->failure_reason, 'Provider không đủ số dư')),
            'can_cancel_refund' => Site::isMain()
                && $this->payment_status === PaymentStatus::Paid
                && $this->order_status === OrderStatus::Failed,
            'provider_reference' => $this->provider_reference, 'failure_reason' => $this->failure_reason,
            'paid_at' => $this->paid_at?->toISOString(), 'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function isProviderBalanceManualReview(): bool
    {
        if (data_get($this->metadata, 'provider_manual_review.code') === 'provider_balance_insufficient') {
            return true;
        }

        return $this->relationLoaded('recipients') && $this->recipients->contains(
            fn (OrderRecipient $recipient): bool => data_get($recipient->provider_response, 'manual_review.code') === 'provider_balance_insufficient',
        );
    }

    private function hasQueryableProviderItems(): bool
    {
        if (! $this->relationLoaded('recipients')) {
            return true;
        }

        $includeCompleted = $this->order_status === OrderStatus::Completed;

        return $this->recipients
            ->when(
                $includeCompleted,
                fn ($recipients) => $recipients->where('status', 'completed'),
                fn ($recipients) => $recipients->whereNotIn('status', ['completed', 'failed', 'cancelled']),
            )
            ->contains(function (OrderRecipient $recipient): bool {
                $includeCompleted = $this->order_status === OrderStatus::Completed;

                return collect(data_get($recipient->provider_response, 'items', []))
                    ->contains(fn (mixed $item): bool => is_array($item)
                        && filled($item['reference'] ?? null)
                        && ($item['status'] ?? null) !== 'failed'
                        && (($item['status'] ?? null) !== 'completed' || $includeCompleted));
            });
    }

    /** @param array<string, mixed> $item */
    private function currentProviderStep(array $item): string
    {
        $status = (string) ($item['status'] ?? '');

        if ($status === 'completed') {
            return 'completed';
        }

        if ($status === 'failed') {
            return 'failed';
        }

        if (filled($item['failure_reason'] ?? null)) {
            return 'manual_review';
        }

        if ((int) ($item['check_attempts'] ?? 0) > 0 || filled($item['last_checked_at'] ?? null)) {
            return 'checking_status';
        }

        if (filled($item['submitted_at'] ?? null) || is_array($item['submission'] ?? null)) {
            return 'awaiting_status';
        }

        return 'queued';
    }
}
