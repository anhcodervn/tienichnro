<?php

namespace App\Features\Admin\Setting\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServicePackageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'service_code' => $this->service_code, 'name' => $this->name,
            'description' => $this->description, 'price' => $this->price, 'billing_type' => $this->billing_type,
            'usage_limit' => $this->usage_limit, 'duration_days' => $this->duration_days,
            'is_active' => $this->is_active, 'sort_order' => $this->sort_order];
    }
}
