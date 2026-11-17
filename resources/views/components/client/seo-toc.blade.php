@props(['id'])

<nav id="{{ $id }}-toc" class="absolute bottom-20 right-4 z-20 max-h-64 w-72 max-w-[calc(100%-2rem)] overflow-y-auto rounded-[10px] border border-slate-200 bg-white p-4 shadow-lg sm:right-7" aria-label="Mục lục bài viết" data-seo-toc hidden>
    <p class="mb-3 text-sm font-bold text-slate-900">Mục lục</p>
    <ol class="grid gap-2 text-sm" data-seo-toc-list></ol>
</nav>
<button type="button" class="absolute bottom-7 right-5 grid h-11 w-11 place-items-center rounded-[10px] bg-slate-200 text-xl text-slate-600 transition hover:bg-slate-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600 sm:right-7" aria-label="Mở mục lục" aria-expanded="false" aria-controls="{{ $id }}-toc" data-seo-toc-toggle hidden><i class="bx bx-list-ul" aria-hidden="true"></i></button>
