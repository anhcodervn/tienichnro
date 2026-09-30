<script setup lang="ts">
import { adminGameServiceService } from '@/services/admin-game-service.service';
import { handleErrorResponse } from '@/utils/response';
import { MessageCircle, RefreshCw, Search, Send } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';

type ChatMessage = {
    id: number;
    sender_role: 'user' | 'collaborator' | 'admin';
    sender_name: string;
    message: string;
    progress: { id: number; type: 'progress' | 'completion'; image_url: string | null } | null;
    created_at: string;
};
type ChatOrder = {
    code: string;
    service_name: string;
    package_name: string;
    status: string;
    user: { name: string; email: string } | null;
    collaborator: { name: string } | null;
    messages_count: number;
    last_message: ChatMessage | null;
};

const threads = ref<ChatOrder[]>([]);
const selected = ref<ChatOrder | null>(null);
const messages = ref<ChatMessage[]>([]);
const search = ref('');
const draft = ref('');
const loading = ref(false);
const sending = ref(false);
let timer: number | null = null;

const loadThreads = async (): Promise<void> => {
    loading.value = true;
    try {
        const response = await adminGameServiceService.chatThreads({ search: search.value || undefined, per_page: 100 });
        threads.value = response.data.data.data;
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

const openThread = async (order: ChatOrder): Promise<void> => {
    selected.value = order;
    try {
        const response = await adminGameServiceService.chatThread(order.code);
        selected.value = response.data.data.order;
        messages.value = response.data.data.messages;
    } catch (error) {
        handleErrorResponse(error);
    }
};

const sendMessage = async (): Promise<void> => {
    if (!selected.value || !draft.value.trim()) return;
    sending.value = true;
    try {
        const response = await adminGameServiceService.sendChatMessage(selected.value.code, draft.value.trim());
        messages.value.push(response.data.data);
        draft.value = '';
        await loadThreads();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        sending.value = false;
    }
};

const roleLabel = (role: ChatMessage['sender_role']): string => ({ user: 'Khách hàng', collaborator: 'CTV', admin: 'Quản trị viên' })[role];
const formatTime = (value: string): string => new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value));

onMounted(async () => {
    await loadThreads();
    timer = window.setInterval(() => {
        void loadThreads();
        if (selected.value) void openThread(selected.value);
    }, 5000);
});
onBeforeUnmount(() => {
    if (timer !== null) window.clearInterval(timer);
});
</script>

<template>
    <main class="grid gap-5 p-4 sm:p-6">
        <header class="flex flex-col gap-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-3">
                <span class="grid size-11 place-items-center rounded-lg bg-emerald-100 text-emerald-700"><MessageCircle class="size-6" /></span>
                <div>
                    <p class="text-xs font-black uppercase tracking-widest text-emerald-700">Dịch vụ game</p>
                    <h1 class="text-2xl font-black">Quản lý chat đơn hàng</h1>
                </div>
            </div>
            <form class="flex gap-2" @submit.prevent="loadThreads">
                <label class="relative"
                    ><Search class="absolute left-3 top-3 size-4 text-slate-400" /><input
                        v-model.trim="search"
                        class="min-h-10 rounded-md border border-slate-300 pl-9 pr-3"
                        placeholder="Mã đơn, email, dịch vụ"
                /></label>
                <button class="rounded-md bg-slate-950 px-4 font-bold text-white">Lọc</button>
            </form>
        </header>

        <section class="grid min-h-[650px] overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm lg:grid-cols-[360px_minmax(0,1fr)]">
            <aside class="max-h-[650px] overflow-y-auto border-b border-slate-200 lg:border-b-0 lg:border-r">
                <button
                    v-for="thread in threads"
                    :key="thread.code"
                    type="button"
                    class="grid w-full gap-1 border-b border-slate-100 p-4 text-left hover:bg-slate-50"
                    :class="selected?.code === thread.code ? 'bg-emerald-50' : ''"
                    @click="openThread(thread)"
                >
                    <span class="flex justify-between gap-3"
                        ><strong class="font-mono text-indigo-700">{{ thread.code }}</strong
                        ><small>{{ thread.messages_count }} tin</small></span
                    >
                    <strong>{{ thread.service_name }}</strong>
                    <span class="truncate text-xs text-slate-500"
                        >{{ thread.user?.name || 'Khách vãng lai' }} · CTV: {{ thread.collaborator?.name || 'Chưa giao' }}</span
                    >
                    <span class="truncate text-xs text-slate-600">{{ thread.last_message?.message || 'Chưa có tin nhắn' }}</span>
                </button>
                <p v-if="!loading && threads.length === 0" class="p-8 text-center text-sm text-slate-500">Chưa có đơn phù hợp.</p>
            </aside>

            <div v-if="selected" class="flex min-h-0 flex-col">
                <header class="border-b border-slate-200 p-4">
                    <strong>{{ selected.service_name }}</strong>
                    <p class="text-xs text-slate-500">
                        {{ selected.code }} · {{ selected.user?.name }} · CTV: {{ selected.collaborator?.name || 'Chưa giao' }}
                    </p>
                </header>
                <div class="grid min-h-0 flex-1 content-start gap-3 overflow-y-auto bg-slate-50 p-4">
                    <article
                        v-for="message in messages"
                        :key="message.id"
                        class="flex"
                        :class="message.sender_role === 'admin' ? 'justify-end' : 'justify-start'"
                    >
                        <div class="max-w-[80%]">
                            <p class="mb-1 text-xs font-bold text-slate-500">{{ roleLabel(message.sender_role) }} · {{ message.sender_name }}</p>
                            <div
                                class="rounded-lg px-4 py-2.5 text-sm shadow-sm"
                                :class="
                                    message.sender_role === 'admin'
                                        ? 'bg-indigo-600 text-white'
                                        : message.sender_role === 'collaborator'
                                          ? 'bg-emerald-100 text-emerald-950'
                                          : 'border border-slate-200 bg-white'
                                "
                            >
                                <p class="whitespace-pre-wrap break-words">{{ message.message }}</p>
                                <div v-if="message.progress" class="border-current/20 mt-2 border-t pt-2">
                                    <p class="mb-2 text-xs font-bold">Cập nhật tiến trình</p>
                                    <a
                                        v-if="message.progress.image_url"
                                        :href="message.progress.image_url"
                                        target="_blank"
                                        rel="noopener"
                                        class="border-current/20 block overflow-hidden rounded-md border bg-white/90"
                                    >
                                        <img :src="message.progress.image_url" alt="Ảnh tiến trình đơn hàng" class="max-h-72 w-full object-contain" />
                                    </a>
                                </div>
                            </div>
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
                        class="min-h-12 flex-1 resize-none rounded-md border border-slate-300 px-3 py-2"
                        placeholder="Admin gửi tin nhắn chen ngang..."
                    ></textarea
                    ><button
                        :disabled="sending"
                        class="grid size-12 place-items-center self-end rounded-md bg-indigo-600 text-white disabled:opacity-50"
                    >
                        <Send class="size-5" />
                    </button>
                </form>
            </div>
            <div v-else class="grid place-items-center text-slate-500">
                <div class="text-center">
                    <RefreshCw class="mx-auto size-8" />
                    <p class="mt-2">Chọn một đơn để xem chat</p>
                </div>
            </div>
        </section>
    </main>
</template>
