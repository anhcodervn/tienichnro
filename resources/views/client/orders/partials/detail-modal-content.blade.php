@php
    $paymentStatus = $order->payment_status->value;
    $orderStatus = $order->order_status->value;
    $paymentLabels = ['pending' => 'Chờ thanh toán', 'paid' => 'Đã thanh toán', 'expired' => 'Hết hạn', 'cancelled' => 'Đã hủy', 'refunded' => 'Đã hoàn tiền'];
    $orderLabels = ['pending' => 'Chờ xử lý', 'processing' => 'Đang xử lý', 'completed' => 'Hoàn thành', 'failed' => 'Thất bại', 'cancelled' => 'Đã hủy'];
    $paymentClasses = ['pending' => 'border-amber-200 bg-amber-50 text-amber-800', 'paid' => 'border-emerald-200 bg-emerald-50 text-emerald-800', 'expired' => 'border-slate-200 bg-slate-100 text-slate-600', 'cancelled' => 'border-slate-200 bg-slate-100 text-slate-600', 'refunded' => 'border-blue-200 bg-blue-50 text-blue-700'];
    $orderClasses = ['pending' => 'border-amber-200 bg-amber-50 text-amber-800', 'processing' => 'border-blue-200 bg-blue-50 text-blue-800', 'completed' => 'border-emerald-200 bg-emerald-50 text-emerald-800', 'failed' => 'border-rose-200 bg-rose-50 text-rose-800', 'cancelled' => 'border-slate-200 bg-slate-100 text-slate-600'];
    $recipientFields = collect($order->checkout_fields_snapshot ?? []);
    $isTerminal = in_array($orderStatus, ['completed', 'failed', 'cancelled'], true);
    $progressSteps = [
        ['label' => 'Đã tạo đơn', 'description' => 'Hệ thống đã tiếp nhận', 'time' => $order->created_at, 'completed' => true],
        ['label' => 'Thanh toán', 'description' => 'Chờ xác nhận giao dịch', 'time' => $order->paid_at, 'completed' => $order->paid_at !== null],
        ['label' => 'Đang xử lý', 'description' => 'Đang nạp vào game', 'time' => $order->processing_at, 'completed' => $order->processing_at !== null],
        ['label' => 'Hoàn thành', 'description' => $orderStatus === 'failed' ? 'Đơn cần được kiểm tra' : 'Kiểm tra kết quả trong game', 'time' => $order->completed_at, 'completed' => $order->completed_at !== null],
    ];
@endphp

