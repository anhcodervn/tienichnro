@extends('client.layouts.app')
@section('title', 'Xác minh tài khoản game Ngọc Rồng Online')
@section('description', 'Lấy cú pháp xác minh tài khoản NRO, chọn danh sách và mở SMS tới 6020 trên điện thoại.')
@section('canonical', route('tools.game-verification'))
@section('tool_title', 'Xác minh tài khoản Ngọc Rồng Online')
@section('tool_description', 'Lấy cú pháp SMS theo từng tài khoản, chọn nhiều dòng để sao chép hoặc mở tin nhắn tới 6020.')
@section('content')
@if(!$service['is_enabled'])
    <x-client.service-maintenance :message="$service['maintenance_message']" />
@else
<div class="grid min-w-0 gap-5">
    @if(session('status'))<p class="rounded-[10px] bg-emerald-50 p-4 text-sm text-emerald-800" role="status">Đã thêm yêu cầu vào hàng chờ.</p>@endif
    @auth
    <form method="POST" action="{{ route('tools.game-verification.store') }}" class="client-card p-4 sm:p-6" data-verification-form data-price="{{ $configuration['price'] }}">
        @csrf
        @include('pages.tools.partials.game-verification-captcha')
        <input type="hidden" name="request_id" value="{{ old('request_id', (string) Illuminate\Support\Str::uuid()) }}">
        <input type="hidden" name="price" value="{{ $configuration['price'] }}">
        <header class="mb-5 flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-base font-bold">Thêm tài khoản lấy cú pháp</h2><p class="mt-1 text-xs text-slate-500">{{ $configuration['price'] === null ? 'Chưa thiết lập phí' : number_format($configuration['price'], 0, ',', '.').' đ / tài khoản' }}</p></div><label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="bulk" value="1" data-verification-bulk-toggle @checked(old('bulk'))>Nhập hàng loạt</label></header>
        @if($errors->any())<div role="alert" class="mb-4 rounded-[10px] bg-rose-50 p-3 text-sm text-rose-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <fieldset class="grid min-w-0 gap-4" @disabled($configuration['price'] === null)>
            <div class="grid min-w-0 items-start gap-4 sm:grid-cols-3" data-verification-single>
                <label class="grid min-w-0 gap-2 text-sm font-semibold">Tài khoản<input name="username" value="{{ old('username') }}" class="client-input !mt-0" placeholder="Nhập tài khoản game" maxlength="100" autocomplete="off" required></label>
                <div class="grid min-w-0 gap-2 text-sm font-semibold"><label for="verification-password">Mật khẩu</label><div class="relative min-w-0"><input id="verification-password" name="old_password" type="password" class="client-input !mt-0 pr-12" placeholder="Nhập mật khẩu game" maxlength="200" autocomplete="new-password" required><button type="button" data-verification-visibility="verification-password" data-verification-label="mật khẩu" aria-label="Hiện mật khẩu" aria-pressed="false" class="group absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-[10px] text-slate-600"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="hidden group-aria-pressed:block" d="m3 3 18 18"/></svg></button></div></div>
                <label class="grid min-w-0 gap-2 text-sm font-semibold">Server<select name="server_code" class="client-input !mt-0" required><option value="">Chọn server</option>@foreach($servers as $server)<option value="{{ $server->server_code }}" @selected((string) old('server_code') === (string) $server->server_code)>{{ $server->name }} · {{ $server->server_code }}</option>@endforeach</select></label>
            </div>
            <div class="grid min-w-0 gap-2" data-verification-bulk hidden><label for="verification-accounts" class="text-sm font-semibold">Danh sách tài khoản</label><textarea id="verification-accounts" name="accounts_text" rows="7" class="client-input font-mono text-sm" maxlength="100000" placeholder="taikhoan|matkhau|3" autocomplete="off" spellcheck="false" disabled></textarea><p class="text-xs text-slate-500">Mỗi dòng: tk|mk|sv. Tối đa 100 tài khoản / lần gửi.</p></div>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4"><p data-verification-total class="text-sm font-semibold"></p><button type="submit" class="client-button gap-2"><span data-verification-submit-label>Lấy cú pháp xác minh</span></button></div>
        </fieldset>
        <p class="mt-4 text-xs leading-5 text-slate-500">Phí thu một lần khi thêm tài khoản. Đơn lỗi hoặc chờ quá 30 phút được chạy lại miễn phí. Khóa captcha dùng từ tài khoản giaicaptcha.vn của bạn.</p>
    </form>
    <section class="client-card min-w-0 overflow-hidden" data-verification-history data-url="{{ route('tools.game-verification.history', $filters) }}" data-stream-url="{{ route('tools.game-verification.stream', $filters) }}" data-export-url="{{ route('tools.game-verification.export') }}">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-4"><div><h2 class="text-base font-bold">Danh sách tài khoản</h2><p class="mt-1 text-xs text-slate-500">20 dòng / trang · chọn dòng đã lấy cú pháp để thao tác</p></div><button type="button" data-verification-refresh class="client-button-secondary !min-h-9 !text-xs">Làm mới</button></header>
        <form method="GET" class="flex flex-wrap gap-3 border-b border-slate-100 p-4"><input name="q" value="{{ $filters['q'] ?? '' }}" class="client-input min-w-0 flex-1 basis-52" placeholder="Tìm tài khoản" maxlength="100" aria-label="Tìm tài khoản"><select name="status" class="client-input !w-auto" aria-label="Trạng thái"><option value="">Tất cả trạng thái</option>@foreach($statuses as $value=>$label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select><button class="client-button-secondary">Lọc</button></form>
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 p-4" data-verification-actions><span class="text-xs text-slate-500" data-verification-selected>0 tài khoản đã chọn</span><button type="button" data-verification-export="sms" class="client-button-secondary !min-h-9 !text-xs">Copy cú pháp</button><button type="button" data-verification-sms class="client-button !min-h-9 !text-xs">Gửi tin nhắn</button></div>
        <p data-verification-history-status role="status" class="px-4 pt-3 text-xs text-slate-500"></p>
        <div data-verification-history-content>@include('pages.tools.partials.game-verification-history')</div>
    </section>
    <dialog id="verification-sms-modal" data-verification-sms-modal aria-labelledby="verification-sms-title" class="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-2xl overflow-y-auto rounded-[10px] border border-slate-200 bg-white p-0 shadow-2xl backdrop:bg-slate-950/60"><header class="flex items-center justify-between gap-3 border-b border-slate-200 p-4"><div><h2 id="verification-sms-title" class="font-bold">Gửi SMS xác minh tới 6020</h2><p class="mt-1 text-xs text-slate-500">Mỗi nút mở ứng dụng tin nhắn với cú pháp điền sẵn. Bạn bấm gửi trên điện thoại; cước theo nhà mạng.</p></div><button type="button" data-verification-sms-close class="client-button-secondary !min-h-9 !px-3" aria-label="Đóng danh sách SMS">×</button></header><div data-verification-sms-list class="grid gap-3 p-4"></div></dialog>
    <dialog id="verification-log-modal" data-verification-log-modal aria-labelledby="verification-log-title" class="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-3xl overflow-hidden rounded-[10px] border border-slate-700 bg-slate-950 p-0 text-slate-100 shadow-2xl backdrop:bg-slate-950/60"><header class="flex items-center justify-between gap-3 border-b border-slate-700 bg-slate-900 p-4"><h2 id="verification-log-title" data-verification-log-title class="text-sm font-semibold">Log lấy cú pháp</h2><button type="button" data-verification-log-close autofocus class="rounded-[10px] px-3 py-2 text-sm text-slate-300" aria-label="Đóng log">Đóng ×</button></header><pre data-verification-log-terminal tabindex="0" class="m-0 h-[55dvh] overflow-y-auto overscroll-contain whitespace-pre-wrap break-words p-4 font-mono text-xs leading-6 text-emerald-300"></pre></dialog>
    @else
    <div class="client-card p-6 text-sm text-slate-600">Đăng nhập để lấy cú pháp xác minh và quản lý tài khoản. <a href="{{ route('login') }}" class="font-semibold text-emerald-700">Đăng nhập</a></div>
    @endauth
</div>
@endif
@endsection
