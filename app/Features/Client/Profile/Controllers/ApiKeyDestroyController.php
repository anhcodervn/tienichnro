<?php

namespace App\Features\Client\Profile\Controllers;

use App\Features\Client\Profile\Actions\RevokeApiKeyAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApiKeyDestroyController extends Controller
{
    public function __invoke(Request $request, int $apiKey, RevokeApiKeyAction $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $action->handle($user, $apiKey, $request);

        return to_route('account.profile.api')->with('success', 'API key đã được thu hồi.');
    }
}
