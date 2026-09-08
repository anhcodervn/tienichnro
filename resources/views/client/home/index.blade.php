@extends('client.layouts.app')

@section('document_title', config('seo.homepage.title'))
@section('description', config('seo.homepage.description'))
@section('canonical', $homeCanonicalUrl)

@push('head')
    @foreach ($homeSchemas as $schema)
        <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endforeach
@endpush

@section('content')
@php
    $requestedGameId = (string) old('game_id', $games->first()?->id);
    $initialGameId = $games->contains(fn ($game) => (string) $game->id === $requestedGameId)
        ? $requestedGameId
        : (string) $games->first()?->id;
@endphp

@if ($homePopupIsPublished && $homePopupHtml->isNotEmpty())
    <x-client.home-popup
        :title="$homePopupTitle"
        :content="$homePopupHtml"
        :display-mode="$homePopupDisplayMode"
        :allow-dismiss="$homePopupAllowDismiss"
        :dismiss-hours="$homePopupDismissHours"
        :popup-key="$homePopupKey"
    />
@endif

<section class="client-container py-5 sm:py-7">
    <header class="mb-5 rounded-[10px] border border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-cyan-50 px-5 py-6 shadow-sm sm:px-7 sm:py-8">
        <p class="text-sm font-extrabold uppercase tracking-[0.14em] text-emerald-700">NapCarot · Nạp game Teamobi</p>
        <h1 class="mt-2 max-w-4xl text-3xl font-extrabold leading-tight tracking-tight text-slate-950 sm:text-4xl">Nạp Carot Game Teamobi Nhanh Chóng, Giá Tốt</h1>
        <p class="mt-4 max-w-5xl text-base leading-7 text-slate-700">
            NapCarot là website hỗ trợ nạp Carot cho các game Teamobi theo quy trình rõ ràng, từ lúc chọn game, máy chủ và gói nạp đến khi theo dõi trạng thái đơn. Người dùng có thể xem giá đang áp dụng trước khi thanh toán, kiểm tra mức thực nhận theo dữ liệu hiện có và tra cứu lại giao dịch bằng thông tin đơn hàng. Hệ thống hướng tới nhiều tựa game quen thuộc như Ngọc Rồng Online, Ninja School Online, Avatar, Avatar Musik, Hải Tặc Tí Hon và Hiệp Sĩ Online; game hoặc gói chưa mở bán sẽ không được hiển thị như một lựa chọn đặt hàng. Bạn có thể xem tổng quan về <a class="font-bold text-emerald-700 hover:text-emerald-800" href="{{ $mainSeoLandings->firstWhere('slug', 'nap-carot')['url'] }}">nạp Carot</a>, tìm hiểu cách <a class="font-bold text-emerald-700 hover:text-emerald-800" href="{{ $mainSeoLandings->firstWhere('slug', 'nap-game-teamobi')['url'] }}">nạp game Teamobi</a>, rồi sử dụng biểu mẫu bên dưới để bắt đầu.
        </p>
    </header>

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

    <div id="nap-game" class="scroll-mt-20">
        @include('client.components.topup-form', ['games' => $games, 'walletBalance' => $walletBalance, 'showConfirmation' => true, 'useH1' => false])
    </div>

    <div class="home-trust-strip" aria-label="Cam kết dịch vụ">
        <span class="inline-flex items-center gap-2"><i class="bx bx-badge-check text-xl text-emerald-700" aria-hidden="true"></i><span><strong>Rõ giá</strong> trước khi thanh toán</span></span>
        <span class="inline-flex items-center gap-2"><i class="bx bx-bolt text-xl text-cyan-700" aria-hidden="true"></i><span><strong>Tự động</strong> đối soát chuyển khoản</span></span>
        <span class="inline-flex items-center gap-2"><i class="bx bx-search text-xl text-sky-700" aria-hidden="true"></i><span><strong>Dễ tra cứu</strong> bằng mã đơn và email</span></span>
    </div>

    <section class="mt-8" aria-labelledby="teamobi-games-title">
        <div class="max-w-3xl">
            <p class="text-sm font-bold text-emerald-700">Danh mục nạp game</p>
            <h2 id="teamobi-games-title" class="mt-1 text-2xl font-extrabold text-slate-950 sm:text-3xl">Nạp Carot cho các game Teamobi</h2>
            <p class="mt-3 leading-7 text-slate-600">Mỗi trang tập trung vào một nhu cầu nạp riêng. Tùy chọn đặt hàng chỉ xuất hiện khi game và gói tương ứng đang hoạt động.</p>
        </div>
        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($homeGameLandings as $gameLanding)
                <a class="client-card group flex min-w-0 items-center gap-4 p-5 transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md" href="{{ $gameLanding['url'] }}">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-[8px] bg-emerald-50 text-2xl text-emerald-700"><i class="bx bx-joystick" aria-hidden="true"></i></span>
                    <span class="min-w-0 flex-1"><strong class="block text-slate-950">{{ $gameLanding['name'] }}</strong><small class="mt-1 block line-clamp-2 leading-5 text-slate-500">Xem thông tin và gói đang hỗ trợ</small></span>
                    <i class="bx bx-right-arrow-alt text-xl text-emerald-700 transition group-hover:translate-x-1" aria-hidden="true"></i>
                </a>
            @endforeach
        </div>
    </section>

    <section class="mt-10 grid gap-5 lg:grid-cols-2" aria-labelledby="why-napcarot-title">
        <div class="client-card p-5 sm:p-7">
            <p class="text-sm font-bold text-cyan-700">Thông tin minh bạch</p>
            <h2 id="why-napcarot-title" class="mt-1 text-2xl font-extrabold text-slate-950">Tại sao nên nạp Carot tại NapCarot?</h2>
            <ul class="mt-5 grid gap-4 text-sm leading-6 text-slate-700">
                <li class="flex gap-3"><i class="bx bx-check-circle mt-0.5 text-xl text-emerald-700" aria-hidden="true"></i><span><strong>Rõ giá trước khi thanh toán:</strong> gói đang mở bán và số tiền cần trả được hiển thị trong biểu mẫu.</span></li>
                <li class="flex gap-3"><i class="bx bx-check-circle mt-0.5 text-xl text-emerald-700" aria-hidden="true"></i><span><strong>Đối soát chuyển khoản:</strong> hệ thống theo dõi giao dịch và cập nhật trạng thái đơn.</span></li>
                <li class="flex gap-3"><i class="bx bx-check-circle mt-0.5 text-xl text-emerald-700" aria-hidden="true"></i><span><strong>Có thể tra cứu:</strong> tài khoản đã đăng nhập xem lịch sử; khách dùng mã đơn và email.</span></li>
                <li class="flex gap-3"><i class="bx bx-check-circle mt-0.5 text-xl text-emerald-700" aria-hidden="true"></i><span><strong>Dữ liệu theo game:</strong> trường nhận hàng, máy chủ và gói nạp thay đổi theo cấu hình thực tế.</span></li>
            </ul>
        </div>
        <div class="client-card p-5 sm:p-7">
            <p class="text-sm font-bold text-cyan-700">Quy trình ngắn gọn</p>
            <h2 class="mt-1 text-2xl font-extrabold text-slate-950">Cách nạp Carot tại NapCarot</h2>
            <ol class="mt-5 grid gap-4">
                <li class="home-seo-step"><span>01</span><div><strong>Chọn game và máy chủ</strong><p>Chọn đúng trò chơi, máy chủ và gói đang mở bán.</p></div></li>
                <li class="home-seo-step"><span>02</span><div><strong>Nhập thông tin nhận hàng</strong><p>Điền đúng tài khoản hoặc trường dữ liệu mà game yêu cầu.</p></div></li>
                <li class="home-seo-step"><span>03</span><div><strong>Kiểm tra và thanh toán</strong><p>Xác nhận giá, thông tin nhận hàng và phương thức thanh toán.</p></div></li>
                <li class="home-seo-step"><span>04</span><div><strong>Theo dõi đơn</strong><p>Xem lịch sử tài khoản hoặc tra cứu bằng mã đơn và email.</p></div></li>
            </ol>
        </div>
    </section>

        <section class="home-reward-card mt-5" aria-labelledby="reward-table-title">
            <header class="border-b border-slate-200 bg-white p-4 sm:p-5">
                <p class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-[0.14em] text-cyan-700"><i class="bx bx-gift text-base" aria-hidden="true"></i>Giá trị nhận trong game</p>
                <h2 id="reward-table-title" class="mt-1 text-xl font-extrabold text-slate-950">Bảng giá nạp Carot</h2>
                <p id="reward-table-description" class="mt-2 text-sm leading-6 text-slate-600">Giá gói đang bán hiển thị trong biểu mẫu; bảng dưới đây dùng dữ liệu hệ thống để đối chiếu mệnh giá và mức thực nhận theo game.</p>

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
</section>

