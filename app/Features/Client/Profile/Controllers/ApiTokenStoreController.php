<?php

namespace App\Features\Client\Profile\Controllers;

use App\Features\Client\Profile\Actions\CreateApiTokenAction;
use App\Features\Client\Profile\Requests\StoreApiTokenRequest;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class ApiTokenStoreController extends Controller
{
    public function __invoke(StoreApiTokenRequest $request, CreateApiTokenAction $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $plainTextToken = $action->handle($user, $request->string('name')->trim()->toString(), $request);

        return to_route('account.profile.api')
            ->with('success', 'API key đã được tạo. Hãy sao chép và lưu lại ngay.')
            ->with('new_api_token', $plainTextToken);
    }
}
