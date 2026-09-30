<?php

namespace App\Features\Admin\User\Actions;

use App\Exceptions\ApiException;
use App\Models\User;

class UpdateAdminUserRoleAction
{
    /** @param array{role:string} $payload */
    public function handle(User $user, array $payload): User
    {
        if ($user->role === User::ROLE_ADMIN) {
            throw new ApiException('Không thể thay đổi vai trò của tài khoản admin.', 422);
        }

        $attributes = ['role' => $payload['role']];

        if ($payload['role'] !== User::ROLE_COLLABORATOR) {
            $attributes['game_service_secondary_password'] = null;
        }

        $user->forceFill($attributes)->save();

        if ($payload['role'] !== User::ROLE_COLLABORATOR) {
            $user->allowedGameServices()->detach();
        }

        return $user->refresh();
    }
}
