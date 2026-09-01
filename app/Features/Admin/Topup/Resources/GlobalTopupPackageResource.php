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
            'name' => $this->name,
            'code' => $this->code,
            'denomination' => $this->denomination,
            'price' => $this->price,
            'original_price' => $this->original_price,
            'discount_percent' => $this->original_price > 0
                ? round((($this->original_price - $this->price) * 100) / $this->original_price, 2)
                : 0,
            'description' => $this->description,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'packages_count' => $this->whenCounted('packages'),
            'provider_price_min' => $this->whenAggregated('packages', 'provider_price', 'min'),
            'provider_price_max' => $this->whenAggregated('packages', 'provider_price', 'max'),
            'level_prices' => $this->whenLoaded('levelPrices'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
