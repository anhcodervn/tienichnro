@props([
    'eyebrow' => null,
    'title' => 'Thanh toán chuyển khoản',
    'subtitle' => 'Hoàn tất thanh toán theo đúng thông tin bên dưới.',
    'code',
    'status' => 'pending',
    'bankName' => null,
    'accountName' => null,
    'accountNumber' => null,
    'amount' => 0,
    'content' => null,
    'qrUrl' => null,
    'backUrl' => null,
    'backLabel' => 'Quay lại',
    'showCountdown' => false,
])

@php
    $statusLabels = [
        'pending' => 'Đang chờ thanh toán',
        'processing' => 'Đang xác nhận',
        'paid' => 'Đã thanh toán',
        'failed' => 'Thanh toán thất bại',
        'cancelled' => 'Đã hủy',
        'expired' => 'Đã hết thời gian',
    ];
    $isPaid = $status === 'paid';
    $isPending = in_array($status, ['pending', 'processing'], true);
    $statusClasses = $isPaid
        ? 'border-emerald-300 bg-emerald-50 text-emerald-700'
        : ($isPending ? 'border-amber-300 bg-amber-50 text-amber-700' : 'border-slate-300 bg-slate-50 text-slate-700');
    $hasPaymentDetails = filled($bankName) || filled($accountNumber) || filled($content);
    $paymentRows = [
        ['Ngân hàng', $bankName, $bankName],
        ['Chủ tài khoản', $accountName, $accountName],
        ['Số tài khoản', $accountNumber, $accountNumber],
        ['Số tiền', number_format((int) $amount, 0, ',', '.').'đ', (string) (int) $amount],
        ['Nội dung', $content, $content],
    ];
@endphp

<section {{ $attributes->merge(['class' => 'client-container py-6 sm:py-8 lg:py-10']) }}>
    <div class="mx-auto max-w-5xl">
        <header class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                @if ($backUrl)
                    <a class="inline-flex min-h-11 items-center gap-1 text-sm font-bold text-indigo-600 transition hover:text-indigo-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600" href="{{ $backUrl }}">
                        <i class="bx bx-left-arrow-alt text-xl" aria-hidden="true"></i>{{ $backLabel }}
                    </a>
                @endif
                @if ($eyebrow)
                    <p class="mt-2 break-all text-xs font-extrabold uppercase tracking-[0.16em] text-emerald-700">{{ $eyebrow }}</p>
                @endif
                <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-950 sm:text-3xl">{{ $title }}</h1>
                <p class="mt-2 text-sm leading-6 text-slate-500">{{ $subtitle }}</p>
                @isset($statusExtra)
                    <div class="mt-2">{{ $statusExtra }}</div>
                @endisset
            </div>

            <div class="flex shrink-0 flex-wrap gap-2">
                <span class="inline-flex min-h-11 items-center gap-2 rounded-[5px] border px-3 text-sm font-bold {{ $statusClasses }}" data-payment-status>
                    <i class="bx {{ $isPaid ? 'bx-check-circle' : 'bx-time-five' }} text-lg" aria-hidden="true"></i>
                    <span data-payment-status-text>{{ $statusLabels[$status] ?? 'Đang xử lý' }}</span>
                </span>
                @if ($showCountdown)
                    <span class="inline-flex min-h-11 items-center gap-2 rounded-[5px] border border-slate-200 bg-white px-3 font-mono text-sm font-extrabold text-indigo-600 shadow-sm" data-payment-countdown>
                        <i class="bx bx-time text-lg" aria-hidden="true"></i><span data-payment-countdown-text>--:--</span>
                    </span>
                @endif
            </div>
        </header>

        @if ($hasPaymentDetails)
            <div class="mt-6 grid min-w-0 grid-cols-1 gap-5 lg:grid-cols-[320px_minmax(0,1fr)]">
                <div class="client-card flex min-w-0 flex-col items-center justify-center p-4 sm:p-5">
                    <p class="text-sm font-extrabold text-slate-950">Quét mã để chuyển khoản</p>
                    <div class="mt-3 aspect-square w-full max-w-72 overflow-hidden rounded-[5px] border border-slate-200 bg-white p-3 shadow-sm">
                        @if ($qrUrl)
                            <img class="h-full w-full object-contain" src="{{ $qrUrl }}" alt="Mã QR thanh toán giao dịch {{ $code }}">
                        @else
                            <div class="grid h-full place-items-center p-4 text-center text-sm leading-6 text-slate-500">QR chưa khả dụng. Vui lòng chuyển khoản theo thông tin bên cạnh.</div>
                        @endif
                    </div>
                    <p class="mt-3 text-xs font-bold text-slate-500">Mã giao dịch: <span class="break-all text-indigo-600">{{ $code }}</span></p>
                </div>

                <div class="client-card min-w-0 divide-y divide-slate-100 overflow-hidden">
                    @foreach ($paymentRows as [$label, $value, $copyValue])
                        <div class="flex min-w-0 items-center gap-3 p-4 sm:p-5">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-semibold text-slate-500">{{ $label }}</p>
                                <p class="mt-1 break-all text-sm font-extrabold {{ in_array($label, ['Số tiền', 'Nội dung'], true) ? 'text-indigo-600' : 'text-slate-950' }}">{{ filled($value) ? $value : '—' }}</p>
                            </div>
                            @if (filled($copyValue))
                                <button class="grid h-11 w-11 shrink-0 place-items-center rounded-[5px] text-slate-400 transition hover:bg-indigo-50 hover:text-indigo-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600" type="button" data-copy="{{ $copyValue }}" aria-label="Sao chép {{ mb_strtolower($label) }}">
                                    <i class="bx bx-copy text-lg" aria-hidden="true"></i>
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-4 flex gap-2 rounded-[5px] border border-amber-300 bg-amber-50 p-3 text-sm leading-6 text-amber-900">
                <i class="bx bx-error-circle mt-0.5 shrink-0 text-lg" aria-hidden="true"></i>
                <p><strong>Quan trọng:</strong> Chuyển đúng số tiền và giữ nguyên nội dung để hệ thống tự đối soát.</p>
            </div>

            @isset($orderSummary)
                <div class="mt-5">{{ $orderSummary }}</div>
            @endisset

            @isset($actions)
                <div class="mt-4">{{ $actions }}</div>
            @endisset
        @else
            <div class="client-card mt-6 p-8 text-center">
                <i class="bx bx-wrench mb-3 text-4xl text-slate-400" aria-hidden="true"></i>
                <h2 class="font-extrabold text-slate-950">Kênh nhận tiền đang tạm bảo trì</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">Vui lòng liên hệ hỗ trợ và cung cấp mã giao dịch {{ $code }}.</p>
            </div>
        @endif
    </div>
</section>
