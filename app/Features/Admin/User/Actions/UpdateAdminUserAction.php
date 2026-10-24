<?php

namespace App\Features\Admin\User\Actions;

use App\Actions\RecordUserLogAction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UpdateAdminUserAction
{
    public function __construct(private readonly RecordUserLogAction $logs) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $user, array $attributes, Request $request): User
    {
        return DB::transaction(function () use ($user, $attributes, $request): User {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $user->fill($attributes);
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }
            $user->save();
            $this->logs->handle($user, 'admin_profile_updated', 'Thông tin tài khoản được cập nhật bởi admin #'.$request->user()->id, $request);

            return $user;
        }, 3);
    }
}
