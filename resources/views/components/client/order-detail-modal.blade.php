<div class="fixed inset-0 z-[70]" data-order-detail-modal aria-hidden="true" hidden>
    <button class="absolute inset-0 bg-slate-950/55 backdrop-blur-[1px]" type="button" data-order-detail-close tabindex="-1" aria-label="Đóng chi tiết đơn hàng"></button>
    <section class="absolute inset-x-3 top-3 flex max-h-[calc(100dvh-1.5rem)] flex-col overflow-hidden rounded-[8px] border border-slate-200 bg-white shadow-2xl sm:inset-x-6 sm:top-6 sm:mx-auto sm:max-w-4xl" role="dialog" aria-modal="true" aria-labelledby="order-detail-modal-title" tabindex="-1" data-order-detail-panel>
        <header class="flex items-center justify-between gap-4 border-b border-slate-200 bg-white px-4 py-3 sm:px-5">
            <div class="min-w-0"><p class="text-xs font-bold uppercase tracking-[0.14em] text-cyan-700">Báo cáo đơn hàng</p><h2 class="truncate text-lg font-extrabold text-slate-950" id="order-detail-modal-title" data-order-detail-title>Chi tiết và tiến độ</h2></div>
            <button class="grid h-10 w-10 shrink-0 place-items-center rounded-[5px] border border-slate-300 text-slate-600 transition hover:border-rose-300 hover:text-rose-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600" type="button" data-order-detail-close aria-label="Đóng"><i class="bx bx-x text-2xl" aria-hidden="true"></i></button>
        </header>
        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain bg-slate-50 p-4 sm:p-5">
            <div class="grid min-h-56 place-items-center" data-order-detail-loading><div class="text-center text-slate-500"><i class="bx bx-loader-alt animate-spin text-3xl text-cyan-700" aria-hidden="true"></i><p class="mt-2 text-sm font-semibold">Đang tải trạng thái mới nhất...</p></div></div>
            <div class="hidden rounded-[5px] border border-rose-200 bg-rose-50 p-5 text-center" data-order-detail-error><i class="bx bx-error-circle text-3xl text-rose-600" aria-hidden="true"></i><p class="mt-2 font-bold text-rose-900" data-order-detail-error-message>Không thể tải thông tin đơn hàng.</p><button class="client-button-secondary mt-4 bg-white" type="button" data-order-detail-retry>Thử lại</button></div>
            <div data-order-detail-content></div>
        </div>
    </section>
</div>
