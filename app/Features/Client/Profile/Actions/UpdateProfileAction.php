<?php

namespace App\Features\Client\Profile\Actions;

use App\Actions\RecordUserLogAction;
use App\Models\User;
use Illuminate\Http\Request;

class UpdateProfileAction
{
    public function __construct(private readonly RecordUserLogAction $recordUserLogAction) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes, Request $request): void
    {
        $user->fill($attributes)->save();

        $this->recordUserLogAction->handle($user, 'profile_updated', 'Cập nhật thông tin tài khoản', $request);
    }
}
