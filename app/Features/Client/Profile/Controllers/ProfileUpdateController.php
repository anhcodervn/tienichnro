<?php

namespace App\Features\Client\Profile\Controllers;

use App\Features\Client\Profile\Actions\UpdateProfileAction;
use App\Features\Client\Profile\Requests\UpdateProfileRequest;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class ProfileUpdateController extends Controller
{
    public function __invoke(UpdateProfileRequest $request, UpdateProfileAction $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $action->handle($user, $request->validated(), $request);

        return to_route('account.profile.edit')->with('success', 'Thông tin tài khoản đã được cập nhật.');
    }
}
