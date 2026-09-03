@extends('client.layouts.app')

@section('title', 'Nạp tiền vào ví')
@section('robots', 'noindex,nofollow')

@section('content')
@php
    $activeTab = request()->query('tab') === 'history' ? 'history' : 'deposit';
@endphp

<section
    class="client-container py-5 sm:py-8 lg:py-10"
    data-wallet-deposit
    data-store-url="{{ route('client/wallet.deposit-requests.store') }}"
    data-payment-url-template="{{ url('/nap-tien/__CODE__/thanh-toan') }}"
>
    <script type="application/json" data-deposit-bonus-tiers>@json($bonusTiers)</script>
    <header class="mb-4 flex flex-col gap-3 sm:mb-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-indigo-600">Ví của bạn</p>
            <h1 class="mt-1 text-2xl font-extrabold text-slate-950 sm:text-3xl">Nạp tiền vào ví</h1>
            <p class="mt-1 text-sm leading-6 text-slate-500">Tạo yêu cầu nạp, sau đó quét QR để thanh toán an toàn.</p>
        </div>
        <div class="flex min-h-14 items-center gap-3 rounded-[5px] border border-slate-200 bg-white px-4 shadow-sm">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-[5px] bg-indigo-50 text-xl text-indigo-600">
                <i class="bx bx-wallet" aria-hidden="true"></i>
            </span>
            <div>
                <p class="text-xs font-semibold text-slate-500">Số dư ví hiện tại</p>
                <p class="text-base font-extrabold tabular-nums text-slate-950" data-deposit-wallet-balance>{{ number_format((float) $wallet['balance'], 0, ',', '.') }}đ</p>
            </div>
        </div>
    </header>

    @if ($activeTab === 'deposit' && $pendingDeposit)
        <div class="mb-4 flex flex-col gap-3 rounded-[5px] border border-amber-300 bg-amber-50 p-3 sm:flex-row sm:items-center sm:justify-between sm:p-4">
            <div class="flex min-w-0 items-start gap-3">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-[5px] bg-amber-100 text-xl text-amber-700">
                    <i class="bx bx-time-five" aria-hidden="true"></i>
                </span>
                <div class="min-w-0">
                    <p class="font-extrabold text-amber-950">Bạn có yêu cầu nạp đang chờ</p>
                    <p class="mt-0.5 text-sm text-amber-800">
                        <span class="font-bold tabular-nums">{{ number_format((float) $pendingDeposit->amount, 0, ',', '.') }}đ</span>
                        <span aria-hidden="true">·</span>
                        <span>{{ $pendingDeposit->transaction_code }}</span>
                    </p>
                </div>
            </div>
            <a class="client-button-secondary min-h-11 shrink-0 border-amber-300 bg-white text-amber-900 hover:bg-amber-100" href="{{ route('wallet.deposit.payment', $pendingDeposit->transaction_code) }}">
                Tiếp tục thanh toán
                <i class="bx bx-right-arrow-alt text-lg" aria-hidden="true"></i>
            </a>
        </div>
    @endif

    @if ($activeTab === 'deposit' && count($bonusTiers) > 0)
        <section class="mb-4 rounded-[5px] border border-emerald-200 bg-emerald-50 p-4">
            <div class="flex items-start gap-3">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-[5px] bg-emerald-600 text-xl text-white"><i class="bx bx-gift" aria-hidden="true"></i></span>
                <div class="min-w-0">
                    <h2 class="font-extrabold text-emerald-950">Khuyến mãi cộng thêm số dư</h2>
                    <p class="mt-1 text-sm leading-6 text-emerald-800">Nạp càng cao, hệ thống tự chọn mốc ưu đãi cao nhất bạn đạt được.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($bonusTiers as $tier)
                            <span class="rounded-[5px] border border-emerald-200 bg-white px-2.5 py-1.5 text-xs font-bold text-emerald-800">
                                Từ {{ number_format($tier['minimum_amount'], 0, ',', '.') }}đ <strong class="text-emerald-600">+{{ $tier['bonus_percent'] }}%</strong>
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    @endif

    <div class="client-card overflow-hidden">
        <nav class="flex min-w-0 gap-6 overflow-x-auto border-b border-slate-200 px-4 sm:px-6" aria-label="Nạp tiền">
            <a @class(['inline-flex min-h-12 shrink-0 items-center border-b-2 px-1 text-sm font-bold transition', 'border-indigo-600 text-indigo-600' => $activeTab === 'deposit', 'border-transparent text-slate-500 hover:text-slate-900' => $activeTab !== 'deposit']) href="{{ route('wallet.deposit.index') }}" @if ($activeTab === 'deposit') aria-current="page" @endif>Nạp tiền</a>
            <a @class(['inline-flex min-h-12 shrink-0 items-center border-b-2 px-1 text-sm font-bold transition', 'border-indigo-600 text-indigo-600' => $activeTab === 'history', 'border-transparent text-slate-500 hover:text-slate-900' => $activeTab !== 'history']) href="{{ route('wallet.deposit.index', ['tab' => 'history']) }}" @if ($activeTab === 'history') aria-current="page" @endif>Lịch sử nạp tiền</a>
        </nav>

        @if ($activeTab === 'history')
            <div class="p-4 sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-xl font-extrabold text-slate-950">Lịch sử nạp tiền</h2>
                        <p class="mt-1 text-sm text-slate-500">Theo dõi các yêu cầu nạp số dư gần đây.</p>
                    </div>
                    <a class="client-button w-full sm:w-auto" href="{{ route('wallet.deposit.index') }}">
                        <i class="bx bx-plus text-lg" aria-hidden="true"></i>
                        Tạo yêu cầu mới
                    </a>
                </div>

                <div class="mt-5 overflow-x-auto rounded-[5px] border border-slate-200">
                    <table class="w-full min-w-[42rem] text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Mã giao dịch</th>
                                <th class="px-4 py-3">Số tiền</th>
                                <th class="px-4 py-3">Trạng thái</th>
                                <th class="px-4 py-3">Thời gian</th>
                                <th class="px-4 py-3 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($depositHistory as $transaction)
                                @php
                                    $transactionRaw = is_array($transaction->raw_data) ? $transaction->raw_data : [];
                                    $transactionBonus = (int) ($transactionRaw['bonus_amount'] ?? 0);
                                    $status = match ($transaction->status) {
                                        'success' => ['Đã cộng tiền', 'bg-emerald-50 text-emerald-700'],
                                        'matched' => ['Đang xác nhận', 'bg-blue-50 text-blue-700'],
                                        'failed' => ['Thất bại', 'bg-rose-50 text-rose-700'],
                                        'cancelled' => ['Đã hủy', 'bg-slate-100 text-slate-600'],
                                        default => ['Chờ thanh toán', 'bg-amber-50 text-amber-700'],
                                    };
                                @endphp
                                <tr>
                                    <td class="px-4 py-4 font-extrabold text-slate-950">{{ $transaction->transaction_code }}</td>
                                    <td class="px-4 py-4 font-bold tabular-nums">
                                        {{ number_format((float) $transaction->amount, 0, ',', '.') }}đ
                                        @if ($transactionBonus > 0)
                                            <span class="mt-1 block text-xs text-emerald-600">+{{ number_format($transactionBonus, 0, ',', '.') }}đ khuyến mãi</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4"><span class="inline-flex rounded-[5px] px-2.5 py-1 text-xs font-bold {{ $status[1] }}">{{ $status[0] }}</span></td>
                                    <td class="px-4 py-4 text-slate-500">{{ $transaction->created_at?->format('d/m/Y H:i') }}</td>
                                    <td class="px-4 py-4 text-right"><a class="font-bold text-indigo-600 hover:text-indigo-700" href="{{ route('wallet.deposit.payment', $transaction->transaction_code) }}">Chi tiết</a></td>
                                </tr>
                            @empty
                                <tr><td class="px-4 py-10 text-center text-slate-500" colspan="5">Bạn chưa có yêu cầu nạp tiền nào.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-5">{{ $depositHistory->links() }}</div>
            </div>
        @else
            <form class="grid min-w-0 lg:grid-cols-[minmax(0,1fr)_19rem]" data-wallet-deposit-form novalidate>
                <div class="grid min-w-0 content-start gap-5 p-4 sm:p-6 lg:p-7">
                    <header>
                        <h2 class="text-lg font-extrabold text-slate-950">Tạo yêu cầu nạp</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-500">Chỉ cần chọn kênh nhận tiền và số tiền muốn nạp.</p>
                    </header>

                    <fieldset class="min-w-0">
                        <legend class="flex items-center gap-2 text-sm font-extrabold text-slate-950">
                            <span class="grid h-6 w-6 place-items-center rounded-[5px] bg-indigo-600 text-xs text-white">1</span>
                            Chọn phương thức
                        </legend>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            @forelse ($rechargeConfigs as $index => $config)
                                <label class="relative flex min-h-[4.5rem] cursor-pointer items-center gap-3 rounded-[5px] border border-slate-300 bg-white p-3 transition duration-200 hover:border-indigo-300 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50/70 has-[:checked]:ring-1 has-[:checked]:ring-indigo-200">
                                    <input class="peer sr-only" type="radio" name="config_id" value="{{ $config['id'] }}" data-deposit-config data-config-bank="{{ $config['bank_name'] }}" @checked($index === 0)>
                                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-[5px] bg-emerald-500 text-xl text-white"><i class="bx bx-building-house" aria-hidden="true"></i></span>
                                    <span class="min-w-0 flex-1">
                                        <strong class="block truncate text-sm text-slate-950">{{ $config['bank_name'] }}</strong>
                                        <span class="mt-1 block text-xs text-slate-500">Chuyển khoản tự động · 1–3 phút</span>
                                    </span>
                                    @if ($index === 0)
                                        <span class="absolute right-9 top-2 rounded-[5px] bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700">Ưu tiên</span>
                                    @endif
                                    <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full border border-slate-300 bg-white text-xs text-white peer-checked:border-indigo-600 peer-checked:bg-indigo-600"><i class="bx bx-check" aria-hidden="true"></i></span>
                                </label>
                            @empty
                                <div class="rounded-[5px] border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 sm:col-span-2">Kênh nạp tiền đang tạm ngừng. Vui lòng liên hệ hỗ trợ.</div>
                            @endforelse
                        </div>
                    </fieldset>

                    <fieldset class="min-w-0">
                        <legend class="flex items-center gap-2 text-sm font-extrabold text-slate-950">
                            <span class="grid h-6 w-6 place-items-center rounded-[5px] bg-indigo-600 text-xs text-white">2</span>
                            Nhập số tiền
                        </legend>
                        <div class="mt-3 rounded-[5px] border border-slate-200 bg-slate-50 p-3 sm:p-4">
                            <label class="sr-only" for="deposit-amount">Số tiền nạp</label>
                            <div class="flex min-h-16 items-center gap-3 rounded-[5px] border border-slate-300 bg-white px-3 transition focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-100 sm:px-4">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-[5px] bg-indigo-50 text-lg text-indigo-600"><i class="bx bx-money" aria-hidden="true"></i></span>
                                <input id="deposit-amount" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-2xl font-extrabold tabular-nums text-slate-950 outline-none focus:ring-0 sm:text-3xl" type="number" name="amount" min="10000" max="50000000" step="1000" inputmode="numeric" autocomplete="off" value="500000" required data-deposit-amount-input>
                                <span class="shrink-0 text-sm font-bold text-slate-400">VND</span>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                @foreach ([100000, 200000, 500000, 1000000] as $quickAmount)
                                    <button class="min-h-11 rounded-[5px] border border-slate-300 bg-white px-2 text-sm font-bold tabular-nums text-slate-600 transition duration-200 hover:border-indigo-400 hover:text-indigo-600" type="button" data-deposit-amount="{{ $quickAmount }}" aria-pressed="{{ $quickAmount === 500000 ? 'true' : 'false' }}">{{ number_format($quickAmount, 0, ',', '.') }}đ</button>
                                @endforeach
                            </div>
                            <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-slate-500">
                                <span>Tối thiểu: <strong>10.000đ</strong></span>
                                <span>Tối đa: <strong>50.000.000đ</strong></span>
                            </div>
                        </div>
                    </fieldset>

                    <div class="flex items-start gap-3 rounded-[5px] border border-blue-200 bg-blue-50 p-3 text-sm leading-6 text-blue-900">
                        <i class="bx bx-shield-quarter mt-0.5 shrink-0 text-xl text-blue-600" aria-hidden="true"></i>
                        <p>Thông tin tài khoản, nội dung chuyển khoản và mã QR sẽ được tạo riêng ở bước thanh toán tiếp theo.</p>
                    </div>
                </div>

                <aside class="min-w-0 border-t border-slate-200 bg-slate-50/70 p-4 sm:p-6 lg:border-l lg:border-t-0">
                    <div class="grid gap-3 lg:sticky lg:top-24">
                        <section class="rounded-[5px] border border-slate-200 bg-white p-4 shadow-sm">
                            <h3 class="flex items-center gap-2 text-sm font-extrabold text-slate-950"><i class="bx bx-receipt text-lg text-indigo-600" aria-hidden="true"></i>Tóm tắt yêu cầu</h3>
                            <dl class="mt-4 grid gap-3 text-sm">
                                <div class="flex justify-between gap-3"><dt class="text-slate-500">Phương thức</dt><dd class="max-w-[10rem] truncate text-right font-extrabold text-slate-950" data-deposit-summary-bank>—</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-slate-500">Số tiền nạp</dt><dd class="font-extrabold tabular-nums text-slate-950" data-deposit-summary-amount>500.000đ</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-slate-500">Khuyến mãi <span data-deposit-summary-rate></span></dt><dd class="font-extrabold tabular-nums text-emerald-600" data-deposit-summary-bonus>0đ</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-slate-500">Phí giao dịch</dt><dd class="font-bold text-emerald-600">0đ</dd></div>
                                <div class="flex justify-between gap-3 border-t border-slate-100 pt-3"><dt class="font-bold text-slate-700">Tổng nhận</dt><dd class="text-base font-extrabold tabular-nums text-emerald-600" data-deposit-summary-total>500.000đ</dd></div>
                            </dl>
                            <button class="client-button mt-5 hidden min-h-11 w-full justify-center bg-indigo-600 hover:bg-indigo-700 lg:inline-flex" type="submit" data-deposit-submit @disabled(count($rechargeConfigs) === 0)>
                                <span data-deposit-submit-text>Tạo yêu cầu nạp</span>
                                <i class="bx bx-right-arrow-alt text-lg" aria-hidden="true"></i>
                            </button>
                        </section>

                        <section class="rounded-[5px] border border-slate-200 bg-white p-4">
                            <h3 class="flex items-center gap-2 text-sm font-extrabold text-slate-950"><i class="bx bx-help-circle text-lg text-indigo-600" aria-hidden="true"></i>Cần lưu ý</h3>
                            <ul class="mt-3 grid gap-2 text-xs leading-5 text-slate-500">
                                <li>• Chuyển đúng số tiền và nội dung được tạo.</li>
                                <li>• Không dùng lại nội dung của yêu cầu cũ.</li>
                                <li>• Trạng thái được cập nhật tự động sau thanh toán.</li>
                            </ul>
                        </section>
                    </div>
                </aside>

                <div class="sticky bottom-2 z-20 border-t border-slate-200 bg-white/95 p-3 shadow-[0_-8px_24px_rgba(15,23,42,0.08)] backdrop-blur lg:hidden" data-deposit-mobile-submit>
                    <div class="mb-2 flex items-center justify-between gap-3 px-1 text-sm">
                        <span class="text-slate-500">Tổng nhận</span>
                        <strong class="tabular-nums text-emerald-600" data-deposit-summary-total>500.000đ</strong>
                    </div>
                    <button class="client-button min-h-11 w-full justify-center bg-indigo-600 hover:bg-indigo-700" type="submit" data-deposit-submit @disabled(count($rechargeConfigs) === 0)>
                        <span data-deposit-submit-text>Tạo yêu cầu nạp</span>
                        <i class="bx bx-right-arrow-alt text-lg" aria-hidden="true"></i>
                    </button>
                </div>
            </form>
        @endif
    </div>
</section>
@endsection
