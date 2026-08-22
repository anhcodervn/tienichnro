<?php

namespace App\Features\Admin\Topup\Controllers;

use App\Features\Admin\Topup\Requests\UpdateOrderStatusRequest;
use App\Features\Admin\Topup\Resources\OrderResource;
use App\Features\Admin\Topup\Services\TopupAdminService;
use App\Http\Controllers\Controller;
use App\Models\Order;
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

    public function show(Order $order): OrderResource
    {
        return OrderResource::make($order->load(['game:id,name', 'server:id,name', 'provider:id,name,slug', 'recipients']));
    }

    public function update(UpdateOrderStatusRequest $request, Order $order): OrderResource
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

        return OrderResource::make($order->load(['game:id,name', 'server:id,name', 'provider:id,name,slug', 'recipients']));
    }
}
