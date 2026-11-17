<?php

namespace App\Features\Client\GameImage\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameImageToolAccountResource extends JsonResource
{
    /** @var array<string, int|string>|null */
    private ?array $encryptedPassword = null;

    /** @param array<string, int|string> $encryptedPassword */
    public function withEncryptedPassword(array $encryptedPassword): static
    {
        $this->encryptedPassword = $encryptedPassword;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'password_encrypted' => $this->when($request->routeIs('api.game-image.claim') && $this->encryptedPassword !== null, $this->encryptedPassword),
            'server' => $this->server,
            'cName' => $this->cname,
            'status' => $this->status,
            'capture_version' => $this->capture_version,
            'error_code' => $this->error_code, 'error_message' => $this->error_message,
            'processing_at' => $this->processing_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'upload_directory' => $this->capture_disk === 'game-image-uploads' && $this->capture_directory ? '/uploads/ghep-anh/'.$this->capture_directory : null,
            'claim_url' => route('api.game-image.claim', $this->resource),
            'status_url' => route('api.game-image.status', $this->resource),
            'logs_url' => route('api.game-image.logs.store', $this->resource),
            'upload_url' => route('api.game-image.upload', $this->resource),
        ];
    }
}
