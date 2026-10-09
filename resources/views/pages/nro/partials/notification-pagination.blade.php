<nav aria-label="Phân trang thông báo" class="flex flex-wrap items-center justify-between gap-4 text-sm">
    <p class="text-slate-600">Trang {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }} · Tổng {{ $paginator->total() }} thông báo</p>
    @if($paginator->hasPages() || $paginator->currentPage() > 1)
        <div class="flex flex-wrap items-center gap-2">
            @if($paginator->onFirstPage())
                <span aria-disabled="true" class="inline-flex min-h-11 items-center rounded-[10px] border border-slate-200 px-3 text-slate-400">Trước</span>
            @else
                <a rel="prev" href="{{ $paginator->previousPageUrl() }}" class="inline-flex min-h-11 items-center rounded-[10px] border border-slate-300 bg-white px-3 text-slate-700 hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-emerald-600">Trước</a>
            @endif
            @foreach($elements as $element)
                @if(is_string($element))
                    <span class="px-1 text-slate-500">{{ $element }}</span>
                @else
                    @foreach($element as $page => $url)
                        @if($page == $paginator->currentPage())
                            <span aria-current="page" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-[10px] border border-emerald-700 bg-emerald-700 px-3 font-semibold text-white">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" aria-label="Trang {{ $page }}" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-[10px] border border-slate-300 bg-white px-3 text-slate-700 hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-emerald-600">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
            @if($paginator->hasMorePages())
                <a rel="next" href="{{ $paginator->nextPageUrl() }}" class="inline-flex min-h-11 items-center rounded-[10px] border border-slate-300 bg-white px-3 text-slate-700 hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-emerald-600">Sau</a>
            @else
                <span aria-disabled="true" class="inline-flex min-h-11 items-center rounded-[10px] border border-slate-200 px-3 text-slate-400">Sau</span>
            @endif
        </div>
    @endif
</nav>
