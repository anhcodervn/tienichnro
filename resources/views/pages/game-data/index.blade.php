@extends('client.layouts.app')
@section('title', 'Dữ liệu game Ngọc Rồng Online')
@section('description', 'Tra cứu vật phẩm, bản đồ, NPC, quái vật và kỹ năng Ngọc Rồng Online.')
@section('canonical', route('nro.game-data.index'))
@section('robots', request()->query() ? 'noindex,follow' : 'index,follow')
@section('content')
<section class="client-container min-w-0 py-6 sm:py-10" data-game-data-page>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="mb-2 text-xs font-extrabold uppercase tracking-[0.18em] text-emerald-700">Ngọc Rồng Online</p>
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-950 sm:text-4xl">Dữ liệu game</h1>
            <p class="mt-2 text-sm leading-6 text-slate-500">Tra cứu thông tin trong game, từ vật phẩm đến từng cấp kỹ năng.</p>
        </div>
        <span class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-800"><span class="size-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>{{ number_format($totalEntries) }} dữ liệu</span>
    </div>
    <div class="grid min-w-0 items-start gap-5 lg:grid-cols-[220px_minmax(0,1fr)] lg:gap-6">
        <aside class="client-card hidden min-w-0 p-3 lg:sticky lg:top-6 lg:block">
            <h2 class="px-3 py-2 text-[11px] font-extrabold uppercase tracking-[0.2em] text-emerald-700">Loại dữ liệu</h2>
            <nav class="mt-2 grid gap-1" aria-label="Loại dữ liệu game">
                @foreach($categories as $key => $item)
                    <a href="{{ route('nro.game-data.index', ['type' => $key]) }}" @if($dataset === $key) aria-current="page" @endif @class(['flex min-h-11 items-center gap-2.5 rounded-lg px-3 py-3 text-sm font-semibold transition', 'bg-emerald-50 text-emerald-800' => $dataset === $key, 'text-slate-600 hover:bg-slate-50' => $dataset !== $key])>
                        <i class="bx {{ $item['icon'] }} text-lg" aria-hidden="true"></i><span class="min-w-0 flex-1">{{ $item['name'] }}</span><span class="text-[11px] tabular-nums opacity-70">{{ number_format($item['count']) }}</span>
                    </a>
                @endforeach
            </nav>
        </aside>
        <div class="min-w-0 space-y-4">
            <div class="client-card min-w-0 p-4 sm:p-5">
                <div class="mb-4 flex items-start gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-emerald-50 text-xl text-emerald-700"><i class="bx {{ $category['icon'] }}" aria-hidden="true"></i></span>
                    <div class="min-w-0"><h2 class="text-xl font-extrabold text-slate-950">{{ $category['name'] }}</h2><p class="mt-1 text-xs leading-5 text-slate-500 sm:text-sm">{{ $category['description'] }}</p></div>
                </div>
                <form action="{{ route('nro.game-data.index') }}" method="GET" class="grid min-w-0 gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
                    <label class="grid min-w-0 gap-1.5 text-xs font-bold text-slate-600 sm:col-span-2 lg:hidden">Loại dữ liệu
                        <select name="type" data-game-data-category class="h-11 w-full min-w-0 rounded-[10px] border border-slate-300 bg-white px-3 text-sm font-medium text-slate-900">
                            @foreach($categories as $key => $item)<option value="{{ $key }}" @selected($dataset === $key)>{{ $item['name'] }} ({{ number_format($item['count']) }})</option>@endforeach
                        </select>
                    </label>
                    <label class="relative min-w-0"><span class="sr-only">Tìm theo tên hoặc ID game</span><i class="bx bx-search pointer-events-none absolute left-3 top-3 text-lg text-slate-400" aria-hidden="true"></i><input name="q" value="{{ $filters['q'] ?? '' }}" type="search" maxlength="200" placeholder="Tìm theo tên hoặc ID game…" class="h-11 w-full min-w-0 rounded-[10px] border border-slate-300 bg-white pl-10 pr-3 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"></label>
                    <button type="submit" class="h-11 rounded-[10px] border border-emerald-700 bg-emerald-700 px-5 text-sm font-bold text-white transition hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2">Tìm kiếm</button>
                </form>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3 text-xs text-slate-500"><p>{{ $records->total() ? $records->firstItem().'–'.$records->lastItem() : '0' }} / {{ number_format($records->total()) }} kết quả</p>@if(!empty($filters['q']))<a class="font-semibold text-emerald-700 hover:underline" href="{{ route('nro.game-data.index', ['type' => $dataset]) }}">Xóa bộ lọc</a>@else<p>Bấm ID để sao chép</p>@endif</div>
            </div>
            @if($cards->isEmpty())
                <div class="client-card grid place-items-center gap-3 px-5 py-14 text-center"><span class="grid size-16 place-items-center rounded-2xl bg-slate-50 text-3xl text-slate-400"><i class="bx bx-search-alt" aria-hidden="true"></i></span><h3 class="font-bold text-slate-800">{{ !empty($filters['q']) ? 'Không tìm thấy dữ liệu phù hợp' : 'Chưa có dữ liệu trong danh mục này' }}</h3><p class="max-w-sm text-sm leading-6 text-slate-500">{{ !empty($filters['q']) ? 'Thử tìm bằng tên khác hoặc ID trong game.' : 'Dữ liệu sẽ xuất hiện tại đây sau khi được cập nhật.' }}</p></div>
            @else
                <div class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-label="Danh sách dữ liệu game">
                    @foreach($cards as $card)
                        <article class="group flex min-w-0 flex-col rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-emerald-300 hover:shadow-md" data-game-data-card>
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <span @class(['max-w-[65%] truncate rounded-md px-2 py-1 text-[10px] font-extrabold uppercase tracking-wide', 'bg-emerald-50 text-emerald-700' => $card['tone'] === 'emerald', 'bg-cyan-50 text-cyan-700' => $card['tone'] === 'cyan', 'bg-amber-50 text-amber-700' => $card['tone'] === 'amber', 'bg-violet-50 text-violet-700' => $card['tone'] === 'violet', 'bg-slate-100 text-slate-600' => $card['tone'] === 'slate'])>{{ $card['badge'] }}</span>
                                <button type="button" data-game-data-copy-id="{{ $card['game_id'] }}" aria-label="Sao chép ID {{ $card['game_id'] }}" class="inline-flex min-h-8 shrink-0 items-center gap-1 rounded-md bg-cyan-50 px-2 text-[11px] font-bold tabular-nums text-cyan-800 transition hover:bg-cyan-100"><span>ID: {{ $card['game_id'] }}</span><i class="bx bx-copy-alt" aria-hidden="true"></i></button>
                            </div>
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="relative grid size-12 shrink-0 place-items-center overflow-hidden rounded-xl border border-slate-100 bg-slate-50 text-2xl text-slate-400"><i class="bx {{ $card['icon'] }}" aria-hidden="true"></i>@if($card['media'])<img src="{{ $card['media'] }}" alt="" width="48" height="48" loading="lazy" decoding="async" data-game-data-image class="absolute inset-0 size-full object-contain p-1">@endif</span>
                                <div class="min-w-0"><h3 class="break-words text-base font-extrabold leading-6 text-slate-900">{{ $card['name'] }}</h3>@if($card['description'] !== '')<p class="mt-1 line-clamp-2 break-words text-xs leading-5 text-slate-500">{{ $card['description'] }}</p>@endif</div>
                            </div>
                            <dl class="mt-auto grid grid-cols-2 gap-3 pb-3 pt-5">@foreach($card['stats'] as $label => $value)<div class="min-w-0"><dt class="text-[10px] font-medium text-slate-500">{{ $label }}</dt><dd class="mt-1 break-words text-xs font-bold tabular-nums text-slate-800">{{ $value }}</dd></div>@endforeach</dl>
                            <a href="{{ $card['url'] }}" data-game-data-open data-name="{{ $card['name'] }}" aria-label="Xem đầy đủ {{ $card['name'] }}" class="flex min-h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-slate-50 text-xs font-bold text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600">Xem đầy đủ<i class="bx bx-code-curly text-base" aria-hidden="true"></i></a>
                        </article>
                    @endforeach
                </div>
                <div class="min-w-0 pt-2">{{ $records->onEachSide(1)->links() }}</div>
            @endif
        </div>
    </div>
    <p class="sr-only" role="status" aria-live="polite" data-game-data-feedback></p>
    <dialog data-game-data-modal aria-labelledby="game-data-modal-title" class="m-auto max-h-[calc(100dvh-1rem)] w-[calc(100%-1rem)] max-w-3xl overflow-y-auto rounded-2xl border border-slate-200 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-950/60 backdrop:backdrop-blur-sm">
        <header class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-4 sm:px-6"><div class="min-w-0"><p class="text-[10px] font-extrabold uppercase tracking-widest text-emerald-700">Dữ liệu đầy đủ</p><h2 id="game-data-modal-title" data-game-data-modal-title class="mt-1 truncate text-lg font-extrabold">Chi tiết</h2></div><button type="button" data-game-data-close autofocus aria-label="Đóng chi tiết" class="grid size-11 shrink-0 place-items-center rounded-xl border border-slate-200 text-2xl text-slate-500 hover:bg-slate-50"><i class="bx bx-x" aria-hidden="true"></i></button></header>
        <div class="min-w-0 p-4 sm:p-6"><p data-game-data-loading role="status" class="py-12 text-center text-sm text-slate-500">Đang tải dữ liệu…</p><p data-game-data-error hidden role="alert" class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"></p><pre data-game-data-json hidden tabindex="0" aria-label="Thông tin dữ liệu dạng JSON" class="max-h-[60dvh] min-w-0 overflow-auto rounded-xl bg-slate-950 p-4 text-xs leading-6 text-emerald-100 sm:text-sm"></pre></div>
        <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-4 sm:px-6"><a data-game-data-page-link class="text-xs font-semibold text-slate-500 hover:text-emerald-700">Mở trang chi tiết</a><button type="button" data-game-data-copy-json disabled class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-emerald-700 px-4 text-xs font-bold text-white disabled:opacity-40"><i class="bx bx-copy-alt text-base" aria-hidden="true"></i>Sao chép JSON</button></footer>
    </dialog>
</section>
@endsection
