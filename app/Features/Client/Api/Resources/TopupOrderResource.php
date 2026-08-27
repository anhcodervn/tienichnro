<?php

namespace App\Features\Client\Api\Resources;

use App\Models\OrderRecipient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TopupOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'order_id' => $this->code,
            'request_id' => $this->idempotency_key,
            'status' => $this->order_status->value,
            'payment_status' => $this->payment_status->value,
            'game' => [
                'id' => $this->game_id,
                'name' => $this->game?->name,
            ],
            'server' => [
                'id' => $this->game_server_id,
                'name' => $this->server?->name,
            ],
            'package' => [
                'id' => $this->topup_package_id,
                'name' => $this->package_name,
                'price' => $this->denomination !== null ? (int) $this->denomination : null,
                'sale_price' => intdiv((int) $this->total_amount, max((int) $this->quantity, 1)),
            ],
            'total' => (int) $this->total_amount,
            'currency' => 'VND',
            'payload' => $this->whenLoaded('recipients', fn (): array => $this->recipients
                ->map(fn (OrderRecipient $recipient): array => [
                    ...$recipient->recipient_data,
                    'amount' => $recipient->quantity,
                    'status' => $recipient->status,
                    'failure_reason' => $recipient->failure_reason,
                ])->all()),
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'processing_at' => $this->processing_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'failed_at' => $this->failed_at?->toISOString(),
        ];
    }
}
