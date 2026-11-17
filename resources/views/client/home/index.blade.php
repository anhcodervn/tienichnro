@extends('client.layouts.app')
@section('document_title', $homeSeo['meta_title'])
@section('description', $homeSeo['meta_description'])
@section('keywords', $homeSeo['meta_keywords'])
@section('canonical', route('home'))
@push('head')
    @if($homeFaqSchema)
        <script type="application/ld+json">{!! json_encode($homeFaqSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endif
@endpush
@section('content')
<div class="client-container grid gap-4 py-4 sm:gap-5 sm:py-6">
    <header class="rounded-[10px] border border-emerald-200 bg-gradient-to-r from-emerald-50 via-white to-cyan-50 px-4 py-4 shadow-sm sm:px-6 sm:py-5" data-home-compact-header>
        <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-emerald-700">Tiện ích NRO · Công cụ hỗ trợ game</p>
        <h1 class="mt-1.5 max-w-4xl text-[22px] font-extrabold leading-tight tracking-tight text-slate-950 sm:text-[26px]">{{ $homeSeo['meta_title'] }}</h1>
        <p class="mt-2 max-w-4xl text-sm leading-6 text-slate-600">{{ $homeSeo['meta_description'] }}</p>
    </header>
    <section id="cong-cu" class="scroll-mt-24 rounded-[10px] border border-slate-200 bg-white p-3 shadow-sm sm:p-5" aria-labelledby="home-tools-title" data-home-tool-picker>
        <div class="flex items-end justify-between gap-3 px-1 pb-4"><div><p class="text-xs font-bold uppercase tracking-[0.12em] text-emerald-700">Chọn nhanh</p><h2 id="home-tools-title" class="mt-0.5 text-lg font-extrabold text-slate-950 sm:text-xl">Công cụ hỗ trợ Ngọc Rồng Online</h2></div><span class="hidden text-xs font-semibold text-slate-500 sm:inline">Chọn công cụ để sử dụng</span></div>
        <x-client.tool-list :tools="$tools" data-home-tool-grid />
        <p class="mt-5 border-t border-slate-100 pt-3 text-xs leading-5 text-slate-500">Các công cụ mới sẽ được bổ sung vào khu vực này.</p>
    </section>
    <a href="https://napcarot.com/community" class="group flex min-h-16 items-center gap-3 rounded-[10px] border border-emerald-200 bg-gradient-to-r from-emerald-50 via-white to-cyan-50 px-4 py-3 shadow-sm transition hover:border-emerald-300 sm:gap-4 sm:px-5" data-community-cta>
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-600 text-xl text-white" aria-hidden="true"><i class="bx bx-group"></i></span><span class="min-w-0 flex-1"><strong class="text-sm font-extrabold text-slate-950 sm:text-base">Tham gia cộng đồng</strong><span class="mt-0.5 block text-xs leading-5 text-slate-600 sm:text-sm">Cùng chia sẻ kinh nghiệm và góp ý công cụ hỗ trợ NRO.</span></span><i class="bx bx-arrow-right text-xl text-emerald-700" aria-hidden="true"></i>
    </a>
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="home-news-title" data-home-latest-news>
        <header class="mb-4 flex items-center justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-[0.12em] text-emerald-700">Cập nhật</p><h2 id="home-news-title" class="mt-1 text-lg font-extrabold text-slate-950 sm:text-xl">Tin tức & hướng dẫn mới</h2></div><a class="shrink-0 text-sm font-bold text-emerald-700" href="{{ route('seo.index') }}">Xem tất cả <span aria-hidden="true">→</span></a></header>
        <div class="grid gap-4 sm:grid-cols-3">
            @forelse($latestPosts as $post)
                <article class="client-card overflow-hidden">
                    @if($post->cover_image)<img class="aspect-video w-full object-cover" src="{{ $post->cover_image }}" alt="{{ $post->cover_alt ?: $post->title }}" loading="lazy">@endif
                    <div class="p-4"><p class="text-xs text-slate-500">{{ $post->published_at->format('d/m/Y') }}</p><h3 class="mt-2 font-bold leading-6 text-slate-900"><a class="hover:text-emerald-700" href="{{ $post->category ? route('seo.show', ['categorySlug' => $post->category->slug, 'postSlug' => $post->slug]) : route('seo.legacy.show', $post->slug) }}">{{ $post->title }}</a></h3>@if($post->excerpt)<p class="mt-2 line-clamp-2 text-sm leading-6 text-slate-600">{{ $post->excerpt }}</p>@endif</div>
                </article>
            @empty
                <p class="col-span-full rounded-[10px] bg-slate-50 p-5 text-sm text-slate-500">Tin tức và hướng dẫn đang được cập nhật.</p>
            @endforelse
        </div>
    </section>
    @if($homeContentHtml !== '')
        <div class="mb-6"><x-client.seo-article :title="$homeSeo['article_title']" id="home-seo"><div data-client-image-viewer>{!! $homeContentHtml !!}</div></x-client.seo-article></div>
    @endif
    @if($homeFaq->isNotEmpty())
        <x-client.seo-faq :items="$homeFaq" id="home-seo-faq" />
    @endif
</div>
@endsection
