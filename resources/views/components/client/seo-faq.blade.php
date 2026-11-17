@props(['items', 'id' => 'seo-faq'])

@if(count($items))
    <section {{ $attributes->class(['min-w-0 overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm']) }} aria-labelledby="{{ $id }}" data-seo-faq>
        <header class="flex items-center gap-2 border-b border-emerald-100 bg-emerald-50 px-5 py-4 text-emerald-800"><i class="bx bx-help-circle text-lg text-emerald-600" aria-hidden="true"></i><h2 id="{{ $id }}" class="text-base font-bold">Câu hỏi thường gặp</h2></header>
        <div class="grid gap-3 bg-slate-50/60 p-4 sm:p-5">
            @foreach($items as $item)
                <details class="group rounded-[10px] border border-slate-200 bg-slate-50 shadow-sm open:bg-white">
                    <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-4 px-4 py-3 text-sm font-medium text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-600 [&::-webkit-details-marker]:hidden"><span>{{ $item['question'] }}</span><i class="bx bx-plus shrink-0 text-xl text-slate-500 group-open:hidden" aria-hidden="true"></i><i class="bx bx-minus hidden shrink-0 text-xl text-emerald-600 group-open:block" aria-hidden="true"></i></summary>
                    <p class="whitespace-pre-line border-t border-slate-100 px-4 py-4 text-sm leading-7 text-slate-600">{{ $item['answer'] }}</p>
                </details>
            @endforeach
        </div>
    </section>
@endif
