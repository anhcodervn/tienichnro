@props(['order'])

@php
    $recipientFields = collect($order->checkout_fields_snapshot ?? []);
    $orderRows = [
        ['Game', $order->game?->name ?? 'Game đã xóa'],
        ['Máy chủ', $order->server?->name ?? 'Không xác định'],
        ['Gói nạp', $order->package_name],
        ['Hình thức', $order->purchase_mode === 'bulk' ? 'Nạp nhiều tài khoản' : 'Nạp một tài khoản'],
        ['Tổng số lượng', number_format((int) $order->quantity, 0, ',', '.').' thẻ'],
        ['Email nhận trạng thái', $order->email],
    ];
@endphp

<section class="overflow-hidden rounded-[5px] border border-slate-200 bg-white" aria-labelledby="payment-order-summary-title">
    <header class="border-b border-slate-200 bg-slate-50 px-4 py-4 sm:px-5">
        <h2 id="payment-order-summary-title" class="flex items-center gap-2 font-extrabold text-slate-950">
            <i class="bx bx-file-detail text-xl text-cyan-700" aria-hidden="true"></i>
            Thông tin đơn hàng
        </h2>
        <p class="mt-1 text-sm leading-6 text-slate-500">Kiểm tra lại gói nạp và tài khoản nhận trước khi chuyển khoản.</p>
    </header>

    <div class="grid min-w-0 lg:grid-cols-2">
        <dl class="divide-y divide-slate-100 border-b border-slate-200 text-sm lg:border-b-0 lg:border-r">
            @foreach ($orderRows as [$label, $value])
                <div class="grid min-w-0 gap-1 px-4 py-3.5 sm:grid-cols-[10rem_minmax(0,1fr)] sm:gap-4 sm:px-5">
                    <dt class="font-semibold text-slate-500">{{ $label }}</dt>
                    <dd class="min-w-0 break-words font-bold text-slate-900 sm:text-right">{{ $value }}</dd>
                </div>
            @endforeach
            <div class="grid min-w-0 gap-1 bg-cyan-50 px-4 py-4 sm:grid-cols-[10rem_minmax(0,1fr)] sm:items-center sm:gap-4 sm:px-5">
                <dt class="font-bold text-cyan-950">Tổng thanh toán</dt>
                <dd class="text-lg font-extrabold text-cyan-800 sm:text-right">{{ number_format((int) $order->total_amount, 0, ',', '.') }}đ</dd>
            </div>
        </dl>

        <div class="min-w-0">
            <div class="border-b border-slate-200 px-4 py-3.5 sm:px-5">
                <h3 class="font-extrabold text-slate-950">Tài khoản nhận</h3>
                <p class="mt-1 text-xs text-slate-500">{{ $order->recipients->count() }} tài khoản · {{ number_format((int) $order->quantity, 0, ',', '.') }} thẻ</p>
            </div>

            <div class="max-h-80 divide-y divide-slate-100 overflow-y-auto">
                @forelse ($order->recipients as $recipient)
                    <article class="p-4 sm:px-5">
                        <div class="flex items-center justify-between gap-3">
                            <strong class="text-sm text-slate-950">Tài khoản #{{ $recipient->position }}</strong>
                            <span class="shrink-0 rounded-[5px] bg-cyan-50 px-2 py-1 text-xs font-extrabold text-cyan-800">{{ $recipient->quantity }} thẻ</span>
                        </div>
                        <dl class="mt-3 grid gap-2 text-sm">
                            @foreach ($recipientFields as $field)
                                @php($fieldKey = (string) ($field['key'] ?? ''))
                                <div class="grid min-w-0 grid-cols-[7rem_minmax(0,1fr)] gap-3">
                                    <dt class="text-slate-500">{{ $field['label'] ?? $fieldKey }}</dt>
                                    <dd class="min-w-0 break-all text-right font-bold text-slate-900">{{ $fieldKey !== '' ? ($recipient->recipient_data[$fieldKey] ?? '—') : '—' }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </article>
                @empty
                    <div class="p-4 text-sm sm:px-5">
                        <p class="text-slate-500">Tài khoản game</p>
                        <p class="mt-1 break-all font-bold text-slate-900">{{ $order->game_account ?: 'Chưa có dữ liệu tài khoản nhận.' }}</p>
                        @if (filled($order->game_character))
                            <p class="mt-3 text-slate-500">Tên nhân vật</p>
                            <p class="mt-1 break-all font-bold text-slate-900">{{ $order->game_character }}</p>
                        @endif
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</section>
