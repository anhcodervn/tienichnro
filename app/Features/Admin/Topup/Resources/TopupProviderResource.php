<?php

namespace App\Features\Admin\Topup\Resources;

use App\Features\Topup\Services\TopupProviderResolver;
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
            'type' => $this->type?->value,
            'has_connection_config' => filled($this->getRawOriginal('connection_config')),
            'payload_field_mapping' => $this->payload_field_mapping ?? [],
            'payload_field_mapping_editor' => $this->when(
                $request->routeIs('admin.topup.topup-providers.show'),
                fn (): array => $this->payload_field_mapping_editor ?? ['default' => [], 'services' => []],
            ),
            'connection_config' => $this->when(
                $request->routeIs('admin.topup.topup-providers.show'),
                fn (): array => $this->maskedConnectionConfig(),
            ),
            'packages_count' => $this->whenCounted('packages'),
            'supports_balance' => TopupProviderResolver::supportsBalance($this->type?->value ?? $this->slug),
            'balance' => $this->balance,
            'balance_currency' => $this->balance_currency,
            'balance_status' => $this->balance_status,
            'balance_checked_at' => $this->balance_checked_at?->toISOString(),
            'balance_error_code' => $this->balance_error_code,
            'balance_error_message' => $this->balance_error_message,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
