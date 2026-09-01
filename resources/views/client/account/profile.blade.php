@extends('client.layouts.app')

@section('title', 'Quản lý tài khoản')
@section('robots', 'noindex,nofollow')

@section('content')
@php
    $avatarUrl = old('avatar', $user->avatar);
    $tabs = [
        ['key' => 'profile', 'route' => 'account.index', 'icon' => 'bx-user-circle', 'title' => 'Thông tin user', 'description' => 'Hồ sơ và trạng thái tài khoản'],
        ['key' => 'password', 'route' => 'account.profile.password', 'icon' => 'bx-key', 'title' => 'Đổi mật khẩu', 'description' => 'Bảo mật phiên đăng nhập'],
        ['key' => 'api', 'route' => 'account.profile.api', 'icon' => 'bx-code-alt', 'title' => 'API key', 'description' => 'Khóa tích hợp cá nhân'],
        ['key' => 'api-docs', 'route' => 'account.profile.api.docs', 'icon' => 'bx-book-open', 'title' => 'Tài liệu API', 'description' => 'Endpoint và mẫu tích hợp'],
        ['key' => 'logs', 'route' => 'account.profile.logs', 'icon' => 'bx-history', 'title' => 'Lịch sử người dùng', 'description' => 'Nhật ký thao tác tài khoản'],
        ['key' => 'wallet', 'route' => 'account.profile.wallet', 'icon' => 'bx-wallet-alt', 'title' => 'Lịch sử dòng tiền', 'description' => 'Biến động số dư ví'],
    ];
    $statusLabel = match ($user->status) {
        'active' => 'Đang hoạt động',
        'banned' => 'Đã khóa',
        default => 'Tạm ngừng',
    };
@endphp

