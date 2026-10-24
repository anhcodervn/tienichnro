<?php

namespace App\Features\Client\Profile\Controllers;

use App\Features\Client\Profile\Actions\RotateLicenseAction;
use App\Features\Client\Profile\Requests\RotateLicenseRequest;
use App\Http\Controllers\Controller;
use App\Models\License;
use Illuminate\Http\RedirectResponse;

class RotateLicenseController extends Controller
{
    public function __invoke(RotateLicenseRequest $request, License $license, RotateLicenseAction $action): RedirectResponse
    {
        $action->handle($license, $request);

        return to_route('account.profile.dashboard')->with('success', 'Đã đổi key cho license #'.$license->id.'. Key cũ đã mất hiệu lực.');
    }
}
