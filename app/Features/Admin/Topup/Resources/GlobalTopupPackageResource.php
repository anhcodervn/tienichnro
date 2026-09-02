<?php

namespace App\Features\Admin\Topup\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GlobalTopupPackageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider_id' => $this->provider_id,
            'provider_name' => $this->provider?->name,
            'provider_slug' => $this->provider?->slug,
            'name' => $this->name,
            'code' => $this->code,
            'denomination' => $this->denomination,
            'provider_price' => $this->provider_price,
            'price' => $this->price,
            'original_price' => $this->denomination,
            'discount_percent' => $this->denomination > 0
                ? round((($this->denomination - $this->price) * 100) / $this->denomination, 2)
                : 0,
            'description' => $this->description,
            'bonus_text' => $this->bonus_text,
            'min_quantity' => $this->min_quantity,
            'max_quantity' => $this->max_quantity,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'metadata' => $this->metadata ?? [],
            'packages_count' => $this->whenCounted('packages'),
            'level_prices' => $this->whenLoaded('levelPrices'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
