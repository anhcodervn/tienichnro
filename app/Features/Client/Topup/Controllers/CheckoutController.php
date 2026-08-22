<?php

namespace App\Features\Client\Topup\Controllers;

use App\Enums\PaymentMethod;
use App\Features\Client\Topup\Requests\StoreOrderRequest;
use App\Features\Topup\Services\OrderService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class CheckoutController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    public function store(StoreOrderRequest $request): RedirectResponse
    {
        $user = $request->user();

        $order = $this->orderService->create(
            payload: $request->validated(),
            authenticatedUser: $user instanceof User ? $user : null,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        $request->session()->put("orders.access.{$order->code}", true);

        return $order->payment_method === PaymentMethod::BankTransfer
            ? redirect()->route('orders.payment', $order)
            : redirect()->route('orders.show', $order)->with('success', 'Thanh toán ví thành công. Đơn hàng đang được xử lý.');
    }
}
