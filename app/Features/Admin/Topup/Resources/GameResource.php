<?php

namespace App\Features\Admin\Topup\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'slug' => $this->slug,
            'short_name' => $this->short_name, 'image' => $this->image, 'description' => $this->description,
            'reward_label' => $this->reward_label,
            'provider_service_code' => $this->provider_service_code,
            'package_mode' => $this->package_mode,
            'min_quantity' => $this->min_quantity,
            'max_quantity' => $this->max_quantity,
            'checkout_fields' => $this->checkoutFields(),
            'status' => $this->status, 'sort_order' => $this->sort_order,
            'metadata' => $this->metadata ?? [], 'servers_count' => $this->whenCounted('servers'),
            'packages_count' => $this->whenCounted('packages'), 'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
