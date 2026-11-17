<?php

namespace App\Features\Client\GameImage\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameImageCaptureLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'capture_version' => $this->capture_version, 'client_log_id' => $this->client_log_id,
            'level' => $this->level, 'step' => $this->step, 'message' => $this->message,
            'occurred_at' => $this->occurred_at?->toISOString(), 'created_at' => $this->created_at?->toISOString()];
    }
}
