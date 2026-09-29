<script setup lang="ts">
import {
    clientAffiliateService,
    type CollaboratorOrder,
    type CollaboratorOrdersData,
    type GameServiceChatMessage,
} from '@/services/client-affiliate.service';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { CheckCircle2, LoaderCircle, MessageCircle, Play, Search, Send, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';

const result = ref<CollaboratorOrdersData>({ data: [], games: [], meta: { current_page: 1, last_page: 1, total: 0 } });
const filters = reactive({ search: '', game_id: '', status: 'pending', page: 1 });
const loading = ref(true);
const actionCode = ref<string | null>(null);
const selected = ref<CollaboratorOrder | null>(null);
const messages = ref<GameServiceChatMessage[]>([]);
const draft = ref('');
const sending = ref(false);
let refreshTimer: number | null = null;
const payloadEntries = computed(() => Object.entries(selected.value?.payload ?? {}));

const statusLabels: Record<CollaboratorOrder['status'], string> = {
    pending: 'Đang chờ',
    processing: 'Đang làm',
    review: 'Chờ admin duyệt',
    completed: 'Đã hoàn thành',
    failed: 'Thất bại',
    cancelled: 'Đã hủy',
};
const statusClasses: Record<CollaboratorOrder['status'], string> = {
    pending: 'bg-amber-100 text-amber-800',
    processing: 'bg-sky-100 text-sky-800',
    review: 'bg-violet-100 text-violet-800',
    completed: 'bg-emerald-100 text-emerald-800',
    failed: 'bg-rose-100 text-rose-800',
    cancelled: 'bg-slate-100 text-slate-700',
};
const money = (value: number | null): string => `${new Intl.NumberFormat('vi-VN').format(value ?? 0)}đ`;
const dateTime = (value: string): string => new Date(value).toLocaleString('vi-VN');
const role = (value: GameServiceChatMessage['sender_role']): string => ({ user: 'Khách hàng', collaborator: 'Bạn', admin: 'Quản trị viên' })[value];
const payloadLabel = (key: string): string =>
    key === 'note' ? 'Ghi chú' : key.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase());

const loadOrders = async (showLoading = true): Promise<void> => {
    if (showLoading) loading.value = true;
    try {
        result.value = await clientAffiliateService.gameServiceOrders({
            search: filters.search || undefined,
            game_id: filters.game_id || undefined,
            status: filters.status || undefined,
            page: filters.page,
        });
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        if (showLoading) loading.value = false;
    }
};
const applyFilters = (): void => {
    filters.page = 1;
    void loadOrders();
};
const changePage = (page: number): void => {
    filters.page = page;
    void loadOrders();
};
const refreshSummary = (): void => window.dispatchEvent(new Event('collaborator:refresh'));

const startOrder = async (order: CollaboratorOrder): Promise<void> => {
    actionCode.value = order.code;
    try {
        const response = await clientAffiliateService.startGameServiceOrder(order.code);
        handleSuccessResponse(response, 'Đã nhận xử lý đơn.');
        await loadOrders(false);
        refreshSummary();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        actionCode.value = null;
    }
};
const submitOrder = async (order: CollaboratorOrder): Promise<void> => {
    actionCode.value = order.code;
    try {
        const response = await clientAffiliateService.submitGameServiceOrder(order.code);
        handleSuccessResponse(response, 'Đã gửi đơn cho admin duyệt.');
        await loadOrders(false);
        refreshSummary();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        actionCode.value = null;
    }
};
const openChat = async (order: CollaboratorOrder): Promise<void> => {
    selected.value = order;
    try {
        messages.value = (await clientAffiliateService.gameServiceOrderThread(order.code)).messages;
    } catch (error) {
        handleErrorResponse(error);
    }
};
const closeChat = (): void => {
    selected.value = null;
    messages.value = [];
    draft.value = '';
};
const send = async (): Promise<void> => {
    if (!selected.value || !draft.value.trim()) return;
    sending.value = true;
    try {
        messages.value.push(await clientAffiliateService.sendGameServiceOrderMessage(selected.value.code, draft.value.trim()));
        draft.value = '';
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        sending.value = false;
    }
};

onMounted(async () => {
    await loadOrders();
    refreshTimer = window.setInterval(() => {
        void loadOrders(false);
        if (selected.value) void openChat(selected.value);
    }, 10000);
});
onBeforeUnmount(() => {
    if (refreshTimer !== null) window.clearInterval(refreshTimer);
});
</script>

