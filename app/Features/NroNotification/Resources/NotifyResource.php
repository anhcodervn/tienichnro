<?php

namespace App\Features\NroNotification\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotifyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'server_id' => $this->server_id, 'server' => $this->whenLoaded('server'),
            'server_code' => $this->whenLoaded('server', fn (): int => $this->server->server_code),
            'code_id' => $this->code_id, 'code' => $this->whenLoaded('code', fn (): string => $this->code->code),
            'boss_id' => $this->boss_id, 'boss_name' => $this->boss_name, 'boss' => $this->whenLoaded('boss'),
            'is_boss' => $this->resource->isBoss(), 'boss_global' => $this->resource->isBoss() && $this->boss_id === null,
            'char_name' => $this->char_name, 'content' => $this->content, 'death_content' => $this->death_content,
            'map_name' => $this->map_name, 'map_id' => $this->map_id, 'zone' => $this->zone, 'zone_name' => $this->zone_name,
            'time_start' => $this->time_start?->toISOString(), 'expires_at' => $this->expires_at?->toISOString(),
            'death_time' => $this->death_time?->toISOString(), 'killed_by' => $this->killed_by,
            'respawn_at' => $this->respawn_at?->toISOString(), 'metadata' => $this->metadata,
            'state' => $this->boss_id === null && $this->boss_name === null ? 'notification' : ($this->death_time === null ? 'living' : 'dead'),
            'created_at' => $this->created_at?->toISOString(), 'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