<article class="grid gap-4" data-order-detail-state data-order-terminal="{{ $isTerminal ? 'true' : 'false' }}">
    <header class="rounded-[5px] border border-slate-200 bg-white p-4 sm:p-5">
        <div class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Mã đơn hàng</p>
                <div class="mt-1 flex min-w-0 items-center gap-2"><h3 class="break-all text-xl font-extrabold text-slate-950">{{ $order->code }}</h3><button class="grid h-8 w-8 shrink-0 place-items-center rounded-[5px] border border-slate-300 text-slate-500" type="button" data-copy="{{ $order->code }}" aria-label="Sao chép mã đơn"><i class="bx bx-copy" aria-hidden="true"></i></button></div>
                <p class="mt-1 text-xs text-slate-500">Tạo lúc {{ $order->created_at?->format('H:i · d/m/Y') }}</p>
            </div>
            <div class="flex flex-wrap gap-2"><span class="inline-flex rounded-[5px] border px-2.5 py-1 text-xs font-bold {{ $paymentClasses[$paymentStatus] ?? 'border-slate-200 bg-slate-50 text-slate-600' }}">{{ $paymentLabels[$paymentStatus] ?? $paymentStatus }}</span><span class="inline-flex rounded-[5px] border px-2.5 py-1 text-xs font-bold {{ $orderClasses[$orderStatus] ?? 'border-slate-200 bg-slate-50 text-slate-600' }}">{{ $orderLabels[$orderStatus] ?? $orderStatus }}</span></div>
        </div>
    </header>

    <section class="rounded-[5px] border border-slate-200 bg-white p-4 sm:p-5" aria-label="Tiến trình đơn hàng">
        <div class="flex items-center justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-[0.12em] text-cyan-700">Theo dõi xử lý</p><h3 class="mt-1 font-extrabold text-slate-950">Tiến trình đơn hàng</h3></div>@unless ($isTerminal)<span class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500"><span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span>Tự cập nhật</span>@endunless</div>
        <ol class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($progressSteps as $index => $step)
                <li @class(['flex gap-3 rounded-[5px] border p-3', 'border-emerald-200 bg-emerald-50' => $step['completed'], 'border-slate-200 bg-slate-50' => ! $step['completed']])><span @class(['grid h-8 w-8 shrink-0 place-items-center rounded-[5px] text-xs font-extrabold', 'bg-emerald-600 text-white' => $step['completed'], 'bg-slate-200 text-slate-500' => ! $step['completed']])>@if ($step['completed'])<i class="bx bx-check text-lg" aria-hidden="true"></i>@else{{ $index + 1 }}@endif</span><div class="min-w-0"><strong class="block text-sm text-slate-950">{{ $step['label'] }}</strong><span class="mt-0.5 block text-xs leading-5 text-slate-500">{{ $step['time']?->format('d/m H:i') ?? $step['description'] }}</span></div></li>
            @endforeach
        </ol>
    </section>

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="overflow-hidden rounded-[5px] border border-slate-200 bg-white">
            <h3 class="border-b border-slate-200 bg-slate-50 px-4 py-3 font-extrabold text-slate-950">Thông tin đơn hàng</h3>
            <dl class="divide-y divide-slate-100 text-sm">
                @foreach ([['Game', $order->game?->name ?? 'Game đã xóa'], ['Máy chủ', $order->server?->name ?? 'Không xác định'], ['Gói nạp', $order->package_name], ['Số lượng', number_format($order->quantity, 0, ',', '.')], ['Email', $order->email], ['Thanh toán', $order->payment_method->value === 'wallet' ? 'Số dư tài khoản' : 'Ngân hàng / ATM']] as [$label, $value])
                    <div class="grid min-w-0 grid-cols-[7rem_minmax(0,1fr)] gap-3 px-4 py-3"><dt class="text-slate-500">{{ $label }}</dt><dd class="min-w-0 break-words text-right font-bold text-slate-900">{{ $value }}</dd></div>
                @endforeach
                <div class="grid grid-cols-[7rem_minmax(0,1fr)] gap-3 bg-cyan-50 px-4 py-3"><dt class="font-bold text-cyan-900">Tổng cộng</dt><dd class="text-right text-lg font-extrabold text-cyan-800">{{ number_format((int) $order->total_amount, 0, ',', '.') }}đ</dd></div>
            </dl>
        </section>

        <section class="overflow-hidden rounded-[5px] border border-slate-200 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-4 py-3"><h3 class="font-extrabold text-slate-950">Tài khoản nhận</h3><p class="mt-0.5 text-xs text-slate-500">{{ $order->recipients->count() }} tài khoản trong đơn</p></div>
            <div class="max-h-72 divide-y divide-slate-100 overflow-y-auto">
                @forelse ($order->recipients as $recipient)
                    @php($recipientLabel = match ($recipient->status) { 'processing' => 'Đang xử lý', 'completed' => 'Hoàn thành', 'failed' => 'Thất bại', default => 'Đang chờ' })
                    <article class="p-4">
                        <div class="flex items-center justify-between gap-3"><strong class="text-sm text-slate-950">Tài khoản #{{ $recipient->position }}</strong><span @class(['rounded-[5px] px-2 py-1 text-[11px] font-bold', 'bg-emerald-100 text-emerald-800' => $recipient->status === 'completed', 'bg-blue-100 text-blue-800' => $recipient->status === 'processing', 'bg-rose-100 text-rose-800' => $recipient->status === 'failed', 'bg-amber-100 text-amber-800' => $recipient->status === 'pending'])>{{ $recipientLabel }}</span></div>
                        <dl class="mt-2 grid gap-1.5 text-xs">
                            @foreach ($recipientFields as $field)
                                @php($fieldKey = (string) ($field['key'] ?? ''))
                                <div class="grid min-w-0 grid-cols-[6rem_minmax(0,1fr)] gap-2"><dt class="text-slate-500">{{ $field['label'] ?? $fieldKey }}</dt><dd class="min-w-0 break-all text-right font-semibold text-slate-900">{{ $fieldKey !== '' ? ($recipient->recipient_data[$fieldKey] ?? '—') : '—' }}</dd></div>
                            @endforeach
                            <div class="grid grid-cols-[6rem_minmax(0,1fr)] gap-2"><dt class="text-slate-500">Số lượng</dt><dd class="text-right font-extrabold text-cyan-800">{{ $recipient->quantity }}</dd></div>
                        </dl>
                    </article>
                @empty
                    <p class="p-5 text-sm text-slate-500">{{ filled($order->game_account) ? 'Tài khoản: '.$order->game_account : 'Chưa có dữ liệu tài khoản nhận.' }}</p>
                @endforelse
            </div>
        </section>
    </div>

    <footer class="flex flex-col gap-2 sm:flex-row sm:justify-end">
        @if ($paymentStatus === 'pending' && $order->payment_method->value === 'bank_transfer')<a class="client-button gap-2" href="{{ route('orders.payment', $order) }}"><i class="bx bx-credit-card text-lg" aria-hidden="true"></i>Thanh toán đơn</a>@endif
        <a class="client-button-secondary gap-2 bg-white" href="{{ route('orders.show', $order) }}"><i class="bx bx-link-external text-lg" aria-hidden="true"></i>Mở trang đầy đủ</a>
    </footer>
</article>
