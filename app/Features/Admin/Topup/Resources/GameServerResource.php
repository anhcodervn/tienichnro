<?php

namespace App\Features\Admin\Topup\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameServerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'game_id' => $this->game_id, 'game_name' => $this->game?->name,
            'name' => $this->name, 'code' => $this->code, 'status' => $this->status,
            'sort_order' => $this->sort_order, 'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