<section class="client-container py-4 sm:py-6 lg:py-8">
    <div class="client-card min-w-0 overflow-hidden">
        <nav class="border-b border-slate-200 bg-slate-50/70" aria-label="Chức năng tài khoản">
            <div class="flex items-center justify-between gap-3 px-3 pb-2 pt-3 md:hidden">
                <p class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Chức năng tài khoản</p>
                <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700"><i class="bx bx-left-arrow-alt text-base" aria-hidden="true"></i>Kéo ngang<i class="bx bx-right-arrow-alt text-base" aria-hidden="true"></i></span>
            </div>

            <div class="w-full overflow-x-auto overscroll-x-contain px-3 pb-3 md:overflow-visible md:p-4" data-account-tabs>
                <div class="flex min-w-max snap-x snap-mandatory gap-2 md:grid md:min-w-0 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($tabs as $tab)
                        <a
                            @class([
                                'flex min-h-[4.75rem] w-[12.5rem] snap-start items-center gap-3 rounded-[5px] border p-3 text-left transition duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 md:w-auto',
                                'border-slate-950 bg-slate-950 text-white shadow-sm ring-1 ring-slate-950/10' => $activeTab === $tab['key'],
                                'border-slate-200 bg-white text-slate-700 hover:border-emerald-300 hover:bg-emerald-50/60' => $activeTab !== $tab['key'],
                            ])
                            href="{{ route($tab['route']) }}"
                            @if ($activeTab === $tab['key']) aria-current="page" data-account-tab-active @endif
                        >
                            <span @class(['grid h-10 w-10 shrink-0 place-items-center rounded-[5px] text-xl', 'bg-white/10 text-white' => $activeTab === $tab['key'], 'bg-slate-100 text-slate-500' => $activeTab !== $tab['key']])>
                                <i class="bx {{ $tab['icon'] }}" aria-hidden="true"></i>
                            </span>
                            <span class="min-w-0">
                                <strong class="block text-sm leading-5">{{ $tab['title'] }}</strong>
                                <span @class(['mt-0.5 block text-xs leading-5', 'text-slate-300' => $activeTab === $tab['key'], 'text-slate-500' => $activeTab !== $tab['key']])>{{ $tab['description'] }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        </nav>

        <div class="min-w-0 p-3 sm:p-4">
            @if ($activeTab === 'profile')
                <div class="grid min-w-0 gap-3 lg:grid-cols-[minmax(0,1fr)_21rem]">
                    <form class="min-w-0 rounded-[5px] border border-slate-200 bg-white p-4 sm:p-5" method="POST" action="{{ route('account.profile.update') }}">
                        @csrf
                        @method('PATCH')

                        <div class="flex flex-col gap-4 rounded-[5px] bg-slate-50 p-4 sm:flex-row sm:items-center">
                            <div class="relative h-20 w-20 shrink-0 overflow-hidden rounded-[5px] border border-slate-200 bg-white">
                                @if ($avatarUrl)
                                    <img class="h-full w-full object-cover" src="{{ $avatarUrl }}" alt="Ảnh đại diện của {{ $user->name }}">
                                @else
                                    <span class="grid h-full w-full place-items-center text-4xl text-slate-400"><i class="bx bx-user" aria-hidden="true"></i></span>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400">Hồ sơ hiển thị</p>
                                <h2 class="mt-1 truncate text-xl font-extrabold text-slate-950">{{ $user->name }}</h2>
                                <p class="mt-1 truncate text-sm text-slate-500">{{ $user->email }}</p>
                            </div>
                        </div>

                        <div class="mt-5 grid min-w-0 gap-4 sm:grid-cols-2">
                            <label class="client-label">Avatar URL
                                <input class="client-input" type="url" name="avatar" value="{{ old('avatar', $user->avatar) }}" placeholder="https://example.com/avatar.jpg" autocomplete="url">
                                @error('avatar')<span class="text-xs font-medium text-rose-600">{{ $message }}</span>@enderror
                            </label>
                            <label class="client-label">Họ tên
                                <input class="client-input" name="full_name" value="{{ old('full_name', $user->full_name) }}" autocomplete="name">
                                @error('full_name')<span class="text-xs font-medium text-rose-600">{{ $message }}</span>@enderror
                            </label>
                            <label class="client-label">Email
                                <input class="client-input bg-slate-100 text-slate-500" type="email" value="{{ $user->email }}" disabled>
                            </label>
                            <label class="client-label">Số điện thoại
                                <input class="client-input" type="tel" name="phone" value="{{ old('phone', $user->phone) }}" inputmode="tel" autocomplete="tel">
                                @error('phone')<span class="text-xs font-medium text-rose-600">{{ $message }}</span>@enderror
                            </label>
                            <label class="client-label">Tên đăng nhập
                                <input class="client-input bg-slate-100 text-slate-500" value="{{ $user->username }}" disabled>
                            </label>
                            <label class="client-label">User ID
                                <input class="client-input bg-slate-100 text-slate-500" value="#{{ $user->id }}" disabled>
                            </label>
                        </div>

                        <div class="mt-5 flex justify-end">
                            <button class="client-button min-h-11 w-full justify-center sm:w-auto" type="submit"><i class="bx bx-save text-lg" aria-hidden="true"></i>Lưu thay đổi</button>
                        </div>
                    </form>

                    <aside class="grid content-start gap-3">
                        @if ($memberLevelStatus && $memberLevelStatus['effective_level'])
                            @php
                                $effectiveLevel = $memberLevelStatus['effective_level'];
                                $unlockedLevel = $memberLevelStatus['unlocked_level'];
                                $nextLevel = $memberLevelStatus['next_level'];
                                $nextThreshold = max(1, (int) ($nextLevel['lifetime_threshold'] ?? $memberLevelStatus['lifetime_completed_amount']));
                                $levelProgress = min(100, (int) round(($memberLevelStatus['lifetime_completed_amount'] / $nextThreshold) * 100));
                            @endphp
                            <section class="rounded-[5px] border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-amber-700">Cấp đại lý đang hưởng</p>
                                        <h2 class="mt-1 flex items-center gap-2 text-xl font-extrabold text-slate-950">
                                            <i class="bx bx-crown text-2xl text-amber-500" aria-hidden="true"></i>
                                            {{ $effectiveLevel['name'] }}
                                        </h2>
                                    </div>
                                    @if ($memberLevelStatus['is_temporarily_downgraded'])
                                        <span class="rounded-[5px] border border-amber-300 bg-white px-2 py-1 text-xs font-bold text-amber-700">Tạm giảm 1 cấp</span>
                                    @endif
                                </div>

                                <dl class="mt-4 grid gap-2 text-sm">
                                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Cấp đã mở khóa</dt><dd class="font-bold text-slate-900">{{ $unlockedLevel['name'] }}</dd></div>
                                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Tổng nạp lịch sử</dt><dd class="font-bold text-slate-900">{{ number_format($memberLevelStatus['lifetime_completed_amount'], 0, ',', '.') }}đ</dd></div>
                                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Nạp trong {{ $unlockedLevel['maintenance_days'] }} ngày</dt><dd class="font-bold text-slate-900">{{ number_format($memberLevelStatus['rolling_completed_amount'], 0, ',', '.') }}đ</dd></div>
                                </dl>

                                @if ($memberLevelStatus['maintenance_remaining_amount'] > 0)
                                    <p class="mt-3 rounded-[5px] bg-amber-100 p-3 text-sm font-semibold text-amber-900">
                                        Nạp thêm {{ number_format($memberLevelStatus['maintenance_remaining_amount'], 0, ',', '.') }}đ để khôi phục {{ $unlockedLevel['name'] }}.
                                    </p>
                                @elseif ($memberLevelStatus['maintenance_expires_at'])
                                    <p class="mt-3 text-xs font-semibold text-emerald-700">Quyền lợi được duy trì đến {{ \Illuminate\Support\Carbon::parse($memberLevelStatus['maintenance_expires_at'])->format('H:i d/m/Y') }}.</p>
                                @endif

                                @if ($nextLevel)
                                    <div class="mt-4">
                                        <div class="mb-1 flex justify-between gap-3 text-xs font-semibold text-slate-500">
                                            <span>Tiến tới {{ $nextLevel['name'] }}</span>
                                            <span>Còn {{ number_format($memberLevelStatus['amount_to_next_level'], 0, ',', '.') }}đ</span>
                                        </div>
                                        <progress class="h-2 w-full overflow-hidden rounded-full accent-amber-500" value="{{ $levelProgress }}" max="100">{{ $levelProgress }}%</progress>
                                    </div>
                                @endif

                                @if ($memberLevelHistories->isNotEmpty())
                                    <div class="mt-4 border-t border-amber-200 pt-3">
                                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-amber-700">Thay đổi gần đây</p>
                                        <ul class="mt-2 grid gap-2 text-xs text-slate-600">
                                            @foreach ($memberLevelHistories->take(4) as $history)
                                                <li class="flex items-center justify-between gap-3 rounded-[5px] bg-white/80 p-2">
                                                    <span class="font-semibold">{{ $history->fromLevel?->name ?? 'Khởi tạo' }} → {{ $history->toLevel?->name ?? 'Tự động' }}</span>
                                                    <time class="shrink-0 text-slate-400" datetime="{{ $history->created_at?->toISOString() }}">{{ $history->created_at?->format('d/m/Y') }}</time>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </section>
                        @endif

                        <section class="rounded-[5px] border border-slate-200 bg-white p-4">
                            <h2 class="flex items-center gap-2 font-extrabold text-slate-950"><i class="bx bx-envelope text-lg text-emerald-600" aria-hidden="true"></i>Tổng quan xác thực</h2>
                            <dl class="mt-4 grid gap-2 text-sm">
                                <div class="flex items-center justify-between gap-3 rounded-[5px] bg-slate-50 p-3"><dt class="text-xs font-bold uppercase tracking-wider text-slate-400">User ID</dt><dd class="font-extrabold">#{{ $user->id }}</dd></div>
                                <div class="flex items-center justify-between gap-3 rounded-[5px] bg-slate-50 p-3"><dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Trạng thái</dt><dd class="font-extrabold">{{ $statusLabel }}</dd></div>
                                <div class="flex items-center justify-between gap-3 rounded-[5px] bg-slate-50 p-3"><dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Ngày tạo</dt><dd class="text-right font-extrabold">{{ $user->created_at?->format('H:i d/m/Y') }}</dd></div>
                                <div class="flex items-center justify-between gap-3 rounded-[5px] bg-slate-50 p-3">
                                    <dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Email xác thực</dt>
                                    @if ($user->hasVerifiedEmail())
                                        <dd class="font-extrabold text-emerald-700">Đã xác thực</dd>
                                    @else
                                        <dd><a class="font-extrabold text-amber-700 underline decoration-amber-300 underline-offset-2" href="{{ route('verification.notice') }}">Xác minh ngay</a></dd>
                                    @endif
                                </div>
                                <div class="flex items-start justify-between gap-3 rounded-[5px] bg-slate-50 p-3"><dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Phiên gần nhất</dt><dd class="text-right font-extrabold">{{ $user->last_login_at?->format('H:i d/m/Y') ?? 'Chưa có' }}@if ($user->last_login_ip)<br><span class="font-medium text-slate-500">{{ $user->last_login_ip }}</span>@endif</dd></div>
                            </dl>
                        </section>

                        <section class="rounded-[5px] border border-blue-200 bg-blue-50 p-4">
                            <h2 class="flex items-center gap-2 font-extrabold text-blue-950"><i class="bx bx-shield-quarter text-lg text-blue-600" aria-hidden="true"></i>Khuyến nghị bảo mật</h2>
                            <ul class="mt-3 grid gap-2 text-sm leading-6 text-blue-900">
                                <li class="rounded-[5px] bg-white/70 p-3">Xác thực email để bảo vệ và nhận lại đơn guest.</li>
                                <li class="rounded-[5px] bg-white/70 p-3">Dùng mật khẩu riêng, không trùng với tài khoản game.</li>
                                <li class="rounded-[5px] bg-white/70 p-3">Kiểm tra lịch sử nếu phát hiện hoạt động bất thường.</li>
                            </ul>
                        </section>
                    </aside>
                </div>
            @elseif ($activeTab === 'password')
                <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_21rem]">
                    <form class="rounded-[5px] border border-slate-200 bg-white p-4 sm:p-6" method="POST" action="{{ route('account.profile.password.update') }}">
                        @csrf
                        @method('PUT')
                        <h2 class="text-xl font-extrabold text-slate-950">Đổi mật khẩu</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-500">Nhập mật khẩu hiện tại trước khi thiết lập mật khẩu mới.</p>

                        <div class="mt-5 grid max-w-2xl gap-4">
                            <label class="client-label">Mật khẩu hiện tại
                                <input class="client-input" type="password" name="current_password" required autocomplete="current-password">
                                @error('current_password')<span class="text-xs font-medium text-rose-600">{{ $message }}</span>@enderror
                            </label>
                            <label class="client-label">Mật khẩu mới
                                <input class="client-input" type="password" name="password" required autocomplete="new-password">
                                @error('password')<span class="text-xs font-medium text-rose-600">{{ $message }}</span>@enderror
                            </label>
                            <label class="client-label">Xác nhận mật khẩu mới
                                <input class="client-input" type="password" name="password_confirmation" required autocomplete="new-password">
                            </label>
                        </div>

                        <button class="client-button mt-5 min-h-11 w-full justify-center sm:w-auto" type="submit"><i class="bx bx-key text-lg" aria-hidden="true"></i>Cập nhật mật khẩu</button>
                    </form>
                    <aside class="rounded-[5px] border border-amber-200 bg-amber-50 p-4">
                        <h2 class="flex items-center gap-2 font-extrabold text-amber-950"><i class="bx bx-lock-alt text-lg" aria-hidden="true"></i>Mật khẩu an toàn</h2>
                        <ul class="mt-3 grid gap-2 text-sm leading-6 text-amber-900">
                            <li>• Ít nhất 8 ký tự.</li>
                            <li>• Không dùng lại mật khẩu tài khoản game.</li>
                            <li>• Không gửi mật khẩu cho nhân viên hỗ trợ.</li>
                        </ul>
                    </aside>
                </div>
            @elseif ($activeTab === 'api')
                <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_21rem]">
                    <div class="grid content-start gap-3">
                        @if (session('new_api_credentials'))
                            @php
                                $newApiCredentials = session('new_api_credentials');
                            @endphp
                            <section class="rounded-[5px] border border-emerald-300 bg-emerald-50 p-4">
                                <h2 class="font-extrabold text-emerald-950">API key và API secret mới — chỉ hiển thị một lần</h2>
                                <p class="mt-1 text-sm text-emerald-800">Sao chép cả hai giá trị ngay. API secret chỉ được lưu dạng hash nên không thể hiển thị lại.</p>
                                <div class="mt-3 grid gap-3">
                                    @foreach (['API key' => $newApiCredentials['api_key'], 'API secret' => $newApiCredentials['api_secret']] as $credentialLabel => $credentialValue)
                                        <div class="grid min-w-0 gap-2 sm:grid-cols-[7rem_minmax(0,1fr)_auto] sm:items-center">
                                            <strong class="text-sm text-emerald-950">{{ $credentialLabel }}</strong>
                                            <code class="min-w-0 break-all rounded-[5px] border border-emerald-200 bg-white p-3 text-xs text-slate-800">{{ $credentialValue }}</code>
                                            <button class="client-button-secondary min-h-11 shrink-0 bg-white" type="button" data-copy="{{ $credentialValue }}"><i class="bx bx-copy text-lg" aria-hidden="true"></i>Sao chép</button>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif

                        <section class="rounded-[5px] border border-slate-200 bg-white p-4 sm:p-6">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h2 class="text-xl font-extrabold text-slate-950">Quản lý API key</h2>
                                    <p class="mt-1 text-sm leading-6 text-slate-500">Mỗi request gửi cặp header <code>X-API-KEY</code> và <code>X-API-SECRET</code>. Không sử dụng Bearer token.</p>
                                </div>
                                <a class="client-button-secondary min-h-11 shrink-0 bg-white" href="{{ route('account.profile.api.docs') }}"><i class="bx bx-book-open text-lg" aria-hidden="true"></i>Xem tài liệu</a>
                            </div>
                            <form class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end" method="POST" action="{{ route('account.profile.api.store') }}">
                                @csrf
                                <label class="client-label min-w-0 flex-1">Tên API key
                                    <input class="client-input" name="name" value="{{ old('name') }}" maxlength="80" placeholder="Ví dụ: Máy chủ thanh toán" required autocomplete="off">
                                    @error('name')<span class="text-xs font-medium text-rose-600">{{ $message }}</span>@enderror
                                </label>
                                <button class="client-button min-h-11 shrink-0 justify-center" type="submit"><i class="bx bx-plus text-lg" aria-hidden="true"></i>Tạo API key</button>
                            </form>
                        </section>

                        <section class="overflow-hidden rounded-[5px] border border-slate-200 bg-white">
                            <div class="border-b border-slate-200 p-4"><h2 class="font-extrabold text-slate-950">API key đang hoạt động</h2></div>
                            <div class="divide-y divide-slate-100">
                                @forelse ($apiKeys as $apiKey)
                                    <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="min-w-0">
                                            <p class="truncate font-extrabold text-slate-950">{{ $apiKey->name }}</p>
                                            <code class="mt-1 block break-all text-xs text-indigo-700">{{ $apiKey->api_key }}</code>
                                            <p class="mt-1 text-xs leading-5 text-slate-500">Tạo {{ $apiKey->created_at?->format('d/m/Y H:i') }} · Dùng gần nhất {{ $apiKey->last_used_at?->format('d/m/Y H:i') ?? 'chưa dùng' }} · Hết hạn {{ $apiKey->expired_at?->format('d/m/Y') ?? 'không giới hạn' }}</p>
                                        </div>
                                        <form method="POST" action="{{ route('account.profile.api.destroy', $apiKey->id) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="client-button-secondary min-h-11 w-full border-rose-200 bg-white text-rose-700 hover:bg-rose-50 sm:w-auto" type="submit"><i class="bx bx-trash text-lg" aria-hidden="true"></i>Thu hồi</button>
                                        </form>
                                    </div>
                                @empty
                                    <div class="p-8 text-center text-sm text-slate-500">Bạn chưa tạo API key nào.</div>
                                @endforelse
                            </div>
                        </section>
                    </div>

                    <aside class="rounded-[5px] border border-rose-200 bg-rose-50 p-4">
                        <h2 class="flex items-center gap-2 font-extrabold text-rose-950"><i class="bx bx-error-circle text-lg" aria-hidden="true"></i>Bảo vệ API key</h2>
                        <ul class="mt-3 grid gap-2 text-sm leading-6 text-rose-900">
                            <li>• API key dùng để định danh; API secret dùng để xác thực và chỉ hiển thị một lần.</li>
                            <li>• Không lưu API secret trong mã nguồn hoặc gửi qua tin nhắn.</li>
                            <li>• Gửi request qua HTTPS và thu hồi key ngay khi nghi ngờ bị lộ.</li>
                        </ul>
                    </aside>
                </div>
            @elseif ($activeTab === 'api-docs')
                <x-client.api-documentation :documentation="$apiDocumentation" />
            @elseif ($activeTab === 'logs')
                <section class="overflow-hidden rounded-[5px] border border-slate-200 bg-white">
                    <div class="flex flex-col gap-1 border-b border-slate-200 p-4 sm:p-5">
                        <h2 class="text-xl font-extrabold text-slate-950">Lịch sử người dùng</h2>
                        <p class="text-sm text-slate-500">Theo dõi các lần đăng nhập và thao tác bảo mật gần đây.</p>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @forelse ($userLogs ?? [] as $log)
                            <article class="grid min-w-0 gap-3 p-4 sm:grid-cols-[2.5rem_minmax(0,1fr)_auto] sm:items-start sm:p-5">
                                <span class="grid h-10 w-10 place-items-center rounded-[5px] bg-slate-100 text-xl text-slate-500"><i class="bx bx-history" aria-hidden="true"></i></span>
                                <div class="min-w-0">
                                    <h3 class="font-extrabold text-slate-950">{{ $log->description ?: $log->action }}</h3>
                                    <p class="mt-1 break-words text-xs leading-5 text-slate-500">Hành động: {{ $log->action }}@if ($log->ip) · IP: {{ $log->ip }}@endif</p>
                                    @if ($log->user_agent)<p class="mt-1 break-all text-xs leading-5 text-slate-400">{{ $log->user_agent }}</p>@endif
                                </div>
                                <time class="text-xs font-medium text-slate-400" datetime="{{ $log->created_at?->toISOString() }}">{{ $log->created_at?->format('d/m/Y H:i') }}</time>
                            </article>
                        @empty
                            <div class="p-10 text-center text-sm text-slate-500">Chưa có hoạt động nào được ghi nhận.</div>
                        @endforelse
                    </div>
                    @if ($userLogs?->hasPages())<div class="border-t border-slate-200 p-4">{{ $userLogs->links() }}</div>@endif
                </section>
            @elseif ($activeTab === 'wallet')
                <div class="grid gap-3">
                    <section class="flex flex-col gap-3 rounded-[5px] border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400">Số dư hiện tại</p>
                            <p class="mt-1 text-2xl font-extrabold tabular-nums text-slate-950">{{ number_format((float) ($wallet?->balance ?? 0), 0, ',', '.') }}đ</p>
                        </div>
                        <a class="client-button min-h-11 justify-center" href="{{ route('wallet.deposit.index') }}"><i class="bx bx-plus-circle text-lg" aria-hidden="true"></i>Nạp tiền</a>
                    </section>

                    <section class="overflow-hidden rounded-[5px] border border-slate-200 bg-white">
                        <div class="border-b border-slate-200 p-4 sm:p-5">
                            <h2 class="text-xl font-extrabold text-slate-950">Lịch sử dòng tiền</h2>
                            <p class="mt-1 text-sm text-slate-500">Toàn bộ biến động số dư được ghi theo sổ cái ví.</p>
                        </div>
                        <div class="w-full min-w-0 overflow-x-auto overscroll-x-contain" tabindex="0" role="region" aria-label="Bảng lịch sử dòng tiền" data-wallet-datatable>
                            <table class="w-full min-w-[880px] table-auto text-left text-sm">
                                <thead class="border-b border-slate-200 bg-slate-50 text-xs font-extrabold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="w-14 px-4 py-3 text-center">STT</th>
                                        <th class="px-4 py-3">Giao dịch<br><span class="normal-case tracking-normal text-slate-400">Thời gian tạo</span></th>
                                        <th class="px-4 py-3">Loại</th>
                                        <th class="px-4 py-3">Biến động số dư<br><span class="normal-case tracking-normal text-slate-400">Số dư trước ± Số tiền = Số dư sau</span></th>
                                        <th class="px-4 py-3 text-right">Trạng thái</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @forelse ($walletTransactions ?? [] as $transaction)
                                        @php
                                            $balanceBefore = (float) $transaction->balance_before;
                                            $balanceAfter = (float) $transaction->balance_after;
                                            $balanceChange = $balanceAfter - $balanceBefore;
                                            $isIncrease = $balanceChange >= 0;
                                            $operation = $isIncrease ? '+' : '−';
                                            $transactionLabel = match ($transaction->type) {
                                                'credit' => 'Tiền vào',
                                                'debit' => 'Tiền ra',
                                                'refund' => 'Hoàn tiền',
                                                'hold' => 'Tạm giữ',
                                                'release' => 'Giải phóng',
                                                'adjustment' => $isIncrease ? 'Điều chỉnh tăng' : 'Điều chỉnh giảm',
                                                default => $isIncrease ? 'Cộng tiền' : 'Trừ tiền',
                                            };
                                            $status = match ($transaction->status) {
                                                'success' => ['Thành công', 'border-emerald-200 bg-emerald-50 text-emerald-700'],
                                                'failed' => ['Thất bại', 'border-rose-200 bg-rose-50 text-rose-700'],
                                                'cancelled' => ['Đã hủy', 'border-slate-200 bg-slate-100 text-slate-600'],
                                                default => ['Đang xử lý', 'border-amber-200 bg-amber-50 text-amber-700'],
                                            };
                                        @endphp
                                        <tr class="transition hover:bg-slate-50/80">
                                            <td class="px-4 py-4 text-center align-middle font-semibold text-slate-500">{{ ($walletTransactions?->firstItem() ?? 1) + $loop->index }}</td>
                                            <td class="min-w-64 px-4 py-4 align-top">
                                                <p class="break-words font-extrabold text-slate-950">{{ $transaction->description ?: 'Giao dịch ví' }}</p>
                                                <time class="mt-1 block whitespace-nowrap text-xs text-slate-500" datetime="{{ $transaction->created_at?->toISOString() }}">{{ $transaction->created_at?->format('d/m/Y H:i') }}</time>
                                            </td>
                                            <td class="px-4 py-4 align-top">
                                                <span @class(['inline-flex items-center gap-1.5 rounded-[5px] border px-2.5 py-1 text-xs font-bold', 'border-emerald-200 bg-emerald-50 text-emerald-700' => $isIncrease, 'border-rose-200 bg-rose-50 text-rose-700' => ! $isIncrease])>
                                                    <i class="bx {{ $isIncrease ? 'bx-trending-up' : 'bx-trending-down' }} text-base" aria-hidden="true"></i>{{ $transactionLabel }}
                                                </span>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-4 align-top font-bold tabular-nums" data-wallet-operation="{{ $isIncrease ? '+' : '-' }}" aria-label="{{ number_format($balanceBefore, 0, ',', '.') }} đồng {{ $isIncrease ? 'cộng' : 'trừ' }} {{ number_format(abs($balanceChange), 0, ',', '.') }} đồng bằng {{ number_format($balanceAfter, 0, ',', '.') }} đồng">
                                                <span class="text-slate-700">{{ number_format($balanceBefore, 0, ',', '.') }}đ</span>
                                                <span @class(['mx-1.5 text-base font-extrabold', 'text-emerald-700' => $isIncrease, 'text-rose-700' => ! $isIncrease])>{{ $operation }}</span>
                                                <span @class(['font-extrabold', 'text-emerald-700' => $isIncrease, 'text-rose-700' => ! $isIncrease])>{{ number_format(abs($balanceChange), 0, ',', '.') }}đ</span>
                                                <span class="mx-1.5 text-slate-400">=</span>
                                                <span class="font-extrabold text-slate-950">{{ number_format($balanceAfter, 0, ',', '.') }}đ</span>
                                            </td>
                                            <td class="px-4 py-4 text-right align-top"><span class="inline-flex rounded-[5px] border px-2.5 py-1 text-xs font-bold {{ $status[1] }}">{{ $status[0] }}</span></td>
                                        </tr>
                                    @empty
                                        <tr><td class="px-6 py-12 text-center text-slate-500" colspan="5">Chưa có giao dịch ví.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <footer class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm text-slate-500">Hiển thị <strong class="text-slate-900">{{ $walletTransactions?->firstItem() ?? 0 }}–{{ $walletTransactions?->lastItem() ?? 0 }}</strong> trong tổng số <strong class="text-slate-900">{{ number_format($walletTransactions?->total() ?? 0) }}</strong> giao dịch.</p>
                            @if ($walletTransactions?->hasPages())<div class="min-w-0">{{ $walletTransactions->onEachSide(1)->links() }}</div>@endif
                        </footer>
                    </section>
                </div>
            @endif
        </div>
    </div>
</section>
@endsection
