@php
    $selectedType = $notificationTypes->firstWhere('code', $filters['code'] ?? '');
    $additionalFilters = $selectedType?->additional_filters ?? [];
    $limitOptions = collect([10, 25, 50, 100, (int) $limit])->unique()->sort();
@endphp
<form method="GET" action="{{ route('nro.notifies.page') }}" data-nro-filters class="mb-6 rounded-xl border border-slate-300 bg-white p-4 shadow-sm sm:p-5">
    <div class="grid grid-cols-2 items-end gap-3 sm:gap-4 lg:grid-cols-[1fr_1fr_2fr]">
        <label class="min-w-0 text-sm font-semibold text-slate-700">Server
            <select name="server_id" class="mt-2 h-11 w-full min-w-0 rounded-[10px] border border-slate-300 bg-white px-3 text-sm font-normal text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20">
                <option value="">Tất cả server</option>
                @foreach($servers as $server)<option value="{{ $server->id }}" data-server-code="{{ $server->server_code }}" @selected(($filters['server_id'] ?? null) == $server->id || (isset($filters['server_code']) && $filters['server_code'] == $server->server_code))>{{ $server->name }}</option>@endforeach
            </select>
        </label>
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
