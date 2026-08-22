<?php

namespace App\Features\Client\Profile\Controllers;

use App\Features\Client\Profile\Actions\RevokeApiTokenAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApiTokenDestroyController extends Controller
{
    public function __invoke(Request $request, int $token, RevokeApiTokenAction $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $action->handle($user, $token, $request);

        return to_route('account.profile.api')->with('success', 'API key đã được thu hồi.');
    }
}
