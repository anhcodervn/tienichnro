<script setup lang="ts">
import { clientAffiliateService, type GameServiceChatMessage, type GameServiceChatOrder } from '@/services/client-affiliate.service';
import { handleErrorResponse } from '@/utils/response';
import { ArrowLeft, LoaderCircle, MessageCircle, RefreshCw, Search, Send } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const threads = ref<GameServiceChatOrder[]>([]);
const selected = ref<GameServiceChatOrder | null>(null);
const messages = ref<GameServiceChatMessage[]>([]);
const search = ref('');
const draft = ref('');
const loading = ref(true);
const loadingThread = ref(false);
const sending = ref(false);
const refreshing = ref(false);
const messageList = ref<HTMLElement | null>(null);
let refreshTimer: number | null = null;

const statusLabels: Record<GameServiceChatOrder['status'], string> = {
    pending: 'Đang chờ',
    processing: 'Đang làm',
    review: 'Chờ duyệt',
    completed: 'Hoàn thành',
    failed: 'Thất bại',
    cancelled: 'Đã hủy',
};
const statusClasses: Record<GameServiceChatOrder['status'], string> = {
    pending: 'bg-amber-100 text-amber-800',
    processing: 'bg-sky-100 text-sky-800',
    review: 'bg-violet-100 text-violet-800',
    completed: 'bg-emerald-100 text-emerald-800',
    failed: 'bg-rose-100 text-rose-800',
    cancelled: 'bg-slate-100 text-slate-700',
};
const filteredThreads = computed(() => {
    const keyword = search.value.trim().toLocaleLowerCase('vi-VN');
    if (!keyword) return threads.value;

    return threads.value.filter((thread) =>
        [thread.code, thread.service_name, thread.package_name, thread.user?.name, thread.user?.email]
            .filter(Boolean)
            .some((value) => String(value).toLocaleLowerCase('vi-VN').includes(keyword)),
    );
});
const roleLabel = (role: GameServiceChatMessage['sender_role']): string =>
    ({ user: 'Khách hàng', collaborator: 'Bạn', admin: 'Quản trị viên' })[role];
const formatTime = (value: string): string => new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value));
const scrollToLatest = async (): Promise<void> => {
    await nextTick();
    if (messageList.value) messageList.value.scrollTop = messageList.value.scrollHeight;
};

const loadThreads = async (showLoading = true): Promise<void> => {
    if (showLoading) loading.value = true;
    try {
        threads.value = await clientAffiliateService.gameServiceOrderChats();
        if (selected.value) {
            selected.value = threads.value.find((thread) => thread.code === selected.value?.code) ?? null;
            if (!selected.value) messages.value = [];
        }
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        if (showLoading) loading.value = false;
    }
};

const openThread = async (order: GameServiceChatOrder, showLoading = true): Promise<void> => {
    selected.value = order;
    if (showLoading) loadingThread.value = true;
    const previousMessageCount = messages.value.length;

    try {
        const thread = await clientAffiliateService.gameServiceOrderThread(order.code);
        selected.value = thread.order;
        messages.value = thread.messages;
        if (showLoading || thread.messages.length > previousMessageCount) await scrollToLatest();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        if (showLoading) loadingThread.value = false;
    }
};

const closeThread = (): void => {
    selected.value = null;
    messages.value = [];
    draft.value = '';
};

const sendMessage = async (): Promise<void> => {
    if (!selected.value || !draft.value.trim()) return;
    sending.value = true;

    try {
        messages.value.push(await clientAffiliateService.sendGameServiceOrderMessage(selected.value.code, draft.value.trim()));
        draft.value = '';
        await scrollToLatest();
        await loadThreads(false);
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        sending.value = false;
    }
};

const refresh = async (): Promise<void> => {
    if (refreshing.value || document.visibilityState === 'hidden') return;
    refreshing.value = true;

    try {
        await loadThreads(false);
        if (selected.value) await openThread(selected.value, false);
    } finally {
        refreshing.value = false;
    }
};

onMounted(async () => {
    await loadThreads();
    if (threads.value[0]) await openThread(threads.value[0]);
    refreshTimer = window.setInterval(() => void refresh(), 5000);
});
onBeforeUnmount(() => {
    if (refreshTimer !== null) window.clearInterval(refreshTimer);
});
</script>

