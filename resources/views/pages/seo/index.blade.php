@extends('client.layouts.app')
@section('title', $pageMetaTitle)
@section('description', $pageMetaDescription)
@section('canonical', $pageMetaCanonical)
@section('robots', $pageMetaRobots)
@section('content')
@php
    $displayPosts = collect([$featuredPost])->filter()->concat($latestPosts);
@endphp

<section class="border-b border-slate-200 bg-gradient-to-b from-emerald-50/70 to-white">
    <div class="client-container py-8 sm:py-12">
        <nav class="flex flex-wrap items-center gap-2 text-sm text-slate-500" aria-label="Breadcrumb">
            <a class="font-semibold transition hover:text-emerald-700" href="{{ route('home') }}">Trang chủ</a>
            <i class="bx bx-chevron-right text-lg text-slate-300" aria-hidden="true"></i>
            @if ($activeCategory)
                <a class="font-semibold transition hover:text-emerald-700" href="{{ route('seo.index') }}">Tin tức</a>
                <i class="bx bx-chevron-right text-lg text-slate-300" aria-hidden="true"></i>
                <span class="font-bold text-slate-800" aria-current="page">{{ $activeCategory->name }}</span>
            @else
                <span class="font-bold text-slate-800" aria-current="page">Tin tức</span>
            @endif
        </nav>

        <div class="mt-6 max-w-3xl">
            <p class="inline-flex items-center gap-2 text-sm font-bold text-emerald-700"><i class="bx bx-book-open text-lg" aria-hidden="true"></i>{{ $activeCategory?->name ?: 'Cẩm nang Ngọc Rồng Online' }}</p>
            <h1 class="mt-2 break-words text-3xl font-extrabold leading-tight tracking-tight text-slate-950 sm:text-4xl lg:text-5xl">{{ $pageTitle }}</h1>
            <p class="mt-4 break-words text-base leading-7 text-slate-600 sm:text-lg">{{ $pageDescription }}</p>
        </div>

        <nav class="mt-7 flex gap-2 overflow-x-auto pb-2" aria-label="Danh mục bài viết">
            <a @class(['shrink-0 rounded-full border px-4 py-2 text-sm font-bold transition', 'border-emerald-600 bg-emerald-600 text-white' => $activeCategorySlug === '', 'border-slate-200 bg-white text-slate-600 hover:border-emerald-300 hover:text-emerald-700' => $activeCategorySlug !== '']) href="{{ route('seo.index') }}">Tất cả</a>
            @foreach ($categories as $category)
                <a @class(['shrink-0 rounded-full border px-4 py-2 text-sm font-bold transition', 'border-emerald-600 bg-emerald-600 text-white' => $activeCategorySlug === $category->slug, 'border-slate-200 bg-white text-slate-600 hover:border-emerald-300 hover:text-emerald-700' => $activeCategorySlug !== $category->slug]) href="{{ route('seo.category', $category->slug) }}">{{ $category->name }} <span class="opacity-70">({{ $category->posts_count }})</span></a>
            @endforeach
        </nav>
    </div>
</section>

