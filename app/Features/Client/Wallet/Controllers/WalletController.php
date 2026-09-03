<?php

namespace App\Features\Client\Wallet\Controllers;

use App\Features\Client\Wallet\Requests\DepositRequestIndexRequest;
use App\Features\Client\Wallet\Requests\StoreDepositRequestRequest;
use App\Features\Client\Wallet\Requests\WalletOverviewRequest;
use App\Features\Client\Wallet\Resources\ClientRechargeConfigResource;
use App\Features\Client\Wallet\Resources\DepositRequestResource;
use App\Features\Client\Wallet\Services\WalletDepositService;
use App\Features\Client\Wallet\Services\WalletService;
use App\Features\Recharge\Services\RechargeBonusService;
use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Models\RechargeBonusTier;
use App\Models\User;
use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly WalletDepositService $walletDepositService,
        private readonly RechargeBonusService $rechargeBonusService,
    ) {}

    public function index(WalletOverviewRequest $request): JsonResponse
    {
        $user = $this->user($request);
        $resolved = $this->walletDepositService->clientConfig($user);
        $resolvedConfigs = $this->walletDepositService->clientConfigs($user);

        return response()->json(ApiResponse::success(data: [
            'wallet' => $this->walletService->getWalletInfo($user),
            'recharge_config' => $resolved !== null
                ? (new ClientRechargeConfigResource($resolved['config']))->resolve()
                : null,
            'recharge_configs' => collect($resolvedConfigs)
                ->map(fn (array $item): array => (new ClientRechargeConfigResource($item['config']))->resolve())
                ->values()
                ->all(),
            'bonus_tiers' => $this->rechargeBonusService->active()
                ->map(fn (RechargeBonusTier $tier): array => $this->rechargeBonusService->serialize($tier))
                ->values()
                ->all(),
        ]));
    }

    public function depositRequests(DepositRequestIndexRequest $request): JsonResponse
    {
        $transactions = $this->walletDepositService->paginateRequests($this->user($request), $request->validated());

        return response()->json(ApiResponse::success(data: [
            'data' => DepositRequestResource::collection($transactions->getCollection())->resolve(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]));
    }

    public function storeDepositRequest(StoreDepositRequestRequest $request): JsonResponse
    {
        $paymentTransaction = $this->walletDepositService->createRequest(
            $this->user($request),
            (float) $request->validated('amount'),
            $request->validated('config_id') !== null ? (int) $request->validated('config_id') : null,
        );

        return response()->json(ApiResponse::success(
            message: 'Đã tạo yêu cầu nạp tiền.',
            data: [
                'deposit_request' => DepositRequestResource::make($paymentTransaction)->resolve(),
            ],
        ), 201);
    }

    public function confirmDepositRequest(PaymentTransaction $paymentTransaction, Request $request): JsonResponse
    {
        $paymentTransaction = $this->walletDepositService->confirmRequest($paymentTransaction, $this->user($request));

        return response()->json(ApiResponse::success(
            message: 'Đã xác nhận bạn đã chuyển khoản.',
            data: [
                'deposit_request' => DepositRequestResource::make($paymentTransaction)->resolve(),
            ],
        ));
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
