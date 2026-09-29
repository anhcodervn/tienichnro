<?php

namespace App\Features\Admin\GameService\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameServicePackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'game_service_id' => $this->game_service_id,
            'service' => $this->whenLoaded('service', fn (): array => [
                'id' => $this->service->id,
                'name' => $this->service->name,
                'game_id' => $this->service->game_id,
                'game_name' => $this->service->game?->name,
            ]),
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'prices' => $this->whenLoaded('prices', fn () => $this->prices->map(fn ($price): array => [
                'id' => $price->id,
                'label' => $price->label,
                'code' => $price->code,
                'price' => $price->price,
                'collaborator_price' => $price->collaborator_price,
                'quantity_enabled' => $price->quantity_enabled,
                'min_quantity' => $price->min_quantity,
                'max_quantity' => $price->max_quantity,
                'status' => $price->status,
                'sort_order' => $price->sort_order,
            ])),
            'orders_count' => $this->whenCounted('orders'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
