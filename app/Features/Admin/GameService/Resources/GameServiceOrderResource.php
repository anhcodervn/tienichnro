<?php

namespace App\Features\Admin\GameService\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameServiceOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'email' => $this->email,
            'game_id' => $this->game_id,
            'game_service_id' => $this->game_service_id,
            'game_name' => $this->game_name,
            'service_name' => $this->service_name,
            'package_name' => $this->package_name,
            'price_label' => $this->price_label,
            'server_name' => $this->server_name,
            'payload' => $this->payload ?? [],
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'admin_note' => $this->admin_note,
            'processing_at' => $this->processing_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
