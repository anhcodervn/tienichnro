@if($packages->isNotEmpty() && auth()->check())
<form data-notification-subscription data-checkout-wizard data-mode="" data-wallet-balance="{{ $wallet->balance }}" action="{{ route('notification-subscriptions.store') }}" novalidate class="grid min-w-0 gap-4">
    @csrf
@endif
<section class="client-card min-w-0 p-4 sm:p-5" data-checkout-step="1">
    <div class="mb-4 flex items-center gap-3"><span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-emerald-700 text-xs font-bold text-white">1</span><div><h2 class="text-sm font-extrabold text-slate-950">Bước 1: Chọn gói dịch vụ</h2><p class="mt-1 text-xs text-slate-500">Chọn gói và quyền sử dụng phù hợp.</p></div></div>
    @if($packages->isEmpty())
    <p class="text-sm text-slate-500">Hiện chưa có gói được mở bán. Vui lòng liên hệ hỗ trợ.</p>
    @else
    @auth
    <select name="package_id" data-package class="sr-only" tabindex="-1" aria-label="Gói dịch vụ"><option value="">Chọn gói dịch vụ</option>
        @foreach($packages as $package)
        <option value="{{ $package->id }}" data-mode="{{ $package->notification_mode ?? 'service' }}" data-service-code="{{ $package->service_code }}" data-price="{{ $package->price }}" data-name="{{ $package->name }}" data-entitlement="{{ $package->billing_type === 'lifetime' ? 'Vĩnh viễn' : ($package->billing_type === 'usage' ? number_format($package->usage_limit, 0, ',', '.').' lượt' : $package->duration_days.' ngày') }}">{{ $package->name }}</option>
        @endforeach
    </select>
    @endauth
    <div class="grid min-w-0 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($packages as $package)
        <article class="min-w-0 rounded-lg border border-slate-200 bg-white p-4" data-package-card="{{ $package->id }}" data-package-id="{{ $package->id }}">
            <div class="flex items-start justify-between gap-2"><h3 class="min-w-0 break-words text-sm font-extrabold text-slate-950">{{ $package->name }}</h3><span class="shrink-0 rounded bg-emerald-50 px-2 py-1 text-[10px] font-bold text-emerald-700">{{ $package->notification_mode === 'personal' ? 'Cá nhân' : ($package->notification_mode === 'webhook' ? 'Webhook' : 'Dịch vụ') }}</span></div>
            <p class="mt-3 text-lg font-extrabold text-emerald-700">{{ number_format($package->price, 0, ',', '.') }} đ</p>
            <p class="mt-1 text-xs font-semibold text-slate-600">{{ $package->billing_type === 'lifetime' ? 'Vĩnh viễn' : ($package->billing_type === 'usage' ? number_format($package->usage_limit, 0, ',', '.').' lượt' : $package->duration_days.' ngày') }}</p>
            @if($package->description)<p class="mt-2 whitespace-pre-line break-words text-xs leading-relaxed text-slate-500">{{ $package->description }}</p>@endif
            @if($package->notification_mode)<p class="mt-2 text-xs leading-relaxed text-slate-500">{{ $package->notification_mode === 'personal' ? 'Một Zalo · Tối đa 10 nhân vật theo server' : 'Toàn bộ thông báo · URL webhook' }}</p>@endif
            @if($package->notification_mode === 'personal')<p class="mt-2 text-[11px] text-amber-800">API gửi Zalo chưa kết nối.</p>@endif
            @auth<button type="button" data-select-package="{{ $package->id }}" aria-pressed="false" class="client-button-secondary mt-3 w-full text-xs"><span data-package-selection-label>Chọn gói này</span></button>@else<a href="{{ route('auth.login') }}" class="client-button-secondary mt-3 w-full text-xs">Đăng nhập để chọn</a>@endauth
        </article>
        @endforeach
    </div>
    @endif
