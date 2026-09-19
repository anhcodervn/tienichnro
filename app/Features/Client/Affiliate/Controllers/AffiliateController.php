<?php

namespace App\Features\Client\Affiliate\Controllers;

use App\Features\Client\Affiliate\Requests\UpdateAffiliatePayoutRequest;
use App\Features\Client\Affiliate\Services\AffiliateDashboardService;
use App\Features\Client\Affiliate\Services\AffiliateRateCardService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AffiliateController extends Controller
{
    public function __construct(
        private readonly AffiliateDashboardService $service,
        private readonly AffiliateRateCardService $rateCardService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['status' => true, 'data' => $this->service->data($this->user($request))]);
    }

    public function updatePayout(UpdateAffiliatePayoutRequest $request): JsonResponse
    {
        $profile = $this->service->updatePayout($this->user($request), $request->validated());

        return response()->json(['status' => true, 'message' => 'Đã cập nhật tài khoản nhận tiền.', 'data' => ['bank_name' => $profile->bank_name]]);
    }

    public function rates(Request $request): JsonResponse
    {
        return response()->json(['status' => true, 'data' => $this->rateCardService->data($this->user($request))]);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
