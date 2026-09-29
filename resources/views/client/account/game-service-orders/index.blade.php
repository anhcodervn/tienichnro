@extends('client.layouts.app')

@section('title', 'Lịch sử dịch vụ')
@section('robots', 'noindex,nofollow')

@section('content')
@php
    $statusLabels = [
        'pending' => 'Chờ tiếp nhận',
        'processing' => 'Đang xử lý',
        'review' => 'Chờ duyệt',
        'completed' => 'Hoàn thành',
        'failed' => 'Thất bại',
        'cancelled' => 'Đã hủy',
    ];
    $statusClasses = [
        'pending' => 'border-amber-200 bg-amber-50 text-amber-700',
        'processing' => 'border-blue-200 bg-blue-50 text-blue-700',
        'review' => 'border-violet-200 bg-violet-50 text-violet-700',
        'completed' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'failed' => 'border-rose-200 bg-rose-50 text-rose-700',
        'cancelled' => 'border-slate-200 bg-slate-100 text-slate-600',
    ];
@endphp

<section class="client-container py-8 sm:py-10">
    <header class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <p class="inline-flex items-center gap-2 text-sm font-bold text-emerald-700"><i class="bx bx-history text-lg" aria-hidden="true"></i>Tài khoản</p>
            <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-950 sm:text-3xl">Lịch sử dịch vụ</h1>
            <p class="mt-2 text-sm leading-6 text-slate-500">Theo dõi các đơn dịch vụ game bạn đã gửi.</p>
        </div>
        <button type="button" class="client-button w-full gap-2 sm:w-auto" data-game-service-picker-open aria-controls="game-service-picker-modal" aria-expanded="false" aria-haspopup="dialog"><i class="bx bx-plus-circle text-lg" aria-hidden="true"></i>Tạo đơn dịch vụ</button>
    </header>

    <div class="client-card mt-6 overflow-hidden" data-game-service-order-history>
        <div class="w-full min-w-0 overflow-x-auto overscroll-x-contain" tabindex="0" role="region" aria-label="Bảng lịch sử dịch vụ">
            <table class="w-full min-w-[820px] table-auto text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-xs font-extrabold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="w-14 px-4 py-3 text-center">STT</th>
                        <th class="px-4 py-3">Mã đơn<br><span class="normal-case tracking-normal text-slate-400">Thời gian tạo</span></th>
                        <th class="px-4 py-3">Dịch vụ</th>
                        <th class="px-4 py-3">Gói · Máy chủ</th>
                        <th class="px-4 py-3 text-right">Tổng tiền</th>
                        <th class="px-4 py-3">Trạng thái</th>
                        <th class="px-4 py-3 text-right">Trao đổi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($orders as $order)
                        <tr class="transition hover:bg-slate-50/80">
                            <td class="px-4 py-4 text-center align-top font-semibold text-slate-500">{{ ($orders->firstItem() ?? 1) + $loop->index }}</td>
                            <td class="px-4 py-4 align-top">
                                <p class="font-extrabold text-indigo-700">{{ $order->code }}</p>
                                <p class="mt-1 whitespace-nowrap text-xs text-slate-500">{{ $order->created_at?->format('d/m/Y H:i') }}</p>
                            </td>
                            <td class="min-w-52 px-4 py-4 align-top">
                                <p class="font-bold text-slate-900">{{ $order->service_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $order->game_name }}</p>
                            </td>
                            <td class="min-w-52 px-4 py-4 align-top">
                                <p class="font-semibold text-slate-800">{{ $order->package_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $order->server_name ?: 'Không xác định' }} · SL {{ number_format($order->quantity) }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-right align-top font-extrabold text-slate-950">{{ number_format($order->total_amount, 0, ',', '.') }}đ</td>
                            <td class="px-4 py-4 align-top"><span class="inline-flex rounded-[5px] border px-2.5 py-1 text-xs font-bold {{ $statusClasses[$order->status] ?? 'border-slate-200 bg-slate-50 text-slate-600' }}">{{ $statusLabels[$order->status] ?? $order->status }}</span></td>
                            <td class="px-4 py-4 text-right align-top"><a class="inline-flex min-h-9 items-center gap-2 rounded-[5px] bg-emerald-600 px-3 text-xs font-bold text-white hover:bg-emerald-700" href="{{ route('account.game-service-orders.chat', $order) }}"><i class="bx bx-message-rounded-dots text-base" aria-hidden="true"></i>Chat</a></td>
                        </tr>
                    @empty
                        <tr><td class="px-6 py-12 text-center text-slate-500" colspan="7">Bạn chưa có đơn dịch vụ nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <footer class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-slate-500">Hiển thị <strong class="text-slate-900">{{ $orders->firstItem() ?? 0 }}–{{ $orders->lastItem() ?? 0 }}</strong> trong tổng số <strong class="text-slate-900">{{ number_format($orders->total()) }}</strong> đơn.</p>
            @if ($orders->hasPages())
                <div class="min-w-0">{{ $orders->onEachSide(1)->links() }}</div>
            @endif
        </footer>
    </div>
</section>
@endsection
