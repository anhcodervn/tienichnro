<?php

namespace App\Features\Client\Subscription\Controllers;

use App\Features\Client\Subscription\Requests\RegisterNotificationSubscriptionRequest;
use App\Features\Client\Subscription\Services\NotificationSubscriptionService;
use App\Features\Client\Wallet\Services\WalletService;
use App\Http\Controllers\Controller;
use App\Models\CodeNotify;
use App\Models\NotificationSubscription;
use App\Models\NroServer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationSubscriptionController extends Controller
{
    public function index(Request $request, WalletService $wallets, NotificationSubscriptionService $service): View
    {
        return view('client.subscription.index', [
            'packages' => $service->availablePackages()->get(),
            'wallet' => $request->user() ? $wallets->wallet($request->user()) : null,
            'subscriptions' => $request->user() ? NotificationSubscription::query()->with('latestDelivery')->where('user_id', $request->user()->id)->latest()->limit(50)->get() : collect(),
            'servers' => NroServer::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'notificationTypes' => CodeNotify::query()->orderBy('id')->get(['code', 'name']),
        ]);
    }

    public function store(RegisterNotificationSubscriptionRequest $request, NotificationSubscriptionService $service): JsonResponse
    {
        $subscription = $service->purchase($request->user(), $request->validated());

        return response()->json(['data' => $subscription, 'message' => 'Đã đăng ký gói và ghi giao dịch ví.'], 201);
    }

    public function update(RegisterNotificationSubscriptionRequest $request, NotificationSubscription $subscription, NotificationSubscriptionService $service): JsonResponse
    {
        return response()->json(['data' => $service->update($request->user(), $subscription, $request->validated()), 'message' => 'Đã lưu cấu hình nhận thông báo.']);
    }
}
