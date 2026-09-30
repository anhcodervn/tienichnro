<?php

namespace App\Features\Client\Affiliate\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SettingStore;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CollaboratorPageController extends Controller
{
    public function __invoke(Request $request, SettingStore $settingStore): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->canAccessCollaboratorDashboard(), 403);

        return view('app', ['systemSettings' => $settingStore->getMany([
            'site_name' => config('app.name', 'Nạp Carot'), 'meta_title' => '', 'meta_description' => '',
            'light_logo' => '', 'dark_logo' => '', 'favicon' => '',
        ])]);
    }
}
