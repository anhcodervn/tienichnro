@php
    $characters = $subscription?->characters ?: [['char_name' => '', 'char_server' => $servers->first()?->server_code]];
    $selectedTypes = $subscription?->notification_types ?: ['BOSS', 'SET_ACTIVATION'];
    $inputClass = 'min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900';
@endphp
<div data-personal-fields class="grid gap-4">
    <label class="grid gap-2 text-sm font-semibold">Zalo ID nhận tin<input name="zalo_id" value="{{ $subscription?->zalo_id }}" maxlength="128" class="{{ $inputClass }}" autocomplete="off"></label>
    <div class="rounded-xl bg-amber-50 p-3 text-xs leading-relaxed text-amber-900">API gửi Zalo chưa kết nối. Cấu hình được lưu nhưng hiện chưa gửi tin hoặc tag người dùng.</div>
    <fieldset class="grid gap-3"><legend class="mb-2 text-sm font-bold">Loại thông báo</legend><div class="flex flex-wrap gap-3">
        @foreach($notificationTypes as $type)
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="notification_types" value="{{ $type->code }}" @checked(in_array($type->code, $selectedTypes, true)) class="h-4 w-4 accent-emerald-700">{{ $type->name }}</label>
        @endforeach
    </div></fieldset>
    <div class="grid gap-3"><div class="flex items-center justify-between gap-2"><h3 class="text-sm font-bold">Nhân vật lắng nghe <span data-character-count></span>/10</h3><button type="button" data-add-character class="min-h-10 rounded-lg border border-slate-300 px-3 text-xs font-semibold">+ Nhân vật</button></div>
        <div data-characters class="grid gap-3">
        @foreach($characters as $character)
            <div data-character class="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] gap-2">
                <input aria-label="Tên nhân vật" data-char-name value="{{ $character['char_name'] }}" placeholder="Tên nhân vật" maxlength="100" class="{{ $inputClass }}">
                <select aria-label="Server nhân vật" data-char-server class="{{ $inputClass }}">@foreach($servers as $server)<option value="{{ $server->server_code }}" @selected((int) $character['char_server'] === $server->server_code)>{{ $server->name }}</option>@endforeach</select>
                <button type="button" data-remove-character aria-label="Xoá nhân vật" class="min-h-11 rounded-lg border border-rose-200 px-3 text-rose-700">×</button>
            </div>
        @endforeach
        </div>
        <p class="text-xs leading-relaxed text-slate-500">Mỗi gói cá nhân nhận về một Zalo, tối đa 10 cặp tên nhân vật và server. Boss xuất hiện không có tên nhân vật được lọc theo server đã chọn. Muốn tăng giới hạn, <a href="{{ route('content.contact') }}" class="font-bold text-emerald-700">liên hệ hỗ trợ và trả thêm phí</a>.</p>
    </div>
</div>
<div data-webhook-fields class="grid gap-3">
    <label class="grid gap-2 text-sm font-semibold">URL webhook<input name="webhook_url" value="{{ $subscription?->webhook_url }}" type="url" maxlength="2000" placeholder="https://example.com/webhook" class="{{ $inputClass }}"></label>
    <p class="text-xs leading-relaxed text-slate-500">Nhận toàn bộ sự kiện thông báo của mọi server, tự lọc và xử lý tại hệ thống của bạn. Mỗi lượt tương ứng một sự kiện gửi thành công; gửi lại khi lỗi không tính thêm lượt.</p>
    @if($subscription?->webhook_secret)
    <details class="rounded-xl border border-slate-200 p-3 text-xs"><summary class="cursor-pointer font-bold">Thông tin xác thực webhook</summary><p class="mt-3 break-all font-mono">{{ $subscription->webhook_secret }}</p><p class="mt-3 leading-relaxed">X-NRO-Signature = sha256=HMAC-SHA256(secret, X-NRO-Timestamp + "." + raw body). Dùng X-NRO-Event-ID để bỏ sự kiện trùng khi gửi lại. JSON gồm event_id, event, server_code, code, content, occurred_at và notification.</p></details>
    @endif
</div>