</section>
<section class="client-card min-w-0 p-4 sm:p-5" data-checkout-step="2">
    <div class="mb-4 flex items-center gap-3"><span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-emerald-700 text-xs font-bold text-white">2</span><div><h2 class="text-sm font-extrabold text-slate-950">Bước 2: Điền thông tin dịch vụ</h2><p class="mt-1 text-xs text-slate-500">Nhập payload theo dịch vụ đã chọn.</p></div></div>
    <p data-payload-locked class="text-xs text-slate-500">Chọn gói ở bước 1 để điền thông tin.</p>
    @if($packages->isNotEmpty() && auth()->check())
    <div data-checkout-payload hidden class="grid gap-5">
        @include('client.subscription.fields', ['subscription' => null])
        @foreach($packages->pluck('service')->filter()->unique('code') as $service)
        <fieldset data-service-payload="{{ $service->code }}" hidden class="grid gap-4 sm:grid-cols-2">
            @if(!empty($service->payload_fields))<legend class="mb-3 text-sm font-bold">Thông tin {{ $service->name }}</legend>@endif
            @foreach($service->payload_fields ?? [] as $field)
            <label class="grid min-w-0 gap-2 text-xs font-semibold {{ $field['type'] === 'textarea' ? 'sm:col-span-2' : '' }}">
                <span>{{ $field['label'] }} @if($field['required'])<span class="text-rose-700">*</span>@endif</span>
                @if($field['type'] === 'select')
                <select data-service-field="{{ $field['name'] }}" data-required="{{ $field['required'] ? 'true' : 'false' }}" disabled class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm"><option value="">Chọn {{ $field['label'] }}</option>@foreach($field['options'] ?? [] as $option)<option value="{{ $option['value'] }}">{{ $option['label'] }}</option>@endforeach</select>
                @elseif($field['type'] === 'textarea')
                <textarea data-service-field="{{ $field['name'] }}" data-required="{{ $field['required'] ? 'true' : 'false' }}" disabled maxlength="2000" placeholder="{{ $field['placeholder'] ?? '' }}" class="min-h-24 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"></textarea>
                @elseif($field['type'] === 'boolean')
                <span class="flex min-h-11 items-center gap-2"><input type="checkbox" data-service-field="{{ $field['name'] }}" data-required="{{ $field['required'] ? 'true' : 'false' }}" disabled class="h-4 w-4 accent-emerald-700">Bật</span>
                @else
                <input type="{{ in_array($field['type'], ['email', 'number', 'password'], true) ? $field['type'] : 'text' }}" data-service-field="{{ $field['name'] }}" data-required="{{ $field['required'] ? 'true' : 'false' }}" disabled @if($field['type'] === 'number') step="any" min="-1000000000000000" max="1000000000000000" @else maxlength="{{ $field['type'] === 'email' ? 254 : 2000 }}" @endif autocomplete="{{ $field['type'] === 'password' ? 'new-password' : 'off' }}" placeholder="{{ $field['placeholder'] ?? '' }}" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                @endif
            </label>
            @endforeach
        </fieldset>
        @endforeach
        <button type="button" data-review-checkout class="client-button-primary justify-self-start">Tiếp tục thanh toán <i class="bx bx-right-arrow-alt" aria-hidden="true"></i></button>
    </div>
    @endif
