<?php

namespace App\Features\Client\Profile\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class AccountLicenseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $canActivate = $this->resource->getRawOriginal('encrypted_key') !== null
            && Gate::forUser($request->user())->allows('download', $this->resource);

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_code' => $this->product?->product_code,
            'product_name' => $this->product?->name,
            'minimum_version' => $this->product?->minimum_version,
            'plan_name' => $this->plan?->name,
            'key_prefix' => $this->key_prefix,
            'key' => $canActivate ? $this->encrypted_key : null,
            'can_activate' => $canActivate,
            'status' => in_array($this->status, ['unused', 'active'], true) && $this->expires_at?->lte(now()) ? 'expired' : $this->status,
            'duration_days' => $this->duration_days,
            'activated_at' => $this->activated_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
        ];
    }
}
