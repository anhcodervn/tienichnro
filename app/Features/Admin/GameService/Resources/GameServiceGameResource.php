<?php

namespace App\Features\Admin\GameService\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameServiceGameResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'code' => $this->provider_service_code,
            'image' => $this->image,
            'status' => $this->status,
            'game_services_enabled' => (bool) $this->game_services_enabled,
            'servers_count' => $this->whenCounted('servers'),
            'game_services_count' => $this->whenCounted('gameServices'),
        ];
    }
}
