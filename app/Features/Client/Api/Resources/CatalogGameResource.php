<?php

namespace App\Features\Client\Api\Resources;

use App\Models\GameServer;
use App\Models\TopupPackage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogGameResource extends JsonResource
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
            'short_name' => $this->short_name,
            'min_amount' => $this->min_quantity,
            'max_amount' => $this->max_quantity,
            'payload_fields' => $this->checkoutFields(),
            'servers' => $this->whenLoaded('servers', fn (): array => $this->servers
                ->map(fn (GameServer $server): array => [
                    'id' => $server->id,
                    'name' => $server->name,
                ])->all()),
            'packages' => $this->whenLoaded('packages', fn (): array => $this->packages
                ->map(fn (TopupPackage $package): array => [
                    'id' => $package->id,
                    'name' => $package->name,
                    'price' => $package->denomination !== null ? (int) $package->denomination : null,
                    'sale_price' => (int) $package->price,
                    'retail_price' => (int) ($package->retail_price ?? $package->price),
                    'package_source' => $package->package_source ?? 'custom',
                    'original_price' => $package->original_price !== null ? (int) $package->original_price : null,
                    'min_amount' => $this->min_quantity,
                    'max_amount' => $this->max_quantity,
                    'currency' => 'VND',
                ])->all()),
        ];
    }
}
