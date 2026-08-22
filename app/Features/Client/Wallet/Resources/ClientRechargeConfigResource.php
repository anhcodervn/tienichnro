<?php

namespace App\Features\Client\Wallet\Resources;

use App\Models\ConfigRecharge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ConfigRecharge
 */
class ClientRechargeConfigResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bank_name' => $this->bank_name,
            'account_name' => $this->account_name,
            'account_number' => $this->account_number,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
