@extends('client.layouts.app')

@section('document_title', $pageTitle)
@section('description', $pageDescription)
@section('keywords', $pageKeywords)
@section('canonical', $canonicalUrl)
@section('robots', $pageRobots)
@if ($pageImage)
    @section('image', $pageImage)
    @section('image_alt', $pageImageAlt)
@endif

@push('head')
    @foreach ($gameSchemas as $schema)
        <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endforeach
@endpush

@section('content')
    <section class="client-container grid gap-5 py-5 sm:py-7">
        <header class="overflow-hidden rounded-[10px] border border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-cyan-50 p-4 shadow-sm sm:p-7" data-game-landing-hero>
            <nav class="flex items-center gap-1 text-xs text-slate-500 sm:gap-1.5 sm:text-sm" aria-label="Breadcrumb">
                <a class="font-semibold hover:text-emerald-700" href="{{ route('home') }}">Trang chủ</a>
                <i class="bx bx-chevron-right text-base text-slate-300 sm:text-lg" aria-hidden="true"></i>
                <span class="truncate font-bold text-slate-800" aria-current="page">Nạp {{ $game->name }}</span>
            </nav>
            <div class="mt-3 flex items-center gap-3 sm:mt-5 sm:gap-5">
                @if ($game->image)
                    <img class="h-16 w-16 shrink-0 rounded-[10px] border border-white object-cover shadow-md sm:h-28 sm:w-28 sm:rounded-[12px]" src="{{ $game->image }}" alt="{{ $game->name }}" width="112" height="112" data-game-landing-icon>
                @else
                    <span class="grid h-16 w-16 shrink-0 place-items-center rounded-[10px] bg-emerald-600 text-xl font-extrabold text-white shadow-md sm:h-28 sm:w-28 sm:rounded-[12px] sm:text-3xl" aria-hidden="true" data-game-landing-icon>
                        {{ mb_substr($game->short_name ?: $game->name, 0, 2) }}
                    </span>
                @endif
                <div class="min-w-0">
                    <p class="text-[10px] font-extrabold uppercase tracking-[0.1em] text-emerald-700 sm:text-sm sm:tracking-[0.14em]">NapCarot · Nạp game tự động</p>
                    <h1 class="mt-1 break-words text-xl font-extrabold leading-snug tracking-tight text-slate-950 sm:mt-2 sm:text-4xl sm:leading-tight">{{ $pageH1 }}</h1>
                </div>
            </div>
        </header>

        <x-client.home-notice :title="$homeNoticeTitle" :content="$homeNoticeHtml" :published="$homeNoticeIsPublished" />

        <div id="nap-game" class="scroll-mt-20">
            @include('client.components.topup-form', [
                'games' => collect([$game]),
                'selectedGame' => $game,
                'walletBalance' => $walletBalance,
                'showConfirmation' => true,
                'useH1' => false,
                'stepLayout' => true,
            ])
        </div>

        <section class="client-card overflow-hidden" aria-labelledby="pending-orders-title" data-recent-pending-orders>
            <header class="border-b border-slate-200 bg-slate-50 px-4 py-4 sm:px-5">
                <p class="text-xs font-bold uppercase tracking-[0.14em] text-amber-700">Đang chờ xử lý</p>
                <h2 id="pending-orders-title" class="mt-1 text-xl font-extrabold text-slate-950">3 đơn pending gần nhất</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">Danh sách được ẩn danh và không hiển thị thông tin tài khoản người mua.</p>
            </header>
            <div class="divide-y divide-slate-100">
                @forelse ($recentPendingOrders as $pendingOrder)
                    <article class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5" data-pending-order>
                        <div class="min-w-0">
                            <h3 class="break-words font-bold text-slate-950">{{ $pendingOrder['package_name'] }}</h3>
                            <p class="mt-1 text-xs text-slate-500">Số lượng: {{ number_format($pendingOrder['quantity'], 0, ',', '.') }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2 text-xs font-semibold text-amber-700">
                            <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1">Pending</span>
                            <time datetime="{{ $pendingOrder['created_at']?->toISOString() }}">{{ $pendingOrder['created_at']?->diffForHumans() }}</time>
                        </div>
                    </article>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-slate-500">Hiện chưa có đơn pending cho game này.</p>
                @endforelse
            </div>
        </section>

        <x-client.game-reward-table :game="$game" />

        <section class="client-card min-w-0 overflow-hidden" aria-labelledby="game-seo-content-title">
            <header class="border-b border-slate-200 bg-slate-50 px-5 py-4">
                <p class="text-xs font-bold uppercase tracking-[0.14em] text-emerald-700">Thông tin nạp game</p>
                <h2 id="game-seo-content-title" class="mt-1 text-2xl font-extrabold text-slate-950">{{ $seoArticleTitle }}</h2>
            </header>
            <x-client.collapsible-article content-id="game-seo-content" class="pb-5 sm:pb-7">
                <div class="article-content min-w-0 max-w-full break-words p-5 pb-0 sm:p-7 sm:pb-0" data-game-description="{{ $game->id }}" data-client-image-viewer>
                    @if ($seoContentHtml->isNotEmpty())
                        {!! $seoContentHtml !!}
                    @else
                        <p>Nội dung hướng dẫn đang được cập nhật.</p>
                    @endif
                </div>
            </x-client.collapsible-article>
        </section>

        @if ($seoFaqs->isNotEmpty())
            <section class="rounded-[10px] border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="game-faq-title" data-game-seo-faq>
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-emerald-700">Giải đáp nhanh</p>
                <h2 id="game-faq-title" class="mt-1 text-xl font-extrabold text-slate-950">Câu hỏi thường gặp về {{ $game->name }}</h2>
                <div class="mt-4 grid gap-3 lg:grid-cols-2">
                    @foreach ($seoFaqs as $faq)
                        <details class="group rounded-[8px] border border-slate-200 bg-slate-50 p-4 open:border-emerald-200 open:bg-emerald-50/40">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 font-bold text-slate-950 [&::-webkit-details-marker]:hidden"><span>{{ $faq['question'] }}</span><i class="bx bx-chevron-down shrink-0 text-xl text-emerald-700 transition group-open:rotate-180" aria-hidden="true"></i></summary>
                            <p class="mt-3 pr-7 text-sm leading-6 text-slate-600">{{ $faq['answer'] }}</p>
                        </details>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($relatedSeoPosts->isNotEmpty())
            <section aria-labelledby="related-seo-posts-title">
                <p class="text-sm font-bold text-cyan-700">Kiến thức liên quan</p>
                <h2 id="related-seo-posts-title" class="mt-1 text-2xl font-extrabold text-slate-950">Bài viết về {{ $game->name }}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($relatedSeoPosts as $post)
                        <article class="client-card min-w-0 p-5">
                            <h3 class="break-words font-extrabold leading-6 text-slate-950">
                                <a class="hover:text-emerald-700" href="{{ $post['url'] }}">{{ $post['title'] }}</a>
                            </h3>
                            @if ($post['excerpt'])
                                <p class="mt-3 line-clamp-3 text-sm leading-6 text-slate-600">{{ $post['excerpt'] }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    </section>
@endsection
