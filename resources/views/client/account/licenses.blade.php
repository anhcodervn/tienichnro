<section class="grid min-w-0 gap-4" aria-labelledby="license-dashboard-title">
    <header class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 id="license-dashboard-title" class="text-xl font-extrabold text-slate-950">Dashboard license</h1>
            <p class="mt-1 text-sm text-slate-500">Các license được quản trị viên cấp cho bạn. Thời hạn bắt đầu từ lần kích hoạt đầu tiên.</p>
        </div>
        <span class="rounded-[5px] bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-700">{{ $licenses->total() }} license</span>
    </header>
    @if (session('success'))
        <p class="rounded-[5px] border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800" role="status">{{ session('success') }}</p>
    @endif
    @if ($errors->any())
        <p class="rounded-[5px] border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800" role="alert">{{ $errors->first() }}</p>
    @endif
    <div class="grid min-w-0 items-start gap-3 lg:grid-cols-2">
        @forelse ($licenses as $license)
            @php
                $licenseStatus = in_array($license->status, ['unused', 'active'], true) && $license->expires_at?->isPast() ? 'expired' : $license->status;
                $statusLabel = match ($licenseStatus) {
                    'unused' => 'Chưa kích hoạt',
                    'active' => 'Đang hoạt động',
                    'expired' => 'Đã hết hạn',
                    'suspended' => 'Tạm khóa',
                    'revoked' => 'Đã thu hồi',
                    default => 'Không khả dụng',
                };
                $licenseKey = $license->encrypted_key;
            @endphp
            <article class="grid min-w-0 gap-3 rounded-[5px] border border-slate-200 bg-white p-3 sm:p-4" data-license-card>
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h2 class="break-words font-extrabold text-slate-950">{{ $license->product?->name ?? 'Sản phẩm không còn khả dụng' }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ $license->plan?->name }} · #{{ $license->id }}</p>
                    </div>
                    <span @class(['rounded-[5px] px-2 py-1 text-xs font-bold', 'bg-emerald-50 text-emerald-700' => $licenseStatus === 'active', 'bg-slate-100 text-slate-600' => $licenseStatus === 'unused', 'bg-rose-50 text-rose-700' => ! in_array($licenseStatus, ['active', 'unused'], true)])>{{ $statusLabel }}</span>
                </div>
                @if ($licenseKey)
                    <div class="grid gap-2">
                        <label class="sr-only" for="license-key-{{ $license->id }}">License key</label>
                        <input id="license-key-{{ $license->id }}" class="client-input min-w-0 font-mono text-xs" type="text" readonly value="{{ $licenseKey }}" autocomplete="off" spellcheck="false">
                    </div>
                @else
                    <p class="rounded-[5px] bg-slate-50 p-3 text-sm text-slate-500">Key {{ $license->key_prefix }}… chưa có bản lưu. Liên hệ quản trị viên để nhận key.</p>
                @endif
                <div class="flex items-center gap-2" data-license-actions>
                    @if ($licenseKey)
                        <button class="client-button-secondary min-w-0 flex-1 whitespace-nowrap px-2 text-xs sm:flex-none sm:px-3" type="button" data-license-copy="license-key-{{ $license->id }}">Sao chép key</button>
                    @endif
                    @can('rotate', $license)
                        <form class="min-w-0 flex-1 sm:flex-none" method="POST" action="{{ route('account.profile.licenses.rotate', $license) }}" data-license-rotate>
                            @csrf
                            <input type="hidden" name="confirm_rotation" value="0">
                            <button class="client-button-secondary w-full whitespace-nowrap px-2 text-xs sm:px-3" type="submit">Đổi key</button>
                        </form>
                    @endcan
                    <button class="ml-auto inline-flex min-h-11 shrink-0 items-center gap-1 rounded-[5px] px-2 text-xs font-bold text-slate-500 hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600" type="button" data-license-toggle="license-details-{{ $license->id }}" aria-controls="license-details-{{ $license->id }}" aria-expanded="false">
                        More<i class="bx bx-chevron-down text-base" aria-hidden="true"></i>
                    </button>
                </div>
                <div id="license-details-{{ $license->id }}" hidden>
                    <dl class="grid gap-3 border-t border-slate-100 pt-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-slate-500">Ngày cấp</dt><dd class="mt-1 font-semibold">{{ $license->created_at?->format('d/m/Y H:i') }}</dd></div>
                        <div><dt class="text-slate-500">Kích hoạt</dt><dd class="mt-1 font-semibold">{{ $license->activated_at?->format('d/m/Y H:i') ?? 'Chưa kích hoạt' }}</dd></div>
                        <div><dt class="text-slate-500">Thời hạn</dt><dd class="mt-1 font-semibold">{{ $license->duration_days === null ? 'Vĩnh viễn' : $license->duration_days.' ngày' }}</dd></div>
                        <div><dt class="text-slate-500">Hết hạn</dt><dd class="mt-1 font-semibold">{{ $license->expires_at?->format('d/m/Y H:i') ?? ($license->duration_days === null ? 'Vĩnh viễn' : 'Tính từ lúc kích hoạt') }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-slate-500">Thiết bị đang sử dụng</dt><dd class="mt-1 break-words font-semibold">{{ $license->devices->firstWhere('device_uuid', $license->current_device_uuid)?->device_name ?? 'Chưa có thiết bị' }}</dd></div>
                    </dl>
                </div>
            </article>
        @empty
            <div class="rounded-[5px] border border-dashed border-slate-300 p-8 text-center lg:col-span-2">
                <i class="bx bx-key text-4xl text-slate-400" aria-hidden="true"></i>
                <h2 class="mt-3 font-extrabold text-slate-950">Bạn chưa được cấp license</h2>
                <p class="mt-2 text-sm text-slate-500">License sẽ xuất hiện tại đây sau khi quản trị viên cấp cho tài khoản của bạn.</p>
                <a class="client-button-secondary mt-4" href="{{ route('content.contact') }}">Liên hệ hỗ trợ</a>
            </div>
        @endforelse
    </div>
    @if ($licenses->hasPages())<div>{{ $licenses->links() }}</div>@endif
    <p class="text-sm text-slate-500" data-license-copy-status role="status" aria-live="polite"></p>
</section>
