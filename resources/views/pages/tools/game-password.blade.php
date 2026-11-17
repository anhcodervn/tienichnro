@extends('client.layouts.app')
@section('title', 'Đổi mật khẩu game Ngọc Rồng Online')
@section('description', 'Đổi mật khẩu tài khoản Ngọc Rồng Online đơn lẻ hoặc hàng loạt, xử lý nền và theo dõi lịch sử.')
@section('canonical', route('tools.game-password'))
@section('tool_title', 'Đổi mật khẩu game Ngọc Rồng Online')
@section('tool_description', 'Nhập tài khoản, chọn server và mật khẩu mới. Yêu cầu được xử lý trong nền, không cần giữ trang mở.')
@section('content')
    @if(!$service['is_enabled'])
        <x-client.service-maintenance :message="$service['maintenance_message']" />
    @else
        <div class="grid min-w-0 grid-cols-1 gap-5" data-game-password>
            @if(session('status'))<p class="rounded-[10px] bg-emerald-50 p-4 text-sm text-emerald-800" role="status">Đã nhận yêu cầu và đưa vào hàng đợi xử lý.</p>@endif
            @auth
                <form method="POST" action="{{ route('tools.game-password.store') }}" class="client-card p-4 sm:p-6" data-password-form data-price="{{ $configuration['price'] }}">
                    @csrf
                    <div class="mb-5 grid min-w-0 gap-3 rounded-[10px] border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2" data-password-captcha data-saved="{{ ($captchaProfile['saved'] ?? false) ? 'true' : 'false' }}" data-save-url="{{ route('tools.game-password.captcha.save') }}" data-refresh-url="{{ route('tools.game-password.captcha.refresh') }}">
                        <p class="text-xs leading-5 text-slate-600 sm:col-span-2">Giải captcha qua <strong>giaicaptcha.vn</strong> bằng khóa của bạn. Nhập cả API Key và API Secret; phí captcha do dịch vụ trừ trên tài khoản của bạn.</p>
                        <label class="grid min-w-0 gap-2 text-sm font-semibold">API Key<input name="captcha_api_key" type="password" class="client-input !mt-0 disabled:bg-slate-100" placeholder="{{ ($captchaProfile['saved'] ?? false) ? '***' : 'ak_xxx' }}" maxlength="512" autocomplete="off" spellcheck="false" @disabled($captchaProfile['saved'] ?? false) @required(!($captchaProfile['saved'] ?? false))></label>
                        <label class="grid min-w-0 gap-2 text-sm font-semibold">API Secret<input name="captcha_api_secret" type="password" class="client-input !mt-0 disabled:bg-slate-100" placeholder="{{ ($captchaProfile['saved'] ?? false) ? '***' : 'sk_xxx' }}" maxlength="512" autocomplete="off" spellcheck="false" @disabled($captchaProfile['saved'] ?? false) @required(!($captchaProfile['saved'] ?? false))></label>
                        <div class="flex flex-wrap items-center gap-2 sm:col-span-2">
                            <button type="button" data-password-captcha-save class="client-button !min-h-9 !px-3 !text-xs" @if($captchaProfile['saved'] ?? false) hidden @endif>Lưu API / Secret</button>
                            <button type="button" data-password-captcha-edit class="client-button-secondary !min-h-9 !px-3 !text-xs" @unless($captchaProfile['saved'] ?? false) hidden @endunless>Đổi khóa</button>
                            <button type="button" data-password-captcha-cancel class="client-button-secondary !min-h-9 !px-3 !text-xs" hidden>Hủy</button>
                            <button type="button" data-password-captcha-refresh class="client-button-secondary !min-h-9 !px-3 !text-xs" @unless($captchaProfile['saved'] ?? false) hidden @endunless>Làm mới số dư</button>
                        </div>
                        <p data-password-captcha-profile class="break-words text-sm font-semibold text-emerald-700 sm:col-span-2" @unless($captchaProfile['saved'] ?? false) hidden @endunless>@if($captchaProfile['saved'] ?? false){{ $captchaProfile['username'] }} • {{ number_format((float) $captchaProfile['balance'], 0, ',', '.') }} đ@endif</p>
                        <p data-password-captcha-message role="status" class="text-xs leading-5 text-slate-500 sm:col-span-2">Khóa lưu mã hóa theo tài khoản đăng nhập; không cần nhập lại ở lần sau. Số dư hiển thị ở lần kiểm tra gần nhất.</p>
                    </div>
                    <input type="hidden" name="request_id" value="{{ old('request_id', (string) Illuminate\Support\Str::uuid()) }}">
                    <input type="hidden" name="price" value="{{ $configuration['price'] }}">
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                        <div><h2 class="text-base font-bold">Thêm yêu cầu đổi mật khẩu</h2><p class="mt-1 text-xs text-slate-500">{{ $configuration['price'] === null ? 'Chưa thiết lập giá' : number_format($configuration['price'], 0, ',', '.').' đ / tài khoản' }}</p></div>
                        <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-600"><input type="checkbox" name="bulk" value="1" @checked(old('bulk')) class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" data-password-bulk-toggle>Nhập hàng loạt</label>
                    </div>
                    @if($errors->any())<div class="mb-4 rounded-[10px] bg-rose-50 p-3 text-sm text-rose-700" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
                    @if($configuration['price'] === null)<p class="mb-4 rounded-[10px] bg-amber-50 p-3 text-sm text-amber-800">Công cụ chưa được cấu hình đầy đủ. Vui lòng quay lại sau.</p>@endif
                    <fieldset class="grid w-full min-w-0 max-w-full grid-cols-1 gap-4" @disabled($configuration['price'] === null)>
                        <div class="grid min-w-0 grid-cols-1 items-start gap-4 sm:grid-cols-2 xl:grid-cols-4" data-password-single>
                            <label class="grid min-w-0 content-start gap-2 text-sm font-semibold"><span>Tài khoản</span><input name="username" class="client-input !mt-0" value="{{ old('username') }}" placeholder="Nhập tài khoản game" maxlength="100" autocomplete="off" required></label>
                            <div class="grid min-w-0 content-start gap-2 text-sm font-semibold">
                                <label for="game-password-old">Mật khẩu cũ</label>
                                <div class="relative min-w-0">
                                    <input id="game-password-old" name="old_password" type="password" class="client-input !mt-0 pr-12" placeholder="Nhập mật khẩu hiện tại" maxlength="200" autocomplete="new-password" required>
                                    <button type="button" class="group absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-[10px] text-slate-600 hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600" data-password-visibility="game-password-old" data-password-label="mật khẩu cũ" aria-controls="game-password-old" aria-label="Hiện mật khẩu cũ" aria-pressed="false" title="Hiện mật khẩu cũ">
                                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="hidden group-aria-pressed:block" d="m3 3 18 18"/></svg>
                                    </button>
                                </div>
                            </div>
                            <label class="grid min-w-0 content-start gap-2 text-sm font-semibold"><span>Server</span><select name="server_code" class="client-input !mt-0" required><option value="">Chọn server</option>@foreach($servers as $server)<option value="{{ $server->server_code }}" @selected((string) old('server_code') === (string) $server->server_code)>{{ $server->name }} · {{ $server->server_code }}</option>@endforeach</select></label>
                            <div class="grid min-w-0 content-start gap-2 text-sm font-semibold">
                                <label for="game-password-new">Mật khẩu mới</label>
                                <div class="relative min-w-0">
                                    <input id="game-password-new" name="new_password" type="password" class="client-input !mt-0 pr-12" placeholder="Mật khẩu mới từ 6 ký tự" minlength="6" maxlength="200" autocomplete="new-password" required>
                                    <button type="button" class="group absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-[10px] text-slate-600 hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600" data-password-visibility="game-password-new" data-password-label="mật khẩu mới" aria-controls="game-password-new" aria-label="Hiện mật khẩu mới" aria-pressed="false" title="Hiện mật khẩu mới">
                                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="hidden group-aria-pressed:block" d="m3 3 18 18"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="grid min-w-0 grid-cols-1 gap-2" data-password-bulk hidden>
                            <label for="password-accounts" class="text-sm font-semibold">Danh sách tài khoản</label>
                            <textarea id="password-accounts" name="accounts_text" rows="7" maxlength="100000" class="client-input font-mono text-sm" placeholder="taikhoan|matkhaucu|3|matkhaumoi" autocomplete="off" spellcheck="false" disabled></textarea>
                            <p class="text-xs leading-5 text-slate-500">Mỗi dòng: <code>tk|mkc|sv|mkm</code>. Server dùng số hoặc sv3. Tối đa 100 tài khoản / lần gửi. Mật khẩu mới tối thiểu 6 ký tự.</p>
                        </div>
                        <div class="flex flex-col gap-4 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-slate-700" aria-live="polite" data-password-total>{{ $configuration['price'] === null ? 'Chưa cấu hình giá' : '1 tài khoản · Tổng phí '.number_format($configuration['price'], 0, ',', '.').' đ' }}</p>
                                <p class="mt-1 text-xs text-slate-500">Phí được trừ khi tiếp nhận yêu cầu.</p>
                            </div>
                            <button class="client-button w-full gap-2 sm:w-auto" type="submit">
                                <i class="bx bx-key text-lg" aria-hidden="true"></i>
                                <span data-password-submit-label>Đổi mật khẩu · 1 tài khoản</span>
                            </button>
                        </div>
                    </fieldset>
                    <p class="mt-4 text-xs leading-5 text-slate-500">Thêm tài khoản sẽ trừ phí và tạo task đổi mật khẩu ngay. Chạy lại miễn phí khi đơn lỗi hoặc pending quá 30 phút, tính từ lần đưa vào hàng chờ gần nhất. Mỗi tài khoản chỉ xử lý một yêu cầu cùng lúc.</p>
                </form>
                <section class="client-card min-w-0 overflow-hidden" data-password-history data-url="{{ route('tools.game-password.history', $filters) }}" data-stream-url="{{ route('tools.game-password.stream', $filters) }}">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-4 sm:px-6"><div><h2 class="text-base font-bold">Lịch sử đổi mật khẩu</h2><p class="mt-1 text-xs text-slate-500">Trạng thái từng tài khoản cập nhật realtime · 20 dòng / trang</p></div><button type="button" class="client-button-secondary !min-h-9 !px-3 !text-xs" data-password-refresh>Làm mới</button></div>
                    <form method="GET" action="{{ route('tools.game-password') }}" class="flex flex-wrap gap-3 border-b border-slate-100 p-4">
                        <input name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" class="client-input min-w-0 flex-1 basis-52" placeholder="Tìm tài khoản" aria-label="Tìm tài khoản">
                        <select name="status" class="client-input !w-auto" aria-label="Trạng thái"><option value="">Tất cả trạng thái</option>@foreach($statuses as $key => $label)<option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>@endforeach</select>
                        <button class="client-button-secondary" type="submit">Lọc</button>
                    </form>
                    <p class="px-4 pt-3 text-xs text-slate-500" role="status" data-password-history-status></p>
                    <div data-password-history-content>@include('pages.tools.partials.game-password-history')</div>
                </section>
                <dialog id="password-log-modal" data-password-log-modal aria-labelledby="password-log-modal-title" class="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-3xl overflow-hidden rounded-[10px] border border-slate-700 bg-slate-950 p-0 text-slate-100 shadow-2xl backdrop:bg-slate-950/60 backdrop:backdrop-blur-sm">
                    <header class="flex items-center justify-between gap-3 border-b border-slate-700 bg-slate-900 px-4 py-3">
                        <div class="flex min-w-0 items-center gap-3"><span class="font-mono text-lg text-emerald-400" aria-hidden="true">&gt;_</span><div class="min-w-0"><h2 id="password-log-modal-title" data-password-log-title class="text-sm font-semibold">Log đổi mật khẩu</h2><p class="mt-1 text-xs text-slate-400">Tiến trình cập nhật realtime</p></div></div>
                        <button type="button" data-password-log-close autofocus class="shrink-0 rounded-[10px] px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400" aria-label="Đóng log">Đóng <span aria-hidden="true">×</span></button>
                    </header>
                    <pre data-password-log-terminal tabindex="0" aria-label="Nội dung log xử lý" class="m-0 h-[55dvh] min-h-40 max-h-[65dvh] overflow-y-auto overscroll-contain whitespace-pre-wrap break-words bg-slate-950 p-4 font-mono text-xs leading-6 text-emerald-300 sm:p-5 sm:text-sm"></pre>
                </dialog>
            @else
                <div class="client-card p-6 text-sm text-slate-600">Đăng nhập để đổi mật khẩu và theo dõi lịch sử. <a href="{{ route('login') }}" class="font-semibold text-emerald-700">Đăng nhập</a></div>
            @endauth
        </div>
    @endif
@endsection
