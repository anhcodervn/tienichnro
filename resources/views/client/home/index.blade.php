@extends('client.layouts.app')

@section('title', 'Nạp Carot game Teamobi nhanh chóng')
@section('description', 'Mua Carot và nạp game Teamobi với bảng giá chiết khấu rõ ràng, thanh toán thuận tiện và theo dõi lịch sử đơn hàng minh bạch.')

@section('content')
@php
    $requestedGameId = (string) old('game_id', $games->first()?->id);
    $initialGameId = $games->contains(fn ($game) => (string) $game->id === $requestedGameId)
        ? $requestedGameId
        : (string) $games->first()?->id;
@endphp

<section class="client-container py-5 sm:py-7">

    @if ($homeNoticeIsPublished && $homeNoticeHtml->isNotEmpty())
        <div class="home-notice-banner" role="note" aria-labelledby="home-notice-title">
            <div class="home-notice-header">
                <i class="bx bx-announcement shrink-0 text-xl text-cyan-700" aria-hidden="true"></i>
                <p id="home-notice-title" class="min-w-0"><strong>Thông báo:</strong> {{ $homeNoticeTitle }}</p>
            </div>
            <div class="article-content article-content--notice home-notice-content">
                {!! $homeNoticeHtml->toHtml() !!}
            </div>
        </div>
    @endif

    <a
        href="{{ route('bio.show') }}"
        class="group mb-4 flex min-h-16 w-full items-center gap-3 rounded-[10px] border border-emerald-200 bg-gradient-to-r from-emerald-50 via-white to-cyan-50 px-4 py-3 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 sm:gap-4 sm:px-5"
        data-community-cta
    >
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-600 text-xl text-white shadow-sm sm:h-11 sm:w-11" aria-hidden="true">
            <i class="bx bx-group"></i>
        </span>
        <span class="min-w-0 flex-1">
            <span class="flex flex-wrap items-center gap-2">
                <strong class="text-sm font-extrabold text-slate-950 sm:text-base">Tham gia cộng đồng</strong>
                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wide text-amber-700">Nổi bật</span>
            </span>
            <span class="mt-0.5 block text-xs leading-5 text-slate-600 sm:text-sm">Kết nối và truy cập nhanh các kênh chính thức của Nạp Carot.</span>
        </span>
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full border border-emerald-200 bg-white text-lg text-emerald-700 transition group-hover:translate-x-1 group-hover:border-emerald-300" aria-hidden="true">
            <i class="bx bx-right-arrow-alt"></i>
        </span>
    </a>

    @include('client.components.topup-form', ['games' => $games, 'walletBalance' => $walletBalance])

    <div class="home-trust-strip" aria-label="Cam kết dịch vụ">
        <span class="inline-flex items-center gap-2"><i class="bx bx-badge-check text-xl text-emerald-700" aria-hidden="true"></i><span><strong>Rõ giá</strong> trước khi thanh toán</span></span>
        <span class="inline-flex items-center gap-2"><i class="bx bx-bolt text-xl text-cyan-700" aria-hidden="true"></i><span><strong>Tự động</strong> đối soát chuyển khoản</span></span>
        <span class="inline-flex items-center gap-2"><i class="bx bx-search text-xl text-sky-700" aria-hidden="true"></i><span><strong>Dễ tra cứu</strong> bằng mã đơn và email</span></span>
    </div>

    @if ($games->isNotEmpty())
        <section class="home-reward-card mt-5" aria-labelledby="reward-table-title">
            <header class="border-b border-slate-200 bg-white p-4 sm:p-5">
                <p class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-[0.14em] text-cyan-700"><i class="bx bx-gift text-base" aria-hidden="true"></i>Giá trị nhận trong game</p>
                <h2 id="reward-table-title" class="mt-1 text-xl font-extrabold text-slate-950">Bảng thực nhận theo từng game</h2>
                <p id="reward-table-description" class="mt-2 text-sm leading-6 text-slate-600">Chọn game để xem số vật phẩm thực nhận tương ứng với từng mệnh giá.</p>

                <div class="home-game-tabs mt-4" role="tablist" aria-label="Chọn bảng giá theo game">
                    @foreach ($games as $game)
                        <button
                            id="reward-tab-{{ $game->id }}"
                            type="button"
                            class="home-game-tab"
                            role="tab"
                            data-game-reward-tab="{{ $game->id }}"
                            aria-controls="reward-panel-{{ $game->id }}"
                            aria-selected="{{ (string) $game->id === $initialGameId ? 'true' : 'false' }}"
                            tabindex="{{ (string) $game->id === $initialGameId ? '0' : '-1' }}"
                        >
                            {{ $game->name ?: $game->short_name }}
                        </button>
                    @endforeach
                </div>
            </header>

            @foreach ($games as $game)
                @php
                    $rewardSettings = $game->globalPackageSettings->filter(
                        fn ($setting) => collect($setting->receives)->contains(fn ($receive) => is_array($receive)),
                    );
                @endphp
                <div
                    id="reward-panel-{{ $game->id }}"
                    role="tabpanel"
                    aria-labelledby="reward-tab-{{ $game->id }}"
                    data-game-reward="{{ $game->id }}"
                    @if ((string) $game->id !== $initialGameId) hidden @endif
                >
                    @if ($rewardSettings->isNotEmpty())
                        <div class="home-table-scroll" tabindex="0" role="region" aria-label="Bảng thực nhận {{ $game->name }}">
                            <table class="home-price-table home-reward-table" aria-describedby="reward-table-description">
                                <caption class="sr-only">Bảng thực nhận {{ $game->name }} theo từng mệnh giá</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Mệnh giá</th>
                                        <th scope="col">Đơn vị nhận</th>
                                        <th scope="col">Cơ bản</th>
                                        <th scope="col">KM X2</th>
                                        <th scope="col">KM X3</th>
                                        <th scope="col">Nạp đầu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rewardSettings as $setting)
                                        @php $receives = collect($setting->receives)->filter(fn ($receive) => is_array($receive))->values(); @endphp
                                        @foreach ($receives as $receiveIndex => $receive)
                                            <tr data-reward-denomination="{{ $setting->denomination }}">
                                                @if ($receiveIndex === 0)
                                                    <th scope="rowgroup" rowspan="{{ $receives->count() }}">
                                                        {{ number_format($setting->denomination, 0, ',', '.') }}đ
                                                    </th>
                                                @endif
                                                <td>
                                                    <span class="inline-flex items-baseline gap-1">
                                                        <strong>{{ data_get($receive, 'label', data_get($receive, 'code', 'Thực nhận')) }}</strong>
                                                        @if (filled(data_get($receive, 'code')))
                                                            <span class="text-xs font-bold text-slate-500">({{ data_get($receive, 'code') }})</span>
                                                        @endif
                                                    </span>
                                                </td>
                                                <td>{{ data_get($receive, 'base_amount') !== null ? number_format((int) data_get($receive, 'base_amount'), 0, ',', '.') : '—' }}</td>
                                                <td>{{ data_get($receive, 'reward_x2_amount') !== null ? number_format((int) data_get($receive, 'reward_x2_amount'), 0, ',', '.') : '—' }}</td>
                                                <td>{{ data_get($receive, 'reward_x3_amount') !== null ? number_format((int) data_get($receive, 'reward_x3_amount'), 0, ',', '.') : '—' }}</td>
                                                <td>{{ data_get($receive, 'first_topup_reward_amount') !== null ? number_format((int) data_get($receive, 'first_topup_reward_amount'), 0, ',', '.') : '—' }}</td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="px-4 py-10 text-center text-sm text-slate-500">Bảng thực nhận đang được cập nhật.</p>
                    @endif

                    <div class="border-t border-slate-200 px-4 py-4 text-center text-xs leading-6 text-slate-700">
                        <p>Nạp ngay tại <strong class="text-red-600">Nạp Carot</strong></p>
                        <p>Lưu ý: khuyến mãi thực tế có thể thay đổi theo game hoặc máy chủ tại thời điểm xử lý.</p>
                    </div>
                </div>
            @endforeach
        </section>
    @endif
