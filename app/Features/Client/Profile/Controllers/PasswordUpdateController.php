<?php

namespace App\Features\Client\Profile\Controllers;

use App\Features\Client\Profile\Actions\UpdatePasswordAction;
use App\Features\Client\Profile\Requests\UpdatePasswordRequest;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class PasswordUpdateController extends Controller
{
    public function __invoke(UpdatePasswordRequest $request, UpdatePasswordAction $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $action->handle($user, $request->string('password')->toString(), $request);

        return to_route('account.profile.password')->with('success', 'Mật khẩu đã được thay đổi.');
    }
}
