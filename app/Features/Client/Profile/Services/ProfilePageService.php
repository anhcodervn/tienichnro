<?php

namespace App\Features\Client\Profile\Services;

use App\Models\User;

class ProfilePageService
{
    /** @return array<string, mixed> */
    public function data(User $user, string $activeTab): array
    {
        abort_unless(in_array($activeTab, ['profile', 'password', 'logs'], true), 404);

        return [
            'user' => $user,
            'activeTab' => $activeTab,
            'userLogs' => $activeTab === 'logs'
                ? $user->userLogs()->latest('id')->paginate(12)->withQueryString()
                : null,
        ];
    }
}
