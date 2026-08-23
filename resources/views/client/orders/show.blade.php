@extends('client.layouts.app')

@section('title', 'Đơn hàng '.$order->code)
@section('robots', 'noindex,nofollow')

@php
    $paymentStatus = $order->payment_status->value;
    $orderStatus = $order->order_status->value;
    $paymentStatusLabel = match ($paymentStatus) {
        'paid' => 'Đã thanh toán',
        'expired' => 'Hết hạn thanh toán',
        'cancelled' => 'Đã hủy thanh toán',
        'refunded' => 'Đã hoàn tiền',
        default => 'Chờ thanh toán',
    };
    $orderStatusLabel = match ($orderStatus) {
        'processing' => 'Đang xử lý',
        'completed' => 'Hoàn thành',
        'failed' => 'Xử lý thất bại',
        'cancelled' => 'Đã hủy',
        default => 'Chờ xử lý',
    };
    $paymentMethodLabel = $order->payment_method->value === 'wallet' ? 'Số dư tài khoản' : 'Ngân hàng / ATM';
    $recipientFields = collect($order->checkout_fields_snapshot ?? []);
    $recipientCount = $order->recipients->count();
    $displayRecipientCount = $recipientCount > 0 ? $recipientCount : (filled($order->game_account) ? 1 : 0);
    $isTerminalFailure = in_array($orderStatus, ['failed', 'cancelled'], true);
    $isCompleted = $orderStatus === 'completed';
    $shouldUseRealtime = ! $isTerminalFailure && ! $isCompleted;
    $needsBankPayment = $paymentStatus === 'pending' && $order->payment_method->value === 'bank_transfer';
    $progressSteps = [
        ['label' => 'Đã tạo đơn', 'description' => 'Hệ thống đã tiếp nhận', 'time' => $order->created_at, 'completed' => true],
        ['label' => 'Thanh toán', 'description' => 'Xác nhận giao dịch', 'time' => $order->paid_at, 'completed' => $order->paid_at !== null],
        ['label' => 'Đang xử lý', 'description' => 'Nạp dữ liệu vào game', 'time' => $order->processing_at, 'completed' => $order->processing_at !== null],
        ['label' => 'Hoàn thành', 'description' => 'Kiểm tra trong game', 'time' => $order->completed_at, 'completed' => $order->completed_at !== null],
    ];
@endphp

@section('content')
<section
    class="client-container py-6 sm:py-10"
    @if ($order->user_id === null)
        data-guest-order-code="{{ $order->code }}"
        data-guest-order-created-at="{{ $order->created_at?->toISOString() }}"
    @endif
    @if ($shouldUseRealtime)
        data-order-realtime-channel="{{ $realtimeChannel }}"
        data-order-payment-status="{{ $paymentStatus }}"
        data-order-status="{{ $orderStatus }}"
        data-order-page="detail"
    @endif
