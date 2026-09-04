<?php

namespace App\Features\Admin\Affiliate\Controllers;

use App\Features\Admin\Affiliate\Requests\AffiliateIndexRequest;
use App\Features\Admin\Affiliate\Requests\UpdateAffiliateCommissionRequest;
use App\Features\Admin\Affiliate\Services\AdminAffiliateService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AffiliateCommissionController extends Controller
{
    public function __construct(private readonly AdminAffiliateService $service) {}

    public function index(AffiliateIndexRequest $request): JsonResponse
    {
        return response()->json(['status' => true, 'data' => $this->service->commissions($request->validated())]);
    }

    public function update(UpdateAffiliateCommissionRequest $request, int $commission): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        return response()->json(['status' => true, 'data' => $this->service->updateCommission(
            $commission,
            $request->validated(),
            $admin,
            $request,
        )]);
    }
}
