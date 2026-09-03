@extends('client.layouts.app')

@section('title', 'Tạo website đại lý')
@section('description', 'Đăng ký website đại lý nạp game vận hành trên hệ thống NapCarot.')
@section('robots', 'noindex,nofollow')

@section('content')
@php
    $primaryDomain = $agencySite?->domains->firstWhere('is_primary', true) ?? $agencySite?->domains->first();
    $statusLabel = match ($agencySite?->status) {
        'active' => 'Đang hoạt động',
        'suspended' => 'Tạm ngừng',
        null => 'Chưa đăng ký',
        default => 'Đang xử lý',
    };
@endphp

<section class="client-container py-6 sm:py-8 lg:py-10">
    <div class="overflow-hidden rounded-[10px] border border-slate-200 bg-slate-950 text-white shadow-sm">
        <div class="grid gap-8 p-5 sm:p-8 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-center lg:p-10">
            <div>
                <span class="inline-flex items-center gap-2 rounded-full border border-emerald-300/20 bg-emerald-400/10 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.14em] text-emerald-300">
                    <i class="bx bx-store-alt text-lg" aria-hidden="true"></i>Website đại lý
                </span>
                <h1 class="mt-4 max-w-3xl text-3xl font-extrabold tracking-tight sm:text-4xl">Kinh doanh bằng thương hiệu và domain của bạn</h1>
                <p class="mt-4 max-w-3xl text-sm leading-7 text-slate-300 sm:text-base">NapCarot vận hành hệ thống nạp, provider và cập nhật đơn. Bạn quản lý khách hàng, giao diện cơ bản và tự đặt giá bán ra trên website đại lý.</p>
                <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                    <a class="client-button min-h-12 justify-center" href="{{ route('client.support.chat') }}"><i class="bx bx-message-circle-dots text-xl" aria-hidden="true"></i>Đăng ký với hỗ trợ</a>
                    <a class="inline-flex min-h-12 items-center justify-center gap-2 rounded-[5px] border border-white/20 px-4 text-sm font-bold text-white transition hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400" href="#quy-trinh"><i class="bx bx-checklist text-xl" aria-hidden="true"></i>Xem quy trình</a>
                </div>
            </div>

            <div class="rounded-[8px] border border-white/10 bg-white/5 p-5">
                <p class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">Trạng thái của bạn</p>
                <div class="mt-3 flex items-center gap-3">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-[5px] bg-emerald-400/10 text-2xl text-emerald-300"><i class="bx bx-globe" aria-hidden="true"></i></span>
                    <div class="min-w-0">
                        <p class="truncate font-extrabold">{{ $agencySite?->name ?? 'Chưa có website đại lý' }}</p>
                        <p class="mt-0.5 truncate text-xs text-slate-400">{{ $primaryDomain?->domain ?? 'Liên hệ để đăng ký domain' }}</p>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between gap-3 border-t border-white/10 pt-4 text-sm">
                    <span class="text-slate-400">Tình trạng</span>
                    <span @class([
                        'rounded-full px-2.5 py-1 text-xs font-bold',
                        'bg-emerald-400/10 text-emerald-300' => $agencySite?->status === 'active',
                        'bg-amber-400/10 text-amber-200' => $agencySite?->status !== 'active',
                    ])>{{ $statusLabel }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['bx-globe-alt', 'Domain riêng', 'Khách hàng truy cập bằng tên miền và thương hiệu của bạn.'],
            ['bx-price-tag-alt', 'Tự đặt giá bán', 'Giá bán lẻ được cộng từ giá vốn tài khoản NapCarot.'],
            ['bx-wallet', 'Một nguồn số dư', 'Đơn từ site con tự động trừ số dư tài khoản thanh toán tại NapCarot.'],
            ['bx-refresh-cw', 'Vận hành tập trung', 'Game, gói nạp, provider và trạng thái đơn được hệ thống mẹ đồng bộ.'],
        ] as [$icon, $title, $description])
            <article class="rounded-[8px] border border-slate-200 bg-white p-5 shadow-sm">
                <span class="grid h-11 w-11 place-items-center rounded-[5px] bg-emerald-50 text-2xl text-emerald-700"><i class="bx {{ $icon }}" aria-hidden="true"></i></span>
                <h2 class="mt-4 font-extrabold text-slate-950">{{ $title }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $description }}</p>
            </article>
        @endforeach
    </div>

    <div id="quy-trinh" class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="rounded-[8px] border border-slate-200 bg-white p-5 sm:p-6">
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-emerald-700">Bắt đầu nhanh</p>
            <h2 class="mt-2 text-2xl font-extrabold text-slate-950">Quy trình mở website</h2>
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                @foreach ([
                    ['01', 'Chuẩn bị domain', 'Bạn sở hữu domain và cấu hình DNS theo thông tin được cung cấp.'],
                    ['02', 'Xác nhận tài khoản', 'Tài khoản NapCarot này được dùng để thanh toán giá vốn.'],
                    ['03', 'Khởi tạo website', 'NapCarot gắn domain, tạo quyền quản trị giới hạn và cấu hình ban đầu.'],
                    ['04', 'Đặt giá và bán hàng', 'Bạn chỉnh giá bán ra, kiểm tra giao diện rồi chính thức nhận đơn.'],
                ] as [$number, $title, $description])
                    <div class="flex gap-3 rounded-[5px] bg-slate-50 p-4">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-[5px] bg-slate-950 text-xs font-black text-white">{{ $number }}</span>
                        <div><h3 class="font-extrabold text-slate-950">{{ $title }}</h3><p class="mt-1 text-sm leading-6 text-slate-600">{{ $description }}</p></div>
                    </div>
                @endforeach
            </div>
        </section>

        <aside class="h-fit rounded-[8px] border border-amber-200 bg-amber-50 p-5 sm:p-6">
            <h2 class="flex items-center gap-2 font-extrabold text-amber-950"><i class="bx bx-info-circle text-xl" aria-hidden="true"></i>Cần chuẩn bị</h2>
            <ul class="mt-4 grid gap-3 text-sm leading-6 text-amber-950/80">
                <li class="flex gap-2"><i class="bx bx-check mt-1 text-lg text-amber-700" aria-hidden="true"></i><span>Một domain chưa được dùng trên hệ thống.</span></li>
                <li class="flex gap-2"><i class="bx bx-check mt-1 text-lg text-amber-700" aria-hidden="true"></i><span>Tài khoản NapCarot đang hoạt động và có số dư để thanh toán đơn.</span></li>
                <li class="flex gap-2"><i class="bx bx-check mt-1 text-lg text-amber-700" aria-hidden="true"></i><span>Tên website, logo và thông tin hỗ trợ khách hàng.</span></li>
            </ul>
            <a class="client-button mt-5 min-h-11 w-full justify-center" href="{{ route('client.support.chat') }}">Trao đổi với hỗ trợ<i class="bx bx-arrow-right text-xl" aria-hidden="true"></i></a>
        </aside>
    </div>
</section>
@endsection
