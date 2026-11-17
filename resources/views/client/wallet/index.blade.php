@extends('client.layouts.app')
@section('title', 'Nạp tiền vào ví')
@section('robots', 'noindex,nofollow')
@section('content')
@php($tab = in_array(request()->query('tab'), ['history', 'transactions'], true) ? request()->query('tab') : 'deposit')
<section id="nap-tien" class="client-container grid scroll-mt-36 gap-5 py-7 sm:py-9">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div><p class="text-xs font-bold uppercase tracking-[0.16em] text-cyan-700">Ví của bạn</p><h1 class="mt-1 text-2xl font-extrabold text-slate-950 sm:text-3xl">Nạp tiền vào ví</h1><p class="mt-2 text-sm text-slate-500">Tạo yêu cầu nạp, sau đó quét QR để thanh toán.</p></div>
        <div class="client-card flex items-center gap-3 px-4 py-3"><span class="grid h-10 w-10 place-items-center rounded-[10px] bg-cyan-50 text-xl text-cyan-700"><i class="bx bx-wallet" aria-hidden="true"></i></span><div><p class="text-xs text-slate-500">Số dư ví hiện tại</p><p class="mt-1 font-extrabold text-slate-950">{{ number_format($wallet->balance, 0, ',', '.') }}đ</p></div></div>
    </div>
    <div class="client-card overflow-hidden">
        <nav class="flex gap-5 overflow-x-auto border-b border-slate-200 px-5 sm:px-6" aria-label="Quản lý ví">
            @foreach(['deposit' => 'Nạp tiền', 'history' => 'Lịch sử nạp tiền', 'transactions' => 'Lịch sử dòng tiền'] as $value => $label)
                <a href="{{ route('account.wallet', $value === 'deposit' ? [] : ['tab' => $value]) }}#nap-tien" @class(['shrink-0 border-b-2 py-4 text-sm font-bold', 'border-cyan-700 text-cyan-700' => $tab === $value, 'border-transparent text-slate-500 hover:text-cyan-700' => $tab !== $value]) @if($tab === $value) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        @if($tab === 'deposit')
            @if($recharge['enabled'])
            <form method="POST" action="{{ route('account.recharge.store') }}" class="grid gap-5 p-5 sm:p-6" data-recharge-form>
                @csrf
                <input type="hidden" name="request_id" value="{{ old('request_id', (string) \Illuminate\Support\Str::uuid()) }}">
                <div><h2 class="flex items-center gap-2 text-sm font-bold"><span class="grid h-6 w-6 place-items-center rounded-md bg-cyan-700 text-xs text-white">1</span>Chọn phương thức</h2>
                    <div class="mt-2 flex w-full items-center gap-3 rounded-[10px] border border-cyan-300 bg-cyan-50/60 p-3 sm:max-w-sm"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-md bg-cyan-700 text-xl text-white"><i class="bx bx-bank" aria-hidden="true"></i></span><div class="min-w-0 flex-1"><p class="font-bold text-slate-950">{{ $recharge['bank']['bank_name'] ?? 'Ngân hàng' }}</p><p class="mt-1 text-xs text-slate-500">Chuyển khoản tự động</p></div><i class="bx bxs-check-circle text-xl text-cyan-700" aria-label="Đã chọn"></i></div>
                </div>
                <div><h2 class="mb-2 flex items-center gap-2 text-sm font-bold"><span class="grid h-6 w-6 place-items-center rounded-md bg-cyan-700 text-xs text-white">2</span><label for="recharge-amount">Nhập số tiền</label></h2>
                    <div class="grid gap-2 rounded-[10px] border border-slate-200 bg-slate-50 p-3 sm:p-4">
                        <div class="relative flex items-center"><i class="bx bx-wallet pointer-events-none absolute left-4 text-xl text-cyan-700" aria-hidden="true"></i><input id="recharge-amount" name="amount" type="text" inputmode="numeric" pattern="[0-9]+|[0-9]{1,3}([.][0-9]{3})+" maxlength="10" value="{{ old('amount', '10.000') }}" required class="client-input !mt-0 !py-3 !pl-12 !pr-16 !text-2xl !font-extrabold focus:!border-cyan-700 focus:!ring-cyan-100 dark:focus:!ring-cyan-950 sm:!text-3xl" placeholder="10.000" aria-describedby="recharge-amount-help" data-recharge-amount><span class="pointer-events-none absolute right-4 text-xs font-bold text-slate-400">VND</span></div>
                        <div class="grid grid-cols-3 gap-2" aria-label="Chọn nhanh số tiền">
                            @foreach([10000, 20000, 50000, 100000, 200000, 500000] as $amount)
                                <button type="button" data-recharge-preset="{{ $amount }}" aria-pressed="false" class="min-h-11 rounded-[10px] border border-slate-300 bg-white px-1 py-2 text-xs font-bold text-slate-600 transition hover:border-cyan-300 aria-pressed:border-cyan-700 aria-pressed:bg-cyan-50 aria-pressed:text-cyan-700 dark:aria-pressed:bg-cyan-950 dark:aria-pressed:text-cyan-200 sm:text-sm">{{ number_format($amount, 0, ',', '.') }}đ</button>
                            @endforeach
                        </div>
                        <p id="recharge-amount-help" class="flex flex-wrap gap-x-5 gap-y-1 pt-1 text-xs text-slate-500"><span>Tối thiểu: <strong>10.000đ</strong></span><span>Tối đa: <strong>50.000.000đ</strong></span></p>
                    </div>
                </div>
                @error('amount')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
                @error('request_id')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
                <div class="grid gap-2"><p class="flex items-center justify-between text-sm text-slate-500" aria-live="polite">Thực nhận<strong class="text-emerald-700" data-recharge-summary>{{ number_format((int) str_replace('.', '', (string) old('amount', 10000)), 0, ',', '.') }}đ</strong></p><button type="submit" class="client-button !bg-cyan-700 hover:!bg-cyan-800 focus-visible:!ring-cyan-700 w-full disabled:cursor-wait disabled:opacity-60" data-recharge-submit><span data-recharge-submit-label>Tạo yêu cầu nạp</span></button><p class="text-center text-xs leading-5 text-slate-500">Mã QR và nội dung chuyển khoản sẽ hiển thị ở bước tiếp theo.</p></div>
            </form>
            @else
                <div class="p-6"><p class="text-sm text-slate-600">Nạp tiền tự động hiện chưa khả dụng.</p><a class="mt-3 inline-block text-sm font-bold text-cyan-700" href="{{ route('content.contact') }}">Liên hệ hỗ trợ</a></div>
            @endif
            <details class="border-t border-slate-200 bg-slate-50 p-5 text-sm sm:px-6"><summary class="cursor-pointer font-bold text-slate-700">Chi tiết yêu cầu & lưu ý</summary><ul class="mt-3 grid list-inside list-disc gap-2 leading-6 text-slate-500"><li>Chuyển đúng số tiền và giữ nguyên nội dung của từng lệnh.</li><li>Không dùng lại mã QR hoặc nội dung của lệnh cũ.</li><li>Tiền được cộng khi hệ thống xác nhận giao dịch thành công.</li></ul></details>
        @elseif($tab === 'history')
            <div class="overflow-x-auto"><table class="w-full whitespace-nowrap text-left text-sm"><thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="p-4">Mã lệnh</th><th class="p-4">Số tiền</th><th class="p-4">Trạng thái</th><th class="p-4">Thời gian</th><th class="p-4">Thao tác</th></tr></thead><tbody>@forelse($rechargeOrders as $rechargeOrder)<tr class="border-t border-slate-100"><td class="p-4 font-mono text-xs">{{ $rechargeOrder->code }}</td><td class="p-4 font-bold">{{ number_format($rechargeOrder->amount, 0, ',', '.') }}đ</td><td class="p-4">{{ \App\Models\WalletRechargeOrder::STATUS_LABELS[$rechargeOrder->status] }}</td><td class="p-4 text-xs">{{ $rechargeOrder->created_at->format('d/m/Y H:i') }}</td><td class="p-4"><a href="{{ route('account.recharge.show', $rechargeOrder) }}" class="font-semibold text-cyan-700">Xem lệnh</a></td></tr>@empty<tr><td colspan="5" class="p-6 text-center text-slate-500">Chưa có lệnh nạp tiền.</td></tr>@endforelse</tbody></table></div>
            <div class="p-4">{{ $rechargeOrders->links() }}</div>
        @else
            <div class="overflow-x-auto"><table class="w-full whitespace-nowrap text-left text-sm"><thead class="bg-slate-50 text-xs text-slate-600"><tr><th class="p-4">Thời gian</th><th class="p-4">Nội dung</th><th class="p-4">Số tiền</th><th class="p-4">Số dư trước</th><th class="p-4">Số dư sau</th></tr></thead><tbody>
                @forelse($transactions as $transaction)
                <tr class="border-t border-slate-100"><td class="p-4 text-xs">{{ $transaction->created_at->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i:s') }}</td><td class="max-w-sm whitespace-normal p-4">{{ $transaction->description }}</td><td class="p-4 font-bold {{ $transaction->direction === 'credit' ? 'text-emerald-700' : 'text-rose-700' }}">{{ $transaction->direction === 'credit' ? '+' : '-' }}{{ number_format($transaction->amount, 0, ',', '.') }}đ</td><td class="p-4">{{ number_format($transaction->balance_before, 0, ',', '.') }}đ</td><td class="p-4">{{ number_format($transaction->balance_after, 0, ',', '.') }}đ</td></tr>
                @empty<tr><td colspan="5" class="p-6 text-center text-slate-500">Chưa có giao dịch.</td></tr>@endforelse
            </tbody></table></div><div class="p-4">{{ $transactions->links() }}</div>
        @endif
    </div>
</section>
@endsection
