@props(['title', 'code', 'copyable' => false])

<div class="min-w-0 overflow-hidden rounded-[5px] border border-slate-800 bg-slate-950">
    <div class="flex items-center justify-between gap-3 border-b border-white/10 px-3 py-2">
        <span class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $title }}</span>
        @if ($copyable)
            <button class="inline-flex min-h-8 items-center gap-1.5 rounded-[5px] border border-white/10 px-2.5 text-xs font-bold text-slate-200 transition hover:bg-white/10" type="button" data-copy="{{ $code }}"><i class="bx bx-copy" aria-hidden="true"></i>Sao chép</button>
        @endif
    </div>
    <pre class="max-h-[34rem] overflow-auto p-4 text-xs leading-6 text-slate-200"><code>{{ $code }}</code></pre>
</div>
