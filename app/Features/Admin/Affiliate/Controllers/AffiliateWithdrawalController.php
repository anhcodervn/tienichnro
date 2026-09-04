<?php

namespace App\Features\Admin\Affiliate\Controllers;

use App\Features\Admin\Affiliate\Requests\AffiliateIndexRequest;
use App\Features\Admin\Affiliate\Requests\UpdateAffiliateWithdrawalRequest;
use App\Features\Admin\Affiliate\Services\AdminAffiliateService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AffiliateWithdrawalController extends Controller
{
    public function __construct(private readonly AdminAffiliateService $service) {}

    public function index(AffiliateIndexRequest $request): JsonResponse
    {
        return response()->json(['status' => true, 'data' => $this->service->withdrawals($request->validated())]);
    }

    public function show(int $withdrawal): JsonResponse
    {
        return response()->json(['status' => true, 'data' => $this->service->withdrawal($withdrawal)]);
    }

    public function update(UpdateAffiliateWithdrawalRequest $request, int $withdrawal): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        return response()->json(['status' => true, 'data' => $this->service->updateWithdrawal(
            $withdrawal, $request->validated(), $admin, $request,
        )]);
    }
}
