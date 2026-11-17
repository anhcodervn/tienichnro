<?php

namespace App\Features\Admin\GameImage\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameImageHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'user_id' => $this->user_id, 'member' => $this->user?->username,
            'username' => $this->username, 'server' => $this->server, 'status' => $this->status,
            'capture_version' => $this->capture_version,
            'error_code' => $this->error_code, 'error_message' => $this->error_message, 'charged_amount' => $this->charged_amount,
            'refunded_amount' => $this->refunded_amount ?? ($this->refunded_at ? $this->charged_amount : null),
            'refunded_at' => $this->refunded_at?->toIso8601String(), 'created_at' => $this->created_at?->toIso8601String(),
            'processing_at' => $this->processing_at?->toIso8601String(), 'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
