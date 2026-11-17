<?php

namespace App\Features\Recharge\Controllers;

use App\Features\Recharge\Requests\StoreRechargeOrderRequest;
use App\Features\Recharge\Services\RechargeConfigurationService;
use App\Features\Recharge\Services\RechargeOrderService;
use App\Features\Recharge\Services\RechargePollService;
use App\Http\Controllers\Controller;
use App\Models\WalletRechargeOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RechargeController extends Controller
{
    public function store(StoreRechargeOrderRequest $request, RechargeOrderService $orders): RedirectResponse
    {
        $order = $orders->create($request->user(), $request->integer('amount'), $request->validated('request_id'));

        return redirect()->route('account.recharge.show', $order);
    }

    public function show(Request $request, WalletRechargeOrder $order): View
    {
        abort_unless($request->user()?->status === 'active' && $order->user_id === $request->user()->id, 404);

        return view('client.wallet.recharge', ['order' => $order]);
    }

    public function status(Request $request, WalletRechargeOrder $order): JsonResponse
    {
        abort_unless($request->user()?->status === 'active' && $order->user_id === $request->user()->id, 404);

        return response()->json(['data' => ['status' => $order->status, 'label' => WalletRechargeOrder::STATUS_LABELS[$order->status], 'paid_at' => $order->paid_at?->toIso8601String()]]);
    }

    public function cron(Request $request, RechargeConfigurationService $config, RechargePollService $poll): JsonResponse
    {
        $key = $config->cronKey();
        $provided = (string) ($request->header('X-Cron-Key') ?? $request->bearerToken() ?? '');
        abort_unless($key !== '' && $provided !== '' && hash_equals($key, $provided), 401);

        return response()->json(['data' => $poll->run()], 200, ['Cache-Control' => 'no-store']);
    }
}
