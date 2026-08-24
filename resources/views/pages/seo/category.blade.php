@extends('client.layouts.app')
@section('title', $pageMetaTitle)
@section('description', $pageMetaDescription)
@section('canonical', $pageMetaCanonical)
@section('robots', $pageMetaRobots)

@section('content')
<section class="border-b border-slate-200 bg-gradient-to-b from-emerald-50/70 to-white">
    <div class="client-container py-8 sm:py-12">
        <nav class="flex min-w-0 items-center gap-1.5 overflow-x-auto whitespace-nowrap pb-2 text-sm text-slate-500" aria-label="Breadcrumb">
            <a class="shrink-0 font-semibold transition hover:text-emerald-700" href="{{ route('home') }}">Trang chủ</a>
            <i class="bx bx-chevron-right shrink-0 text-lg text-slate-300" aria-hidden="true"></i>
            <a class="shrink-0 font-semibold transition hover:text-emerald-700" href="{{ route('seo.index') }}">Bài viết</a>
            <i class="bx bx-chevron-right shrink-0 text-lg text-slate-300" aria-hidden="true"></i>
            <span class="max-w-64 truncate font-bold text-slate-800" aria-current="page">{{ $category->name }}</span>
        </nav>

        <div class="mt-6 grid min-w-0 gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
            <div class="min-w-0 max-w-3xl">
                <p class="inline-flex items-center gap-2 text-sm font-bold text-emerald-700">
                    <i class="bx bx-folder-open text-lg" aria-hidden="true"></i>
                    Danh mục bài viết
                </p>
                <h1 class="mt-2 break-words text-3xl font-extrabold leading-tight tracking-tight text-slate-950 sm:text-4xl lg:text-5xl">{{ $pageTitle }}</h1>
                <p class="mt-4 break-words text-base leading-7 text-slate-600 sm:text-lg">{{ $pageDescription }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-3 rounded-[5px] border border-emerald-200 bg-white px-4 py-3 shadow-sm">
                <span class="grid h-10 w-10 place-items-center rounded-[5px] bg-emerald-50 text-2xl text-emerald-700"><i class="bx bx-news" aria-hidden="true"></i></span>
                <span>
                    <strong class="block text-lg font-extrabold tabular-nums text-slate-950">{{ number_format($posts->total(), 0, ',', '.') }}</strong>
                    <small class="text-xs font-medium text-slate-500">bài viết đã xuất bản</small>
                </span>
            </div>
        </div>

        <nav class="mt-7 flex gap-2 overflow-x-auto pb-2" aria-label="Danh mục bài viết">
            <a class="shrink-0 rounded-[5px] border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-600 transition hover:border-emerald-300 hover:text-emerald-700" href="{{ route('seo.index') }}">Tất cả</a>
            @foreach ($categories as $categoryItem)
                <a
                    @class([
                        'shrink-0 rounded-[5px] border px-4 py-2 text-sm font-bold transition',
                        'border-emerald-600 bg-emerald-600 text-white' => $categoryItem->id === $category->id,
                        'border-slate-200 bg-white text-slate-600 hover:border-emerald-300 hover:text-emerald-700' => $categoryItem->id !== $category->id,
                    ])
                    href="{{ route('seo.category', $categoryItem->slug) }}"
                    @if ($categoryItem->id === $category->id) aria-current="page" @endif
                >{{ $categoryItem->name }} <span class="opacity-75">({{ $categoryItem->posts_count }})</span></a>
            @endforeach
        </nav>
    </div>
</section>

<section class="client-container py-8 sm:py-10">
    <form class="client-card flex min-w-0 flex-col gap-3 p-3 sm:flex-row" method="GET" action="{{ route('seo.category', $category->slug) }}" role="search">
        <label class="sr-only" for="category-post-search">Tìm trong danh mục {{ $category->name }}</label>
        <div class="relative min-w-0 flex-1">
            <i class="bx bx-search pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-xl text-slate-400" aria-hidden="true"></i>
            <input
                id="category-post-search"
                class="min-h-12 w-full min-w-0 rounded-[5px] border border-slate-300 bg-white pl-11 pr-4 text-base outline-none transition placeholder:text-slate-400 focus:border-emerald-600 focus:ring-4 focus:ring-emerald-100"
                name="q"
                value="{{ $search }}"
                maxlength="100"
                placeholder="Tìm bài viết trong {{ $category->name }}..."
            >
        </div>
        <button class="client-button w-full gap-2 sm:w-auto" type="submit"><i class="bx bx-search text-lg" aria-hidden="true"></i>Tìm kiếm</button>
    </form>

    @if ($search !== '')
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-600">Tìm thấy <strong class="text-slate-950">{{ $posts->total() }}</strong> kết quả cho “<strong class="text-slate-950">{{ $search }}</strong>”</p>
            <a class="inline-flex items-center gap-1 text-sm font-bold text-emerald-700 transition hover:text-emerald-800" href="{{ route('seo.category', $category->slug) }}"><i class="bx bx-x text-lg" aria-hidden="true"></i>Xóa tìm kiếm</a>
        </div>
    @endif

    @if ($posts->isNotEmpty())
        <div class="mt-7 grid min-w-0 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($posts as $post)
                <article class="client-card group flex min-w-0 flex-col overflow-hidden transition duration-200 hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md">
                    <a class="block overflow-hidden bg-slate-100" href="{{ $post['url'] }}" tabindex="-1" aria-hidden="true">
                        @if ($post['cover_image'])
                            <img class="aspect-[16/9] w-full object-cover transition duration-300 group-hover:scale-[1.02]" src="{{ $post['cover_image'] }}" alt="" loading="lazy">
                        @else
                            <span class="grid aspect-[16/9] place-items-center bg-gradient-to-br from-emerald-50 to-slate-100 text-4xl text-emerald-300"><i class="bx bx-news" aria-hidden="true"></i></span>
                        @endif
                    </a>
                    <div class="flex min-w-0 flex-1 flex-col p-5">
                        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-slate-500">
                            <span class="rounded-[5px] bg-emerald-50 px-2.5 py-1 font-bold text-emerald-700">{{ $category->name }}</span>
                            <span>{{ $post['published_label'] }}</span>
                        </div>
                        <h2 class="mt-3 break-words text-xl font-extrabold leading-snug text-slate-950">
                            <a class="transition hover:text-emerald-700" href="{{ $post['url'] }}">{{ $post['title'] }}</a>
                        </h2>
                        <p class="mt-3 line-clamp-3 text-sm leading-6 text-slate-600">{{ $post['excerpt'] }}</p>
                        <div class="mt-auto flex items-center justify-between gap-3 pt-5 text-xs font-medium text-slate-400">
                            <span class="inline-flex items-center gap-1"><i class="bx bx-time-five text-base" aria-hidden="true"></i>{{ $post['reading_minutes'] }} phút đọc</span>
                            <span class="inline-flex items-center gap-1 font-bold text-emerald-700">Đọc bài<i class="bx bx-right-arrow-alt text-lg" aria-hidden="true"></i></span>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        @if ($posts->hasPages())
            <nav class="mt-8" aria-label="Phân trang bài viết">
                {{ $posts->onEachSide(1)->links() }}
            </nav>
        @endif
    @else
        <div class="client-card mt-7 grid place-items-center gap-3 p-10 text-center">
            <span class="grid h-16 w-16 place-items-center rounded-[5px] bg-slate-100 text-4xl text-slate-300"><i class="bx bx-file-find" aria-hidden="true"></i></span>
            <div>
                <h2 class="font-extrabold text-slate-950">Chưa tìm thấy bài viết phù hợp</h2>
                <p class="mt-1 text-sm leading-6 text-slate-500">Thử từ khóa khác hoặc xem toàn bộ bài viết trong danh mục này.</p>
            </div>
            @if ($search !== '')
                <a class="client-button-secondary mt-2 gap-2" href="{{ route('seo.category', $category->slug) }}"><i class="bx bx-reset text-lg" aria-hidden="true"></i>Xem toàn bộ</a>
            @endif
        </div>
    @endif
</section>
@endsection
