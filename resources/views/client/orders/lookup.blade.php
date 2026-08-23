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
        <section class="client-card min-w-0 overflow-hidden" data-guest-order-history>
            <div class="flex flex-col gap-2 border-b border-slate-200 bg-slate-50 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div>
                    <h2 class="flex items-center gap-2 font-extrabold text-slate-950"><i class="bx bx-receipt text-xl text-cyan-700" aria-hidden="true"></i>Đơn nạp gần đây</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Bấm Chi tiết để xem tiến độ mới nhất của từng đơn.</p>
                </div>
                <span class="inline-flex w-fit items-center gap-1.5 rounded-[5px] bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800"><i class="bx bx-shield-quarter text-base" aria-hidden="true"></i>Lưu cục bộ an toàn</span>
            </div>

            <div class="grid gap-3 p-4 sm:p-5" data-guest-order-history-list></div>

            <div class="p-8 text-center" data-guest-order-history-empty>
                <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-slate-100 text-slate-400"><i class="bx bx-receipt text-3xl" aria-hidden="true"></i></span>
                <h2 class="mt-4 font-extrabold text-slate-900">Chưa có đơn nào trên thiết bị này</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">Tạo đơn mới hoặc thêm một đơn cũ bằng mã đơn và email.</p>
            </div>

            <template data-guest-order-history-item>
                <article class="grid min-w-0 gap-4 rounded-[5px] border border-slate-200 bg-white p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                    <div class="flex min-w-0 items-center gap-2">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-[5px] bg-cyan-50 text-cyan-700"><i class="bx bx-package text-xl" aria-hidden="true"></i></span>
                        <div class="min-w-0"><strong class="block truncate text-slate-950" data-guest-order-history-code></strong><time class="mt-0.5 block text-xs text-slate-500" data-guest-order-history-time></time></div>
                    </div>
                    <button class="client-button-secondary w-full shrink-0 gap-2 bg-white sm:w-auto" type="button" data-guest-order-detail data-order-detail-trigger><i class="bx bx-show text-lg" aria-hidden="true"></i>Chi tiết</button>
                </article>
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
