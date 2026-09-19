@extends('client.layouts.app')

@section('title', 'Thanh toán đơn '.$order->code)
@section('robots', 'noindex,nofollow')

@section('content')
<div
    @if ($order->user_id === null)
        data-guest-order-code="{{ $order->code }}"
        data-guest-order-created-at="{{ $order->created_at?->toISOString() }}"
    @endif
    @if (in_array($order->order_status->value, ['pending', 'processing'], true))
        data-order-realtime-channel="{{ $realtimeChannel }}"
        data-order-payment-status="{{ $order->payment_status->value }}"
        data-order-status="{{ $order->order_status->value }}"
        data-order-page="payment"
        data-order-show-url="{{ route('orders.show', $order) }}"
    @endif
>
<x-client.bank-transfer-payment
    :eyebrow="'Đơn '.$order->code"
    title="Thanh toán chuyển khoản"
    subtitle="Hoàn tất thanh toán để hệ thống bắt đầu xử lý đơn nạp game."
    :code="$order->code"
    :status="$payment['status'] ?? $order->payment_status->value"
    :bank-name="$payment['bank_name'] ?? null"
    :account-name="$payment['account_name'] ?? null"
    :account-number="$payment['account_number'] ?? null"
    :amount="$payment['amount'] ?? (int) $order->total_amount"
    :content="$payment['content'] ?? null"
    :qr-url="$payment['qr_url'] ?? null"
    :back-url="route('orders.show', $order)"
    back-label="Xem chi tiết đơn"
>
    @if (in_array($order->order_status->value, ['pending', 'processing'], true))
        <x-slot:statusExtra>
            <p class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500" data-order-realtime-connection role="status">
                <span class="h-2 w-2 rounded-full bg-amber-400" data-order-realtime-dot aria-hidden="true"></span>
                <span data-order-realtime-text>Đang kết nối cập nhật realtime...</span>
            </p>
        </x-slot:statusExtra>
    @endif
    <x-slot:orderSummary>
        <x-client.order-payment-summary :order="$order" />
    </x-slot:orderSummary>
</x-client.bank-transfer-payment>
</div>
@endsection
