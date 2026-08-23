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
                {{ $homeNoticeHtml }}
            </div>
        </div>
    @endif

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
                <div
                    id="reward-panel-{{ $game->id }}"
                    role="tabpanel"
                    aria-labelledby="reward-tab-{{ $game->id }}"
                    data-game-reward="{{ $game->id }}"
                    @if ((string) $game->id !== $initialGameId) hidden @endif
                >
                    @if ($game->packages->isNotEmpty())
                        <div class="home-table-scroll" tabindex="0" role="region" aria-label="Bảng thực nhận {{ $game->name }}">
                            <table class="home-price-table home-reward-table" aria-describedby="reward-table-description">
                                <caption class="sr-only">Bảng thực nhận {{ $game->name }} theo từng mệnh giá</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Nhóm</th>
                                        @foreach ($game->packages as $package)
                                            <th scope="col" data-package-column="{{ $package->id }}">
                                                {{ $package->denomination ? number_format($package->denomination, 0, ',', '.').'đ' : $package->name }}
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <th scope="row">{{ $game->reward_label ?: 'Thực nhận' }}</th>
                                        @foreach ($game->packages as $package)
                                            <td data-package-column="{{ $package->id }}">{{ $package->carot_amount !== null ? number_format($package->carot_amount, 0, ',', '.') : '—' }}</td>
                                        @endforeach
                                    </tr>
                                    <tr>
                                        <th scope="row">KM X2</th>
                                        @foreach ($game->packages as $package)
                                            <td data-package-column="{{ $package->id }}">{{ $package->reward_x2_amount !== null ? number_format($package->reward_x2_amount, 0, ',', '.') : '—' }}</td>
                                        @endforeach
                                    </tr>
                                    <tr>
                                        <th scope="row">KM X3</th>
                                        @foreach ($game->packages as $package)
                                            <td data-package-column="{{ $package->id }}">{{ $package->reward_x3_amount !== null ? number_format($package->reward_x3_amount, 0, ',', '.') : '—' }}</td>
                                        @endforeach
                                    </tr>
                                    <tr>
                                        <th scope="row">X2 nạp đầu</th>
                                        @foreach ($game->packages as $package)
                                            <td data-package-column="{{ $package->id }}">{{ $package->first_topup_reward_amount !== null ? number_format($package->first_topup_reward_amount, 0, ',', '.') : '—' }}</td>
                                        @endforeach
                                    </tr>
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
