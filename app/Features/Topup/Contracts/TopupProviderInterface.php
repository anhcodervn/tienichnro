<?php

namespace App\Features\Topup\Contracts;

use App\Features\Topup\DTOs\TopupProviderResultDto;
use App\Models\GameServer;
use App\Models\Order;
use App\Models\OrderRecipient;
use App\Models\TopupPackage;
use App\Models\TopupProvider;

interface TopupProviderInterface
{
    public function assertConfigured(TopupProvider $provider, TopupPackage $package, GameServer $server): void;

    public function submit(
        Order $order,
        OrderRecipient $recipient,
        ?TopupProvider $provider,
        string $requestId,
        ?int $quantity = null,
    ): TopupProviderResultDto;

    public function status(Order $order, OrderRecipient $recipient, TopupProvider $provider, string $requestId, string $reference): TopupProviderResultDto;

    public function supportsStatusChecks(): bool;

    public function supportsBatchQuantity(): bool;
}
