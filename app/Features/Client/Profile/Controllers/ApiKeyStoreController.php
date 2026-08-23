<?php

namespace App\Features\Client\Profile\Controllers;

use App\Features\Client\Profile\Actions\CreateApiKeyAction;
use App\Features\Client\Profile\Requests\StoreApiKeyRequest;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class ApiKeyStoreController extends Controller
{
    public function __invoke(StoreApiKeyRequest $request, CreateApiKeyAction $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $credentials = $action->handle($user, $request->string('name')->trim()->toString(), $request);

        return to_route('account.profile.api')
            ->with('success', 'API key và API secret đã được tạo. Hãy sao chép và lưu lại ngay.')
            ->with('new_api_credentials', $credentials);
    }
}
