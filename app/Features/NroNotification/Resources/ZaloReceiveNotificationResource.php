<?php

namespace App\Features\NroNotification\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ZaloReceiveNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'box_zalo_id' => $this->box_zalo_id,
            'zalo_id' => $this->zalo_id,
            'char_name' => $this->char_name,
            'char_server' => $this->char_server,
            'type_receive' => $this->type_receive,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
