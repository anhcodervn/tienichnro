@extends('client.layouts.app')
@section('title', 'Giải captcha NRO')
@section('description', 'Demo giải captcha NRO, mua token_captcha và tài liệu tích hợp API cURL.')
@section('canonical', route('tools.captcha'))
@section('tool_title', 'Giải captcha NRO')
@section('tool_description', 'Thử giải captcha miễn phí sau khi xác minh Cloudflare. API tích hợp sử dụng 1 token_captcha / lượt giải thành công.')
@section('content')
<section data-captcha-tool
    data-rates="{{ json_encode($rates) }}"
    data-solve-url="{{ route('tools.captcha.demo') }}"
    data-purchase-url="{{ route('tools.captcha.purchase') }}"
    data-key-url="{{ route('tools.captcha.key') }}"
    data-history-url="{{ route('tools.captcha.history') }}">
    <div class="grid min-w-0 gap-5">
        <p data-captcha-message role="status" aria-live="polite" class="text-sm"></p>
        <div class="grid min-w-0 items-start gap-5 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]">
        <section class="client-card overflow-hidden lg:col-start-1 lg:row-start-1 lg:row-span-2" aria-labelledby="captcha-demo-title">
            <header class="flex items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/70 px-5 py-4">
                <h2 id="captcha-demo-title" class="text-base font-bold">Thử giải captcha</h2>
                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Miễn phí</span>
            </header>
            <div class="grid gap-4 p-5">
            <p class="text-sm text-slate-500">Chọn một ảnh captcha để thử, không cần đăng nhập.</p>
            <form data-captcha-solve class="grid gap-4" aria-describedby="captcha-upload-help">
                @csrf
                <label data-captcha-dropzone class="relative flex min-h-48 cursor-pointer flex-col items-center justify-center gap-3 rounded-[10px] border-2 border-dotted border-slate-300 bg-slate-50/50 px-4 py-6 text-center transition hover:border-emerald-500 hover:bg-emerald-50/50 focus-within:border-emerald-500 focus-within:ring-4 focus-within:ring-emerald-100">
                    <input class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0" aria-label="Chọn ảnh captcha" type="file" accept="image/png,image/jpeg,image/webp" required name="image">
                    <span class="flex h-11 w-11 items-center justify-center rounded-[10px] bg-white text-emerald-600 shadow-sm"><i class="bx bx-image-plus text-2xl" aria-hidden="true"></i></span>
                    <span class="text-sm font-medium text-slate-700" data-captcha-file-name>Kéo thả ảnh vào đây hoặc <span class="font-semibold text-emerald-700">bấm để chọn ảnh</span></span>
                    <span id="captcha-upload-help" class="text-xs text-slate-400">PNG, JPEG, WebP · Tối đa 2 MB · Một ảnh mỗi lượt</span>
                    <img data-captcha-preview hidden class="max-h-32 max-w-full rounded-[10px] border border-slate-200 bg-white object-contain p-2" alt="Ảnh captcha đã chọn">
                </label>
                @if($turnstileSiteKey !== '')
                    <div class="min-w-0 overflow-x-auto"><div class="cf-turnstile" data-captcha-turnstile data-sitekey="{{ $turnstileSiteKey }}" data-action="captcha_demo" data-size="flexible"></div></div>
                @else
                    <p class="rounded-[10px] bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-800">Lượt thử miễn phí đang chờ cấu hình Cloudflare Turnstile.</p>
                @endif
                <button class="client-button gap-2 disabled:cursor-not-allowed disabled:opacity-50" type="submit" @disabled($turnstileSiteKey === '')><i class="bx bx-scan text-lg" aria-hidden="true"></i>Giải captcha miễn phí</button>
            </form>
            <p data-captcha-result class="break-words rounded-[10px] bg-emerald-50 px-4 py-3 text-lg font-bold text-emerald-800 empty:hidden" aria-live="polite"></p>
            <p class="text-xs leading-5 text-slate-400">Mỗi lượt thử cần xác minh Cloudflare, không trừ token_captcha. Tối đa 5 lượt/phút.</p>
            </div>
        </section>
        @auth
        <section class="client-card flex flex-wrap items-center justify-between gap-4 p-5 lg:col-start-2 lg:row-start-1">
            <div><h2 class="font-bold">Ví token_captcha</h2>
                <p class="mt-2 text-2xl font-extrabold"><span data-captcha-balance>{{ number_format($wallet->balance) }}</span> token</p>
                <p class="mt-1 text-sm text-slate-600">Đang giữ: {{ $wallet->reserved }} token · Ví chính: {{ number_format($mainBalance) }}đ</p>
            </div>
            <button class="client-button" type="button" data-captcha-buy>Mua token</button>
        </section>
        <section class="client-card grid gap-4 p-5 lg:col-start-2 lg:row-start-2">
            <h2 class="text-lg font-bold">API token cá nhân</h2>
            <p class="text-sm text-slate-600" data-captcha-key-status>{{ $apiKey ? 'Token đang dùng: '.$apiKey->prefix.'…' : 'Chưa tạo API token.' }}</p>
            <div class="flex flex-wrap gap-3">
                <button class="client-button" type="button" data-captcha-key-create>Tạo / thay API token</button>
                <button class="client-button-secondary" type="button" data-captcha-key-revoke>Thu hồi token</button>
            </div>
            <label class="client-label">Token mới chỉ hiển thị một lần
                <input readonly class="client-input mt-2 font-mono" data-captcha-key-value placeholder="Tạo token để lấy khóa API">
            </label>
            <p class="text-xs text-slate-600">Thay token sẽ vô hiệu hóa token cũ. Khóa này chỉ dùng cho API giải captcha, không dùng để đăng nhập hoặc mua token.</p>
        </section>
        @else
        <div class="client-card grid gap-3 p-5 lg:col-start-2 lg:row-start-1"><h2 class="font-bold">Tích hợp API vào ứng dụng</h2><p class="text-sm leading-6 text-slate-500">Đăng nhập để mua token, tạo khóa API và xem lịch sử giải của bạn.</p><a class="client-button justify-self-start" href="{{ route('login') }}">Đăng nhập để dùng API</a></div>
        @endauth
        </div>
        <section class="client-card grid gap-4 p-6">
            <h2 class="text-lg font-bold">Tài liệu cURL</h2>
            <p class="text-sm">Chỉ cần gửi ảnh base64 trong trường <code>image</code> (có thể có tiền tố data URI). Mỗi request là một lượt giải mới, tính phí 1 token khi thành công.</p>
            <pre class="overflow-x-auto rounded-[10px] bg-slate-900 p-4 text-xs leading-6 text-slate-100"><code>curl --request POST '{{ route('api.captcha.solve') }}' \
  --header 'Authorization: Bearer YOUR_API_TOKEN' \
  --header 'Content-Type: application/json' \
  --header 'Accept: application/json' \
  --data '{"image":"BASE64_IMAGE"}'</code></pre>
            <pre class="overflow-x-auto rounded-[10px] bg-slate-900 p-4 text-xs leading-6 text-slate-100"><code>{
  "status": true,
  "code": "93371",
  "time": 0.842
}</code></pre>
            <p class="text-sm text-slate-600"><code>time</code> là thời gian gọi bộ giải, tính bằng giây. Lượt thất bại trả status=false, code=null. Gửi lại ảnh sẽ tạo lượt giải mới.</p>
            <p class="text-sm text-slate-600">200: thành công · 401: khóa sai/đã thu hồi · 422: dữ liệu sai hoặc thiếu token · 429: quá nhiều yêu cầu · 500: lỗi xử lý · 502: bộ giải lỗi, không tính phí · 503: công cụ tắt hoặc bảo trì. Các lỗi trả status=false, code=null, message giải thích nguyên nhân và time=0 nếu chưa gọi bộ giải.</p>
            <pre class="overflow-x-auto rounded-[10px] bg-slate-900 p-4 text-xs leading-6 text-slate-100"><code>{
  "status": false,
  "code": null,
  "time": 0,
  "message": "Công cụ giải captcha đang tắt hoặc bảo trì. Vui lòng thử lại sau."
}</code></pre>
            <div class="flex flex-wrap gap-3 text-sm">
                @foreach($rates as $rate)
                <span class="rounded-[10px] bg-slate-100 px-3 py-2">Từ {{ number_format($rate['min_tokens']) }} token: {{ number_format($rate['unit_price']) }}đ/token</span>
                @endforeach
            </div>
        </section>
        @auth
        <section class="client-card min-w-0 overflow-hidden p-4 sm:p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div><h2 class="text-lg font-bold">Lịch sử của bạn</h2><p class="mt-1 text-xs text-slate-500">20 bản ghi / trang · Mới nhất trước</p></div>
                <button class="inline-flex min-h-9 items-center gap-1.5 rounded-[10px] border border-slate-200 px-3 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 disabled:opacity-50" type="button" data-captcha-history-load><i class="bx bx-refresh text-base" aria-hidden="true"></i>Làm mới</button>
            </div>
            <div role="tablist" aria-label="Lịch sử captcha" class="flex flex-wrap gap-2">
                <button type="button" role="tab" id="captcha-solves-tab" aria-controls="captcha-solves-panel" aria-selected="true" tabindex="0" class="client-button" data-captcha-history-tab="solves">Lịch sử giải</button>
                <button type="button" role="tab" id="captcha-transactions-tab" aria-controls="captcha-transactions-panel" aria-selected="false" tabindex="-1" class="client-button-secondary" data-captcha-history-tab="transactions">Lịch sử ví</button>
            </div>
            <div id="captcha-solves-panel" role="tabpanel" aria-labelledby="captcha-solves-tab" tabindex="0" data-captcha-history-panel="solves">
                <form data-captcha-filters="solves" class="my-4 flex flex-wrap items-end gap-2">
                    <label class="min-w-40 flex-1 text-xs font-medium text-slate-500">Kết quả captcha<input name="solves_search" type="search" maxlength="100" placeholder="Tìm kết quả…" class="client-input mt-1 !min-h-9 !py-2 !text-xs"></label>
                    <label class="text-xs font-medium text-slate-500">Trạng thái<select name="status" class="client-input mt-1 !min-h-9 !py-2 !text-xs"><option value="">Tất cả trạng thái</option value="success">Thành công</option><option value="failed">Thất bại</option><option value="processing">Đang xử lý</option></select></label>
                    <label class="text-xs font-medium text-slate-500">Nguồn<select name="source" class="client-input mt-1 !min-h-9 !py-2 !text-xs"><option value="">Tất cả nguồn</option value="demo">Demo</option><option value="api">API</option></select></label>
                    <button type="submit" class="min-h-9 rounded-[10px] bg-emerald-700 px-4 text-xs font-semibold text-white transition hover:bg-emerald-800 disabled:opacity-50">Lọc</button>
                    <button type="reset" class="min-h-9 rounded-[10px] px-3 text-xs font-medium text-slate-500 hover:bg-slate-50">Xóa lọc</button>
                </form>
                <div class="overflow-x-auto rounded-[10px] border border-slate-200">
                    <table class="w-full whitespace-nowrap text-left text-xs"><caption class="sr-only">Lịch sử giải captcha</caption>
                        <thead class="border-b border-slate-200 bg-slate-50 text-slate-500"><tr><th scope="col" class="px-3 py-3 font-medium">Mã lượt</th><th scope="col" class="px-3 py-3 font-medium">Kết quả</th><th scope="col" class="px-3 py-3 font-medium">Trạng thái</th><th scope="col" class="px-3 py-3 font-medium">Nguồn</th><th scope="col" class="px-3 py-3 text-right font-medium">Thời gian giải</th><th scope="col" class="px-3 py-3 font-medium">Thời điểm</th></tr></thead>
                        <tbody data-captcha-history class="divide-y divide-slate-100"></tbody>
                    </table>
                </div>
                <div class="mt-3 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500"><p data-captcha-solves-summary role="status" aria-live="polite"></p><nav aria-label="Phân trang lịch sử giải" class="flex items-center gap-2"><button type="button" class="min-h-9 rounded-[10px] border border-slate-200 px-3 font-medium hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40" data-captcha-solves-prev disabled>Trước</button><span data-captcha-solves-page class="px-1 tabular-nums"></span><button type="button" class="min-h-9 rounded-[10px] border border-slate-200 px-3 font-medium hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40" data-captcha-solves-next disabled>Sau</button></nav></div>
            </div>
            <div id="captcha-transactions-panel" role="tabpanel" aria-labelledby="captcha-transactions-tab" tabindex="0" data-captcha-history-panel="transactions" hidden>
                <form data-captcha-filters="transactions" class="my-4 flex flex-wrap items-end gap-2">
                    <label class="min-w-40 flex-1 text-xs font-medium text-slate-500">Nội dung giao dịch<input name="transactions_search" type="search" maxlength="100" placeholder="Tìm nội dung…" class="client-input mt-1 !min-h-9 !py-2 !text-xs"></label>
                    <label class="text-xs font-medium text-slate-500">Loại giao dịch<select name="direction" class="client-input mt-1 !min-h-9 !py-2 !text-xs"><option value="">Tất cả giao dịch</option value="credit">Cộng token</option><option value="debit">Trừ token</option></select></label>
                    <button type="submit" class="min-h-9 rounded-[10px] bg-emerald-700 px-4 text-xs font-semibold text-white transition hover:bg-emerald-800 disabled:opacity-50">Lọc</button><button type="reset" class="min-h-9 rounded-[10px] px-3 text-xs font-medium text-slate-500 hover:bg-slate-50">Xóa lọc</button>
                </form>
                <div class="overflow-x-auto rounded-[10px] border border-slate-200">
                    <table class="w-full text-left text-xs"><caption class="sr-only">Lịch sử ví token captcha</caption>
                        <thead class="whitespace-nowrap border-b border-slate-200 bg-slate-50 text-slate-500"><tr><th scope="col" class="px-3 py-3 font-medium">Mã GD</th><th scope="col" class="px-3 py-3 font-medium">Nội dung</th><th scope="col" class="px-3 py-3 text-right font-medium">Token</th><th scope="col" class="px-3 py-3 text-right font-medium">Số dư</th><th scope="col" class="px-3 py-3 font-medium">Thời điểm</th></tr></thead>
                        <tbody data-captcha-transactions class="divide-y divide-slate-100"></tbody>
                    </table>
                </div>
                <div class="mt-3 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500"><p data-captcha-transactions-summary role="status" aria-live="polite"></p><nav aria-label="Phân trang lịch sử ví" class="flex items-center gap-2"><button type="button" class="min-h-9 rounded-[10px] border border-slate-200 px-3 font-medium hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40" data-captcha-transactions-prev disabled>Trước</button><span data-captcha-transactions-page class="px-1 tabular-nums"></span><button type="button" class="min-h-9 rounded-[10px] border border-slate-200 px-3 font-medium hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40" data-captcha-transactions-next disabled>Sau</button></nav></div>
            </div>
        </section>
        <dialog data-captcha-modal class="w-full max-w-md rounded-[10px] p-6 text-slate-900 shadow-xl backdrop:bg-black/50">
            <form data-captcha-purchase class="grid gap-4">
                @csrf
                <h2 class="text-xl font-bold">Mua token_captcha</h2>
                <label class="client-label">Số token
                    <input name="tokens" class="client-input mt-2" type="number" min="1" max="10000000" step="1" value="1000" required>
                </label>
                <p data-captcha-price class="text-sm"></p>
                <p class="text-xs text-slate-600">Thanh toán bằng ví chính. Giá áp dụng cho toàn bộ số token khi đạt ngưỡng quy đổi.</p>
                <div class="flex gap-3"><button type="submit" class="client-button">Xác nhận mua</button><button type="button" class="client-button-secondary" data-captcha-close>Đóng</button></div>
            </form>
        </dialog>
        @endauth
    </div>
</section>
@endsection
@if($turnstileSiteKey !== '')
    @push('scripts')
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endpush
@endif
