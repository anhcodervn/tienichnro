@extends('client.layouts.app')

@section('title', 'Lịch sử đơn hàng')
@section('robots', 'noindex,nofollow')

@section('content')
@php
    $paymentLabels = [
        'pending' => 'Chờ thanh toán',
        'paid' => 'Đã thanh toán',
        'expired' => 'Hết hạn',
        'cancelled' => 'Đã hủy',
        'refunded' => 'Đã hoàn tiền',
    ];
    $orderLabels = [
        'pending' => 'Chờ xử lý',
        'processing' => 'Đang xử lý',
        'completed' => 'Hoàn thành',
        'failed' => 'Thất bại',
        'cancelled' => 'Đã hủy',
    ];
    $paymentClasses = [
        'pending' => 'border-amber-200 bg-amber-50 text-amber-700',
        'paid' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'expired' => 'border-slate-200 bg-slate-100 text-slate-600',
        'cancelled' => 'border-slate-200 bg-slate-100 text-slate-600',
        'refunded' => 'border-blue-200 bg-blue-50 text-blue-700',
    ];
    $orderClasses = [
        'pending' => 'border-amber-200 bg-amber-50 text-amber-700',
        'processing' => 'border-blue-200 bg-blue-50 text-blue-700',
        'completed' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'failed' => 'border-rose-200 bg-rose-50 text-rose-700',
        'cancelled' => 'border-slate-200 bg-slate-100 text-slate-600',
    ];
    $hasFilters = collect($filters)->except(['page'])->filter(fn ($value) => filled($value))->isNotEmpty();
@endphp

