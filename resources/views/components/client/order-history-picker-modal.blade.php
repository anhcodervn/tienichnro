@props(['topupUrl', 'serviceUrl'])

<div id="order-history-picker-modal" class="fixed inset-0 z-[80] lg:hidden" data-order-history-picker-modal aria-hidden="true" hidden>
    <button
        type="button"
        class="absolute inset-0 bg-slate-950/55 backdrop-blur-[2px]"
        data-order-history-picker-close
        data-order-history-picker-backdrop
        tabindex="-1"
        aria-label="Đóng chọn lịch sử"
    ></button>

    <section
        class="absolute inset-x-3 bottom-[calc(4.75rem+env(safe-area-inset-bottom))] mx-auto max-w-md overflow-hidden rounded-[11px] border border-slate-200 bg-white shadow-2xl"
        data-order-history-picker-panel
        role="dialog"
        aria-modal="true"
        aria-labelledby="order-history-picker-title"
        aria-describedby="order-history-picker-description"
        tabindex="-1"
    >
        <header class="flex items-start justify-between gap-4 border-b border-slate-200 bg-slate-50 px-4 py-4">
            <div class="min-w-0">
                <h2 id="order-history-picker-title" class="font-extrabold text-slate-950">Chọn lịch sử cần xem</h2>
                <p id="order-history-picker-description" class="mt-1 text-xs leading-5 text-slate-500">Theo dõi đơn nạp game hoặc đơn dịch vụ của bạn.</p>
            </div>
            <button
                type="button"
                class="grid h-10 w-10 shrink-0 place-items-center rounded-[8px] border border-slate-300 bg-white text-xl text-slate-600 transition hover:border-rose-300 hover:text-rose-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600"
                data-order-history-picker-close
                aria-label="Đóng chọn lịch sử"
            >
                <i class="bx bx-x" aria-hidden="true"></i>
            </button>
        </header>

        <nav class="grid gap-2 p-3" aria-label="Loại lịch sử đơn hàng">
            <a
                href="{{ $topupUrl }}"
                class="flex min-h-16 items-center gap-3 rounded-[9px] border border-slate-200 px-4 py-3 text-left transition hover:border-emerald-300 hover:bg-emerald-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600"
                data-order-history-picker-link
            >
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-[8px] bg-cyan-50 text-xl text-cyan-700"><i class="bx bx-receipt" aria-hidden="true"></i></span>
                <span class="min-w-0 flex-1">
                    <strong class="block text-sm text-slate-950">Lịch sử nạp</strong>
                    <span class="mt-0.5 block text-xs text-slate-500">Các đơn nạp game và trạng thái xử lý</span>
                </span>
                <i class="bx bx-chevron-right text-xl text-slate-400" aria-hidden="true"></i>
            </a>

            <a
                href="{{ $serviceUrl }}"
                class="flex min-h-16 items-center gap-3 rounded-[9px] border border-slate-200 px-4 py-3 text-left transition hover:border-emerald-300 hover:bg-emerald-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600"
                data-order-history-picker-link
            >
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-[8px] bg-violet-50 text-xl text-violet-700"><i class="bx bx-joystick" aria-hidden="true"></i></span>
                <span class="min-w-0 flex-1">
                    <strong class="block text-sm text-slate-950">Lịch sử dịch vụ</strong>
                    <span class="mt-0.5 block text-xs text-slate-500">Các đơn dịch vụ game đã đặt</span>
                </span>
                <i class="bx bx-chevron-right text-xl text-slate-400" aria-hidden="true"></i>
            </a>
        </nav>
    </section>
</div>
