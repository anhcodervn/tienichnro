@extends('client.layouts.app')
@section('title', 'Thanh toán chuyển khoản')
@section('robots', 'noindex,nofollow')
@section('content')
@php($payable = in_array($order->status, ['pending', 'processing'], true) && $order->expires_at?->isFuture())
<section class="client-container grid gap-5 py-7 sm:py-9" data-wallet-recharge data-status="{{ $order->status }}" data-status-url="{{ route('account.recharge.status', $order) }}" data-expires-at="{{ $order->expires_at?->toIso8601String() }}">
    <div class="flex flex-wrap items-start justify-between gap-4"><div><a href="{{ route('account.wallet') }}#nap-tien" class="text-xs font-bold text-cyan-700">Quay lại nạp tiền</a><h1 class="mt-2 text-2xl font-extrabold text-slate-950">Thanh toán chuyển khoản</h1></div><div class="flex items-center gap-2"><span class="rounded-[10px] border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-bold text-amber-700">{{ \App\Models\WalletRechargeOrder::STATUS_LABELS[$order->status] }}</span>@if($payable)<span class="client-card px-3 py-2 font-mono text-xs font-bold text-cyan-700" data-recharge-countdown aria-label="Thời gian còn lại">--:--</span>@endif</div></div>
    @if($payable)
        <div class="grid items-stretch gap-4 lg:grid-cols-[0.95fr_1.05fr]">
            <div class="client-card p-4 sm:p-5" data-recharge-qr>
                <div class="mb-3 flex items-center justify-between gap-3"><h2 class="text-sm font-bold">Quét mã để chuyển khoản</h2><strong class="whitespace-nowrap text-xl text-cyan-700">{{ number_format($order->amount, 0, ',', '.') }}đ</strong></div>
                @if($qrUrl = $order->paymentQrUrl())
                    <img src="{{ $qrUrl }}" alt="Mã VietQR chuyển khoản {{ number_format($order->amount, 0, ',', '.') }} đồng cho lệnh {{ $order->code }}" class="mx-auto h-auto w-full max-w-sm rounded-[10px] border border-slate-200 bg-white" referrerpolicy="no-referrer" data-recharge-qr-image>
                    <p class="hidden mt-3 text-sm text-amber-700" data-recharge-qr-error>Không tải được mã QR. Bạn có thể chuyển khoản bằng thông tin bên cạnh.</p>
                @else
                    <p class="rounded-[10px] bg-slate-50 p-5 text-sm leading-6 text-slate-500">Chưa có mã QR cho ngân hàng này. Vui lòng chuyển khoản bằng thông tin bên cạnh.</p>
                @endif
            </div>
            <div class="client-card overflow-hidden"><div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 p-4"><h2 class="text-sm font-bold">Thông tin đơn</h2><span class="break-all font-mono text-xs text-slate-500">{{ $order->code }}</span></div>
                <dl class="divide-y divide-slate-100 text-sm">
                    @foreach(['Số tài khoản' => $order->account_number, 'Số tiền' => (string) $order->amount, 'Nội dung' => $order->transfer_content] as $label => $value)
                        <div class="flex items-center justify-between gap-3 px-4 py-4"><dt class="shrink-0 text-xs text-slate-500">{{ $label }}</dt><dd class="flex min-w-0 items-center gap-2"><strong class="break-all text-right {{ $label === 'Số tài khoản' ? 'text-slate-950' : 'text-cyan-700' }}">{{ $label === 'Số tiền' ? number_format($order->amount, 0, ',', '.').'đ' : $value }}</strong><button type="button" data-recharge-copy="{{ $value }}" aria-label="Sao chép {{ mb_strtolower($label) }}" class="grid h-8 w-8 shrink-0 place-items-center rounded-md text-lg text-slate-400 hover:bg-cyan-50 hover:text-cyan-700"><i class="bx bx-copy" aria-hidden="true"></i></button></dd></div>
                    @endforeach
                    <div class="flex justify-between gap-3 px-4 py-3"><dt class="text-xs text-slate-500">Ngân hàng</dt><dd class="text-right font-bold">{{ $order->bank_name }}</dd></div>
                    <div class="flex justify-between gap-3 px-4 py-3"><dt class="shrink-0 text-xs text-slate-500">Chủ tài khoản</dt><dd class="text-right font-bold">{{ $order->account_name }}</dd></div>
                    <div class="flex justify-between gap-3 px-4 py-3"><dt class="text-xs text-slate-500">Nhận vào ví</dt><dd class="font-bold text-emerald-700">{{ number_format($order->amount, 0, ',', '.') }}đ</dd></div>
                </dl><p class="px-4 py-3 text-xs text-cyan-700" role="status" data-recharge-copy-status></p>
            </div>
        </div>
        <p class="text-xs leading-6 text-amber-800"><strong>Quan trọng:</strong> Chuyển đúng số tiền và giữ nguyên nội dung để hệ thống tự đối soát. Không dùng lại nội dung này cho lần nạp khác.</p>
    @else
        <div class="client-card p-6">
            @if($order->status === 'paid')<p class="font-bold text-emerald-700">Đã cộng {{ number_format($order->amount, 0, ',', '.') }}đ vào ví của bạn.</p>
            @elseif(in_array($order->status, ['creation_failed', 'creating'], true))<p class="text-sm leading-6 text-amber-700">Chưa xác nhận được lệnh nạp với APIBankVN. Không chuyển tiền cho lệnh này. Quay lại ví để tạo yêu cầu mới hoặc liên hệ hỗ trợ nếu cần kiểm tra.</p>
            @else<p class="text-sm leading-6 text-slate-600">Lệnh đã kết thúc hoặc hết thời gian thanh toán. Không chuyển tiền cho lệnh này. Nếu đã chuyển khoản nhưng ví chưa nhận tiền, liên hệ hỗ trợ và cung cấp mã lệnh {{ $order->code }} để đối soát.</p>@endif
        </div>
    @endif
    <p class="hidden text-sm text-amber-700" role="status" data-recharge-expired>Lệnh đã hết thời gian thanh toán. Không tiếp tục chuyển khoản. Hệ thống đang kiểm tra trạng thái cuối cùng.</p>
    <button type="button" class="client-button !bg-cyan-700 hover:!bg-cyan-800 focus-visible:!ring-cyan-700 w-full" data-recharge-refresh>{{ $payable ? 'Tôi đã chuyển khoản' : 'Làm mới trạng thái' }}</button>
    <div class="flex flex-wrap justify-between gap-3 text-xs"><p data-recharge-live-status role="status" class="text-slate-500">Tự cập nhật khi thanh toán thành công.</p><a href="{{ route('account.wallet', ['tab' => 'history']) }}#nap-tien" class="font-semibold text-cyan-700">Lịch sử nạp tiền</a></div>
</section>
@endsection