>
    <div class="mx-auto max-w-6xl">
        <nav class="mb-4 flex min-w-0 items-center gap-2 text-xs font-semibold text-slate-500 sm:text-sm" aria-label="Điều hướng đơn hàng">
            <a class="shrink-0 transition hover:text-emerald-700" href="{{ route('home') }}">Trang chủ</a>
            <i class="bx bx-chevron-right text-base" aria-hidden="true"></i>
            <a class="shrink-0 transition hover:text-emerald-700" href="{{ $order->user_id !== null ? route('account.orders.index') : route('orders.lookup') }}">Lịch sử đơn hàng</a>
            <i class="bx bx-chevron-right text-base" aria-hidden="true"></i>
            <span class="truncate text-slate-800" aria-current="page">{{ $order->code }}</span>
        </nav>

        <header class="overflow-hidden rounded-[5px] border border-slate-200 bg-white shadow-sm">
            <div class="grid gap-5 border-b border-slate-200 bg-slate-50 px-4 py-5 sm:px-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                <div class="min-w-0">
                    <p class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-[0.14em] text-cyan-700">
                        <i class="bx bx-receipt text-lg" aria-hidden="true"></i>
                        Chi tiết đơn hàng
                    </p>
                    <div class="mt-2 flex min-w-0 flex-wrap items-center gap-2">
                        <h1 class="break-all text-2xl font-extrabold text-slate-950 sm:text-3xl">{{ $order->code }}</h1>
                        <button type="button" class="grid h-9 w-9 shrink-0 place-items-center rounded-[5px] border border-slate-300 bg-white text-slate-600 transition hover:border-cyan-500 hover:text-cyan-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600" data-copy="{{ $order->code }}" aria-label="Sao chép mã đơn {{ $order->code }}">
                            <i class="bx bx-copy text-lg" aria-hidden="true"></i>
                        </button>
                    </div>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Tạo lúc {{ $order->created_at?->format('H:i · d/m/Y') }} · Thông tin được cập nhật theo trạng thái xử lý thực tế.</p>
                    @if ($shouldUseRealtime)
                        <p class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500" data-order-realtime-connection role="status">
                            <span class="h-2 w-2 rounded-full bg-amber-400" data-order-realtime-dot aria-hidden="true"></span>
                            <span data-order-realtime-text>Đang kết nối cập nhật realtime...</span>
                        </p>
                    @endif
                </div>

                <div class="flex flex-wrap gap-2 lg:max-w-xs lg:justify-end">
                    <span @class([
                        'inline-flex items-center gap-1.5 rounded-[5px] px-3 py-2 text-xs font-extrabold',
                        'bg-emerald-100 text-emerald-800' => $paymentStatus === 'paid',
                        'bg-amber-100 text-amber-900' => $paymentStatus === 'pending',
                        'bg-rose-100 text-rose-800' => in_array($paymentStatus, ['expired', 'cancelled'], true),
                        'bg-cyan-100 text-cyan-800' => $paymentStatus === 'refunded',
                    ])>
                        <i @class(['bx text-base', 'bx-check-circle' => $paymentStatus === 'paid', 'bx-time-five' => $paymentStatus === 'pending', 'bx-error-circle' => in_array($paymentStatus, ['expired', 'cancelled'], true), 'bx-undo' => $paymentStatus === 'refunded']) aria-hidden="true"></i>
                        <span data-order-payment-status-text>{{ $paymentStatusLabel }}</span>
                    </span>
                    <span @class([
                        'inline-flex items-center gap-1.5 rounded-[5px] px-3 py-2 text-xs font-extrabold',
                        'bg-emerald-100 text-emerald-800' => $orderStatus === 'completed',
                        'bg-blue-100 text-blue-800' => $orderStatus === 'processing',
                        'bg-amber-100 text-amber-900' => $orderStatus === 'pending',
                        'bg-rose-100 text-rose-800' => in_array($orderStatus, ['failed', 'cancelled'], true),
                    ])>
                        <i @class(['bx text-base', 'bx-check-circle' => $orderStatus === 'completed', 'bx-loader-circle bx-spin' => $orderStatus === 'processing', 'bx-time-five' => $orderStatus === 'pending', 'bx-error-circle' => in_array($orderStatus, ['failed', 'cancelled'], true)]) aria-hidden="true"></i>
                        <span data-order-status-text>{{ $orderStatusLabel }}</span>
                    </span>
                </div>
            </div>

            <dl class="grid grid-cols-2 divide-x divide-y divide-slate-200 sm:grid-cols-4 sm:divide-y-0">
                <div class="min-w-0 p-4 sm:p-5">
                    <dt class="flex items-center gap-1.5 text-xs font-semibold text-slate-500"><i class="bx bx-joystick text-base text-cyan-700" aria-hidden="true"></i>Game</dt>
                    <dd class="mt-1 truncate font-extrabold text-slate-950">{{ $order->game?->name ?? 'Không xác định' }}</dd>
                </div>
                <div class="min-w-0 p-4 sm:p-5">
                    <dt class="flex items-center gap-1.5 text-xs font-semibold text-slate-500"><i class="bx bx-server text-base text-cyan-700" aria-hidden="true"></i>Máy chủ</dt>
                    <dd class="mt-1 truncate font-extrabold text-slate-950">{{ $order->server?->name ?? 'Không áp dụng' }}</dd>
                </div>
                <div class="min-w-0 p-4 sm:p-5">
                    <dt class="flex items-center gap-1.5 text-xs font-semibold text-slate-500"><i class="bx bx-group text-base text-cyan-700" aria-hidden="true"></i>Người nhận</dt>
                    <dd class="mt-1 font-extrabold text-slate-950">{{ $displayRecipientCount }} tài khoản</dd>
                </div>
                <div class="min-w-0 p-4 sm:p-5">
                    <dt class="flex items-center gap-1.5 text-xs font-semibold text-slate-500"><i class="bx bx-wallet text-base text-cyan-700" aria-hidden="true"></i>Tổng thanh toán</dt>
                    <dd class="mt-1 whitespace-nowrap font-extrabold text-cyan-800">{{ number_format((int) $order->total_amount, 0, ',', '.') }}đ</dd>
                </div>
            </dl>
        </header>

        @if ($isTerminalFailure)
            <div class="mt-5 flex gap-3 rounded-[5px] border border-rose-200 bg-rose-50 p-4 text-sm leading-6 text-rose-900" role="alert">
                <i class="bx bx-error-circle mt-0.5 shrink-0 text-xl" aria-hidden="true"></i>
                <div>
                    <strong>Đơn hàng chưa thể hoàn tất.</strong>
                    <p>Vui lòng giữ lại mã đơn và liên hệ hỗ trợ để được kiểm tra. Không tạo lại đơn nếu giao dịch đã được trừ tiền.</p>
                </div>
            </div>
        @elseif ($isCompleted)
            <div class="mt-5 flex gap-3 rounded-[5px] border border-emerald-200 bg-emerald-50 p-4 text-sm leading-6 text-emerald-900" role="status">
                <i class="bx bx-check-circle mt-0.5 shrink-0 text-xl" aria-hidden="true"></i>
                <div>
                    <strong>Đơn hàng đã hoàn thành.</strong>
                    <p>Hãy đăng nhập game và kiểm tra giá trị đã nhận. Liên hệ hỗ trợ nếu kết quả chưa đúng.</p>
                </div>
            </div>
        @endif

        <section class="client-card mt-5 p-4 sm:p-6" aria-labelledby="order-progress-title">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.12em] text-cyan-700">Theo dõi xử lý</p>
                    <h2 id="order-progress-title" class="mt-1 text-lg font-extrabold text-slate-950">Tiến trình đơn hàng</h2>
                </div>
                <p class="text-xs leading-5 text-slate-500">Mỗi bước được ghi nhận tự động bởi hệ thống.</p>
            </div>

            <ol class="mt-5 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($progressSteps as $index => $step)
                    <li @class([
                        'relative flex min-w-0 gap-3 rounded-[5px] border p-3.5',
                        'border-emerald-200 bg-emerald-50' => $step['completed'],
                        'border-slate-200 bg-slate-50' => ! $step['completed'],
                    ])>
                        <span @class([
                            'grid h-8 w-8 shrink-0 place-items-center rounded-[5px] text-xs font-extrabold',
                            'bg-emerald-600 text-white' => $step['completed'],
                            'bg-slate-200 text-slate-500' => ! $step['completed'],
                        ])>
                            @if ($step['completed'])
                                <i class="bx bx-check text-lg" aria-hidden="true"></i>
                            @else
                                {{ $index + 1 }}
                            @endif
                        </span>
                        <div class="min-w-0">
                            <strong class="block text-sm text-slate-950">{{ $step['label'] }}</strong>
                            <span class="mt-0.5 block text-xs text-slate-500">{{ $step['time']?->format('d/m H:i') ?? $step['description'] }}</span>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>

        <div class="mt-5 grid min-w-0 gap-5 lg:grid-cols-[minmax(0,1.55fr)_minmax(18rem,0.75fr)] lg:items-start">
            <div class="grid min-w-0 gap-5">
                <section class="client-card overflow-hidden" aria-labelledby="order-info-title">
                    <div class="border-b border-slate-200 px-4 py-4 sm:px-5">
                        <h2 id="order-info-title" class="flex items-center gap-2 font-extrabold text-slate-950"><i class="bx bx-file-detail text-xl text-cyan-700" aria-hidden="true"></i>Thông tin đơn</h2>
                    </div>
                    <dl class="divide-y divide-slate-100">
                        @foreach ([
                            ['Gói nạp', $order->package_name],
                            ['Tổng số lượng', number_format($order->quantity, 0, ',', '.')],
                            ['Hình thức mua', $order->purchase_mode === 'bulk' ? 'Nạp cho nhiều tài khoản' : 'Nạp cho một tài khoản'],
                            ['Email nhận trạng thái', $order->email],
                            ['Phương thức thanh toán', $paymentMethodLabel],
                        ] as [$label, $value])
                            <div class="grid min-w-0 gap-1 px-4 py-3.5 sm:grid-cols-[11rem_minmax(0,1fr)] sm:gap-4 sm:px-5">
                                <dt class="text-sm font-semibold text-slate-500">{{ $label }}</dt>
                                <dd class="min-w-0 break-words text-sm font-bold text-slate-900 sm:text-right">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>

                @if ($order->recipients->isNotEmpty())
                    <section class="client-card overflow-hidden" aria-labelledby="order-recipients-title">
                        <div class="border-b border-slate-200 px-4 py-4 sm:px-5">
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                                <div>
                                    <h2 id="order-recipients-title" class="flex items-center gap-2 font-extrabold text-slate-950"><i class="bx bx-user-check text-xl text-cyan-700" aria-hidden="true"></i>Thông tin nhận hàng</h2>
                                    <p class="mt-1 text-sm text-slate-500">{{ $order->purchase_mode === 'bulk' ? 'Danh sách tài khoản và số lượng đã đối soát.' : 'Thông tin tài khoản được dùng để nạp game.' }}</p>
                                </div>
                                <span class="text-xs font-bold text-cyan-700">
                                    {{ $recipientCount }} tài khoản · {{ number_format($order->quantity, 0, ',', '.') }} lượt nạp
                                    <span class="block text-slate-500" data-order-recipient-summary>
                                        {{ $order->recipients->where('status', 'completed')->count() }}/{{ $recipientCount }} tài khoản hoàn tất
                                    </span>
                                </span>
                            </div>
                        </div>

                        <div class="grid gap-3 p-3 sm:hidden">
                            @foreach ($order->recipients as $recipient)
                                @php
                                    $recipientStatusLabel = match ($recipient->status) {
                                        'processing' => 'Đang xử lý',
                                        'completed' => 'Hoàn thành',
                                        'failed' => 'Thất bại',
                                        default => 'Đang chờ',
                                    };
                                @endphp
                                <article class="min-w-0 rounded-[5px] border border-slate-200 bg-slate-50 p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <strong class="text-sm text-slate-950">Tài khoản #{{ $recipient->position }}</strong>
                                        <span @class([
                                            'rounded-[5px] px-2 py-1 text-[11px] font-extrabold',
                                            'bg-emerald-100 text-emerald-800' => $recipient->status === 'completed',
                                            'bg-blue-100 text-blue-800' => $recipient->status === 'processing',
                                            'bg-rose-100 text-rose-800' => $recipient->status === 'failed',
                                            'bg-amber-100 text-amber-900' => $recipient->status === 'pending',
                                        ])>{{ $recipientStatusLabel }}</span>
                                    </div>
                                    <dl class="mt-3 grid gap-2">
                                        @foreach ($recipientFields as $field)
                                            @php($fieldKey = (string) ($field['key'] ?? ''))
                                            <div class="grid min-w-0 grid-cols-[7rem_minmax(0,1fr)] gap-2 text-sm">
                                                <dt class="text-slate-500">{{ $field['label'] ?? $fieldKey }}</dt>
                                                <dd class="min-w-0 break-all text-right font-bold text-slate-900">{{ $fieldKey !== '' ? ($recipient->recipient_data[$fieldKey] ?? '—') : '—' }}</dd>
                                            </div>
                                        @endforeach
                                        <div class="grid grid-cols-[7rem_minmax(0,1fr)] gap-2 border-t border-slate-200 pt-2 text-sm">
                                            <dt class="text-slate-500">Số lượng</dt>
                                            <dd class="text-right font-extrabold text-cyan-800">{{ $recipient->quantity }}</dd>
                                        </div>
                                    </dl>
                                </article>
                            @endforeach
                        </div>

                        <div class="home-table-scroll hidden sm:block">
                            <table class="home-history-table">
                                <thead>
                                    <tr>
                                        <th scope="col">#</th>
                                        @foreach ($recipientFields as $field)
                                            <th scope="col">{{ $field['label'] ?? $field['key'] }}</th>
                                        @endforeach
                                        <th scope="col">Số lượng</th>
                                        <th scope="col">Trạng thái</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($order->recipients as $recipient)
                                        <tr>
                                            <td class="font-bold text-slate-500">{{ $recipient->position }}</td>
                                            @foreach ($recipientFields as $field)
                                                @php($fieldKey = (string) ($field['key'] ?? ''))
                                                <td class="break-all font-semibold text-slate-900">{{ $fieldKey !== '' ? ($recipient->recipient_data[$fieldKey] ?? '—') : '—' }}</td>
                                            @endforeach
                                            <td class="font-extrabold text-cyan-800">{{ $recipient->quantity }}</td>
                                            <td>
                                                <span @class([
                                                    'whitespace-nowrap rounded-[5px] px-2 py-1 text-xs font-bold',
                                                    'bg-emerald-100 text-emerald-800' => $recipient->status === 'completed',
                                                    'bg-blue-100 text-blue-800' => $recipient->status === 'processing',
                                                    'bg-rose-100 text-rose-800' => $recipient->status === 'failed',
                                                    'bg-amber-100 text-amber-900' => $recipient->status === 'pending',
                                                ])>{{ match ($recipient->status) { 'processing' => 'Đang xử lý', 'completed' => 'Hoàn thành', 'failed' => 'Thất bại', default => 'Đang chờ' } }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @elseif (filled($order->game_account))
                    <section class="client-card overflow-hidden" aria-labelledby="order-recipient-legacy-title">
                        <div class="border-b border-slate-200 px-4 py-4 sm:px-5">
                            <h2 id="order-recipient-legacy-title" class="flex items-center gap-2 font-extrabold text-slate-950"><i class="bx bx-user-check text-xl text-cyan-700" aria-hidden="true"></i>Thông tin nhận hàng</h2>
                            <p class="mt-1 text-sm text-slate-500">Thông tin tài khoản được dùng để nạp game.</p>
                        </div>
                        <dl class="divide-y divide-slate-100">
                            <div class="grid min-w-0 gap-1 px-4 py-3.5 sm:grid-cols-[11rem_minmax(0,1fr)] sm:gap-4 sm:px-5">
                                <dt class="text-sm font-semibold text-slate-500">Tài khoản game</dt>
                                <dd class="min-w-0 break-all text-sm font-bold text-slate-900 sm:text-right">{{ $order->game_account }}</dd>
                            </div>
                            @if (filled($order->game_character))
                                <div class="grid min-w-0 gap-1 px-4 py-3.5 sm:grid-cols-[11rem_minmax(0,1fr)] sm:gap-4 sm:px-5">
                                    <dt class="text-sm font-semibold text-slate-500">Tên nhân vật</dt>
                                    <dd class="min-w-0 break-all text-sm font-bold text-slate-900 sm:text-right">{{ $order->game_character }}</dd>
                                </div>
                            @endif
                            <div class="grid min-w-0 gap-1 px-4 py-3.5 sm:grid-cols-[11rem_minmax(0,1fr)] sm:gap-4 sm:px-5">
                                <dt class="text-sm font-semibold text-slate-500">Số lượng</dt>
                                <dd class="text-sm font-extrabold text-cyan-800 sm:text-right">{{ $order->quantity }}</dd>
                            </div>
                        </dl>
                    </section>
                @endif
            </div>

            <aside class="grid min-w-0 gap-4 lg:sticky lg:top-24">
                <section class="client-card overflow-hidden" aria-labelledby="order-summary-title">
                    <div class="border-b border-slate-200 bg-slate-50 px-4 py-4">
                        <h2 id="order-summary-title" class="flex items-center gap-2 font-extrabold text-slate-950"><i class="bx bx-calculator text-xl text-cyan-700" aria-hidden="true"></i>Tóm tắt thanh toán</h2>
                    </div>
                    <dl class="grid gap-3 p-4 text-sm">
                        <div class="flex items-center justify-between gap-4"><dt class="text-slate-500">Giá gốc</dt><dd class="font-bold text-slate-900">{{ number_format((int) $order->subtotal, 0, ',', '.') }}đ</dd></div>
                        <div class="flex items-center justify-between gap-4"><dt class="text-slate-500">Chiết khấu</dt><dd class="font-bold text-emerald-700">-{{ number_format((int) $order->discount_amount, 0, ',', '.') }}đ</dd></div>
                        <div class="flex items-start justify-between gap-4"><dt class="text-slate-500">Phương thức</dt><dd class="text-right font-bold text-slate-900">{{ $paymentMethodLabel }}</dd></div>
                        <div class="flex items-start justify-between gap-4"><dt class="text-slate-500">Thanh toán</dt><dd class="text-right font-bold text-slate-900" data-order-payment-status-text>{{ $paymentStatusLabel }}</dd></div>
                        <div class="mt-1 flex items-end justify-between gap-4 border-t border-slate-200 pt-4"><dt class="font-bold text-slate-900">Tổng cộng</dt><dd class="text-xl font-extrabold text-cyan-800">{{ number_format((int) $order->total_amount, 0, ',', '.') }}đ</dd></div>
                    </dl>

                    <div class="grid gap-2 border-t border-slate-200 bg-slate-50 p-4">
                        @if ($isTerminalFailure)
                            <a class="client-button w-full gap-2" href="{{ route('content.contact') }}"><i class="bx bx-message-circle-dots text-lg" aria-hidden="true"></i>Liên hệ hỗ trợ</a>
                        @elseif ($isCompleted)
                            <a class="client-button w-full gap-2" href="{{ route('home') }}"><i class="bx bx-refresh-cw text-lg" aria-hidden="true"></i>Tạo đơn mới</a>
                        @elseif ($needsBankPayment)
                            <a class="client-button w-full gap-2" href="{{ route('orders.payment', $order) }}"><i class="bx bx-credit-card text-lg" aria-hidden="true"></i>Thanh toán đơn hàng</a>
                        @else
                            <div class="flex gap-2 rounded-[5px] border border-cyan-200 bg-cyan-50 p-3 text-xs leading-5 text-cyan-900">
                                <i class="bx bx-info-circle mt-0.5 shrink-0 text-lg" aria-hidden="true"></i>
                                <p>Đơn đang được hệ thống xử lý. Bạn có thể quay lại trang này để kiểm tra trạng thái mới nhất.</p>
                            </div>
                        @endif

                        @auth
                            <a class="client-button-secondary w-full gap-2 bg-white" href="{{ route('account.orders.index') }}"><i class="bx bx-history text-lg" aria-hidden="true"></i>Xem lịch sử đơn</a>
                        @else
                            <a class="client-button-secondary w-full gap-2 bg-white" href="{{ route('orders.lookup') }}"><i class="bx bx-history text-lg" aria-hidden="true"></i>Lịch sử đơn hàng</a>
                        @endauth
                    </div>
                </section>

                <div class="flex gap-3 rounded-[5px] border border-slate-200 bg-white p-4 text-xs leading-5 text-slate-600">
                    <i class="bx bx-shield-quarter mt-0.5 shrink-0 text-xl text-emerald-700" aria-hidden="true"></i>
                    <p><strong class="text-slate-900">Lưu ý bảo mật:</strong> chỉ cung cấp mã đơn cho bộ phận hỗ trợ chính thức. Không gửi mật khẩu game hoặc mã OTP.</p>
                </div>
            </aside>
        </div>
    </div>
</section>
@endsection
