@extends('client.layouts.app')

@section('title', 'Kết nối API')
@section('description', 'Tài liệu kết nối API nạp game, mẫu cURL và response đầy đủ.')
@section('robots', 'noindex,nofollow')

@section('content')
<section class="client-container grid gap-6 py-6 sm:py-8 lg:py-10">
    <header class="overflow-hidden rounded-[10px] border border-slate-800 bg-slate-950 text-white shadow-sm">
        <div class="grid lg:grid-cols-[minmax(0,1fr)_21rem]">
            <div class="p-5 sm:p-8 lg:p-10">
                <span class="inline-flex items-center gap-2 rounded-full border border-cyan-300/20 bg-cyan-400/10 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.14em] text-cyan-300">
                    <i class="bx bx-code text-lg" aria-hidden="true"></i>REST API v1
                </span>
                <h1 class="mt-4 max-w-3xl text-3xl font-extrabold tracking-tight sm:text-4xl">Tích hợp nạp game vào website hoặc phần mềm của bạn</h1>
                <p class="mt-4 max-w-3xl text-sm leading-7 text-slate-300 sm:text-base">
                    Tài liệu bên dưới có đầy đủ header xác thực, tham số request, lệnh cURL và response JSON cho từng endpoint. Giá API tự động áp dụng đúng giá riêng của tài khoản đang gọi.
                </p>
                <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                    <a class="client-button min-h-12 justify-center" href="{{ route('account.profile.api') }}">
                        <i class="bx bx-key text-xl" aria-hidden="true"></i>Quản lý API key
                    </a>
                    <a class="inline-flex min-h-12 items-center justify-center gap-2 rounded-[5px] border border-white/20 px-4 text-sm font-bold text-white transition hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400" href="#authentication">
                        <i class="bx bx-book-open text-xl" aria-hidden="true"></i>Bắt đầu tích hợp
                    </a>
                </div>
            </div>

            <aside class="grid content-center gap-3 border-t border-white/10 bg-white/5 p-5 sm:p-6 lg:border-l lg:border-t-0">
                <p class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">Trạng thái tài khoản</p>
                <div class="flex items-center justify-between gap-3 rounded-[8px] border border-white/10 bg-white/10 p-4">
                    <span class="flex items-center gap-3 font-bold text-white">
                        <i class="bx bx-key text-2xl text-cyan-300" aria-hidden="true"></i>API key hoạt động
                    </span>
                    <strong class="text-2xl text-cyan-200">{{ $activeApiKeyCount }}</strong>
                </div>
                <div class="rounded-[8px] border border-amber-300/20 bg-amber-300/10 p-4 text-sm leading-6 text-amber-100">
                    API secret chỉ hiển thị một lần khi tạo. Chỉ gọi API từ backend và tuyệt đối không nhúng secret vào JavaScript phía trình duyệt.
                </div>
            </aside>
        </div>
    </header>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Quy trình kết nối API">
        @foreach ([
            ['bx-key', '1. Tạo API key', 'Tạo key và lưu secret an toàn trên server.'],
            ['bx-database', '2. Lấy catalog', 'Đồng bộ game, server, mệnh giá và giá bán.'],
            ['bx-cart-plus', '3. Tạo đơn', 'Gửi UUID duy nhất cùng dữ liệu người nhận.'],
            ['bx-refresh-cw', '4. Theo dõi đơn', 'Dùng order_id để cập nhật trạng thái xử lý.'],
        ] as [$icon, $title, $description])
            <article class="flex gap-3 rounded-[8px] border border-slate-200 bg-white p-4 shadow-sm">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-[6px] bg-cyan-50 text-xl text-cyan-700">
                    <i class="bx {{ $icon }}" aria-hidden="true"></i>
                </span>
                <div>
                    <h2 class="font-extrabold text-slate-950">{{ $title }}</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-600">{{ $description }}</p>
                </div>
            </article>
        @endforeach
    </section>

    <x-client.api-documentation :documentation="$apiDocumentation" />
</section>
@endsection
