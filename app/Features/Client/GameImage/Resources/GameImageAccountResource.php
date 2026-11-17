<?php

namespace App\Features\Client\GameImage\Resources;

use App\Features\Client\GameImage\Services\GameImageAssetService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameImageAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'cName' => $this->cname,
            'server' => $this->server,
            'status' => $this->status,
            'capture_version' => $this->capture_version,
            'error_code' => $this->error_code, 'error_message' => $this->error_message,
            'charged_amount' => $this->charged_amount,
            'refunded_amount' => $this->refunded_amount ?? ($this->refunded_at ? $this->charged_amount : null),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'layout' => $this->layout,
            'capture_urls' => app(GameImageAssetService::class)->sourceUrls($this->resource),
            'capture_upload_url' => route('tools.game-image.capture', $this->resource),
            'data_download_url' => $this->capture_directory ? route('tools.game-image.download-data', $this->resource) : null,
            'preview_url' => $this->preview_path ? route('tools.game-image.preview', $this->resource) : null,
            'edit_url' => route('tools.game-image.edit', $this->resource),
            'update_url' => route('tools.game-image.update', $this->resource),
            'share_url' => route('tools.game-image.share', $this->resource),
            'has_share' => $this->share_token !== null && $this->share_expires_at?->isFuture(),
        ];
    }
}
