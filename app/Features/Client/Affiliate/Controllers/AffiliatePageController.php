<?php

namespace App\Features\Client\Affiliate\Controllers;

use App\Features\Affiliate\Services\AffiliateProgramService;
use App\Features\Client\Affiliate\Services\AffiliatePublicPageService;
use App\Http\Controllers\Controller;
use App\Support\SettingStore;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AffiliatePageController extends Controller
{
    public function __invoke(
        AffiliateProgramService $programService,
        AffiliatePublicPageService $publicPageService,
        SettingStore $settingStore,
    ): View {
        $program = $programService->enabled();
        abort_unless($program !== null, 404);

        if (! Auth::check()) {
            return view('client.affiliate.introduction', $publicPageService->data($program));
        }

        return view('app', ['systemSettings' => $settingStore->getMany([
            'site_name' => config('app.name', 'Nạp Carot'), 'meta_title' => '', 'meta_description' => '',
            'light_logo' => '', 'dark_logo' => '', 'favicon' => '',
        ])]);
    }
}
