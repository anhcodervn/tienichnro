<?php

namespace App\Features\Client\Profile\Actions;

use App\Actions\RecordUserLogAction;
use App\Models\User;
use Illuminate\Http\Request;

class UpdatePasswordAction
{
    public function __construct(private readonly RecordUserLogAction $recordUserLogAction) {}

    public function handle(User $user, string $password, Request $request): void
    {
        $user->forceFill(['password' => $password])->save();

        $this->recordUserLogAction->handle($user, 'password_changed', 'Đổi mật khẩu tài khoản', $request);
    }
}
