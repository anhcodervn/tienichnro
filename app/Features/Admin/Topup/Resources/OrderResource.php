<?php

namespace App\Features\Admin\Topup\Resources;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Features\Topup\Services\TopupProviderResolver;
use App\Models\OrderRecipient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'email' => $this->email,
            'user_id' => $this->user_id, 'game' => $this->game?->name, 'server' => $this->server?->name,
            'game_account' => $this->game_account, 'game_character' => $this->game_character,
            'package_name' => $this->package_name, 'quantity' => $this->quantity,
            'purchase_mode' => $this->purchase_mode,
            'provider' => $this->provider === null ? null : [
                'id' => $this->provider->id,
                'name' => $this->provider->name,
                'slug' => $this->provider->slug,
            ],
            'checkout_fields' => $this->checkout_fields_snapshot ?? [],
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
                        ])->values()->all(),
                ])->all()),
            'total_amount' => $this->total_amount, 'payment_method' => $this->payment_method->value,
            'payment_status' => $this->payment_status->value, 'order_status' => $this->order_status->value,
            'can_reorder' => $this->payment_status === PaymentStatus::Paid
                && $this->order_status === OrderStatus::Failed
                && TopupProviderResolver::supportsBalance($this->provider?->slug),
            'can_sync_provider' => $this->payment_status === PaymentStatus::Paid
                && $this->order_status === OrderStatus::Processing
                && TopupProviderResolver::supportsStatusChecks($this->provider?->slug),
            'provider_reference' => $this->provider_reference, 'failure_reason' => $this->failure_reason,
            'paid_at' => $this->paid_at?->toISOString(), 'created_at' => $this->created_at?->toISOString(),
        ];
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
