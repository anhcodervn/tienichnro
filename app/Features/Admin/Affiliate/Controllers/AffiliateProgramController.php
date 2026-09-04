<?php

namespace App\Features\Admin\Affiliate\Controllers;

use App\Features\Admin\Affiliate\Requests\AffiliateIndexRequest;
use App\Features\Admin\Affiliate\Requests\UpdateAffiliatePackageRateRequest;
use App\Features\Admin\Affiliate\Requests\UpdateAffiliateProgramRequest;
use App\Features\Admin\Affiliate\Services\AdminAffiliateService;
use App\Http\Controllers\Controller;
use App\Models\TopupPackage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AffiliateProgramController extends Controller
{
    public function __construct(private readonly AdminAffiliateService $service) {}

    public function show(AffiliateIndexRequest $request): JsonResponse
    {
        $payload = $request->validated();

        return response()->json(['status' => true, 'data' => $this->service->configuration(isset($payload['site_id']) ? (int) $payload['site_id'] : null)]);
    }

    public function update(UpdateAffiliateProgramRequest $request): JsonResponse
    {
        return response()->json(['status' => true, 'data' => $this->service->updateProgram(
            $request->validated(), $this->admin($request), $request,
        )]);
    }

    public function updateRate(UpdateAffiliatePackageRateRequest $request, TopupPackage $topupPackage): JsonResponse
    {
        return response()->json(['status' => true, 'data' => $this->service->updateRate(
            $topupPackage, $request->validated(), $this->admin($request), $request,
        )]);
    }

    private function admin(Request $request): User
    {
        /** @var User $admin */
        $admin = $request->user();

        return $admin;
    }
}
