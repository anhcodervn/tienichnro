<?php

namespace App\Features\Admin\User\Actions;

use App\Actions\RecordUserLogAction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UpdateUserGameServiceSecondaryPasswordAction
{
    public function __construct(private readonly RecordUserLogAction $recordUserLogAction) {}

    /** @param  array{password: string, password_confirmation: string}  $payload */
    public function handle(User $user, array $payload, User $admin, Request $request): User
    {
        if ($user->role !== User::ROLE_COLLABORATOR) {
            throw ValidationException::withMessages([
                'user' => 'Chỉ có thể cấu hình mật khẩu C2 cho tài khoản CTV.',
            ]);
        }

        $user->forceFill(['game_service_secondary_password' => Hash::make($payload['password'])])->save();

        $this->recordUserLogAction->handle(
            $user,
            'admin_reset_game_service_secondary_password',
            sprintf('Mật khẩu C2 dịch vụ được admin %s cập nhật', $admin->email ?: $admin->username ?: "#{$admin->id}"),
            $request,
        );

        return $user->refresh();
    }
}