<section class="border-y border-slate-200 bg-white">
    <div class="client-container grid gap-10 py-8 lg:grid-cols-2 lg:py-12">
        <section aria-labelledby="home-faq-title">
            <p class="text-sm font-bold text-emerald-700">Giải đáp trước khi nạp</p>
            <h2 id="home-faq-title" class="mt-1 text-2xl font-extrabold text-slate-950 sm:text-3xl">Các câu hỏi thường gặp về nạp Carot</h2>
            <div class="mt-5 grid gap-3">
                @foreach ($homeFaqs as $faq)
                    <details class="group rounded-[8px] border border-slate-200 bg-slate-50 p-4 open:border-emerald-200 open:bg-emerald-50/40">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 font-bold text-slate-950 [&::-webkit-details-marker]:hidden">
                            <span>{{ $faq['question'] }}</span><i class="bx bx-chevron-down shrink-0 text-xl text-emerald-700 transition group-open:rotate-180" aria-hidden="true"></i>
                        </summary>
                        <p class="mt-3 pr-7 text-sm leading-6 text-slate-600">{{ $faq['answer'] }}</p>
                    </details>
                @endforeach
            </div>
        </section>

        <section aria-labelledby="latest-guides-title">
            <p class="text-sm font-bold text-emerald-700">Bài viết mới</p>
            <div class="flex items-end justify-between gap-3">
                <h2 id="latest-guides-title" class="mt-1 text-2xl font-extrabold text-slate-950 sm:text-3xl">Kiến thức và hướng dẫn nạp game</h2>
                <a class="shrink-0 text-sm font-bold text-emerald-700 hover:text-emerald-800" href="{{ route('seo.index') }}">Xem tất cả</a>
            </div>
            @if ($latestSeoPosts->isNotEmpty())
                <div class="mt-5 grid gap-3">
                    @foreach ($latestSeoPosts as $post)
                        <article class="rounded-[8px] border border-slate-200 p-4 transition hover:border-emerald-300">
                            <p class="text-xs font-bold uppercase tracking-wide text-emerald-700">{{ $post['category'] }}</p>
                            <h3 class="mt-1 font-extrabold leading-6 text-slate-950"><a class="hover:text-emerald-700" href="{{ $post['url'] }}">{{ $post['title'] }}</a></h3>
                            @if ($post['excerpt'])
                                <p class="mt-2 line-clamp-2 text-sm leading-6 text-slate-600">{{ $post['excerpt'] }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            @else
                <div class="client-card mt-5 p-6 text-sm leading-6 text-slate-600">Các bài hướng dẫn đang được biên tập. Chỉ nội dung đã xuất bản mới hiển thị tại đây.</div>
            @endif
        </section>
    </div>
</section>
@endsection