<template>
    <main class="grid gap-5 p-4 sm:p-6">
        <header
            class="flex flex-col gap-4 rounded-xl border border-emerald-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <span class="grid size-11 place-items-center rounded-xl bg-emerald-100 text-emerald-700"><MessageCircle class="size-6" /></span>
                <div>
                    <p class="text-xs font-black uppercase tracking-widest text-emerald-700">Work Center</p>
                    <h1 class="text-2xl font-black text-slate-950">Chat đơn đã nhận</h1>
                    <p class="text-sm text-slate-500">Trao đổi riêng với khách hàng và quản trị viên theo từng đơn.</p>
                </div>
            </div>
            <button
                type="button"
                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 px-4 text-sm font-bold text-slate-700 hover:bg-slate-50"
                @click="refresh"
            >
                <RefreshCw class="size-4" /> Làm mới
            </button>
        </header>

        <section class="grid min-h-[680px] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm lg:grid-cols-[360px_minmax(0,1fr)]">
            <aside
                class="max-h-[680px] overflow-y-auto border-b border-slate-200 lg:block lg:border-b-0 lg:border-r"
                :class="selected ? 'hidden' : 'block'"
            >
                <div class="sticky top-0 z-10 border-b border-slate-200 bg-white p-3">
                    <label class="relative block">
                        <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model.trim="search"
                            class="min-h-11 w-full rounded-lg border border-slate-300 pl-9 pr-3 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                            placeholder="Tìm mã đơn, dịch vụ, khách hàng"
                        />
                    </label>
                </div>
                <div v-if="loading" class="grid min-h-64 place-items-center"><LoaderCircle class="size-8 animate-spin text-emerald-600" /></div>
                <template v-else>
                    <button
                        v-for="thread in filteredThreads"
                        :key="thread.code"
                        type="button"
                        class="grid w-full gap-2 border-b border-slate-100 p-4 text-left transition hover:bg-slate-50"
                        :class="selected?.code === thread.code ? 'bg-emerald-50' : ''"
                        @click="openThread(thread)"
                    >
                        <span class="flex items-center justify-between gap-3">
                            <strong class="font-mono text-emerald-700">{{ thread.code }}</strong>
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-bold" :class="statusClasses[thread.status]">{{
                                statusLabels[thread.status]
                            }}</span>
                        </span>
                        <span class="truncate font-bold text-slate-900">{{ thread.service_name }} · {{ thread.package_name }}</span>
                        <span class="flex items-center justify-between gap-3 text-xs text-slate-500">
                            <span class="truncate">{{ thread.user?.name || 'Khách vãng lai' }}</span>
                            <span class="shrink-0">{{ thread.messages_count }} tin</span>
                        </span>
                        <span class="truncate text-xs text-slate-600">{{ thread.last_message?.message || 'Chưa có tin nhắn' }}</span>
                    </button>
                </template>
                <p v-if="!loading && filteredThreads.length === 0" class="p-8 text-center text-sm text-slate-500">Chưa có đơn đã nhận phù hợp.</p>
            </aside>

            <div v-if="selected" class="flex min-h-0 flex-col">
                <header class="flex items-start gap-3 border-b border-slate-200 p-4">
                    <button
                        type="button"
                        class="grid size-9 shrink-0 place-items-center rounded-lg border border-slate-200 lg:hidden"
                        @click="closeThread"
                    >
                        <ArrowLeft class="size-4" />
                    </button>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <strong class="truncate text-slate-950">{{ selected.service_name }} · {{ selected.package_name }}</strong>
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-bold" :class="statusClasses[selected.status]">{{
                                statusLabels[selected.status]
                            }}</span>
                        </div>
                        <p class="truncate text-xs text-slate-500">{{ selected.code }} · {{ selected.user?.name || 'Khách vãng lai' }}</p>
                    </div>
                </header>
                <div v-if="loadingThread" class="grid flex-1 place-items-center"><LoaderCircle class="size-8 animate-spin text-emerald-600" /></div>
                <div v-else ref="messageList" class="grid min-h-0 flex-1 content-start gap-3 overflow-y-auto bg-slate-50 p-4">
                    <article
                        v-for="message in messages"
                        :key="message.id"
                        class="flex"
                        :class="message.sender_role === 'collaborator' ? 'justify-end' : 'justify-start'"
                    >
                        <div class="max-w-[85%]">
                            <p class="mb-1 text-xs font-bold text-slate-500">{{ roleLabel(message.sender_role) }} · {{ message.sender_name }}</p>
                            <p
                                class="whitespace-pre-wrap break-words rounded-xl px-4 py-2.5 text-sm shadow-sm"
                                :class="
                                    message.sender_role === 'collaborator'
                                        ? 'bg-emerald-600 text-white'
                                        : message.sender_role === 'admin'
                                          ? 'bg-indigo-100 text-indigo-950'
                                          : 'border border-slate-200 bg-white text-slate-900'
                                "
                            >
                                {{ message.message }}
                            </p>
                            <time class="text-[10px] text-slate-400">{{ formatTime(message.created_at) }}</time>
                        </div>
                    </article>
                    <p v-if="messages.length === 0" class="place-self-center text-sm text-slate-500">Chưa có trao đổi trong đơn này.</p>
                </div>
                <form class="flex gap-2 border-t border-slate-200 p-4" @submit.prevent="sendMessage">
                    <textarea
                        v-model="draft"
                        required
                        maxlength="5000"
                        rows="2"
                        class="min-h-12 flex-1 resize-none rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        placeholder="Nhập tin nhắn cho khách hàng hoặc admin..."
                    ></textarea>
                    <button
                        :disabled="sending"
                        class="grid size-12 place-items-center self-end rounded-lg bg-emerald-600 text-white disabled:opacity-50"
                    >
                        <LoaderCircle v-if="sending" class="size-5 animate-spin" /><Send v-else class="size-5" />
                    </button>
                </form>
            </div>
            <div v-else class="hidden place-items-center text-slate-500 lg:grid">
                <div class="text-center">
                    <MessageCircle class="mx-auto size-9" />
                    <p class="mt-2 font-bold">Chọn một đơn đã nhận để bắt đầu trao đổi</p>
                </div>
            </div>
        </section>
    </main>
</template>
