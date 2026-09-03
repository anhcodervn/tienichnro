<?php

namespace App\Features\Client\Api\Services;

use App\Features\Topup\Services\OrderRecipientService;

class ApiDocumentationService
{
    /** @return array<string, int|string> */
    public function data(): array
    {
        return [
            'base_url' => url('/api/v1'),
            'balance_endpoint' => route('api.v1.balance'),
            'catalog_endpoint' => route('api.v1.catalog'),
            'create_order_endpoint' => route('api.v1.orders.store'),
            'order_status_endpoint' => route('api.v1.orders.show', ['order' => 'ORDER_ID']),
            'max_recipients' => OrderRecipientService::MAX_RECIPIENTS,
            'max_quantity_per_recipient' => OrderRecipientService::MAX_QUANTITY_PER_RECIPIENT,
        ];
    }
}
