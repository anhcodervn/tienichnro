<?php

namespace App\Features\Admin\Topup\Controllers;

use App\Features\Admin\Topup\Requests\UpdateOrderStatusRequest;
use App\Features\Admin\Topup\Resources\OrderResource;
use App\Features\Admin\Topup\Services\TopupAdminService;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly TopupAdminService $service) {}

    public function index(Request $request): JsonResponse
    {
        $data = OrderResource::collection($this->service->orders($request))->response()->getData(true);
        $data['statistics'] = $this->service->todayCardStatistics($request->integer('tenant_id') ?: null);

        return response()->json(['status' => true, 'data' => $data]);
    }

    public function show(string $order): JsonResponse
    {
        return $this->sensitiveOrderResponse($this->service->findOrder($order));
    }

    public function update(UpdateOrderStatusRequest $request, string $order): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();
        $order = $this->service->updateOrder(
            $this->service->findOrder($order),
            $request->string('action')->toString(),
            $request->filled('reason') ? $request->string('reason')->toString() : null,
            $admin,
            $request,
        );

        return $this->sensitiveOrderResponse($order);
    }

    private function sensitiveOrderResponse(Order $order): JsonResponse
    {
        $isPlatformView = app(TenantContext::class)->isActive() && app(TenantContext::class)->isMain();
        $relations = [
            'game:id,name', 'server:id,name', 'provider:id,name,slug,type', 'recipients',
            'latestPaymentTransaction', 'legacyPaymentTransaction',
        ];

        if ($isPlatformView) {
            $relations[] = 'tenant:id,name,slug';
        }

        $order->load($relations);

        return OrderResource::make($order)->response()->header('Cache-Control', 'private, no-store');
    }
}
