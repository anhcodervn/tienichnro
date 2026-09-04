<?php

namespace App\Features\Client\Affiliate\Controllers;

use App\Features\Affiliate\Services\AffiliateWalletService;
use App\Features\Client\Affiliate\Requests\ConvertAffiliateBalanceRequest;
use App\Features\Client\Affiliate\Requests\StoreAffiliateWithdrawalRequest;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AffiliateWalletController extends Controller
{
    public function __construct(private readonly AffiliateWalletService $service) {}

    public function convert(ConvertAffiliateBalanceRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $conversion = $this->service->convert($this->user($request), (int) $payload['amount'], $payload['idempotency_key']);

        return response()->json([
            'status' => true,
            'message' => 'Quy đổi sang ví chính thành công.',
            'data' => $conversion->only(['id', 'amount', 'status', 'created_at']),
        ]);
    }

    public function withdraw(StoreAffiliateWithdrawalRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $withdrawal = $this->service->requestWithdrawal($this->user($request), (int) $payload['amount'], $payload['idempotency_key']);

        return response()->json([
            'status' => true,
            'message' => 'Đã gửi yêu cầu rút hoa hồng.',
            'data' => $withdrawal->only(['id', 'amount', 'status', 'created_at']),
        ], 201);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
