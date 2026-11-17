@extends('client.layouts.app')
@section('title', 'Ghép ảnh game NRO')
@section('description', 'Thêm tài khoản game và chỉnh ảnh ghép NRO với panel tương tác.')
@section('canonical', route('tools.game-image'))
@section('tool_title', 'Ghép ảnh game NRO')
@section('tool_description', 'Thêm tài khoản → chọn Điều chỉnh → sắp xếp ảnh và lưu bố cục.')
@section('content')
    @if(!$service['is_enabled'])
        <x-client.service-maintenance :message="$service['maintenance_message']" />
    @else
        <div class="grid gap-6">
            @if(session('status'))<p class="rounded-[10px] bg-emerald-50 p-4 text-sm text-emerald-800" role="status">{{ session('status') }}</p>@endif
            @auth
                <form method="POST" action="{{ route('tools.game-image.store') }}" class="client-card grid gap-5 p-4 sm:p-6" data-game-image-account-form>
                    @csrf
                    <div><h2 class="text-lg font-bold">Thêm tài khoản</h2><p class="mt-2 text-sm text-slate-600">Lưu tài khoản trước, sau đó bấm Điều chỉnh trong danh sách bên dưới. Mật khẩu được mã hóa khi lưu.</p></div>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div><label class="client-label" for="capture-username">Tài khoản</label><input class="client-input mt-2" id="capture-username" name="username" value="{{ old('username') }}" autocomplete="off" maxlength="100" required>@error('username')<p class="mt-2 text-sm text-rose-600" role="alert">{{ $message }}</p>@enderror</div>
                        <div><label class="client-label" for="capture-password">Mật khẩu</label><input class="client-input mt-2" id="capture-password" name="password" type="password" autocomplete="new-password" maxlength="200" required>@error('password')<p class="mt-2 text-sm text-rose-600" role="alert">{{ $message }}</p>@enderror</div>
                        <div><label class="client-label" for="capture-server">Server</label><select class="client-input mt-2" id="capture-server" name="server" required><option value="">Chọn server</option>@foreach($servers as $server)<option value="{{ $server->server_code }}" @selected((string) old('server') === (string) $server->server_code)>{{ $server->name }} &middot; {{ $server->server_code }}</option>@endforeach</select>@error('server')<p class="mt-2 text-sm text-rose-600" role="alert">{{ $message }}</p>@enderror</div>
                    </div>
                    @if($capturePrice !== null)
                        <p class="text-sm text-slate-600">Phí ghép ảnh: <strong class="text-emerald-700">{{ number_format($capturePrice, 0, ',', '.') }} đ / tài khoản</strong>. Trừ tiền khi thêm; hoàn lại nếu tool chụp ảnh báo lỗi.</p>
                    @else
                        <p class="text-sm text-amber-700">Công cụ chưa được cấu hình phí ghép ảnh. Vui lòng quay lại sau.</p>
                    @endif
                    @error('balance')<p class="text-sm text-rose-600" role="alert">{{ $message }}</p>@enderror
                    @error('price')<p class="text-sm text-rose-600" role="alert">{{ $message }}</p>@enderror
                    <div><button class="client-button" type="submit" @disabled($capturePrice === null)>Thêm tài khoản</button></div>
                </form>
                <section class="client-card min-w-0 overflow-hidden" aria-labelledby="game-image-accounts-title" data-game-image-accounts data-client-image-viewer data-stream-url="{{ route('tools.game-image.stream', $filters) }}">
                    <header class="border-b border-slate-200 p-4 sm:p-5"><h2 class="text-lg font-bold" id="game-image-accounts-title">Quản lý tài khoản</h2><p class="mt-1 text-xs text-emerald-700" role="status" aria-live="polite" data-game-image-live-status></p><p class="mt-1 text-sm text-slate-500">Xem ảnh ghép đã lưu hoặc tải trọn bộ ảnh panel, detail của từng tài khoản.</p></header>
                    <form method="GET" action="{{ route('tools.game-image') }}" class="flex flex-wrap items-end gap-3 border-b border-slate-100 p-4" role="search" aria-label="Lọc tài khoản ghép ảnh">
                        <label class="grid min-w-0 flex-1 basis-48 gap-1.5 text-xs font-semibold">Tìm tài khoản hoặc server<input name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" class="client-input !mt-0 !min-h-10 !py-2 !text-sm" placeholder="Nhập username hoặc server"></label>
                        <label class="grid gap-1.5 text-xs font-semibold">Trạng thái<select name="status" class="client-input !mt-0 !min-h-10 !py-2 !text-sm"><option value="">Tất cả</option>@foreach(\App\Models\GameImageAccount::STATUS_LABELS as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
                        <button type="submit" class="client-button !min-h-10 !px-4 !py-2">Lọc</button>
                        @if(filled($filters['q'] ?? null) || filled($filters['status'] ?? null))<a href="{{ route('tools.game-image') }}" class="client-button-secondary !min-h-10 !px-3 !py-2">Đặt lại</a>@endif
                    </form>
                    <div data-game-image-account-content>@include('pages.tools.partials.game-image-account-table')</div>
                </section>
                <dialog id="game-image-log-modal" data-game-image-log-modal aria-labelledby="game-image-log-modal-title" class="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-3xl overflow-hidden rounded-[10px] border border-slate-700 bg-slate-950 p-0 text-slate-100 shadow-2xl backdrop:bg-slate-950/60 backdrop:backdrop-blur-sm">
                    <header class="flex items-center justify-between gap-3 border-b border-slate-700 bg-slate-900 px-4 py-3">
                        <div class="flex min-w-0 items-center gap-3"><span class="font-mono text-lg text-emerald-400" aria-hidden="true">&gt;_</span><div class="min-w-0"><h2 id="game-image-log-modal-title" data-game-image-log-title class="text-sm font-semibold">Log chụp ảnh</h2><p class="mt-1 text-xs text-slate-400">Tiến trình cập nhật realtime</p></div></div>
                        <button type="button" data-game-image-log-close autofocus class="shrink-0 rounded-[10px] px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400" aria-label="Đóng log">Đóng <span aria-hidden="true">×</span></button>
                    </header>
                    <pre data-game-image-log-terminal tabindex="0" aria-label="Nội dung log xử lý" class="m-0 h-[55dvh] min-h-40 max-h-[65dvh] overflow-y-auto overscroll-contain whitespace-pre-wrap break-words bg-slate-950 p-4 font-mono text-xs leading-6 text-emerald-300 sm:p-5 sm:text-sm"></pre>
                </dialog>
                <dialog data-game-image-details-modal aria-labelledby="game-image-details-title" class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-[10px] border border-slate-200 bg-white p-0 text-slate-900 shadow-xl backdrop:bg-slate-950/60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                    <form data-game-image-details-form class="grid gap-4 p-5">
                        <div class="flex items-center justify-between gap-3"><h2 id="game-image-details-title" class="font-bold">Sửa thông tin tài khoản</h2><button type="button" data-game-image-details-close aria-label="Đóng" class="rounded-[10px] px-3 py-2">Đóng ×</button></div>
                        <label class="grid gap-2 text-sm font-semibold">Tài khoản<input name="username" required maxlength="100" autocomplete="off" class="client-input !mt-0" /></label>
                        <label class="grid gap-2 text-sm font-semibold">Server<select name="server" required class="client-input !mt-0">@foreach($servers as $server)<option value="{{ $server->server_code }}">{{ $server->name }} · {{ $server->server_code }}</option>@endforeach</select></label>
                        <label class="grid gap-2 text-sm font-semibold">Mật khẩu mới<span class="flex min-w-0 gap-2"><input name="password" type="password" maxlength="200" autocomplete="new-password" placeholder="Để trống để giữ mật khẩu đã lưu" class="client-input !mt-0 min-w-0 flex-1" /><button type="button" data-game-image-details-eye aria-pressed="false" class="client-button-secondary !px-3">Hiện</button></span></label>
                        <p class="text-xs leading-5 text-slate-500">Sửa thông tin không tạo lượt chụp hoặc trừ phí mới. Khi tài khoản đang chụp ảnh, hãy chờ lượt chụp kết thúc trước khi sửa.</p>
                        <p data-game-image-details-error role="alert" class="text-sm text-rose-600"></p>
                        <div class="flex justify-end gap-2"><button type="button" data-game-image-details-close class="client-button-secondary">Huỷ</button><button type="submit" class="client-button">Lưu thay đổi</button></div>
                    </form>
                </dialog>
            @else
                <div class="client-card grid justify-items-start gap-4 p-6"><p>Đăng nhập để lưu tài khoản game và bố cục ảnh của bạn.</p><a class="client-button" href="{{ route('auth.login') }}">Đăng nhập</a></div>
            @endauth
        </div>
    @endif
@endsection
