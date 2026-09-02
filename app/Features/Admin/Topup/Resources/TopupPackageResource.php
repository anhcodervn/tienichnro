<?php

namespace App\Features\Admin\Topup\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TopupPackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'game_id' => $this->game_id, 'game_name' => $this->game?->name,
            'provider_id' => $this->provider_id, 'provider_name' => $this->provider?->name,
            'name' => $this->name, 'denomination' => $this->denomination,
            'provider_price' => $this->provider_price, 'price' => $this->price, 'original_price' => $this->original_price,
            'discount_percent' => $this->discount_percent, 'description' => $this->description,
            'bonus_text' => $this->bonus_text, 'min_quantity' => $this->min_quantity,
            'max_quantity' => $this->max_quantity, 'status' => $this->status, 'sort_order' => $this->sort_order,
            'metadata' => $this->metadata ?? [], 'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
