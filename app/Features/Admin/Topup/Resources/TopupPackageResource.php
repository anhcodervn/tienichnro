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
            'game_server_id' => $this->game_server_id, 'server_name' => $this->server?->name,
            'provider_id' => $this->provider_id, 'provider_name' => $this->provider?->name,
            'provider_service_code' => $this->provider_service_code,
            'name' => $this->name, 'denomination' => $this->denomination, 'carot_amount' => $this->carot_amount,
            'reward_x2_amount' => $this->reward_x2_amount, 'reward_x3_amount' => $this->reward_x3_amount,
            'first_topup_reward_amount' => $this->first_topup_reward_amount,
            'provider_price' => $this->provider_price, 'price' => $this->price, 'original_price' => $this->original_price,
            'discount_percent' => $this->discount_percent, 'description' => $this->description,
            'bonus_text' => $this->bonus_text, 'min_quantity' => $this->min_quantity,
            'max_quantity' => $this->max_quantity, 'status' => $this->status, 'sort_order' => $this->sort_order,
            'metadata' => $this->metadata ?? [], 'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
