<?php

namespace App\Features\Client\Topup\Controllers;

use App\Features\Client\Topup\Requests\GuestOrderHistoryRequest;
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
    public function lookup(Request $request): View|RedirectResponse
    {
        if ($request->user() instanceof User) {
            return redirect()->route('account.orders.index');
        }

        return view('client.orders.lookup');
    }

    public function find(LookupOrderRequest $request): JsonResponse|RedirectResponse
    {
        $order = Order::query()
            ->where('code', Str::upper($request->string('code')->toString()))
            ->where('normalized_email', Str::lower(trim($request->string('email')->toString())))
            ->first();

        if (! $order instanceof Order) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Không tìm thấy đơn hàng khớp mã và email.',
                ], 404);
            }

            return back()->withErrors(['code' => 'Không tìm thấy đơn hàng khớp mã và email.'])->withInput();
        }

        $request->session()->put("orders.access.{$order->code}", true);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => true,
                'data' => [
                    'code' => $order->code,
                    'created_at' => $order->created_at?->toISOString(),
                    'detail_url' => route('orders.details', $order),
                ],
            ]);
        }

        return redirect()->route('orders.show', $order);
    }

    public function history(GuestOrderHistoryRequest $request): JsonResponse
    {
        $codes = collect($request->validated('codes'))
            ->map(fn (mixed $code): string => Str::upper((string) $code))
            ->filter(fn (string $code): bool => (bool) $request->session()->get("orders.access.{$code}", false))
            ->values();

        $orders = Order::query()
            ->select([
                'id', 'code', 'game_server_id', 'game_account', 'quantity', 'total_amount',
                'payment_status', 'order_status', 'created_at',
            ])
            ->with('server:id,name')
            ->whereIn('code', $codes)
            ->get()
            ->keyBy('code');

        return response()->json([
            'status' => true,
            'data' => [
                'orders' => $codes
                    ->map(fn (string $code): ?array => $orders->has($code) ? $this->historyItem($orders->get($code)) : null)
                    ->filter()
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function details(Request $request, Order $order): View
    {
        $this->authorizeAccess($request, $order);
        $order->load(['game:id,name,slug', 'server:id,name', 'recipients']);

        return view('client.orders.partials.detail-modal-content', ['order' => $order]);
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
        $order->load([
            'game:id,name',
            'server:id,name',
            'recipients:id,order_id,position,recipient_data,quantity',
        ]);

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
            'topup_id' => $order->topup_id,
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

    /**
     * @return array{code: string, created_at: ?string, account: string, server: ?string, quantity: int, total_amount: int, payment_status: string, order_status: string}
     */
    private function historyItem(Order $order): array
    {
        return [
            'code' => $order->code,
            'created_at' => $order->created_at?->toISOString(),
            'account' => (string) $order->game_account,
            'server' => $order->server?->name,
            'quantity' => (int) $order->quantity,
            'total_amount' => (int) $order->total_amount,
            'payment_status' => $order->payment_status->value,
            'order_status' => $order->order_status->value,
        ];
    }
}
