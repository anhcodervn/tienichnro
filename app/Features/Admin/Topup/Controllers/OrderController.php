<?php

namespace App\Features\Admin\Topup\Controllers;

use App\Enums\PaymentMethod;
use App\Features\Admin\Topup\Requests\UpdateOrderStatusRequest;
use App\Features\Admin\Topup\Resources\OrderResource;
use App\Features\Admin\Topup\Services\TopupAdminService;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly TopupAdminService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['status' => true, 'data' => OrderResource::collection($this->service->orders($request))->response()->getData(true)]);
    }

    public function show(Order $order): JsonResponse
    {
        return $this->sensitiveOrderResponse($order);
    }

    public function update(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();
        $order = $this->service->updateOrder(
            $order,
            $request->string('action')->toString(),
            $request->filled('reason') ? $request->string('reason')->toString() : null,
            $admin,
            $request,
        );

        return $this->sensitiveOrderResponse($order);
    }

    private function sensitiveOrderResponse(Order $order): JsonResponse
    {
        $order->load(['game:id,name', 'server:id,name', 'provider:id,name,slug', 'recipients', 'latestPaymentTransaction']);

        if ($order->payment_method === PaymentMethod::BankTransfer && $order->latestPaymentTransaction === null) {
            $legacyTransaction = PaymentTransaction::query()
                ->whereNull('order_id')
                ->where('transaction_code', $order->code)
                ->latest('id')
                ->first();

            $order->setRelation('latestPaymentTransaction', $legacyTransaction);
        }

        return OrderResource::make($order)->response()->header('Cache-Control', 'private, no-store');
    }
}
