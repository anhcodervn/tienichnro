@props(['seo', 'embedded' => false])

@if ($seo && ($seo['show_article'] ?? true))
    <article @class(['min-w-0', 'client-container pb-8 sm:pb-12' => ! $embedded]) aria-labelledby="tool-seo-title" data-tool-guide>
        <x-client.seo-article :title="$seo['title']" id="tool-seo" :heading-level="$embedded ? 2 : 1">
            @if ($seo['excerpt'])
                <p class="text-sm leading-7 text-slate-600">{{ $seo['excerpt'] }}</p>
            @endif
            @if ($seo['cover_image'])
                <img class="mt-5 h-auto max-h-80 w-full rounded-[10px] border border-slate-200 bg-slate-50 object-contain" src="{{ $seo['cover_image'] }}" alt="{{ $seo['cover_alt'] }}" loading="lazy">
            @endif
            <div lang="{{ $seo['language'] }}" data-client-image-viewer>{{ $seo['content_html'] }}</div>
        </x-client.seo-article>
    </article>
@endif
