<?php

namespace App\Features\Admin\GameService\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameServiceOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'email' => $this->email,
            'game_id' => $this->game_id,
            'game_service_id' => $this->game_service_id,
            'game_name' => $this->game_name,
            'service_name' => $this->service_name,
            'package_name' => $this->package_name,
            'price_label' => $this->price_label,
            'server_name' => $this->server_name,
            'payload' => [],
            'payload_locked' => true,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total_amount' => $this->total_amount,
            'collaborator_unit_cost' => $this->collaborator_unit_cost,
            'collaborator_total_cost' => $this->collaborator_total_cost,
            'gross_profit' => $this->gross_profit,
            'tax_enabled' => $this->tax_enabled,
            'tax_calculation_type' => $this->tax_calculation_type?->value,
            'vat_rate' => $this->vat_rate !== null ? (float) $this->vat_rate : null,
            'pit_rate' => $this->pit_rate !== null ? (float) $this->pit_rate : null,
            'estimated_vat' => $this->estimated_vat,
            'estimated_pit' => $this->estimated_pit,
            'estimated_tax' => $this->estimated_tax,
            'net_profit' => $this->net_profit,
            'profit_margin' => $this->profit_margin !== null ? (float) $this->profit_margin : null,
            'status' => $this->status,
            'collaborator_id' => $this->collaborator_id,
            'collaborator' => $this->whenLoaded('collaborator', fn (): ?array => $this->collaborator ? [
                'id' => (int) $this->collaborator->id,
                'name' => (string) $this->collaborator->name,
            ] : null),
            'admin_note' => $this->admin_note,
            'processing_at' => $this->processing_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'collaborator_held_at' => $this->collaborator_held_at?->toISOString(),
            'collaborator_available_at' => $this->collaborator_available_at?->toISOString(),
            'collaborator_settled_at' => $this->collaborator_settled_at?->toISOString(),
            'collaborator_refunded_at' => $this->collaborator_refunded_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
