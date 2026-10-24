<?php

namespace App\Features\Client\Profile\Services;

use App\Models\License;
use App\Models\User;

class ProfilePageService
{
    /** @return array<string, mixed> */
    public function data(User $user, string $activeTab): array
    {
        abort_unless(in_array($activeTab, ['dashboard', 'profile', 'password', 'logs'], true), 404);

        return [
            'user' => $user,
            'activeTab' => $activeTab,
            'licenses' => $activeTab === 'dashboard'
                ? License::query()->where('user_id', $user->id)
                    ->with(['product', 'plan', 'devices' => fn ($query) => $query->where('status', 'active')])
                    ->latest('id')->paginate(12)->withQueryString()
                : null,
            'userLogs' => $activeTab === 'logs'
                ? $user->userLogs()->latest('id')->paginate(12)->withQueryString()
                : null,
        ];
    }
}
