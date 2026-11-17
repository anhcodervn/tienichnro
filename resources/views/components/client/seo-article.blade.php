@props(['title', 'id' => 'seo-article', 'headingLevel' => 2])

<section id="{{ $id }}" class="relative min-w-0 w-full rounded-[10px] border border-slate-200 bg-white p-5 pb-20 shadow-sm sm:p-7 sm:pb-20" aria-labelledby="{{ $id }}-title" data-seo-article>
    <header class="border-b border-slate-100 pb-5">
        @if((int) $headingLevel === 1)
            <h1 id="{{ $id }}-title" class="text-[22px] font-extrabold text-slate-950">{{ $title }}</h1>
        @else
            <h2 id="{{ $id }}-title" class="text-lg font-extrabold text-slate-950 sm:text-[22px]">{{ $title }}</h2>
        @endif
    </header>
    <div class="relative mt-5" data-seo-viewport>
        <div id="{{ $id }}-content" class="article-content" data-seo-content>{{ $slot }}</div>
        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-white to-transparent" data-seo-fade hidden></div>
    </div>
    <x-client.seo-toc :id="$id" />
    <button type="button" class="absolute -bottom-5 left-1/2 inline-flex min-h-10 -translate-x-1/2 items-center gap-2 whitespace-nowrap rounded-[10px] bg-cyan-600 px-6 text-sm font-semibold text-white shadow-md transition hover:bg-cyan-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600 focus-visible:ring-offset-2" aria-expanded="true" aria-controls="{{ $id }}-content" data-seo-expand hidden><span data-seo-expand-label>Xem thêm</span><i class="bx bx-chevron-down text-lg" aria-hidden="true"></i></button>
</section>
