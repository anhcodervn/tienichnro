<?php

namespace App\Features\Admin\License\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LicenseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'product_id' => $this->product_id, 'plan_id' => $this->plan_id, 'user_id' => $this->user_id,
            'product_name' => $this->whenLoaded('product', fn () => $this->product->name), 'plan_name' => $this->whenLoaded('plan', fn () => $this->plan->name), 'key_prefix' => $this->key_prefix,
            'status' => in_array($this->status, ['unused', 'active'], true) && $this->expires_at?->lte(now()) ? 'expired' : $this->status,
            'activated_at' => $this->activated_at?->toISOString(), 'expires_at' => $this->expires_at?->toISOString(), 'generation' => $this->generation,
            'current_device_uuid' => $this->current_device_uuid, 'last_transfer_at' => $this->last_transfer_at?->toISOString(),
            'online' => $this->whenLoaded('sessions', fn () => $this->sessions->contains(fn ($session) => $session->status === 'active' && $session->generation === $this->generation && $session->lease_expires_at->isFuture() && $this->status === 'active' && (! $this->expires_at || $this->expires_at->isFuture()) && $this->product->is_active)),
            'devices' => $this->whenLoaded('devices'), 'sessions' => $this->whenLoaded('sessions'), 'events' => $this->whenLoaded('events')];
    }
}
