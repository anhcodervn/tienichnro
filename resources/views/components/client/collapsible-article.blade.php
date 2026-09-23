@props(['contentId'])

<div {{ $attributes->merge(['class' => 'seo-collapsible']) }} data-seo-collapsible data-expanded="false">
    <div class="relative">
        <div id="{{ $contentId }}" class="seo-collapsible-content" data-seo-collapsible-content>
            {{ $slot }}
        </div>
        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-white via-white/95 to-transparent" data-seo-collapsible-fade hidden></div>
    </div>
    <button
        type="button"
        class="mx-auto mt-4 flex min-h-11 items-center justify-center gap-2 rounded-[8px] border border-emerald-300 bg-white px-5 py-2.5 text-sm font-bold text-emerald-700 shadow-sm transition hover:border-emerald-500 hover:bg-emerald-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2"
        aria-controls="{{ $contentId }}"
        aria-expanded="false"
        data-seo-collapsible-toggle
        hidden
    >
        <span data-seo-collapsible-label>Xem thêm</span>
        <i class="bx bx-chevron-down text-xl transition-transform" aria-hidden="true" data-seo-collapsible-icon></i>
    </button>
</div>
