<?php

namespace App\Features\Admin\GameService\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'game_id' => $this->game_id,
            'game' => $this->whenLoaded('game', fn (): array => [
                'id' => $this->game->id,
                'name' => $this->game->name,
                'slug' => $this->game->slug,
                'code' => $this->game->provider_service_code,
            ]),
            'name' => $this->name,
            'slug' => $this->slug,
            'code' => $this->code,
            'description' => $this->description,
            'background_image' => $this->background_image,
            'payload_fields' => $this->payload_fields ?? [],
            'seo_content' => $this->seo_content ?? [],
            'faqs' => $this->faqs ?? [],
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'server_ids' => $this->whenLoaded('servers', fn () => $this->servers->pluck('id')->values()),
            'servers' => $this->whenLoaded('servers', fn () => $this->servers->map(fn ($server): array => [
                'id' => $server->id,
                'name' => $server->name,
                'code' => $server->code,
            ])),
            'packages_count' => $this->whenCounted('packages'),
            'orders_count' => $this->whenCounted('orders'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
