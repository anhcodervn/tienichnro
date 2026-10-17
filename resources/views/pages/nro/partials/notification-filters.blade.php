@php
    $selectedType = $notificationTypes->firstWhere('code', $filters['code'] ?? '');
    $additionalFilters = $selectedType?->additional_filters ?? [];
    $limitOptions = auth()->check() ? collect([10, 25, 50, 100, (int) $limit])->unique()->sort() : collect([10]);
@endphp
<form method="GET" action="{{ route('nro.notifies.page') }}" data-nro-filters class="mb-6 rounded-xl border border-slate-300 bg-white p-4 shadow-sm sm:p-5">
    <div class="grid grid-cols-2 items-end gap-3 sm:gap-4 lg:grid-cols-[1fr_1fr_2fr]">
        <div class="min-w-0 text-sm font-semibold text-slate-700" data-nro-server-select>
            <label id="nro-server-label" for="nro-server-native">Server</label>
            <select id="nro-server-native" name="server_id" data-nro-server-native class="mt-2 h-11 w-full min-w-0 rounded-[10px] border border-slate-300 bg-white px-3 text-sm font-normal text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20">
                <option value="">Tất cả server</option>
                @foreach($servers as $server)<option value="{{ $server->id }}" data-server-code="{{ $server->server_code }}" @selected(($filters['server_id'] ?? null) == $server->id || (isset($filters['server_code']) && $filters['server_code'] == $server->server_code))>{{ $server->name }} ({{ $server->connection ? 'Hoạt động' : 'Không hoạt động' }})</option>@endforeach
            </select>
            <div hidden data-nro-server-custom class="relative mt-2">
                <button type="button" data-nro-server-trigger aria-haspopup="listbox" aria-expanded="false" aria-controls="nro-server-list" aria-labelledby="nro-server-label nro-server-value nro-server-status" class="flex h-11 w-full items-center gap-2 rounded-[10px] border border-slate-300 bg-white px-3 text-left text-sm font-normal text-slate-900 transition hover:border-emerald-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600/30">
                    <span data-nro-server-dot aria-hidden="true" class="size-2 shrink-0 rounded-full bg-slate-400"></span>
                    <span id="nro-server-value" data-nro-server-value class="min-w-0 flex-1 truncate">Tất cả server</span>
                    <span id="nro-server-status" data-nro-server-status class="sr-only"></span>
                    <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" class="size-4 shrink-0 text-slate-500"><path d="m6 8 4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </button>
                <div hidden id="nro-server-list" data-nro-server-list role="listbox" aria-labelledby="nro-server-label" class="absolute left-0 top-full z-40 mt-2 max-h-80 w-72 max-w-[calc(100vw-3rem)] overflow-y-auto rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl shadow-slate-900/10">
                    <button type="button" role="option" tabindex="-1" aria-selected="false" data-nro-server-option data-value="" data-name="Tất cả server" data-status="" class="flex min-h-11 w-full items-center gap-2.5 rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-700 hover:bg-slate-50 focus:bg-emerald-50 focus:outline-none aria-selected:bg-emerald-50 aria-selected:text-emerald-800">
                        <span aria-hidden="true" class="size-2 shrink-0 rounded-full bg-slate-400"></span>
                        <span class="min-w-0 flex-1 truncate">Tất cả server</span>
                    </button>
                    @foreach($servers as $server)
                        <button type="button" role="option" tabindex="-1" aria-selected="false" data-nro-server-option data-value="{{ $server->id }}" data-name="{{ $server->name }}" data-status="{{ $server->connection ? 'online' : 'offline' }}" class="flex min-h-11 w-full items-center gap-2.5 rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-700 hover:bg-slate-50 focus:bg-emerald-50 focus:outline-none aria-selected:bg-emerald-50 aria-selected:text-emerald-800">
                            <span aria-hidden="true" @class(['size-2 shrink-0 rounded-full', 'bg-emerald-500' => $server->connection, 'bg-rose-500' => !$server->connection])></span>
                            <span class="min-w-0 flex-1 truncate">{{ $server->name }}</span>
                            <span @class(['shrink-0 rounded-full px-2 py-1 text-[10px] font-semibold', 'bg-emerald-50 text-emerald-700' => $server->connection, 'bg-rose-50 text-rose-700' => !$server->connection])>{{ $server->connection ? 'Hoạt động' : 'Không hoạt động' }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
        <label class="min-w-0 text-sm font-semibold text-slate-700">Loại thông báo
            <select name="code" data-nro-type class="mt-2 h-11 w-full min-w-0 rounded-[10px] border border-slate-300 bg-white px-3 text-sm font-normal text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20">
                <option value="" data-additional-filters="[]">Tất cả loại</option>
                @foreach($notificationTypes as $type)<option value="{{ $type->code }}" data-additional-filters="{{ json_encode($type->additional_filters ?? []) }}" @selected(($filters['code'] ?? '') === $type->code)>{{ $type->name }}</option>@endforeach
            </select>
        </label>
        <div data-nro-extra-group @if(!count($additionalFilters)) hidden @endif class="col-span-2 min-w-0 lg:col-span-1">
            <div class="grid grid-cols-2 items-end gap-3">
                <label data-nro-extra="boss" @if(!in_array('boss', $additionalFilters, true)) hidden @endif class="min-w-0 flex-1 text-xs font-medium text-slate-600">Loại boss
                    <select name="boss_id" @disabled(!in_array('boss', $additionalFilters, true)) class="mt-1 h-11 w-full min-w-0 rounded-[10px] border border-slate-300 bg-white px-3 text-sm font-normal text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20">
                        <option value="">Tất cả boss</option>
                        @foreach($bosses as $boss)<option value="{{ $boss->id }}" @selected(($filters['boss_id'] ?? null) == $boss->id)>{{ $boss->name }}</option>@endforeach
                    </select>
                </label>
                <label data-nro-extra="state" @if(!in_array('state', $additionalFilters, true)) hidden @endif class="min-w-0 flex-1 text-xs font-medium text-slate-600">Trạng thái boss
                    <select name="state" @disabled(!in_array('state', $additionalFilters, true)) class="mt-1 h-11 w-full min-w-0 rounded-[10px] border border-slate-300 bg-white px-3 text-sm font-normal text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20">
                        @foreach(['' => 'Tất cả trạng thái', 'living' => 'Đang sống', 'respawning' => 'Sắp hồi sinh', 'history' => 'Lịch sử boss'] as $value => $label)<option value="{{ $value }}" @selected(($filters['state'] ?? '') === $value)>{{ $label }}</option>@endforeach
                    </select>
                </label>
            </div>
        </div>
    </div>
    <div class="mt-3 grid grid-cols-2 items-end gap-3 sm:mt-4 sm:gap-4 sm:border-t sm:border-slate-200 sm:pt-4 sm:grid-cols-[1fr_10rem]">
        <label class="col-span-2 min-w-0 text-sm font-semibold text-slate-700 sm:col-span-1">Từ khóa
            <input name="q" value="{{ $filters['q'] ?? '' }}" type="search" maxlength="200" placeholder="Tên boss, map, người tiêu diệt hoặc nội dung thông báo…" class="mt-2 h-11 w-full rounded-[10px] border border-slate-300 bg-white px-3 text-sm font-normal text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20">
        </label>
        <label class="min-w-0 text-sm font-semibold text-slate-700">Số thông báo
            <select name="limit" class="mt-2 h-11 w-full rounded-[10px] border border-slate-300 bg-white px-3 text-sm font-normal text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20">
                @foreach($limitOptions as $value)<option value="{{ $value }}" @selected($value == $limit)>{{ $value }} thông báo</option>@endforeach
            </select>
        </label>
        <button class="h-11 w-full rounded-[10px] border border-emerald-700 bg-emerald-700 px-3 text-sm font-bold text-white hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 sm:col-span-2 sm:w-auto sm:justify-self-end sm:px-5" type="submit">Lọc thông báo</button>
    </div>
</form>
