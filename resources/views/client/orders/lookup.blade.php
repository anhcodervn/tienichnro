@extends('client.layouts.app')

@section('title', 'Lịch sử đơn hàng')
@section('robots', 'noindex,nofollow')

@section('content')
<section class="client-container py-8 sm:py-10">
    <header class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <p class="inline-flex items-center gap-2 text-sm font-bold text-emerald-700"><i class="bx bx-history text-lg" aria-hidden="true"></i>Thiết bị này</p>
            <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-950 sm:text-3xl">Lịch sử đơn hàng</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Theo dõi các đơn đã tạo trên trình duyệt này. Mã đơn được lưu trong 1 năm, thông tin nhạy cảm không được lưu trên thiết bị.</p>
        </div>
        <a class="client-button w-full gap-2 sm:w-auto" href="{{ route('topup.index') }}"><i class="bx bx-plus-circle text-lg" aria-hidden="true"></i>Tạo đơn mới</a>
    </header>

    <div class="mt-6 grid min-w-0 gap-5 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
        <section class="client-card min-w-0 overflow-hidden" data-guest-order-history data-guest-order-history-url="{{ route('orders.history') }}">
            <div class="flex flex-col gap-2 border-b border-slate-200 bg-slate-50 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div>
                    <h2 class="flex items-center gap-2 font-extrabold text-slate-950"><i class="bx bx-receipt text-xl text-cyan-700" aria-hidden="true"></i>Đơn nạp gần đây</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Bấm Chi tiết để xem tiến độ mới nhất của từng đơn.</p>
                </div>
                <span class="inline-flex w-fit items-center gap-1.5 rounded-[5px] bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800"><i class="bx bx-shield-quarter text-base" aria-hidden="true"></i>Lưu cục bộ an toàn</span>
            </div>

            <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <label class="relative block min-w-0 flex-1 sm:max-w-sm">
                    <span class="sr-only">Tìm trong lịch sử đơn hàng</span>
                    <i class="bx bx-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-lg text-slate-400" aria-hidden="true"></i>
                    <input class="client-input mt-0 min-h-11 pl-10" type="search" placeholder="Tìm theo mã đơn..." autocomplete="off" data-guest-order-history-search>
                </label>
                <p class="shrink-0 text-sm text-slate-500" data-guest-order-history-count>0 đơn</p>
            </div>

            <div data-guest-order-history-table hidden>
                <div class="w-full min-w-0 overflow-x-auto overscroll-x-contain" tabindex="0" role="region" aria-label="Bảng lịch sử đơn hàng trên thiết bị">
                    <table class="w-full min-w-[860px] table-auto text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-xs font-extrabold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="w-14 px-4 py-3 text-center">STT</th>
                                <th class="px-4 py-3">Mã đơn<br><span class="normal-case tracking-normal text-slate-400">Thời gian tạo</span></th>
                                <th class="px-4 py-3">Thông tin<br><span class="normal-case tracking-normal text-slate-400">Account · Server · Số lượng</span></th>
                                <th class="px-4 py-3 text-right">Tổng tiền</th>
                                <th class="px-4 py-3">Trạng thái</th>
                                <th class="px-4 py-3 text-right">Xem chi tiết</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100" data-guest-order-history-list></tbody>
                    </table>
                </div>
                <p class="hidden px-5 py-10 text-center text-sm font-semibold text-slate-500" data-guest-order-history-filter-empty>Không tìm thấy mã đơn phù hợp.</p>
            </div>

            <div class="p-8 text-center" data-guest-order-history-empty>
                <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-slate-100 text-slate-400"><i class="bx bx-receipt text-3xl" aria-hidden="true"></i></span>
                <h2 class="mt-4 font-extrabold text-slate-900">Chưa có đơn nào trên thiết bị này</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">Tạo đơn mới hoặc thêm một đơn cũ bằng mã đơn và email.</p>
            </div>

            <template data-guest-order-history-item>
                <tr class="transition hover:bg-slate-50/80" data-guest-order-history-row>
                    <td class="px-4 py-4 text-center align-middle font-semibold text-slate-500" data-guest-order-history-index></td>
                    <td class="px-4 py-4 align-middle"><button class="font-extrabold text-indigo-700 transition hover:text-indigo-800" type="button" data-guest-order-detail data-order-detail-trigger data-guest-order-history-code></button><time class="mt-1 block whitespace-nowrap text-xs text-slate-500" data-guest-order-history-time></time></td>
                    <td class="min-w-64 px-4 py-4 align-middle"><p class="max-w-72 break-all font-bold text-slate-900" data-guest-order-history-account>Đang tải...</p><p class="mt-1 text-xs text-slate-500">SV: <strong class="text-slate-700" data-guest-order-history-server>—</strong></p><p class="mt-1 text-xs text-slate-500">Số lượng: <strong class="text-slate-700" data-guest-order-history-quantity>—</strong></p></td>
                    <td class="whitespace-nowrap px-4 py-4 text-right align-middle font-extrabold text-slate-950" data-guest-order-history-total>—</td>
                    <td class="px-4 py-4 align-middle"><span class="inline-flex rounded-[5px] border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-bold text-slate-600" data-guest-order-history-status>Đang tải...</span></td>
                    <td class="px-4 py-4 text-right align-middle"><button class="client-button-secondary min-h-10 shrink-0 gap-2 bg-white" type="button" data-guest-order-detail data-order-detail-trigger><i class="bx bx-show text-lg" aria-hidden="true"></i>Chi tiết</button></td>
                </tr>
            </template>
        </section>

        <aside class="client-card overflow-hidden lg:sticky lg:top-24">
            <div class="border-b border-slate-200 bg-slate-50 px-5 py-4">
                <h2 class="flex items-center gap-2 font-extrabold text-slate-950"><i class="bx bx-link-alt text-xl text-cyan-700" aria-hidden="true"></i>Thêm đơn vào lịch sử</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">Dùng khi đổi trình duyệt hoặc danh sách chưa có đơn cần xem.</p>
            </div>
            <form class="grid gap-4 p-5" method="POST" action="{{ route('orders.lookup.submit') }}" data-order-history-unlock>
                @csrf
                <label class="client-label">Mã đơn<input class="client-input uppercase" name="code" value="{{ old('code', request()->query('code')) }}" placeholder="TOP260820AB12CD" autocomplete="off" required></label>
                <label class="client-label">Email đặt hàng<input class="client-input" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required></label>
                <p class="hidden text-sm font-semibold text-rose-600" role="alert" data-order-history-unlock-error></p>
                @if ($errors->any())<p class="text-sm font-semibold text-rose-600" role="alert">{{ $errors->first() }}</p>@endif
                <button class="client-button w-full gap-2" type="submit"><i class="bx bx-plus-circle text-lg" aria-hidden="true"></i>Thêm và xem chi tiết</button>
            </form>
        </aside>
    </div>
</section>

<x-client.order-detail-modal />
@endsection
