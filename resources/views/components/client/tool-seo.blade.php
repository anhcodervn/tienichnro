@props(['seo', 'embedded' => false])

@if ($seo)
    <article @class(['min-w-0', 'client-container pb-8 sm:pb-12' => ! $embedded]) aria-labelledby="tool-seo-title" data-tool-guide>
        <div class="client-card mx-auto max-w-4xl p-4 sm:p-6">
            @if ($embedded)
                <h2 id="tool-seo-title" class="text-xl font-bold text-slate-900">Hướng dẫn và thông tin</h2>
            @else
                <h1 id="tool-seo-title" class="text-2xl font-extrabold text-slate-900">{{ $seo['title'] }}</h1>
            @endif
            @if (! $embedded && $seo['excerpt'])
                <p class="mt-3 text-sm leading-7 text-slate-600">{{ $seo['excerpt'] }}</p>
            @endif
            @if ($seo['cover_image'])
                <img class="mt-5 h-auto max-h-80 w-full rounded-[10px] border border-slate-200 bg-slate-50 object-contain" src="{{ $seo['cover_image'] }}" alt="{{ $seo['cover_alt'] }}" loading="lazy">
            @endif
            <div class="article-content mt-6" lang="{{ $seo['language'] }}" data-client-image-viewer>{{ $seo['content_html'] }}</div>
            @if ($seo['faq'])
                <section class="mt-8 grid gap-3" aria-labelledby="tool-seo-faq">
                    <h3 id="tool-seo-faq" class="text-xl font-bold text-slate-900">Câu hỏi thường gặp</h3>
                    @foreach ($seo['faq'] as $item)
                        <details class="rounded-lg border border-slate-200 p-4">
                            <summary class="cursor-pointer font-semibold text-slate-900">{{ $item['question'] }}</summary>
                            <p class="mt-3 whitespace-pre-line text-sm leading-7 text-slate-600">{{ $item['answer'] }}</p>
                        </details>
                    @endforeach
                </section>
            @endif
        </div>
    </article>
@endif
