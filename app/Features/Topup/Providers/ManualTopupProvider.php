<?php

namespace App\Features\Topup\Providers;

use App\Features\Topup\Contracts\TopupProviderInterface;
use App\Features\Topup\DTOs\TopupProviderResultDto;
use App\Features\Topup\Enums\TopupProviderStatus;
use App\Models\GameServer;
use App\Models\Order;
use App\Models\OrderRecipient;
use App\Models\TopupPackage;
use App\Models\TopupProvider;

class ManualTopupProvider implements TopupProviderInterface
{
    public function assertConfigured(TopupProvider $provider, TopupPackage $package, GameServer $server): void
    {
        // Manual fulfillment does not require remote credentials.
    }

    public function submit(Order $order, OrderRecipient $recipient, ?TopupProvider $provider, string $requestId): TopupProviderResultDto
    {
        return new TopupProviderResultDto(
            status: TopupProviderStatus::Processing,
            reference: 'MANUAL-'.$requestId,
            message: 'Đơn hàng đang chờ quản trị viên xử lý thủ công.',
        );
    }

    public function status(Order $order, OrderRecipient $recipient, TopupProvider $provider, string $requestId, string $reference): TopupProviderResultDto
    {
        return new TopupProviderResultDto(TopupProviderStatus::Processing, $reference);
    }

    public function supportsStatusChecks(): bool
    {
        return false;
    }
}