<section class="client-container py-8 sm:py-10">
    <header class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <p class="inline-flex items-center gap-2 text-sm font-bold text-emerald-700"><i class="bx bx-history text-lg" aria-hidden="true"></i>Tài khoản</p>
            <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-950 sm:text-3xl">Lịch sử đơn hàng</h1>
            <p class="mt-2 text-sm leading-6 text-slate-500">Theo dõi, tìm kiếm và lọc các đơn nạp game của bạn.</p>
        </div>
        <a class="client-button w-full gap-2 sm:w-auto" href="{{ route('topup.index') }}"><i class="bx bx-plus-circle text-lg" aria-hidden="true"></i>Tạo đơn mới</a>
    </header>

    <form class="client-card mt-6 p-4 sm:p-5" method="GET" action="{{ route('account.orders.index') }}" data-order-filters>
        <div class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="min-w-0 sm:col-span-2">
                <label class="text-sm font-bold text-slate-700" for="order-search">Tìm kiếm</label>
                <div class="relative mt-2">
                    <i class="bx bx-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-lg text-slate-400" aria-hidden="true"></i>
                    <input class="client-input pl-10" id="order-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Mã đơn, tài khoản game, tên gói hoặc game">
                </div>
            </div>

            <div class="min-w-0">
                <label class="text-sm font-bold text-slate-700" for="order-game">Game</label>
                <select class="client-input mt-2" id="order-game" name="game_id">
                    <option value="">Tất cả game</option>
                    @foreach ($games as $game)
                        <option value="{{ $game->id }}" @selected((string) ($filters['game_id'] ?? '') === (string) $game->id)>{{ $game->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-0">
                <label class="text-sm font-bold text-slate-700" for="order-payment-status">Thanh toán</label>
                <select class="client-input mt-2" id="order-payment-status" name="payment_status">
                    <option value="">Tất cả trạng thái</option>
                    @foreach ($paymentLabels as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['payment_status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-0">
                <label class="text-sm font-bold text-slate-700" for="order-status">Xử lý đơn</label>
                <select class="client-input mt-2" id="order-status" name="order_status">
                    <option value="">Tất cả trạng thái</option>
                    @foreach ($orderLabels as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['order_status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-0">
                <label class="text-sm font-bold text-slate-700" for="order-date-from">Từ ngày</label>
                <input class="client-input mt-2" id="order-date-from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </div>

            <div class="min-w-0">
                <label class="text-sm font-bold text-slate-700" for="order-date-to">Đến ngày</label>
                <input class="client-input mt-2" id="order-date-to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </div>

            <div class="grid min-w-0 grid-cols-2 gap-3">
                <div class="min-w-0">
                    <label class="text-sm font-bold text-slate-700" for="order-sort">Sắp xếp</label>
                    <select class="client-input mt-2" id="order-sort" name="sort">
                        <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Mới nhất</option>
                        <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Cũ nhất</option>
                        <option value="amount_desc" @selected(($filters['sort'] ?? '') === 'amount_desc')>Tiền cao</option>
                        <option value="amount_asc" @selected(($filters['sort'] ?? '') === 'amount_asc')>Tiền thấp</option>
                    </select>
                </div>
                <div class="min-w-0">
                    <label class="text-sm font-bold text-slate-700" for="order-per-page">Số dòng</label>
                    <select class="client-input mt-2" id="order-per-page" name="per_page">
                        @foreach ([10, 15, 25, 50] as $perPage)
                            <option value="{{ $perPage }}" @selected((int) ($filters['per_page'] ?? 15) === $perPage)>{{ $perPage }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        @if ($errors->any())
            <p class="mt-4 text-sm font-semibold text-rose-600" role="alert">{{ $errors->first() }}</p>
        @endif

        <div class="mt-5 flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-slate-500">Tìm thấy <strong class="text-slate-900">{{ number_format($orders->total()) }}</strong> đơn hàng.</p>
            <div class="flex flex-col gap-2 sm:flex-row">
                @if ($hasFilters)
                    <a class="client-button-secondary w-full gap-2 bg-white sm:w-auto" href="{{ route('account.orders.index') }}"><i class="bx bx-reset text-lg" aria-hidden="true"></i>Xóa bộ lọc</a>
                @endif
                <button class="client-button w-full gap-2 sm:w-auto" type="submit"><i class="bx bx-filter-alt text-lg" aria-hidden="true"></i>Áp dụng bộ lọc</button>
            </div>
        </div>
    </form>

    <div class="client-card mt-5 hidden overflow-hidden lg:block" data-order-table>
        <div class="w-full min-w-0 overflow-x-auto overscroll-x-contain">
            <table class="w-full min-w-[920px] table-auto text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-xs font-extrabold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Mã đơn / thời gian</th>
                        <th class="px-4 py-3">Game / máy chủ</th>
                        <th class="px-4 py-3">Gói nạp</th>
                        <th class="px-4 py-3">Thanh toán</th>
                        <th class="px-4 py-3">Xử lý</th>
                        <th class="px-4 py-3 text-right">Tổng tiền</th>
                        <th class="px-4 py-3 text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($orders as $order)
                        <tr class="transition hover:bg-slate-50/80">
                            <td class="px-4 py-4 align-top">
                                <a class="font-extrabold text-indigo-700 hover:text-indigo-800" href="{{ route('account.orders.show', $order) }}">{{ $order->code }}</a>
                                <p class="mt-1 whitespace-nowrap text-xs text-slate-500">{{ $order->created_at?->format('d/m/Y H:i') }}</p>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <p class="font-bold text-slate-900">{{ $order->game?->name ?? 'Game đã xóa' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $order->server?->name ?? 'Không xác định' }}</p>
                                <p class="mt-1 max-w-48 truncate text-xs text-slate-500" title="{{ $order->game_account }}">TK: {{ $order->game_account }}</p>
                            </td>
                            <td class="max-w-56 px-4 py-4 align-top">
                                <p class="break-words font-semibold text-slate-900">{{ $order->package_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">SL: {{ number_format($order->quantity) }}</p>
                            </td>
                            <td class="px-4 py-4 align-top"><span class="inline-flex rounded-[5px] border px-2.5 py-1 text-xs font-bold {{ $paymentClasses[$order->payment_status->value] ?? 'border-slate-200 bg-slate-50 text-slate-600' }}">{{ $paymentLabels[$order->payment_status->value] ?? $order->payment_status->value }}</span></td>
                            <td class="px-4 py-4 align-top"><span class="inline-flex rounded-[5px] border px-2.5 py-1 text-xs font-bold {{ $orderClasses[$order->order_status->value] ?? 'border-slate-200 bg-slate-50 text-slate-600' }}">{{ $orderLabels[$order->order_status->value] ?? $order->order_status->value }}</span></td>
                            <td class="whitespace-nowrap px-4 py-4 text-right align-top font-extrabold text-slate-950">{{ number_format((int) $order->total_amount, 0, ',', '.') }}đ</td>
                            <td class="px-4 py-4 text-right align-top"><a class="inline-flex min-h-11 items-center gap-1 rounded-[5px] px-3 font-bold text-emerald-700 transition hover:bg-emerald-50 hover:text-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600" href="{{ route('account.orders.show', $order) }}">Chi tiết<i class="bx bx-chevron-right text-lg" aria-hidden="true"></i></a></td>
                        </tr>
                    @empty
                        <tr><td class="px-6 py-12 text-center text-slate-500" colspan="7">Không tìm thấy đơn hàng phù hợp.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5 grid gap-3 lg:hidden" data-order-mobile-list>
        @forelse ($orders as $order)
            <article class="client-card min-w-0 p-4">
                <div class="flex min-w-0 items-start justify-between gap-3">
                    <div class="min-w-0">
                        <a class="break-all font-extrabold text-indigo-700" href="{{ route('account.orders.show', $order) }}">{{ $order->code }}</a>
                        <p class="mt-1 text-xs text-slate-500">{{ $order->created_at?->format('d/m/Y H:i') }}</p>
                    </div>
                    <strong class="shrink-0 whitespace-nowrap text-right text-slate-950">{{ number_format((int) $order->total_amount, 0, ',', '.') }}đ</strong>
                </div>
                <div class="mt-4 grid min-w-0 grid-cols-2 gap-3 rounded-[5px] bg-slate-50 p-3 text-sm">
                    <div class="min-w-0"><p class="text-xs text-slate-500">Game</p><p class="mt-1 break-words font-bold">{{ $order->game?->name ?? 'Game đã xóa' }}</p></div>
                    <div class="min-w-0"><p class="text-xs text-slate-500">Máy chủ</p><p class="mt-1 break-words font-bold">{{ $order->server?->name ?? 'Không xác định' }}</p></div>
                    <div class="col-span-2 min-w-0"><p class="text-xs text-slate-500">Gói nạp</p><p class="mt-1 break-words font-bold">{{ $order->package_name }} × {{ number_format($order->quantity) }}</p></div>
                    <div class="col-span-2 min-w-0"><p class="text-xs text-slate-500">Tài khoản game</p><p class="mt-1 break-all font-bold">{{ $order->game_account }}</p></div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <span class="inline-flex rounded-[5px] border px-2.5 py-1 text-xs font-bold {{ $paymentClasses[$order->payment_status->value] ?? 'border-slate-200 bg-slate-50 text-slate-600' }}">{{ $paymentLabels[$order->payment_status->value] ?? $order->payment_status->value }}</span>
                    <span class="inline-flex rounded-[5px] border px-2.5 py-1 text-xs font-bold {{ $orderClasses[$order->order_status->value] ?? 'border-slate-200 bg-slate-50 text-slate-600' }}">{{ $orderLabels[$order->order_status->value] ?? $order->order_status->value }}</span>
                </div>
                <a class="client-button-secondary mt-4 w-full gap-2 bg-white" href="{{ route('account.orders.show', $order) }}">Xem chi tiết<i class="bx bx-right-arrow-alt text-lg" aria-hidden="true"></i></a>
            </article>
        @empty
            <div class="client-card p-8 text-center">
                <i class="bx bx-receipt mb-3 text-4xl text-slate-300" aria-hidden="true"></i>
                <p class="font-bold text-slate-700">Không tìm thấy đơn hàng phù hợp.</p>
                @if ($hasFilters)<a class="mt-3 inline-flex font-bold text-emerald-700" href="{{ route('account.orders.index') }}">Xóa bộ lọc</a>@endif
            </div>
        @endforelse
    </div>

    @if ($orders->hasPages())
        <div class="mt-6">{{ $orders->onEachSide(1)->links() }}</div>
    @endif
</section>
@endsection