<template>
    <main class="grid gap-5 p-4 sm:p-6">
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <form class="grid gap-3 lg:grid-cols-[minmax(240px,1fr)_220px_220px_auto]" @submit.prevent="applyFilters">
                <label class="relative">
                    <Search class="absolute left-3 top-1/2 size-5 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model.trim="filters.search"
                        class="min-h-12 w-full rounded-xl border-2 border-slate-200 pl-11 pr-4 outline-none focus:border-emerald-500"
                        placeholder="Tìm theo mã đơn"
                    />
                </label>
                <select v-model="filters.game_id" class="min-h-12 rounded-xl border-2 border-slate-200 px-3" @change="applyFilters">
                    <option value="">Tất cả game</option>
                    <option v-for="game in result.games" :key="game.id" :value="game.id">{{ game.name }}</option>
                </select>
                <select v-model="filters.status" class="min-h-12 rounded-xl border-2 border-slate-200 px-3" @change="applyFilters">
                    <option value="">Tất cả trạng thái</option>
                    <option v-for="(label, value) in statusLabels" :key="value" :value="value">{{ label }}</option>
                </select>
                <button class="min-h-12 rounded-xl bg-slate-950 px-5 font-bold text-white">Lọc đơn</button>
            </form>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <header class="flex items-center justify-between gap-4 border-b border-slate-200 p-5">
                <div>
                    <h1 class="font-black text-slate-950">Đơn dịch vụ được giao</h1>
                    <p class="text-sm text-slate-500">{{ result.meta.total }} đơn phù hợp bộ lọc</p>
                </div>
                <span v-if="filters.status === 'pending'" class="rounded-full bg-amber-100 px-3 py-1 text-xs font-black text-amber-800"
                    >Mặc định: đơn đang chờ</span
                >
            </header>
            <div v-if="loading" class="grid min-h-72 place-items-center"><LoaderCircle class="size-9 animate-spin text-emerald-600" /></div>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Mã đơn / thời gian</th>
                            <th class="px-5 py-3">Game / dịch vụ</th>
                            <th class="px-5 py-3">Gói / máy chủ</th>
                            <th class="px-5 py-3">Tiền công</th>
                            <th class="px-5 py-3">Trạng thái</th>
                            <th class="px-5 py-3 text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="order in result.data" :key="order.code" class="hover:bg-slate-50">
                            <td class="px-5 py-4">
                                <p class="font-mono font-black text-emerald-700">{{ order.code }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ dateTime(order.created_at) }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <p class="font-bold">{{ order.game_name }}</p>
                                <p class="text-slate-500">{{ order.service_name }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <p class="font-bold">{{ order.package_name }}</p>
                                <p class="text-slate-500">{{ order.server_name || 'Không yêu cầu máy chủ' }} · SL {{ order.quantity }}</p>
                            </td>
                            <td class="px-5 py-4 font-black text-emerald-700">{{ money(order.collaborator_amount) }}</td>
                            <td class="px-5 py-4">
                                <span class="rounded-full px-3 py-1 text-xs font-bold" :class="statusClasses[order.status]">{{
                                    statusLabels[order.status]
                                }}</span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        class="grid size-10 place-items-center rounded-xl border border-slate-200 hover:bg-slate-100"
                                        title="Trao đổi"
                                        @click="openChat(order)"
                                    >
                                        <MessageCircle class="size-5" />
                                    </button>
                                    <button
                                        v-if="order.status === 'pending'"
                                        type="button"
                                        :disabled="actionCode === order.code"
                                        class="inline-flex min-h-10 items-center gap-2 rounded-xl bg-sky-600 px-4 font-bold text-white disabled:opacity-50"
                                        @click="startOrder(order)"
                                    >
                                        <Play class="size-4" /> Nhận đơn
                                    </button>
                                    <button
                                        v-if="order.status === 'processing'"
                                        type="button"
                                        :disabled="actionCode === order.code"
                                        class="inline-flex min-h-10 items-center gap-2 rounded-xl bg-violet-600 px-4 font-bold text-white disabled:opacity-50"
                                        @click="submitOrder(order)"
                                    >
                                        <CheckCircle2 class="size-4" /> Gửi duyệt
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="result.data.length === 0" class="p-10 text-center text-slate-500">Không có đơn phù hợp bộ lọc.</p>
            </div>
            <footer v-if="result.meta.last_page > 1" class="flex items-center justify-end gap-2 border-t border-slate-200 p-4">
                <button
                    :disabled="result.meta.current_page <= 1"
                    class="rounded-lg border px-4 py-2 font-bold disabled:opacity-40"
                    @click="changePage(result.meta.current_page - 1)"
                >
                    Trước
                </button>
                <span class="px-2 text-sm font-bold">{{ result.meta.current_page }}/{{ result.meta.last_page }}</span>
                <button
                    :disabled="result.meta.current_page >= result.meta.last_page"
                    class="rounded-lg border px-4 py-2 font-bold disabled:opacity-40"
                    @click="changePage(result.meta.current_page + 1)"
                >
                    Sau
                </button>
            </footer>
        </section>

        <div v-if="selected" class="fixed inset-0 z-50 grid bg-slate-950/50 p-3 sm:place-items-center" @click.self="closeChat">
            <section class="flex min-h-0 w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl sm:h-[720px] sm:max-h-[90vh]">
                <header class="flex items-center justify-between border-b border-slate-200 p-4">
                    <div>
                        <p class="font-black">Trao đổi đơn {{ selected.code }}</p>
                        <p class="text-xs text-slate-500">{{ selected.service_name }} · {{ selected.package_name }}</p>
                    </div>
                    <button class="grid size-10 place-items-center rounded-xl hover:bg-slate-100" @click="closeChat"><X class="size-5" /></button>
                </header>
                <div class="border-b border-slate-200 bg-white p-4">
                    <p class="mb-3 text-xs font-black uppercase tracking-wider text-slate-500">Thông tin thực hiện đơn</p>
                    <dl class="grid gap-2 sm:grid-cols-2">
                        <div class="rounded-xl bg-slate-50 p-3">
                            <dt class="text-xs font-bold text-slate-500">Máy chủ</dt>
                            <dd class="mt-1 break-words font-bold">{{ selected.server_name || 'Không yêu cầu' }}</dd>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <dt class="text-xs font-bold text-slate-500">Số lượng</dt>
                            <dd class="mt-1 font-bold">{{ selected.quantity }}</dd>
                        </div>
                        <div v-for="[key, value] in payloadEntries" :key="key" class="rounded-xl bg-slate-50 p-3">
                            <dt class="text-xs font-bold text-slate-500">{{ payloadLabel(key) }}</dt>
                            <dd class="mt-1 whitespace-pre-wrap break-words font-bold">{{ value || '—' }}</dd>
                        </div>
                    </dl>
                </div>
                <div class="grid flex-1 content-start gap-3 overflow-y-auto bg-slate-50 p-4">
                    <article
                        v-for="message in messages"
                        :key="message.id"
                        class="flex"
                        :class="message.sender_role === 'collaborator' ? 'justify-end' : 'justify-start'"
                    >
                        <div class="max-w-[85%]">
                            <small class="font-bold text-slate-500">{{ role(message.sender_role) }} · {{ message.sender_name }}</small>
                            <p
                                class="mt-1 whitespace-pre-wrap rounded-xl px-4 py-2.5 text-sm"
                                :class="
                                    message.sender_role === 'collaborator'
                                        ? 'bg-emerald-600 text-white'
                                        : message.sender_role === 'admin'
                                          ? 'bg-indigo-100 text-indigo-950'
                                          : 'border border-slate-200 bg-white'
                                "
                            >
                                {{ message.message }}
                            </p>
                        </div>
                    </article>
                    <p v-if="messages.length === 0" class="place-self-center text-sm text-slate-500">Chưa có tin nhắn.</p>
                </div>
                <form class="flex gap-2 border-t border-slate-200 p-4" @submit.prevent="send">
                    <textarea
                        v-model="draft"
                        required
                        maxlength="5000"
                        rows="2"
                        class="flex-1 resize-none rounded-xl border-2 border-slate-200 px-3 py-2"
                        placeholder="Nhập tin nhắn..."
                    ></textarea
                    ><button
                        :disabled="sending"
                        class="grid size-12 place-items-center self-end rounded-xl bg-emerald-600 text-white disabled:opacity-50"
                    >
                        <Send class="size-5" />
                    </button>
                </form>
            </section>
        </div>
    </main>
</template>
