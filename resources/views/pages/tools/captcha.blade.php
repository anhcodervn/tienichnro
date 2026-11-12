@extends('client.layouts.app')
@section('title', 'Giải captcha NRO')
@section('description', 'Demo giải captcha NRO, mua token_captcha và tài liệu tích hợp API cURL.')
@section('canonical', route('tools.captcha'))
@section('tool_title', 'Giải captcha NRO')
@section('tool_description', '1 token_captcha / lượt giải thành công. Lượt lỗi không mất token. API và demo dùng chung ví token riêng.')
@section('content')
<section data-captcha-tool
    data-rates="{{ json_encode($rates) }}"
    data-solve-url="{{ route('tools.captcha.solve') }}"
    data-purchase-url="{{ route('tools.captcha.purchase') }}"
    data-key-url="{{ route('tools.captcha.key') }}"
    data-history-url="{{ route('tools.captcha.history') }}">
    <div class="grid min-w-0 gap-5">
        <p data-captcha-message role="status" aria-live="polite" class="text-sm"></p>
        @auth
        <div class="grid min-w-0 items-start gap-5 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]">
        <section class="client-card flex flex-wrap items-center justify-between gap-4 p-5 lg:col-start-2 lg:row-start-1">
            <div><h2 class="font-bold">Ví token_captcha</h2>
                <p class="mt-2 text-2xl font-extrabold"><span data-captcha-balance>{{ number_format($wallet->balance) }}</span> token</p>
                <p class="mt-1 text-sm text-slate-600">Đang giữ: {{ $wallet->reserved }} token · Ví chính: {{ number_format($mainBalance) }}đ</p>
            </div>
            <button class="client-button" type="button" data-captcha-buy>Mua token</button>
        </section>
        <section class="client-card grid gap-4 p-5 lg:col-start-1 lg:row-start-1 lg:row-span-2">
            <h2 class="text-lg font-bold">Demo giải captcha</h2>
            <form data-captcha-solve class="grid gap-4">
                @csrf
                <label class="client-label">Ảnh captcha (PNG, JPEG, WebP; tối đa 2MB)
                    <input class="client-input mt-2" type="file" accept="image/png,image/jpeg,image/webp" required name="image">
                </label>
                <img data-captcha-preview hidden class="max-h-48 max-w-full rounded-lg border" alt="Ảnh captcha đã chọn">
                <button class="client-button" type="submit">Giải captcha · 1 token khi thành công</button>
            </form>
            <p data-captcha-result class="break-words text-2xl font-bold" aria-live="polite"></p>
            <p class="text-xs text-slate-600">Bộ giải đang thử nghiệm. Thành công được tính khi API trả success=true và result không rỗng, kể cả needs_review=true hoặc verified=false.</p>
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
        </div>
        @else
        <div class="client-card p-6"><a class="client-button" href="{{ route('login') }}">Đăng nhập để demo, mua token và tạo khóa API</a></div>
        @endauth
        <section class="client-card grid gap-4 p-6">
            <h2 class="text-lg font-bold">Tài liệu cURL</h2>
            <p class="text-sm">Chỉ cần gửi ảnh base64 trong trường <code>image</code> (có thể có tiền tố data URI). Mỗi request là một lượt giải mới, tính phí 1 token khi thành công.</p>
            <pre class="overflow-x-auto rounded-xl bg-slate-900 p-4 text-xs leading-6 text-slate-100"><code>curl --request POST '{{ route('api.captcha.solve') }}' \
  --header 'Authorization: Bearer YOUR_API_TOKEN' \
  --header 'Content-Type: application/json' \
  --header 'Accept: application/json' \
  --data '{"image":"BASE64_IMAGE"}'</code></pre>
            <pre class="overflow-x-auto rounded-xl bg-slate-900 p-4 text-xs leading-6 text-slate-100"><code>{
  "status": true,
  "code": "93371",
  "time": 0.842
}</code></pre>
            <p class="text-sm text-slate-600"><code>time</code> là thời gian gọi bộ giải, tính bằng giây. Lượt thất bại trả status=false, code=null. Gửi lại ảnh sẽ tạo lượt giải mới.</p>
            <p class="text-sm text-slate-600">200: thành công · 401: khóa sai/đã thu hồi · 422: dữ liệu sai hoặc thiếu token · 429: quá nhiều yêu cầu · 500: lỗi xử lý · 502: bộ giải lỗi, không tính phí · 503: công cụ tắt hoặc bảo trì. Các lỗi trả status=false, code=null, message giải thích nguyên nhân và time=0 nếu chưa gọi bộ giải.</p>
            <pre class="overflow-x-auto rounded-xl bg-slate-900 p-4 text-xs leading-6 text-slate-100"><code>{
  "status": false,
  "code": null,
  "time": 0,
  "message": "Công cụ giải captcha đang tắt hoặc bảo trì. Vui lòng thử lại sau."
}</code></pre>
            <div class="flex flex-wrap gap-3 text-sm">
                @foreach($rates as $rate)
                <span class="rounded-lg bg-slate-100 px-3 py-2">Từ {{ number_format($rate['min_tokens']) }} token: {{ number_format($rate['unit_price']) }}đ/token</span>
                @endforeach
            </div>
        </section>
        @auth
        <section class="client-card grid gap-4 p-6">
            <h2 class="text-lg font-bold">Lịch sử của bạn</h2>
            <div role="tablist" aria-label="Lịch sử captcha" class="flex flex-wrap gap-2">
                <button type="button" role="tab" id="captcha-solves-tab" aria-controls="captcha-solves-panel" aria-selected="true" tabindex="0" class="client-button" data-captcha-history-tab="solves">Lịch sử giải</button>
                <button type="button" role="tab" id="captcha-transactions-tab" aria-controls="captcha-transactions-panel" aria-selected="false" tabindex="-1" class="client-button-secondary" data-captcha-history-tab="transactions">Lịch sử ví</button>
            </div>
            <button class="client-button-secondary justify-self-start" type="button" data-captcha-history-load>Làm mới lịch sử</button>
            <div id="captcha-solves-panel" role="tabpanel" aria-labelledby="captcha-solves-tab" tabindex="0" data-captcha-history-panel="solves">
                <div class="grid gap-4">
                    <div data-captcha-history class="grid gap-2 text-sm"></div>
                    <div class="flex gap-3"><button type="button" class="client-button-secondary" data-captcha-solves-prev>Trước</button><button type="button" class="client-button-secondary" data-captcha-solves-next>Sau</button></div>
                </div>
            </div>
            <div id="captcha-transactions-panel" role="tabpanel" aria-labelledby="captcha-transactions-tab" tabindex="0" data-captcha-history-panel="transactions" hidden>
                <div class="grid gap-4">
                    <div data-captcha-transactions class="grid gap-2 text-sm"></div>
                    <div class="flex gap-3"><button type="button" class="client-button-secondary" data-captcha-transactions-prev>Trước</button><button type="button" class="client-button-secondary" data-captcha-transactions-next>Sau</button></div>
                </div>
            </div>
        </section>
        <dialog data-captcha-modal class="w-full max-w-md rounded-2xl p-6 text-slate-900 shadow-xl backdrop:bg-black/50">
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
