<?php

namespace App\Features\Topup\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderClaimService
{
    public function claimGuestOrdersForUser(User $user): void
    {
        if (! $user->hasVerifiedEmail() || blank($user->email)) {
            return;
        }

        $normalizedEmail = Str::lower(trim((string) $user->email));

        DB::transaction(function () use ($user, $normalizedEmail): void {
            Order::query()
                ->whereNull('user_id')
                ->where('normalized_email', $normalizedEmail)
                ->lockForUpdate()
                ->update(['user_id' => $user->id, 'updated_at' => now()]);
        }, 3);
    }
}
