@extends('client.layouts.app')

@section('document_title', $homeSeoMetaTitle)
@section('description', $homeSeoMetaDescription)
@section('keywords', $homeSeoMetaKeywords)
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
        $napCarotUrl = data_get($mainSeoLandings->firstWhere('slug', 'nap-carot'), 'url', route('home'));
        $teamobiUrl = data_get($mainSeoLandings->firstWhere('slug', 'nap-game-teamobi'), 'url', route('home'));
    @endphp

    @if ($homePopupIsPublished && $homePopupHtml->isNotEmpty())
        <x-client.home-popup :title="$homePopupTitle" :content="$homePopupHtml" :display-mode="$homePopupDisplayMode" :allow-dismiss="$homePopupAllowDismiss" :dismiss-hours="$homePopupDismissHours"
            :popup-key="$homePopupKey" />
    @endif

    <div class="client-container grid gap-4 py-4 sm:gap-5 sm:py-6">
        <header class="rounded-[10px] border border-emerald-200 bg-gradient-to-r from-emerald-50 via-white to-cyan-50 px-4 py-4 shadow-sm sm:px-6 sm:py-5" data-home-compact-header>
            <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-emerald-700">NapCarot · Nạp game Teamobi</p>
            <h1 class="mt-1.5 max-w-4xl text-2xl font-extrabold leading-tight tracking-tight text-slate-950 sm:text-3xl">{{ $homeSeoH1 }}</h1>
            <p class="mt-2 max-w-4xl text-sm leading-6 text-slate-600">Chọn đúng game để xem máy chủ, gói nạp và giá đang áp dụng. Mỗi game có một trang nạp riêng, dễ kiểm tra trên điện thoại.</p>
        </header>

        <section class="rounded-[10px] border border-slate-200 bg-white p-3 shadow-sm sm:p-5" aria-labelledby="home-game-picker-title" data-home-game-picker>
            <div class="flex items-end justify-between gap-3 px-1 pb-3">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.12em] text-emerald-700">Chọn nhanh</p>
                    <h2 id="home-game-picker-title" class="mt-0.5 text-lg font-extrabold text-slate-950 sm:text-xl">Nạp Carot cho các game Teamobi</h2>
                </div>
                <span class="hidden shrink-0 text-xs font-semibold text-slate-500 sm:inline">Chạm vào game để nạp</span>
            </div>

            <div class="grid grid-cols-3 gap-x-3 gap-y-5 min-[420px]:grid-cols-4 sm:grid-cols-5 md:grid-cols-6 lg:grid-cols-8" data-home-game-grid>
                @forelse ($games as $game)
                    <a
                        class="group flex min-w-0 flex-col items-center gap-2 rounded-[10px] text-center transition hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-4"
                        href="{{ route('topup.game', ['game' => $game]) }}"
                        aria-label="Nạp {{ $game->name }}"
                        data-home-game-link
                    >
                        @if ($game->image)
                            <img
                                class="aspect-square w-full max-w-[7.5rem] rounded-[12px] border border-slate-200 bg-slate-100 object-cover shadow-sm transition group-hover:shadow-md"
                                src="{{ $game->image }}"
                                alt="Ảnh game {{ $game->name }}"
                                width="120"
                                height="120"
                                @if ($loop->index >= 4) loading="lazy" @endif
                                decoding="async"
                            >
                        @else
                            <span class="grid aspect-square w-full max-w-[7.5rem] place-items-center rounded-[12px] border border-emerald-500 bg-emerald-600 text-xl font-extrabold uppercase text-white shadow-sm transition group-hover:shadow-md" aria-hidden="true">
                                {{ mb_substr($game->short_name ?: $game->name, 0, 2) }}
                            </span>
                        @endif
                        <strong class="line-clamp-2 min-h-10 w-full text-sm font-semibold leading-5 text-slate-900 group-hover:text-emerald-700">{{ $game->name }}</strong>
                    </a>
                @empty
                    <p class="col-span-full rounded-[8px] bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">Danh sách game đang được cập nhật.</p>
                @endforelse
            </div>
        </section>

        <a
            href="{{ route('bio.show') }}"
            class="group flex min-h-16 w-full items-center gap-3 rounded-[10px] border border-emerald-200 bg-gradient-to-r from-emerald-50 via-white to-cyan-50 px-4 py-3 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 sm:gap-4 sm:px-5"
            data-community-cta
        >
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-600 text-xl text-white shadow-sm sm:h-11 sm:w-11" aria-hidden="true"><i class="bx bx-group"></i></span>
            <span class="min-w-0 flex-1">
                <span class="flex flex-wrap items-center gap-2">
                    <strong class="text-sm font-extrabold text-slate-950 sm:text-base">Tham gia cộng đồng</strong>
                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wide text-amber-700">Nổi bật</span>
                </span>
                <span class="mt-0.5 block text-xs leading-5 text-slate-600 sm:text-sm">Kênh chính thức, hướng dẫn và hỗ trợ từ Nạp Carot.</span>
            </span>
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full border border-emerald-200 bg-white text-lg text-emerald-700 transition group-hover:translate-x-1 group-hover:border-emerald-300" aria-hidden="true"><i class="bx bx-arrow-right"></i></span>
        </a>

        <div class="home-trust-strip !mt-0" aria-label="Thống kê nền tảng" data-home-trust-strip>
            <span data-home-stat="games"><i class="bx bx-joystick text-lg text-sky-700" aria-hidden="true"></i><strong>{{ number_format($homeStatistics['games'], 0, ',', '.') }}</strong><span>Game</span></span>
            <span data-home-stat="members"><i class="bx bx-group text-lg text-emerald-700" aria-hidden="true"></i><strong>{{ number_format($homeStatistics['members'], 0, ',', '.') }}</strong><span>Thành viên</span></span>
            <span data-home-stat="orders"><i class="bx bx-receipt text-lg text-cyan-700" aria-hidden="true"></i><strong>{{ number_format($homeStatistics['orders'], 0, ',', '.') }}</strong><span>Đơn hàng</span></span>
        </div>

        <section id="bang-gia-thuc-nhan" class="home-reward-card" aria-labelledby="reward-table-title" data-home-reward-reference>
            <header class="border-b border-slate-200 bg-white p-4 sm:p-5">
                <p class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-[0.14em] text-cyan-700"><i class="bx bx-gift text-base" aria-hidden="true"></i>Tham khảo tất cả game</p>
                <h2 id="reward-table-title" class="mt-1 text-xl font-extrabold text-slate-950">Bảng giá nạp Carot và mức thực nhận</h2>
                <p id="reward-table-description" class="mt-2 text-sm leading-6 text-slate-600">Chọn từng game để đối chiếu mệnh giá và vật phẩm dự kiến nhận. Giá thanh toán chính xác được hiển thị tại trang nạp của game.</p>

                <div class="home-game-tabs mt-4 snap-x snap-mandatory" role="tablist" aria-label="Chọn bảng giá theo game">
                    @foreach ($homeRewardGames as $rewardGame)
                        <button
                            id="reward-tab-{{ $rewardGame['id'] }}"
                            type="button"
                            class="home-game-tab snap-start"
                            role="tab"
                            data-game-reward-tab="{{ $rewardGame['id'] }}"
                            aria-controls="reward-panel-{{ $rewardGame['id'] }}"
                            aria-selected="{{ (string) $rewardGame['id'] === $initialGameId ? 'true' : 'false' }}"
                            tabindex="{{ (string) $rewardGame['id'] === $initialGameId ? '0' : '-1' }}"
                        >{{ $rewardGame['name'] }}</button>
                    @endforeach
                </div>
            </header>

            @foreach ($homeRewardGames as $rewardGame)
                <div id="reward-panel-{{ $rewardGame['id'] }}" role="tabpanel" aria-labelledby="reward-tab-{{ $rewardGame['id'] }}" data-game-reward="{{ $rewardGame['id'] }}" @if ((string) $rewardGame['id'] !== $initialGameId) hidden @endif>
                    @if ($rewardGame['rows']->isNotEmpty())
                        <div class="home-table-scroll" tabindex="0" role="region" aria-label="Bảng thực nhận {{ $rewardGame['name'] }}">
                            <table class="home-price-table home-reward-table !w-full !min-w-0 sm:!min-w-[42rem]" aria-describedby="reward-table-description">
                                <caption class="sr-only">Bảng thực nhận {{ $rewardGame['name'] }} theo từng mệnh giá</caption>
                                <thead>
                                    <tr><th scope="col">Mệnh giá</th><th scope="col">Đơn vị nhận</th><th scope="col">Cơ bản</th><th class="hidden sm:table-cell" scope="col">KM X2</th><th class="hidden sm:table-cell" scope="col">KM X3</th><th class="hidden sm:table-cell" scope="col">Nạp đầu</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($rewardGame['rows'] as $rewardRow)
                                        @foreach ($rewardRow['receives'] as $receiveIndex => $receive)
                                            <tr data-reward-denomination="{{ $rewardRow['denomination'] }}">
                                                @if ($receiveIndex === 0)
                                                    <th scope="rowgroup" rowspan="{{ $rewardRow['receives']->count() }}">{{ number_format($rewardRow['denomination'], 0, ',', '.') }}đ</th>
                                                @endif
                                                <td><span class="inline-flex items-baseline gap-1"><strong>{{ data_get($receive, 'label', data_get($receive, 'code', 'Thực nhận')) }}</strong>@if (filled(data_get($receive, 'code')))<span class="text-xs font-bold text-slate-500">({{ data_get($receive, 'code') }})</span>@endif</span></td>
                                                <td>{{ data_get($receive, 'base_amount') !== null ? number_format((int) data_get($receive, 'base_amount'), 0, ',', '.') : '—' }}</td>
                                                <td class="hidden sm:table-cell">{{ data_get($receive, 'reward_x2_amount') !== null ? number_format((int) data_get($receive, 'reward_x2_amount'), 0, ',', '.') : '—' }}</td>
                                                <td class="hidden sm:table-cell">{{ data_get($receive, 'reward_x3_amount') !== null ? number_format((int) data_get($receive, 'reward_x3_amount'), 0, ',', '.') : '—' }}</td>
                                                <td class="hidden sm:table-cell">{{ data_get($receive, 'first_topup_reward_amount') !== null ? number_format((int) data_get($receive, 'first_topup_reward_amount'), 0, ',', '.') : '—' }}</td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="px-4 py-10 text-center text-sm text-slate-500">Bảng thực nhận đang được cập nhật.</p>
                    @endif
                    <div class="border-t border-slate-200 px-4 py-3 text-center text-xs leading-5 text-slate-600">Khuyến mãi thực tế có thể thay đổi theo game, máy chủ và thời điểm xử lý.</div>
                </div>
            @endforeach
        </section>

        <article id="huong-dan-nap-carot" class="home-seo-article p-5 sm:p-7" aria-labelledby="home-seo-title" data-home-seo-article>
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-emerald-700">Hướng dẫn nạp game</p>
            <h2 id="home-seo-title" class="mt-1 text-2xl font-extrabold leading-tight text-slate-950">{{ $homeSeoArticleTitle }}</h2>
            <x-client.collapsible-article content-id="home-seo-content" class="mt-4">
                @if ($homeSeoIsPublished)
                    <div class="article-content text-sm leading-7 text-slate-700">{!! $homeSeoHtml !!}</div>
                @else
                    <div class="grid gap-4 text-sm leading-7 text-slate-700 md:grid-cols-2">
                        <p>NapCarot hỗ trợ người chơi đi thẳng vào trang nạp của từng game. Sau khi chọn game ở đầu trang, bạn có thể nhập tài khoản, chọn mệnh giá, máy chủ và kiểm tra tổng tiền trước khi xác nhận. Cách tổ chức này giúp giảm nhầm lẫn giữa các game có trường tài khoản hoặc mức thực nhận khác nhau.</p>
                        <p>Bảng thực nhận phía trên dùng dữ liệu hệ thống để tham khảo nhanh. Khi cần tìm hiểu thêm, xem nội dung về <a class="font-bold text-emerald-700 hover:text-emerald-800" href="{{ $napCarotUrl }}">nạp Carot</a> hoặc hướng dẫn <a class="font-bold text-emerald-700 hover:text-emerald-800" href="{{ $teamobiUrl }}">nạp game Teamobi</a>. Giá cuối cùng luôn được xác nhận tại trang nạp của game bạn chọn.</p>
                    </div>
                @endif
            </x-client.collapsible-article>
        </article>

        <section class="grid gap-4 lg:grid-cols-2" aria-label="Lợi ích và quy trình nạp Carot" data-home-supporting-blocks>
            <article class="client-card p-5 sm:p-6">
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-cyan-700">Thông tin minh bạch</p>
                <h2 class="mt-1 text-xl font-extrabold text-slate-950">Tại sao nên nạp Carot tại NapCarot?</h2>
                <ul class="mt-4 grid gap-3 text-sm leading-6 text-slate-700">
                    <li class="flex gap-3"><i class="bx bx-check-circle mt-0.5 text-xl text-emerald-700" aria-hidden="true"></i><span><strong>Rõ giá:</strong> xem số tiền cần thanh toán trước khi tạo đơn.</span></li>
                    <li class="flex gap-3"><i class="bx bx-check-circle mt-0.5 text-xl text-emerald-700" aria-hidden="true"></i><span><strong>Đúng dữ liệu:</strong> mỗi game có trường tài khoản, máy chủ và gói nạp riêng.</span></li>
                    <li class="flex gap-3"><i class="bx bx-check-circle mt-0.5 text-xl text-emerald-700" aria-hidden="true"></i><span><strong>Dễ tra cứu:</strong> theo dõi trạng thái đơn bằng tài khoản hoặc email.</span></li>
                </ul>
            </article>
            <article class="client-card p-5 sm:p-6">
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-cyan-700">Quy trình ngắn gọn</p>
                <h2 class="mt-1 text-xl font-extrabold text-slate-950">Cách nạp Carot tại NapCarot</h2>
                <ol class="mt-4 grid gap-3">
                    <li class="home-seo-step"><span>01</span><div><strong>Chọn game</strong><p>Mở đúng trang nạp của trò chơi.</p></div></li>
                    <li class="home-seo-step"><span>02</span><div><strong>Nhập thông tin</strong><p>Điền tài khoản, số lượng và máy chủ.</p></div></li>
                    <li class="home-seo-step"><span>03</span><div><strong>Chọn gói và thanh toán</strong><p>Kiểm tra lại trong modal trước khi tạo đơn.</p></div></li>
                </ol>
            </article>
        </section>

        <section class="rounded-[10px] border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="latest-guides-title" data-home-seo-posts>
            <div><p class="text-xs font-bold uppercase tracking-[0.12em] text-emerald-700">Bài viết mới</p><h2 id="latest-guides-title" class="mt-1 text-xl font-extrabold text-slate-950 sm:text-2xl">Kiến thức và hướng dẫn nạp game</h2></div>
            @if ($latestSeoPosts->isNotEmpty())
                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    @foreach ($latestSeoPosts as $post)
                        <article class="rounded-[8px] border border-slate-200 p-4 transition hover:border-emerald-300" data-home-latest-post>
                            <p class="text-xs font-bold uppercase tracking-wide text-emerald-700">{{ $post['category'] }}</p>
                            <h3 class="mt-1 font-extrabold leading-6 text-slate-950"><a class="hover:text-emerald-700" href="{{ $post['url'] }}">{{ $post['title'] }}</a></h3>
                            @if ($post['excerpt'])<p class="mt-2 line-clamp-2 text-sm leading-6 text-slate-600">{{ $post['excerpt'] }}</p>@endif
                        </article>
                    @endforeach
                </div>
                <a class="mt-3 flex min-h-11 items-center justify-center rounded-[8px] border border-dashed border-emerald-300 bg-emerald-50 px-4 text-sm font-extrabold text-emerald-800 transition hover:border-emerald-500 hover:bg-emerald-100 sm:ml-auto sm:w-fit" href="{{ route('seo.index') }}" data-home-all-posts>Xem tất cả bài viết <i class="bx bx-arrow-right ml-2" aria-hidden="true"></i></a>
            @else
                <p class="mt-4 rounded-[8px] bg-slate-50 p-5 text-sm leading-6 text-slate-600">Các bài hướng dẫn đang được biên tập. Chỉ nội dung đã xuất bản mới hiển thị tại đây.</p>
            @endif
        </section>

        <section class="rounded-[10px] border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="home-faq-title" data-home-faq>
            <p class="text-xs font-bold uppercase tracking-[0.12em] text-emerald-700">Giải đáp trước khi nạp</p>
            <h2 id="home-faq-title" class="mt-1 text-xl font-extrabold text-slate-950 sm:text-2xl">Các câu hỏi thường gặp về nạp Carot</h2>
            <div class="mt-4 grid gap-3 lg:grid-cols-2">
                @foreach ($homeFaqs as $faq)
                    <details class="group rounded-[8px] border border-slate-200 bg-slate-50 p-4 open:border-emerald-200 open:bg-emerald-50/40">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 font-bold text-slate-950 [&::-webkit-details-marker]:hidden"><span>{{ $faq['question'] }}</span><i class="bx bx-chevron-down shrink-0 text-xl text-emerald-700 transition group-open:rotate-180" aria-hidden="true"></i></summary>
                        <p class="mt-3 pr-7 text-sm leading-6 text-slate-600">{{ $faq['answer'] }}</p>
                    </details>
                @endforeach
            </div>
        </section>
    </div>
@endsection