<section class="client-container py-8 sm:py-10">
    <form class="client-card flex min-w-0 flex-col gap-3 p-3 sm:flex-row" method="GET" action="{{ $activeCategory ? route('seo.category', $activeCategory->slug) : route('seo.index') }}" role="search">
        <label class="sr-only" for="seo-post-search">Tìm bài viết</label>
        <div class="relative min-w-0 flex-1">
            <i class="bx bx-search pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-xl text-slate-400" aria-hidden="true"></i>
            <input id="seo-post-search" class="min-h-12 w-full min-w-0 rounded-[5px] border border-slate-300 bg-white pl-11 pr-4 text-base outline-none transition placeholder:text-slate-400 focus:border-emerald-600 focus:ring-4 focus:ring-emerald-100" name="q" value="{{ $search }}" placeholder="Tìm hướng dẫn, mẹo nạp game...">
        </div>
        <button class="client-button w-full gap-2 sm:w-auto" type="submit"><i class="bx bx-search text-lg" aria-hidden="true"></i>Tìm kiếm</button>
    </form>

    <div class="mt-7 grid min-w-0 gap-7 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <main class="min-w-0">
            @if ($search !== '')
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-slate-600">Kết quả cho <strong class="text-slate-950">“{{ $search }}”</strong></p>
                    <a class="text-sm font-bold text-emerald-700 hover:text-emerald-800" href="{{ $activeCategory ? route('seo.category', $activeCategory->slug) : route('seo.index') }}">Xóa tìm kiếm</a>
                </div>
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                @foreach ($displayPosts as $post)
                    <article class="client-card group flex min-w-0 flex-col overflow-hidden transition duration-200 hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md">
                        <a class="block overflow-hidden bg-slate-100" href="{{ $post['url'] }}" tabindex="-1" aria-hidden="true">
                            @if ($post['cover_image'])
                                <img class="aspect-[16/9] w-full object-cover transition duration-300 group-hover:scale-[1.02]" src="{{ $post['cover_image'] }}" alt="{{ $post['title'] }}" loading="lazy">
                            @else
                                <span class="grid aspect-[16/9] place-items-center text-4xl text-slate-300"><i class="bx bx-news" aria-hidden="true"></i></span>
                            @endif
                        </a>
                        <div class="flex flex-1 flex-col p-5">
                            @if ($post['category_slug'])
                                <a class="w-fit rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-emerald-700 transition hover:bg-emerald-100" href="{{ route('seo.category', $post['category_slug']) }}">{{ $post['category_name'] }}</a>
                            @else
                                <span class="w-fit rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-slate-600">Hướng dẫn</span>
                            @endif
                            <h2 class="mt-3 break-words text-xl font-extrabold leading-snug text-slate-950"><a class="transition hover:text-emerald-700" href="{{ $post['url'] }}">{{ $post['title'] }}</a></h2>
                            <p class="mt-3 line-clamp-3 text-sm leading-6 text-slate-600">{{ $post['excerpt'] }}</p>
                            <div class="mt-auto flex items-center justify-between gap-3 pt-5 text-xs font-medium text-slate-400">
                                <span>{{ $post['published_label'] }} · {{ $post['reading_minutes'] }} phút đọc</span>
                                <span class="inline-flex items-center gap-1 font-bold text-emerald-700">Đọc bài<i class="bx bx-right-arrow-alt text-lg" aria-hidden="true"></i></span>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($displayPosts->isEmpty())
                <div class="client-card grid place-items-center gap-3 p-10 text-center text-slate-500">
                    <i class="bx bx-file-find text-5xl text-slate-300" aria-hidden="true"></i>
                    <p>Chưa tìm thấy bài viết phù hợp.</p>
                </div>
            @endif
            <div class="mt-6">{{ $posts->links() }}</div>
        </main>

        <aside class="min-w-0 space-y-5 lg:sticky lg:top-24 lg:self-start">
            <section class="client-card p-5">
                <h2 class="flex items-center gap-2 font-extrabold text-slate-950"><i class="bx bx-category text-xl text-emerald-600" aria-hidden="true"></i>Danh mục</h2>
                <div class="mt-4 grid gap-1">
                    @foreach ($categories as $category)
                        <a @class(['flex items-center justify-between gap-3 rounded-[5px] px-3 py-2.5 text-sm font-semibold transition', 'bg-emerald-50 text-emerald-800' => $activeCategorySlug === $category->slug, 'text-slate-600 hover:bg-slate-50 hover:text-emerald-700' => $activeCategorySlug !== $category->slug]) href="{{ route('seo.category', $category->slug) }}"><span class="min-w-0 truncate">{{ $category->name }}</span><span class="rounded-full bg-white px-2 py-0.5 text-xs text-slate-500">{{ $category->posts_count }}</span></a>
                    @endforeach
                </div>
            </section>

            @if ($popularTags->isNotEmpty())
                <section class="client-card p-5">
                    <h2 class="flex items-center gap-2 font-extrabold text-slate-950"><i class="bx bx-purchase-tag-alt text-xl text-emerald-600" aria-hidden="true"></i>Chủ đề nổi bật</h2>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($popularTags as $tag)
                            <a class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-emerald-300 hover:text-emerald-700" href="{{ route('seo.index', ['q' => $tag]) }}">{{ $tag }}</a>
                        @endforeach
                    </div>
                </section>
            @endif
        </aside>
    </div>
</section>
@endsection
