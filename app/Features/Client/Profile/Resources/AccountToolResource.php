<?php

namespace App\Features\Client\Profile\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountToolResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'product_code' => $this->product_code,
            'description' => $this->description, 'image_url' => $this->image_url ? url($this->image_url) : null,
            'latest_version' => $this->latest_version, 'minimum_version' => $this->minimum_version,
            'downloads' => [
                'tool' => $this->tool_zip_path ? ['url' => route('api.account.tools.download', ['product' => $this->id, 'kind' => 'tool']), 'size' => $this->tool_zip_size, 'sha256' => $this->tool_zip_sha256] : null,
                'update' => $this->update_zip_path ? ['url' => route('api.account.tools.download', ['product' => $this->id, 'kind' => 'update']), 'size' => $this->update_zip_size, 'sha256' => $this->update_zip_sha256] : null,
            ],
        ];
    }
}
