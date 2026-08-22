<?php

namespace App\Listeners;

use App\Features\Topup\Services\OrderClaimService;
use App\Models\User;
use Illuminate\Auth\Events\Verified;

class ClaimVerifiedUserOrders
{
    public function __construct(private readonly OrderClaimService $orderClaimService) {}

    public function handle(Verified $event): void
    {
        if ($event->user instanceof User) {
            $this->orderClaimService->claimGuestOrdersForUser($event->user);
        }
    }
}
