@extends('client.layouts.app')

@section('title', 'Chat đơn '.$order->code)
@section('robots', 'noindex,nofollow')

@section('content')
<section class="client-container py-8 sm:py-10">
    <header class="mb-5 flex items-center gap-3">
        <a href="{{ route('account.game-service-orders.index') }}" class="grid size-11 place-items-center rounded-[5px] border border-slate-200 bg-white text-slate-700"><i class="bx bx-arrow-back text-xl"></i></a>
        <div><p class="font-mono text-sm font-bold text-emerald-700">{{ $order->code }}</p><h1 class="text-2xl font-extrabold text-slate-950">{{ $order->service_name }}</h1><p class="text-sm text-slate-500">Trao đổi với CTV xử lý đơn và quản trị viên.</p></div>
    </header>

    <div class="client-card flex min-h-[620px] flex-col overflow-hidden" data-order-chat data-thread-url="{{ route('client.game-service-orders.messages.index', $order) }}" data-send-url="{{ route('client.game-service-orders.messages.store', $order) }}">
        <div class="grid flex-1 content-start gap-3 overflow-y-auto bg-slate-50 p-4 sm:p-6" data-order-chat-messages><p class="place-self-center text-sm text-slate-500">Đang tải cuộc trò chuyện...</p></div>
        <form class="flex gap-2 border-t border-slate-200 bg-white p-3 sm:p-4" data-order-chat-form>
            <textarea required maxlength="5000" rows="2" class="min-h-12 flex-1 resize-none rounded-[5px] border border-slate-300 px-3 py-2 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" placeholder="Nhập tin nhắn..." data-order-chat-input></textarea>
            <button class="grid size-12 place-items-center self-end rounded-[5px] bg-emerald-600 text-xl text-white hover:bg-emerald-700" aria-label="Gửi tin nhắn"><i class="bx bx-send"></i></button>
        </form>
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
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const roleLabel = { user: 'Bạn', collaborator: 'CTV', admin: 'Quản trị viên' };
    const escapeHtml = (value) => { const node = document.createElement('div'); node.textContent = value; return node.innerHTML; };
    const render = (messages) => {
        area.innerHTML = messages.length ? messages.map((message) => `<article class="flex ${message.sender_role === 'user' ? 'justify-end' : 'justify-start'}"><div class="max-w-[84%]"><p class="mb-1 text-xs font-bold text-slate-500">${roleLabel[message.sender_role]} · ${escapeHtml(message.sender_name)}</p><p class="whitespace-pre-wrap break-words rounded-[8px] px-4 py-2.5 text-sm ${message.sender_role === 'user' ? 'bg-emerald-600 text-white' : message.sender_role === 'admin' ? 'bg-indigo-100 text-indigo-950' : 'border border-slate-200 bg-white'}">${escapeHtml(message.message)}</p></div></article>`).join('') : '<p class="place-self-center text-sm text-slate-500">Chưa có tin nhắn. Bạn có thể bắt đầu trao đổi tại đây.</p>';
        area.scrollTop = area.scrollHeight;
    };
    const load = async () => { const response = await fetch(root.dataset.threadUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } }); if (response.ok) render((await response.json()).data.messages); };
    form.addEventListener('submit', async (event) => { event.preventDefault(); const message = input.value.trim(); if (!message) return; const response = await fetch(root.dataset.sendUrl, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify({ message }) }); if (response.ok) { input.value = ''; await load(); } });
    void load();
    window.setInterval(load, 5000);
});
</script>
@endpush