</section>

@guest
    <section class="bg-white">
        <div class="client-container py-6 sm:py-8">
            <article class="home-seo-article">
                <header class="border-b border-slate-200 bg-cyan-50 px-5 py-5 sm:px-6">
                    <p class="inline-flex items-center gap-1.5 text-sm font-bold text-red-600"><i class="bx bx-book-open text-lg" aria-hidden="true"></i>Hướng dẫn nạp game</p>
                    <h2 class="mt-1 max-w-4xl text-xl font-bold leading-tight text-slate-950">Nạp Carot game Teamobi nhanh, rõ giá và dễ tra cứu</h2>
                    <p class="mt-4 max-w-4xl leading-7 text-slate-600">Nạp Carot giúp bạn bổ sung vật phẩm và tiện ích trong các game Teamobi đang hỗ trợ. Tại Nạp Carot, bảng giá được công khai theo từng game và từng gói để bạn dễ so sánh trước khi đặt mua.</p>
                </header>

                <div class="grid gap-8 px-5 py-7 sm:px-8 lg:grid-cols-[1.15fr_0.85fr] lg:gap-12 lg:py-9">
                    <div>
                        <h3 class="flex items-center gap-2 text-xl font-extrabold text-slate-950"><i class="bx bx-checks text-2xl text-emerald-700" aria-hidden="true"></i>Cách mua Carot trong 3 bước</h3>
                        <ol class="mt-5 grid gap-4 sm:grid-cols-3 lg:grid-cols-1">
                            <li class="home-seo-step"><span>01</span><div><strong>Chọn thông tin</strong><p>Chọn game, máy chủ, gói nạp và nhập chính xác tài khoản nhận Carot.</p></div></li>
                            <li class="home-seo-step"><span>02</span><div><strong>Thanh toán</strong><p>Thanh toán bằng chuyển khoản hoặc số dư ví nếu bạn đã đăng nhập.</p></div></li>
                            <li class="home-seo-step"><span>03</span><div><strong>Theo dõi đơn</strong><p>Dùng mã đơn và email để kiểm tra tiến độ từ lúc thanh toán đến khi hoàn tất.</p></div></li>
                        </ol>
                    </div>

                    <div>
                        <h3 class="flex items-center gap-2 text-xl font-extrabold text-slate-950"><i class="bx bx-joystick text-2xl text-cyan-700" aria-hidden="true"></i>Game đang hỗ trợ</h3>
                        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                            @foreach ($games as $game)
                                <a class="home-game-link" href="{{ route('topup.game', $game) }}">
                                    <span class="grid h-10 w-10 place-items-center rounded-[5px] bg-emerald-50 text-xs font-extrabold text-emerald-700">{{ mb_substr($game->short_name ?: $game->name, 0, 3) }}</span>
                                    <span><strong class="block text-slate-900">Nạp {{ $game->name }}</strong><small class="mt-1 block text-slate-500">{{ $game->packages->count() }} gói đang mở bán</small></span>
                                    <span class="ml-auto text-emerald-700" aria-hidden="true">→</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </article>
        </div>
    </section>
@endguest
@endsection
