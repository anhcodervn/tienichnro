<?php

namespace App\Features\Client\Profile\Controllers;

use App\Features\Client\Profile\Services\ProfilePageService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfilePageController extends Controller
{
    public function __invoke(Request $request, ProfilePageService $service, string $tab = 'profile'): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('client.account.profile', $service->data($user, $tab));
    }
}
