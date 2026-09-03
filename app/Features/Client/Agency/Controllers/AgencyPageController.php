<?php

namespace App\Features\Client\Agency\Controllers;

use App\Features\Client\Api\Services\ApiDocumentationService;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use App\Utils\Site;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgencyPageController extends Controller
{
    public function website(Request $request): View
    {
        abort_unless(app(TenantContext::class)->isActive() && Site::isMain(), 404);

        /** @var User $user */
        $user = $request->user();
        $agencySite = Tenant::query()
            ->with(['domains:id,tenant_id,domain,is_primary,is_verified'])
            ->where('billing_user_id', $user->id)
            ->latest('id')
            ->first();

        return view('client.agency.website', compact('agencySite'));
    }

    public function api(Request $request, ApiDocumentationService $documentationService): View
    {
        /** @var User $user */
        $user = $request->user();
        $activeApiKeyCount = $user->apiKeys()
            ->where('key_type', 'topup')
            ->where('status', 'active')
            ->count();

        $apiDocumentation = $documentationService->data();

        return view('client.agency.api', compact('activeApiKeyCount', 'apiDocumentation'));
    }
}
