@forelse($notifies as $notify)
    @php
        $isBoss = $notify->isBoss();
        $hasLifecycle = $notify->boss_id !== null || $notify->boss_name !== null;
        $state = $isBoss ? ($notify->death_time ? 'dead' : ($hasLifecycle ? 'living' : 'global')) : 'notification';
    @endphp
    <article data-notify-id="{{ $notify->id }}" data-notify-state="{{ $state }}" role="listitem" @class([
        'min-w-0 rounded-xl border border-l-4 px-4 py-4 text-sm leading-6 shadow-sm sm:px-5',
        'border-emerald-500 bg-emerald-50' => $state === 'living',
        'border-rose-500 bg-rose-50' => $state === 'dead',
        'border-amber-500 bg-amber-50' => $state === 'global',
        'border-sky-500 bg-sky-50' => $state === 'notification',
    ])>
        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
            <h2 @class([
                'break-words text-lg font-extrabold leading-6',
                'text-emerald-900' => $state === 'living',
                'text-rose-900' => $state === 'dead',
                'text-amber-900' => $state === 'global',
                'text-sky-900' => $state === 'notification',
            ])>{{ $isBoss ? 'Boss: ' : 'Thông báo: ' }}{{ $notify->boss_name ?? $notify->boss?->name ?? $notify->code->name }}</h2>
            <span class="shrink-0 rounded-full border border-slate-300 bg-white px-2.5 py-0.5 text-xs font-bold text-slate-800">{{ $notify->server->name }}</span>
        </div>
        <div class="mt-2 flex flex-wrap gap-2">
            <span @class([
                'rounded-full px-3 py-1 text-xs font-bold text-white',
                'bg-emerald-700' => $state === 'living',
                'bg-rose-700' => $state === 'dead',
                'bg-amber-700' => $state === 'global',
                'bg-sky-700' => $state === 'notification',
            ])>{{ match ($state) { 'living' => 'Đã xuất hiện', 'dead' => 'Đã bị tiêu diệt', 'global' => 'Boss global', default => $notify->code->name } }}</span>
            @if($isBoss && $notify->boss_id === null && $state !== 'global')<span class="rounded-[10px] border border-amber-300 bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Boss global</span>@endif
        </div>
        <div class="mt-2 grid gap-1 break-words text-slate-900">
            @if($notify->map_name)<p class="font-semibold text-sky-800">Map: {{ $notify->map_name }}@if($notify->zone_name !== null || $notify->zone !== null) — khu {{ $notify->zone_name ?? $notify->zone }}@endif</p>@endif
            <p class="text-xs leading-5 text-slate-600">{{ $hasLifecycle && $notify->content !== $notify->death_content ? 'Thời gian xuất hiện' : 'Thời gian' }} · <time class="font-medium tabular-nums" datetime="{{ $notify->time_start->toISOString() }}">{{ $notify->time_start->setTimezone('Asia/Ho_Chi_Minh')->format('d/m/Y - H:i:s') }}</time><span data-nro-relative="{{ $notify->time_start->toISOString() }}"></span></p>
            @if($isBoss && $notify->death_time)
                <p class="font-medium text-rose-800">Thời gian chết: <time class="tabular-nums" datetime="{{ $notify->death_time->toISOString() }}">{{ $notify->death_time->setTimezone('Asia/Ho_Chi_Minh')->format('d/m/Y - H:i:s') }}</time><span data-nro-relative="{{ $notify->death_time->toISOString() }}"></span></p>
                <p class="font-semibold text-violet-800">Người tiêu diệt: {{ $notify->killed_by ?: 'Không xác định' }}</p>
                <p class="mt-1 w-fit max-w-full rounded-[10px] border border-amber-300 bg-amber-100 px-3 py-1.5 font-semibold text-amber-900">Hồi sinh: @if($notify->respawn_at){{ $notify->respawn_at->setTimezone('Asia/Ho_Chi_Minh')->format('d/m/Y - H:i:s') }} · <span class="font-bold tabular-nums" data-nro-respawn="{{ $notify->respawn_at->toISOString() }}">Đang tính thời gian…</span>@else Không xác định @endif</p>
            @endif
            @if(!$isBoss || $notify->boss_name === null)<p class="whitespace-pre-wrap font-medium">{{ $notify->content }}</p>@endif
        </div>
        @if($isBoss)
            <details class="mt-2 text-sm leading-5 text-slate-600">
                <summary class="w-fit cursor-pointer font-semibold text-slate-700 hover:text-sky-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-600 focus-visible:ring-offset-2">Chi tiết thông báo</summary>
                <div class="mt-2 grid gap-1">
                    <p class="whitespace-pre-wrap break-words">{{ $notify->content }}</p>
                    @if($notify->death_content)<p class="whitespace-pre-wrap break-words">{{ $notify->death_content }}</p>@endif
                </div>
            </details>
        @endif
    </article>
@empty
    <p class="p-10 text-center text-sm text-slate-500">Chưa có thông báo phù hợp. Dữ liệu sẽ xuất hiện khi bộ thu thập gửi thông báo game.</p>
@endforelse