</section>
<section class="client-card min-w-0 p-4 sm:p-5" data-checkout-step="3">
    <div class="mb-4 flex items-center gap-3"><span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-emerald-700 text-xs font-bold text-white">3</span><div><h2 class="text-sm font-extrabold text-slate-950">Bước 3: Thanh toán <span data-checkout-total-heading class="text-emerald-700 dark:text-emerald-400"></span></h2><p class="mt-1 text-xs text-slate-500">Kiểm tra gói và thanh toán bằng số dư ví.</p></div></div>
    <p data-payment-locked class="text-xs text-slate-500">Hoàn tất thông tin ở bước 2 để thanh toán.</p>
    @if($packages->isNotEmpty() && auth()->check())
    <div data-checkout-payment class="grid gap-4">
        <dl class="grid gap-2 rounded-lg bg-slate-50 p-4 text-xs sm:grid-cols-2"><div><dt class="text-slate-500">Gói đã chọn</dt><dd data-review-name class="mt-1 font-bold"></dd></div><div><dt class="text-slate-500">Quyền sử dụng</dt><dd data-review-entitlement class="mt-1 font-bold"></dd></div></dl>
        <div>
            <p class="mb-2 flex items-center gap-2 text-xs font-semibold"><i class="bx bx-credit-card" aria-hidden="true"></i> Phương thức thanh toán</p>
            <div class="grid grid-cols-2 gap-2">
                <div data-wallet-method class="flex min-h-[64px] items-center justify-center gap-2 rounded border-2 border-cyan-700 bg-cyan-50 p-3 text-cyan-900 dark:border-cyan-500 dark:bg-cyan-950 dark:text-cyan-100"><i class="bx bx-wallet text-xl" aria-hidden="true"></i><div><p class="text-xs font-bold">Số dư tài khoản</p><p class="mt-1 text-[10px]">{{ number_format($wallet->balance, 0, ',', '.') }} đ</p></div></div>
                <button type="button" disabled class="flex min-h-[64px] items-center justify-center gap-2 rounded border border-slate-200 bg-slate-50 p-3 text-slate-400 disabled:cursor-not-allowed dark:border-slate-700 dark:bg-slate-800"><i class="bx bx-qr text-xl" aria-hidden="true"></i><span><span class="block text-xs font-bold">QR thanh toán</span><span class="mt-1 block text-[10px]">Chưa hỗ trợ chuyển khoản tự động</span></span></button>
            </div>
        </div>
        <div data-payment-summary hidden class="grid gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 sm:p-5 dark:border-emerald-800 dark:bg-emerald-950">
            <div class="flex flex-wrap items-center justify-between gap-2 text-sm"><span class="text-slate-600">Giá gói dịch vụ</span><span data-review-unit-price class="font-semibold text-slate-950"></span></div>
            <div class="flex items-center justify-between gap-2 text-sm"><span class="text-slate-600">Số lượng</span><span class="font-semibold text-slate-950">1 gói</span></div>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-emerald-200 pt-3 dark:border-emerald-800"><span class="text-sm font-extrabold text-slate-950">Tổng tiền thanh toán</span><strong data-review-price aria-live="polite" class="text-2xl font-extrabold text-emerald-700 dark:text-emerald-400 sm:text-3xl"></strong></div>
            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500"><span>Số dư ví sau thanh toán</span><span data-review-balance-after class="font-semibold"></span></div>
        </div>
        <p data-wallet-insufficient hidden class="text-xs text-rose-700">Số dư ví chưa đủ. <a class="font-bold underline" href="{{ route('account.wallet') }}">Mở ví để nạp thêm tiền</a>.</p>
        <p class="text-xs leading-relaxed text-slate-500">Giá được trừ từ ví khi đăng ký thành công. Thời hạn bắt đầu ngay khi kích hoạt.</p>
        <button type="submit" disabled data-payment-submit class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded bg-cyan-700 px-4 py-3 text-xs font-extrabold uppercase text-white transition hover:bg-cyan-800 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-600 dark:disabled:bg-slate-700 dark:disabled:text-slate-400"><i class="bx bx-bolt-circle text-lg" aria-hidden="true"></i><span data-payment-submit-label>Chọn gói dịch vụ</span></button>
        <button type="button" data-edit-payload class="justify-self-start text-xs font-bold text-emerald-700">← Sửa thông tin bước 2</button>
    </div>
    @endif
</section>
@if($packages->isNotEmpty() && auth()->check())
</form>
@elseif(!auth()->check())
<a class="client-button-primary justify-self-start" href="{{ route('auth.login') }}">Đăng nhập để đăng ký</a>
@endif
