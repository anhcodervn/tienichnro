@extends('client.layouts.app')
@section('title', $pageMetaTitle)
@section('description', $pageMetaDescription)
@section('canonical', $pageMetaCanonical)
@section('robots', $post->robots)
@section('image'){{ $pageMetaImage }}@endsection
@section('og_type', 'article')

@push('head')
    @if ($articleSchema)
        <script type="application/ld+json">{!! json_encode($articleSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endif
    @if ($breadcrumbSchema)
        <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endif
@endpush

@section('content')
<article class="border-b border-slate-200 bg-gradient-to-b from-emerald-50/60 via-white to-white">
    <div class="client-container py-7 sm:py-10">
        <nav class="flex min-w-0 items-center gap-1.5 overflow-x-auto whitespace-nowrap pb-2 text-sm text-slate-500" aria-label="Breadcrumb">
            <a class="shrink-0 font-semibold transition hover:text-emerald-700" href="{{ route('home') }}">Trang chủ</a>
            <i class="bx bx-chevron-right shrink-0 text-lg text-slate-300" aria-hidden="true"></i>
            <a class="shrink-0 font-semibold transition hover:text-emerald-700" href="{{ route('seo.index') }}">Tin tức</a>
            @if ($post->category)
                <i class="bx bx-chevron-right shrink-0 text-lg text-slate-300" aria-hidden="true"></i>
                <a class="shrink-0 font-semibold text-emerald-700 transition hover:text-emerald-800" href="{{ route('seo.category', $post->category->slug) }}">{{ $post->category->name }}</a>
            @endif
            <i class="bx bx-chevron-right shrink-0 text-lg text-slate-300" aria-hidden="true"></i>
            <span class="max-w-64 truncate font-medium text-slate-700" aria-current="page">{{ $post->title }}</span>
        </nav>

        <header class="mx-auto mt-6 max-w-4xl text-center">
            @if ($post->category)
                <a class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-emerald-700 transition hover:bg-emerald-100" href="{{ route('seo.category', $post->category->slug) }}"><i class="bx bx-folder-open text-base" aria-hidden="true"></i>{{ $post->category->name }}</a>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-slate-600"><i class="bx bx-file text-base" aria-hidden="true"></i>Hướng dẫn</span>
            @endif
            <h1 class="mt-4 break-words text-3xl font-extrabold leading-tight tracking-tight text-slate-950 sm:text-4xl lg:text-5xl">{{ $post->title }}</h1>
            @if ($post->excerpt)
                <p class="mx-auto mt-5 max-w-3xl text-base leading-7 text-slate-600 sm:text-lg">{{ $post->excerpt }}</p>
            @endif
            <div class="mt-5 flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-sm font-medium text-slate-500">
                <span class="inline-flex items-center gap-1.5"><i class="bx bx-calendar text-lg text-emerald-600" aria-hidden="true"></i>{{ $post->published_at?->format('d/m/Y') }}</span>
                <span class="inline-flex items-center gap-1.5"><i class="bx bx-time-five text-lg text-emerald-600" aria-hidden="true"></i>{{ $readingMinutes }} phút đọc</span>
                @if ($post->updated_at && $post->published_at && $post->updated_at->gt($post->published_at))
                    <span class="inline-flex items-center gap-1.5"><i class="bx bx-refresh text-lg text-emerald-600" aria-hidden="true"></i>Cập nhật {{ $post->updated_at->format('d/m/Y') }}</span>
                @endif
            </div>
        </header>

        @if ($coverImage)
            <figure class="mx-auto mt-8 max-w-5xl overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
                <img class="aspect-video h-auto w-full object-cover" src="{{ $coverImage }}" alt="{{ $post->cover_alt ?: $post->title }}">
            </figure>
        @endif
    </div>
</article>

<section class="client-container py-8 sm:py-10">
    <div class="grid min-w-0 gap-7 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <main class="min-w-0">
            <div class="article-content client-card min-w-0 max-w-full break-words p-5 sm:p-8 lg:p-10">{!! $contentHtml !!}</div>

            <footer class="mt-5 flex flex-col gap-4 rounded-[5px] border border-emerald-200 bg-emerald-50 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-wide text-emerald-700">Bạn đang đọc</p>
                    <p class="mt-1 break-words font-extrabold text-emerald-950">{{ $post->title }}</p>
                </div>
                <a class="client-button-secondary shrink-0 border-emerald-300 bg-white text-emerald-700" href="{{ $post->category ? route('seo.category', $post->category->slug) : route('seo.index') }}"><i class="bx bx-list-ul text-lg" aria-hidden="true"></i>Xem thêm bài viết</a>
            </footer>
        </main>

        <aside class="min-w-0 space-y-5 lg:sticky lg:top-24 lg:self-start">
            @if ($headingIndex !== [])
                <nav class="client-card p-5" aria-label="Mục lục bài viết">
                    <h2 class="flex items-center gap-2 font-extrabold text-slate-950"><i class="bx bx-list-ol text-xl text-emerald-600" aria-hidden="true"></i>Mục lục</h2>
                    <ol class="mt-4 grid gap-1.5 border-l-2 border-slate-100 pl-3">
                        @foreach ($headingIndex as $heading)
                            <li @class(['pl-3' => $heading['level'] > 2])><a class="block rounded-[5px] px-2 py-1.5 text-sm leading-5 text-slate-600 transition hover:bg-emerald-50 hover:text-emerald-700" href="#{{ $heading['id'] }}">{{ $heading['text'] }}</a></li>
                        @endforeach
                    </ol>
                </nav>
            @endif

            <section class="client-card p-5">
                <h2 class="flex items-center gap-2 font-extrabold text-slate-950"><i class="bx bx-category text-xl text-emerald-600" aria-hidden="true"></i>Danh mục bài viết</h2>
                <div class="mt-4 grid gap-1">
                    @foreach ($sidebarCategories as $category)
                        <a @class(['flex items-center justify-between gap-3 rounded-[5px] px-3 py-2.5 text-sm font-semibold transition', 'bg-emerald-50 text-emerald-800' => $post->category?->id === $category->id, 'text-slate-600 hover:bg-slate-50 hover:text-emerald-700' => $post->category?->id !== $category->id]) href="{{ route('seo.category', $category->slug) }}"><span class="min-w-0 truncate">{{ $category->name }}</span><span class="rounded-full bg-white px-2 py-0.5 text-xs text-slate-500">{{ $category->posts_count }}</span></a>
                    @endforeach
                </div>
            </section>
        </aside>
    </div>

    @if ($relatedPosts->isNotEmpty())
        <section class="mt-10 border-t border-slate-200 pt-8" aria-labelledby="related-posts-title">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-sm font-bold text-emerald-700">Đọc tiếp</p>
                    <h2 id="related-posts-title" class="mt-1 text-2xl font-extrabold text-slate-950">Bài viết cùng danh mục</h2>
                </div>
                @if ($post->category)
                    <a class="inline-flex items-center gap-1 text-sm font-bold text-emerald-700 hover:text-emerald-800" href="{{ route('seo.category', $post->category->slug) }}">Xem tất cả<i class="bx bx-right-arrow-alt text-lg" aria-hidden="true"></i></a>
                @endif
            </div>
            <div class="mt-5 grid gap-5 md:grid-cols-3">
                @foreach ($relatedPosts as $relatedPost)
                    <article class="client-card group overflow-hidden">
                        <a class="block overflow-hidden bg-slate-100" href="{{ $relatedPost['url'] }}">
                            @if ($relatedPost['cover_image'])
                                <img class="aspect-video w-full object-cover transition duration-300 group-hover:scale-[1.02]" src="{{ $relatedPost['cover_image'] }}" alt="{{ $relatedPost['title'] }}" loading="lazy">
                            @else
                                <span class="grid aspect-video place-items-center text-3xl text-slate-300"><i class="bx bx-news" aria-hidden="true"></i></span>
                            @endif
                        </a>
                        <div class="p-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-emerald-700">{{ $relatedPost['category_name'] ?: 'Hướng dẫn' }}</p>
                            <h3 class="mt-2 line-clamp-2 font-extrabold leading-6 text-slate-950"><a class="transition hover:text-emerald-700" href="{{ $relatedPost['url'] }}">{{ $relatedPost['title'] }}</a></h3>
                            <p class="mt-3 text-xs text-slate-400">{{ $relatedPost['published_label'] }} · {{ $relatedPost['reading_minutes'] }} phút đọc</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</section>
@endsection
