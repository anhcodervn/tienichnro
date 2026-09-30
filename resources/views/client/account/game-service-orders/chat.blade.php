@extends('client.layouts.app')

@section('title', 'Chat đơn '.$order->code)
@section('robots', 'noindex,nofollow')

@section('content')
<section class="client-container py-8 sm:py-10">
    <header class="mb-5 grid gap-4 overflow-hidden rounded-[10px] border border-emerald-300 bg-gradient-to-br from-emerald-100 via-emerald-50 to-cyan-100 p-4 shadow-sm sm:p-5" data-order-chat-heading>
        <a href="{{ route('account.game-service-orders.index') }}" class="inline-flex min-h-10 w-fit items-center gap-2 rounded-[5px] border border-emerald-200 bg-white/90 px-3 text-sm font-extrabold text-emerald-800 shadow-sm transition hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600" aria-label="Quay lại lịch sử dịch vụ" data-order-chat-back>
            <i class="bx bx-arrow-back text-xl" aria-hidden="true"></i>
            <span>Quay lại</span>
        </a>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex min-w-0 items-center gap-3 sm:gap-4">
                @if ($gameIcon)
                    <img class="size-16 shrink-0 rounded-[10px] border-2 border-white object-cover shadow-md sm:size-20" src="{{ $gameIcon }}" alt="Icon {{ $order->game_name }}" width="80" height="80" decoding="async" fetchpriority="high">
                @else
                    <span class="grid size-16 shrink-0 place-items-center rounded-[10px] border-2 border-white bg-emerald-700 text-lg font-extrabold uppercase text-white shadow-md sm:size-20">
                        {{ mb_strtoupper(mb_substr($order->game_name ?: $order->service_name, 0, 2)) }}
                    </span>
                @endif
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="flex items-center gap-1.5 font-mono text-xs font-extrabold uppercase tracking-wide text-emerald-800"><i class="bx bx-receipt" aria-hidden="true"></i>{{ $order->code }}</p>
                        <x-client.game-service-order-status :status="$order->status" />
                    </div>
                    <h1 class="mt-1 truncate text-2xl font-extrabold tracking-tight text-slate-950">{{ $order->service_name }}</h1>
                    <p class="mt-1 truncate text-sm font-semibold text-slate-600">{{ $order->game_name }} · {{ $order->package_name }}</p>
                    @if (in_array($order->status, ['failed', 'cancelled'], true))
                        <p class="mt-2 text-xs font-bold text-rose-700" data-game-service-order-chat-support>Đơn đã được trả về/hoàn tiền. Bạn vẫn có thể chat để nắm rõ vấn đề.</p>
                    @endif
                </div>
            </div>
            <button type="button" class="inline-flex min-h-11 w-full shrink-0 items-center justify-center gap-2 rounded-[5px] bg-emerald-700 px-4 text-sm font-extrabold text-white shadow-sm transition hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2 focus-visible:ring-offset-emerald-50 sm:w-auto" data-order-progress-open aria-controls="order-progress-modal" aria-expanded="false" aria-haspopup="dialog">
                <i class="bx bx-task text-xl" aria-hidden="true"></i>
                Xem tiến trình
            </button>
        </div>
    </header>

    <div data-order-chat data-thread-url="{{ route('client.game-service-orders.messages.index', $order) }}" data-send-url="{{ route('client.game-service-orders.messages.store', $order) }}" data-progress-url="{{ route('client.game-service-orders.progress.index', $order) }}">
        <section class="client-card flex min-h-[620px] flex-col overflow-hidden">
            <header class="flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3 sm:px-5">
                <div><h2 class="font-extrabold text-slate-950">Chat đơn hàng</h2><p class="text-xs text-slate-500">Tin nhắn giữa bạn, CTV và quản trị viên.</p></div>
                <i class="bx bx-message-circle-dots text-2xl text-emerald-700" aria-hidden="true"></i>
            </header>
            <div class="grid flex-1 content-start gap-3 overflow-y-auto bg-slate-50 p-4 sm:p-6" data-order-chat-messages><p class="place-self-center text-sm text-slate-500">Đang tải cuộc trò chuyện...</p></div>
            <form class="flex gap-2 border-t border-slate-200 bg-white p-3 sm:p-4" data-order-chat-form>
                <textarea required maxlength="5000" rows="2" class="min-h-12 flex-1 resize-none rounded-[5px] border border-slate-300 px-3 py-2 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" placeholder="Nhập tin nhắn..." data-order-chat-input></textarea>
                <button class="grid size-12 place-items-center self-end rounded-[5px] bg-emerald-600 text-xl text-white hover:bg-emerald-700" aria-label="Gửi tin nhắn"><i class="bx bx-send"></i></button>
            </form>
        </section>

        <div id="order-progress-modal" class="fixed inset-0 z-[80]" data-order-progress-modal aria-hidden="true" hidden>
            <button type="button" class="absolute inset-0 bg-slate-950/60 backdrop-blur-[1px]" data-order-progress-close tabindex="-1" aria-label="Đóng tiến trình đơn hàng"></button>
            <section class="absolute inset-x-3 top-1/2 mx-auto flex max-h-[calc(100dvh-1.5rem)] max-w-3xl -translate-y-1/2 flex-col overflow-hidden rounded-[8px] border border-slate-200 bg-white shadow-2xl sm:inset-x-6" role="dialog" aria-modal="true" aria-labelledby="order-progress-modal-title" tabindex="-1" data-order-progress-panel>
                <header class="flex shrink-0 items-center justify-between gap-4 border-b border-slate-200 bg-white px-4 py-3 sm:px-5">
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-emerald-700">{{ $order->code }}</p>
                        <h2 id="order-progress-modal-title" class="truncate text-lg font-extrabold text-slate-950">Tiến trình thực hiện</h2>
                    </div>
                    <button type="button" class="grid size-10 shrink-0 place-items-center rounded-[5px] border border-slate-300 text-slate-600 transition hover:border-rose-300 hover:text-rose-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600" data-order-progress-close aria-label="Đóng">
                        <i class="bx bx-x text-2xl" aria-hidden="true"></i>
                    </button>
                </header>
                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain bg-slate-50 p-4 sm:p-5">
                    <div class="grid gap-3" data-order-progress aria-live="polite"><p class="text-sm text-slate-500">Đang tải tiến trình...</p></div>
                </div>
            </section>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-order-chat]');
    if (!root) return;
    const area = root.querySelector('[data-order-chat-messages]');
    const form = root.querySelector('[data-order-chat-form]');
    const input = root.querySelector('[data-order-chat-input]');
    const progressArea = root.querySelector('[data-order-progress]');
    const progressModal = root.querySelector('[data-order-progress-modal]');
    const progressPanel = root.querySelector('[data-order-progress-panel]');
    const progressOpen = document.querySelector('[data-order-progress-open]');
    const progressCloseButtons = [...root.querySelectorAll('[data-order-progress-close]')];
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let progressPreviouslyFocused = null;
    const roleLabel = { user: 'Bạn', collaborator: 'CTV', admin: 'Quản trị viên' };
    const escapeHtml = (value) => { const node = document.createElement('div'); node.textContent = value; return node.innerHTML; };
    const render = (messages) => {
        area.innerHTML = messages.length ? messages.map((message) => `<article class="flex ${message.sender_role === 'user' ? 'justify-end' : 'justify-start'}"><div class="max-w-[84%]"><p class="mb-1 text-xs font-bold text-slate-500">${roleLabel[message.sender_role]} · ${escapeHtml(message.sender_name)}</p><div class="whitespace-pre-wrap break-words rounded-[8px] px-4 py-2.5 text-sm ${message.sender_role === 'user' ? 'bg-emerald-600 text-white' : message.sender_role === 'admin' ? 'bg-indigo-100 text-indigo-950' : 'border border-slate-200 bg-white'}"><p>${escapeHtml(message.message)}</p>${message.progress ? `<div class="mt-2 border-t border-current/20 pt-2"><p class="mb-2 text-xs font-bold">Cập nhật tiến trình</p>${message.progress.image_url ? `<a href="${escapeHtml(message.progress.image_url)}" target="_blank" rel="noopener" class="block overflow-hidden rounded-[8px] border border-current/20 bg-white/90"><img src="${escapeHtml(message.progress.image_url)}" alt="Ảnh tiến trình đơn hàng" class="max-h-80 w-full object-contain"></a>` : ''}</div>` : ''}</div></div></article>`).join('') : '<p class="place-self-center text-sm text-slate-500">Chưa có tin nhắn. Bạn có thể bắt đầu trao đổi tại đây.</p>';
        area.scrollTop = area.scrollHeight;
    };
    const renderProgress = (updates) => {
        progressArea.innerHTML = updates.length ? updates.map((update) => `<article class="rounded-[8px] border ${update.type === 'completion' ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-white'} p-4"><div class="flex flex-wrap items-center justify-between gap-2"><strong class="text-sm ${update.type === 'completion' ? 'text-emerald-800' : 'text-slate-900'}">${update.type === 'completion' ? 'Báo cáo hoàn thành' : 'Cập nhật tiến trình'}</strong><time class="text-xs text-slate-500">${new Date(update.created_at).toLocaleString('vi-VN')}</time></div><p class="mt-1 text-xs font-bold text-slate-500">${escapeHtml(update.author?.name || 'Tài khoản đã xóa')}</p><p class="mt-3 whitespace-pre-wrap break-words text-sm leading-6 text-slate-800">${escapeHtml(update.description)}</p>${update.image_url ? `<a href="${escapeHtml(update.image_url)}" target="_blank" rel="noopener" class="mt-3 block w-fit"><img src="${escapeHtml(update.image_url)}" alt="Ảnh tiến trình đơn" class="max-h-80 rounded-[8px] border border-slate-200 object-contain"></a>` : ''}</article>`).join('') : '<p class="text-sm text-slate-500">CTV chưa cập nhật tiến trình cho đơn này.</p>';
    };
    const load = async () => { const response = await fetch(root.dataset.threadUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } }); if (response.ok) render((await response.json()).data.messages); };
    const loadProgress = async () => {
        try {
            const response = await fetch(root.dataset.progressUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Unable to load progress');
            renderProgress((await response.json()).data.progress);
        } catch {
            progressArea.innerHTML = '<div class="rounded-[5px] border border-rose-200 bg-rose-50 p-4 text-center text-sm font-semibold text-rose-700">Không thể tải tiến trình. Vui lòng đóng và thử lại.</div>';
        }
    };
    const closeProgress = ({ restoreFocus = true } = {}) => {
        if (progressModal.hidden) return;
        progressModal.hidden = true;
        progressModal.setAttribute('aria-hidden', 'true');
        progressOpen.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('client-game-picker-open');
        if (restoreFocus && progressPreviouslyFocused instanceof HTMLElement && progressPreviouslyFocused.isConnected) {
            progressPreviouslyFocused.focus({ preventScroll: true });
        }
    };
    const openProgress = () => {
        progressPreviouslyFocused = document.activeElement;
        progressModal.hidden = false;
        progressModal.setAttribute('aria-hidden', 'false');
        progressOpen.setAttribute('aria-expanded', 'true');
        document.body.classList.add('client-game-picker-open');
        progressPanel.focus({ preventScroll: true });
        void loadProgress();
    };
    progressOpen.addEventListener('click', openProgress);
    progressCloseButtons.forEach((button) => button.addEventListener('click', () => closeProgress()));
    document.addEventListener('keydown', (event) => {
        if (progressModal.hidden) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            closeProgress();
            return;
        }
        if (event.key !== 'Tab') return;
        const focusable = [...progressModal.querySelectorAll('button:not([disabled]):not([tabindex="-1"]), a[href], [tabindex]:not([tabindex="-1"])')];
        if (!focusable.length) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && (document.activeElement === first || document.activeElement === progressPanel)) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
    form.addEventListener('submit', async (event) => { event.preventDefault(); const message = input.value.trim(); if (!message) return; const response = await fetch(root.dataset.sendUrl, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify({ message }) }); if (response.ok) { input.value = ''; await load(); } });
    void load();
    window.setInterval(() => { void load(); if (!progressModal.hidden) void loadProgress(); }, 5000);
    window.addEventListener('pagehide', () => closeProgress({ restoreFocus: false }));
});
</script>
@endpush
