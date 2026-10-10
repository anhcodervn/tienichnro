@extends('client.layouts.app')

@section('title', 'Quản lý tài khoản')
@section('robots', 'noindex,nofollow')

@section('content')
@php
    $avatarUrl = old('avatar', $user->avatar);
    $tabs = [
        ['key' => 'profile', 'route' => 'account.index', 'icon' => 'bx-user-circle', 'title' => 'Thông tin user', 'description' => 'Hồ sơ và trạng thái tài khoản'],
        ['key' => 'password', 'route' => 'account.profile.password', 'icon' => 'bx-key', 'title' => 'Đổi mật khẩu', 'description' => 'Bảo mật phiên đăng nhập'],
        ['key' => 'logs', 'route' => 'account.profile.logs', 'icon' => 'bx-history', 'title' => 'Lịch sử người dùng', 'description' => 'Nhật ký thao tác tài khoản'],
        ['key' => 'wallet', 'route' => 'account.wallet', 'icon' => 'bx-wallet', 'title' => 'Ví & dòng tiền', 'description' => 'Số dư và lịch sử giao dịch'],
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
                                <li class="rounded-[5px] bg-white/70 p-3">Xác thực email để bảo vệ tài khoản.</li>
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
            @endif
        </div>
    </div>
</section>
@endsection
