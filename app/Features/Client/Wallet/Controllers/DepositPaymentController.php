<?php

namespace App\Features\Client\Wallet\Controllers;

use App\Features\Client\Wallet\Resources\DepositRequestResource;
use App\Features\Client\Wallet\Services\WalletDepositService;
use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepositPaymentController extends Controller
{
    public function __construct(
        private readonly WalletDepositService $walletDepositService,
    ) {}

    public function __invoke(Request $request, PaymentTransaction $paymentTransaction): View
    {
        /** @var User $user */
        $user = $request->user();
        $paymentTransaction = $this->walletDepositService->ownedTransaction($paymentTransaction, $user);
        $rawData = is_array($paymentTransaction->raw_data) ? $paymentTransaction->raw_data : [];

        abort_unless(
            $paymentTransaction->order_id === null
            && in_array($rawData['provider'] ?? null, ['manual', 'apibankvn_api'], true),
            404,
        );

        return view('client.wallet.payment', [
            'user' => $user,
            'deposit' => (new DepositRequestResource($paymentTransaction))->resolve(),
        ]);
    }
}
