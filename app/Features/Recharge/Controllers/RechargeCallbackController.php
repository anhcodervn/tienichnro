<?php

namespace App\Features\Recharge\Controllers;

use App\Features\Client\Wallet\Resources\DepositRequestResource;
use App\Features\Client\Wallet\Services\WalletDepositService;
use App\Features\Recharge\Requests\ApiBankVnCallbackRequest;
use App\Features\Topup\Services\Payments\BankPaymentService;
use App\Http\Controllers\Controller;
use App\Models\ConfigRecharge;
use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;

class RechargeCallbackController extends Controller
{
    public function __construct(
        private readonly WalletDepositService $walletDepositService,
        private readonly BankPaymentService $bankPaymentService,
    ) {}

    public function apibankvn(ApiBankVnCallbackRequest $request): JsonResponse
    {
        if ($response = $this->ensureAuthorized($request)) {
            return $response;
        }

        $paymentTransaction = $this->bankPaymentService->match($request->callbackPayload(), $request->all())
            ?? $this->walletDepositService->handleApiBankVnCallback($request->callbackPayload(), $request->all());

        if ($paymentTransaction === null) {
            return response()->json(ApiResponse::error('Khong tim thay giao dich nap tien phu hop.'), 404);
        }

        return response()->json(ApiResponse::success(
            message: 'Da xu ly callback nap tien thanh cong.',
            data: [
                'deposit_request' => DepositRequestResource::make($paymentTransaction)->resolve(),
            ],
        ));
    }

    private function ensureAuthorized(ApiBankVnCallbackRequest $request): ?JsonResponse
    {
        $bankId = (int) $request->integer('bank_id');

        /** @var ConfigRecharge|null $config */
        $config = ConfigRecharge::query()
            ->where('provider', 'apibankvn_api')
            ->where('api_bank_id', $bankId)
            ->latest('id')
            ->first();

        if (! $config instanceof ConfigRecharge) {
            return response()->json(ApiResponse::error('Khong tim thay cau hinh nap tien phu hop voi bank_id.'), 404);
        }

        $secret = trim((string) ($config->webhook_secret ?? ''));

        if ($secret === '') {
            return response()->json(ApiResponse::error('Cau hinh webhook secret cua bank nay chua day du.'), 503);
        }

        $providedSecret = $request->webhookSecret();

        if ($providedSecret === '' || ! hash_equals($secret, $providedSecret)) {
            return response()->json(ApiResponse::error('Webhook secret khong hop le.'), 403);
        }

        $providedSign = trim((string) $request->input('sign', ''));
        $expectedSign = md5($secret.$bankId);

        if ($providedSign === '' || ! hash_equals($expectedSign, $providedSign)) {
            return response()->json(ApiResponse::error('Sign khong hop le.'), 403);
        }

        return null;
    }
}
