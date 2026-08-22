<?php

namespace App\Features\Admin\Topup\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TopupProviderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'has_connection_config' => filled($this->getRawOriginal('connection_config')),
            'connection_config' => $this->when(
                $request->routeIs('admin.topup.topup-providers.show'),
                fn (): array => $this->maskedConnectionConfig(),
            ),
            'packages_count' => $this->whenCounted('packages'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
