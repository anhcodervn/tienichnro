<?php

namespace App\Features\Client\Topup\Controllers;

use App\Features\Client\Topup\Requests\LookupOrderRequest;
use App\Features\Topup\Services\Payments\OrderBankPaymentService;
use App\Features\Topup\Support\OrderRealtimeChannel;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function lookup(): View
    {
        return view('client.orders.lookup');
    }

    public function find(LookupOrderRequest $request): RedirectResponse
    {
        $order = Order::query()
            ->where('code', Str::upper($request->string('code')->toString()))
            ->where('normalized_email', Str::lower(trim($request->string('email')->toString())))
            ->first();

        if (! $order instanceof Order) {
            return back()->withErrors(['code' => 'Không tìm thấy đơn hàng khớp mã và email.'])->withInput();
        }

        $request->session()->put("orders.access.{$order->code}", true);

        return redirect()->route('orders.show', $order);
    }

    public function show(Request $request, Order $order): View
    {
        $this->authorizeAccess($request, $order);
        $order->load(['game:id,name,slug', 'server:id,name', 'recipients']);

        return view('client.orders.show', [
            'order' => $order,
            'realtimeChannel' => OrderRealtimeChannel::for($order),
        ]);
    }

    public function payment(Request $request, Order $order, OrderBankPaymentService $orderBankPaymentService): View
    {
        $this->authorizeAccess($request, $order);

        return view('client.orders.payment', [
            'order' => $order,
            'payment' => $orderBankPaymentService->paymentDetails($order),
            'realtimeChannel' => OrderRealtimeChannel::for($order),
        ]);
    }

    public function status(Request $request, Order $order): JsonResponse
    {
        $this->authorizeAccess($request, $order);

        return response()->json([
            'payment_status' => $order->payment_status->value,
            'order_status' => $order->order_status->value,
            'updated_at' => $order->updated_at?->toISOString(),
        ]);
    }

    private function authorizeAccess(Request $request, Order $order): void
    {
        $user = $request->user();
        $isOwner = $user instanceof User && $order->user_id === $user->id;
        $hasSessionAccess = (bool) $request->session()->get("orders.access.{$order->code}", false);

        abort_unless($isOwner || $hasSessionAccess || $request->hasValidSignature(), 403);
    }
}
