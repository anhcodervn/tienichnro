<script setup lang="ts">
import SecondaryPasswordDialog from '@/components/shared/SecondaryPasswordDialog.vue';
import {
    clientAffiliateService,
    type CollaboratorOrder,
    type CollaboratorOrderPreview,
    type CollaboratorOrdersData,
    type GameServiceOrderProgress,
} from '@/services/client-affiliate.service';
import { gameServiceSecondaryAuthService } from '@/services/game-service-secondary-auth.service';
import { useUserStore } from '@/stores/user.store';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { Camera, CheckCircle2, Eye, EyeOff, ImagePlus, LoaderCircle, MessageCircle, Play, Search, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

type OrderStatusFilter = CollaboratorOrder['status'] | '';

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
const normalizeStatus = (value: unknown): OrderStatusFilter => {
    if (value === 'all') return '';

    return typeof value === 'string' && value in statusLabels ? (value as CollaboratorOrder['status']) : 'pending';
};

const route = useRoute();
const router = useRouter();
const userStore = useUserStore();
const result = ref<CollaboratorOrdersData>({ data: [], games: [], meta: { current_page: 1, last_page: 1, total: 0 } });
const filters = reactive<{ search: string; game_id: string; status: OrderStatusFilter; page: number }>({
    search: '',
    game_id: '',
    status: normalizeStatus(route.query.status),
    page: 1,
});
const loading = ref(true);
const actionCode = ref<string | null>(null);
const previewLoadingCode = ref<string | null>(null);
const orderPreview = ref<CollaboratorOrderPreview | null>(null);
const workOrder = ref<CollaboratorOrderPreview | null>(null);
const workOrderLoadingCode = ref<string | null>(null);
const workTab = ref<'information' | 'progress' | 'completion'>('information');
const workTabs = [
    { key: 'information', label: '1. Thông tin' },
    { key: 'progress', label: '2. Tiến trình' },
    { key: 'completion', label: '3. Báo cáo hoàn thành' },
] as const;
const progressUpdates = ref<GameServiceOrderProgress[]>([]);
const progressDescription = ref('');
const progressImage = ref<File | null>(null);
const progressImagePreview = ref<string | null>(null);
const progressSaving = ref(false);
const completionDescription = ref('');
const completionImage = ref<File | null>(null);
const completionImagePreview = ref<string | null>(null);
const completionSaving = ref(false);
const workPayload = ref<Record<string, string | number | null>>({});
const payloadVisible = ref(false);
const payloadLoading = ref(false);
const secondaryDialogOpen = ref(false);
const secondaryConfigured = ref(false);
const secondaryUnlocking = ref(false);
let refreshTimer: number | null = null;
const payloadEntries = computed(() => Object.entries(workPayload.value));

const money = (value: number | null): string => `${new Intl.NumberFormat('vi-VN').format(value ?? 0)}đ`;
const dateTime = (value: string): string => new Date(value).toLocaleString('vi-VN');
const payloadLabel = (key: string): string =>
    key === 'note' ? 'Ghi chú' : key.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase());
const progressAuthor = (update: GameServiceOrderProgress): string => {
    if (update.author?.role === 'admin') return `Quản trị viên · ${update.author.name}`;

    return update.author?.name ? `CTV · ${update.author.name}` : 'Tài khoản đã xóa';
};
const releasePreview = (preview: { value: string | null }): void => {
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = null;
};
const selectImage = (event: Event, target: 'progress' | 'completion'): void => {
    const file = (event.currentTarget as HTMLInputElement).files?.[0] ?? null;
    const preview = target === 'progress' ? progressImagePreview : completionImagePreview;
    releasePreview(preview);
    if (target === 'progress') progressImage.value = file;
    else completionImage.value = file;
    if (file) preview.value = URL.createObjectURL(file);
};

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
const applyStatusFilter = async (): Promise<void> => {
    filters.page = 1;
    await router.replace({
        name: 'collaborator.orders',
        query: { ...route.query, status: filters.status || 'all' },
    });
    await loadOrders();
};
const changePage = (page: number): void => {
    filters.page = page;
    void loadOrders();
};
const refreshSummary = (): void => window.dispatchEvent(new Event('collaborator:refresh'));

const openOrderPreview = async (order: CollaboratorOrder): Promise<void> => {
    previewLoadingCode.value = order.code;
    try {
        orderPreview.value = await clientAffiliateService.gameServiceOrderPreview(order.code);
    } catch (error) {
        handleErrorResponse(error);
        await loadOrders(false);
    } finally {
        previewLoadingCode.value = null;
    }
};
const closeOrderPreview = (): void => {
    if (actionCode.value === orderPreview.value?.code) return;

    orderPreview.value = null;
};

const resetWorkForms = (): void => {
    progressDescription.value = '';
    progressImage.value = null;
    completionDescription.value = '';
    completionImage.value = null;
    releasePreview(progressImagePreview);
    releasePreview(completionImagePreview);
    workPayload.value = {};
    payloadVisible.value = false;
};

const openWorkOrder = async (order: CollaboratorOrder): Promise<void> => {
    workOrderLoadingCode.value = order.code;
    try {
        const [details, timeline] = await Promise.all([
            clientAffiliateService.gameServiceOrderPreview(order.code),
            clientAffiliateService.gameServiceOrderProgress(order.code),
        ]);
        resetWorkForms();
        workOrder.value = details;
        progressUpdates.value = timeline;
        workTab.value = 'information';
    } catch (error) {
        handleErrorResponse(error);
        await loadOrders(false);
    } finally {
        workOrderLoadingCode.value = null;
    }
};

const closeWorkOrder = (): void => {
    if (progressSaving.value || completionSaving.value) return;
    workOrder.value = null;
    progressUpdates.value = [];
    resetWorkForms();
};

const startOrder = async (order: CollaboratorOrder): Promise<void> => {
    actionCode.value = order.code;
    try {
        const response = await clientAffiliateService.startGameServiceOrder(order.code);
        handleSuccessResponse(response, 'Đã nhận xử lý đơn.');
        orderPreview.value = null;
        await loadOrders(false);
        refreshSummary();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        actionCode.value = null;
    }
};
const openChat = (order: CollaboratorOrder): void => {
    void router.push({ name: 'collaborator.chats', query: { order: order.code } });
};

const loadPayload = async (): Promise<void> => {
    if (!workOrder.value) return;
    payloadLoading.value = true;
    try {
        workPayload.value = await clientAffiliateService.gameServiceOrderPayload(workOrder.value.code);
        payloadVisible.value = true;
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        payloadLoading.value = false;
    }
};

const requestPayload = async (): Promise<void> => {
    try {
        const status = await gameServiceSecondaryAuthService.status();
        secondaryConfigured.value = status.configured;
        if (status.unlocked) {
            await loadPayload();
        } else {
            secondaryDialogOpen.value = true;
        }
    } catch (error) {
        handleErrorResponse(error);
    }
};

const hidePayload = (): void => {
    payloadVisible.value = false;
    workPayload.value = {};
};

const unlockSecondaryPassword = async (password: string): Promise<void> => {
    secondaryUnlocking.value = true;
    try {
        await gameServiceSecondaryAuthService.unlock(password);
        secondaryConfigured.value = true;
        secondaryDialogOpen.value = false;
        await loadPayload();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        secondaryUnlocking.value = false;
    }
};

const storeProgress = async (): Promise<void> => {
    if (!workOrder.value || !progressDescription.value.trim()) return;
    progressSaving.value = true;
    const payload = new FormData();
    payload.append('description', progressDescription.value.trim());
    if (progressImage.value) payload.append('image', progressImage.value);

    try {
        const response = await clientAffiliateService.storeGameServiceOrderProgress(workOrder.value.code, payload);
        handleSuccessResponse(response, 'Đã cập nhật tiến trình đơn.');
        progressUpdates.value = await clientAffiliateService.gameServiceOrderProgress(workOrder.value.code);
        progressDescription.value = '';
        progressImage.value = null;
        releasePreview(progressImagePreview);
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        progressSaving.value = false;
    }
};

const completeOrder = async (): Promise<void> => {
    if (!workOrder.value || !completionDescription.value.trim() || !completionImage.value) return;
    completionSaving.value = true;
    const payload = new FormData();
    payload.append('description', completionDescription.value.trim());
    payload.append('image', completionImage.value);

    try {
        const response = await clientAffiliateService.completeGameServiceOrder(workOrder.value.code, payload);
        handleSuccessResponse(response, 'Đã gửi báo cáo hoàn thành cho admin duyệt.');
        completionSaving.value = false;
        closeWorkOrder();
        await loadOrders(false);
        refreshSummary();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        completionSaving.value = false;
    }
};

watch(
    () => route.query.status,
    (status) => {
        const normalizedStatus = normalizeStatus(status);
        if (filters.status === normalizedStatus) return;

        filters.status = normalizedStatus;
        filters.page = 1;
        void loadOrders();
    },
);

onMounted(async () => {
    await loadOrders();
    refreshTimer = window.setInterval(() => {
        void loadOrders(false);
        if (workOrder.value) {
            void clientAffiliateService.gameServiceOrderProgress(workOrder.value.code).then((updates) => {
                progressUpdates.value = updates;
            });
        }
    }, 10000);
});
onBeforeUnmount(() => {
    if (refreshTimer !== null) window.clearInterval(refreshTimer);
    releasePreview(progressImagePreview);
    releasePreview(completionImagePreview);
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
                <select v-model="filters.status" class="min-h-12 rounded-xl border-2 border-slate-200 px-3" @change="applyStatusFilter">
                    <option value="">Tất cả trạng thái</option>
                    <option v-for="(label, value) in statusLabels" :key="value" :value="value">{{ label }}</option>
                </select>
                <button class="min-h-12 rounded-xl bg-slate-950 px-5 font-bold text-white">Lọc đơn</button>
            </form>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <header class="flex items-center justify-between gap-4 border-b border-slate-200 p-5">
                <div>
                    <h1 class="font-black text-slate-950">Đơn chờ nhận và đơn được giao</h1>
                    <p class="text-sm text-slate-500">{{ result.meta.total }} đơn phù hợp bộ lọc</p>
                </div>
                <span v-if="filters.status" class="rounded-full px-3 py-1 text-xs font-black" :class="statusClasses[filters.status]"
                    >Bộ lọc: {{ statusLabels[filters.status] }}</span
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
                                        v-if="order.can_chat"
                                        type="button"
                                        class="grid size-10 place-items-center rounded-xl border border-slate-200 hover:bg-slate-100"
                                        title="Trao đổi"
                                        @click="openChat(order)"
                                    >
                                        <MessageCircle class="size-5" />
                                    </button>
                                    <button
                                        v-if="order.can_claim"
                                        type="button"
                                        :disabled="previewLoadingCode === order.code"
                                        class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-sky-600 px-4 font-bold text-white transition hover:bg-sky-700 disabled:opacity-50"
                                        @click="openOrderPreview(order)"
                                    >
                                        <LoaderCircle v-if="previewLoadingCode === order.code" class="size-4 animate-spin" />
                                        <Eye v-else class="size-4" />
                                        {{ previewLoadingCode === order.code ? 'Đang tải...' : 'Xem đơn' }}
                                    </button>
                                    <button
                                        v-if="order.status === 'processing'"
                                        type="button"
                                        :disabled="workOrderLoadingCode === order.code"
                                        class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-violet-600 px-4 font-bold text-white disabled:opacity-50"
                                        @click="openWorkOrder(order)"
                                    >
                                        <LoaderCircle v-if="workOrderLoadingCode === order.code" class="size-4 animate-spin" />
                                        <Eye v-else class="size-4" />
                                        {{ workOrderLoadingCode === order.code ? 'Đang tải...' : 'Xem đơn' }}
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

        <div
            v-if="orderPreview"
            class="fixed inset-0 z-[60] grid place-items-center bg-slate-950/55 p-3"
            role="dialog"
            aria-modal="true"
            aria-labelledby="order-preview-title"
            @click.self="closeOrderPreview"
        >
            <section class="flex max-h-[calc(100dvh-1.5rem)] w-full max-w-2xl flex-col overflow-hidden rounded-lg bg-white shadow-2xl">
                <header class="flex items-start justify-between gap-4 border-b border-slate-200 p-5">
                    <div>
                        <p class="font-mono text-sm font-black text-emerald-700">{{ orderPreview.code }}</p>
                        <h2 id="order-preview-title" class="mt-1 text-xl font-black text-slate-950">Xem thông tin trước khi nhận đơn</h2>
                        <p class="mt-1 text-sm text-slate-500">Kiểm tra đúng dịch vụ và ghi chú của khách trước khi xác nhận.</p>
                    </div>
                    <button
                        type="button"
                        :disabled="actionCode === orderPreview.code"
                        class="grid size-10 shrink-0 place-items-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-50"
                        aria-label="Đóng"
                        @click="closeOrderPreview"
                    >
                        <X class="size-5" />
                    </button>
                </header>

                <div class="grid min-h-0 gap-5 overflow-y-auto p-5">
                    <dl class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Game</dt>
                            <dd class="mt-1 font-black text-slate-950">{{ orderPreview.game_name }}</dd>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Dịch vụ</dt>
                            <dd class="mt-1 font-black text-slate-950">{{ orderPreview.service_name }}</dd>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Gói dịch vụ</dt>
                            <dd class="mt-1 font-black text-slate-950">{{ orderPreview.package_name }}</dd>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Máy chủ</dt>
                            <dd class="mt-1 font-black text-slate-950">{{ orderPreview.server_name || 'Không yêu cầu' }}</dd>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Số lượng</dt>
                            <dd class="mt-1 font-black text-slate-950">{{ orderPreview.quantity }}</dd>
                        </div>
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                            <dt class="text-xs font-bold uppercase tracking-wide text-emerald-700">Tiền công</dt>
                            <dd class="mt-1 text-lg font-black text-emerald-800">{{ money(orderPreview.collaborator_amount) }}</dd>
                        </div>
                    </dl>

                    <section class="rounded-lg border-2 border-amber-200 bg-amber-50 p-4">
                        <h3 class="text-sm font-black uppercase tracking-wide text-amber-800">Ghi chú của người dùng</h3>
                        <p v-if="orderPreview.customer_note" class="mt-2 whitespace-pre-wrap break-words text-sm leading-6 text-slate-800">
                            {{ orderPreview.customer_note }}
                        </p>
                        <p v-else class="mt-2 text-sm italic text-slate-500">Người dùng không để lại ghi chú.</p>
                    </section>

                    <p class="rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm leading-6 text-sky-900">
                        Sau khi xác nhận, đơn sẽ chuyển sang trạng thái <strong>Đang làm</strong> và bạn mới có thể xem thông tin tài khoản, trao đổi
                        với khách hàng.
                    </p>
                </div>

                <footer class="flex flex-col-reverse gap-2 border-t border-slate-200 p-4 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        :disabled="actionCode === orderPreview.code"
                        class="min-h-11 rounded-lg border border-slate-300 px-5 font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                        @click="closeOrderPreview"
                    >
                        Đóng
                    </button>
                    <button
                        type="button"
                        :disabled="actionCode === orderPreview.code"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-5 font-black text-white transition hover:bg-emerald-700 disabled:opacity-50"
                        @click="startOrder(orderPreview)"
                    >
                        <LoaderCircle v-if="actionCode === orderPreview.code" class="size-5 animate-spin" />
                        <Play v-else class="size-5" />
                        {{ actionCode === orderPreview.code ? 'Đang nhận đơn...' : 'Xác nhận nhận đơn' }}
                    </button>
                </footer>
            </section>
        </div>

        <div
            v-if="workOrder"
            class="fixed inset-0 z-[70] grid place-items-center bg-slate-950/60 p-3"
            role="dialog"
            aria-modal="true"
            @click.self="closeWorkOrder"
        >
            <section class="flex max-h-[calc(100dvh-1.5rem)] w-full max-w-4xl flex-col overflow-hidden rounded-[11px] bg-white shadow-2xl">
                <header class="flex items-start justify-between gap-4 border-b border-slate-200 p-5">
                    <div>
                        <p class="font-mono text-sm font-black text-emerald-700">{{ workOrder.code }}</p>
                        <h2 class="mt-1 text-xl font-black text-slate-950">{{ workOrder.service_name }}</h2>
                        <p class="text-sm text-slate-500">{{ workOrder.game_name }} · {{ workOrder.package_name }}</p>
                    </div>
                    <button
                        type="button"
                        class="grid size-10 shrink-0 place-items-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-100"
                        aria-label="Đóng"
                        @click="closeWorkOrder"
                    >
                        <X class="size-5" />
                    </button>
                </header>

                <nav class="grid grid-cols-3 border-b border-slate-200 bg-slate-50 p-2" aria-label="Nội dung đơn">
                    <button
                        v-for="tab in workTabs"
                        :key="tab.key"
                        type="button"
                        class="min-h-11 rounded-lg px-2 text-sm font-black transition"
                        :class="workTab === tab.key ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
                        @click="workTab = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </nav>

                <div class="min-h-0 flex-1 overflow-y-auto p-5">
                    <section v-if="workTab === 'information'" class="grid gap-5">
                        <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <dt class="text-xs font-bold uppercase text-slate-500">Máy chủ</dt>
                                <dd class="mt-1 font-black">{{ workOrder.server_name || 'Không yêu cầu' }}</dd>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <dt class="text-xs font-bold uppercase text-slate-500">Số lượng</dt>
                                <dd class="mt-1 font-black">{{ workOrder.quantity }}</dd>
                            </div>
                            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                                <dt class="text-xs font-bold uppercase text-emerald-700">Tiền công</dt>
                                <dd class="mt-1 font-black text-emerald-800">{{ money(workOrder.collaborator_amount) }}</dd>
                            </div>
                        </dl>

                        <div class="rounded-lg border-2 border-amber-200 bg-amber-50 p-4">
                            <h3 class="text-sm font-black uppercase tracking-wide text-amber-800">Ghi chú của người dùng</h3>
                            <p v-if="workOrder.customer_note" class="mt-2 whitespace-pre-wrap break-words text-sm leading-6 text-slate-800">
                                {{ workOrder.customer_note }}
                            </p>
                            <p v-else class="mt-2 text-sm italic text-slate-500">Người dùng không để lại ghi chú.</p>
                        </div>

                        <section class="overflow-hidden rounded-lg border-2 border-slate-200">
                            <header class="flex items-center justify-between gap-3 bg-slate-50 px-4 py-3">
                                <div>
                                    <h3 class="font-black text-slate-950">Thông tin tài khoản khách</h3>
                                    <p class="text-xs text-slate-500">Mặc định được ẩn và chỉ giải mã sau khi xác thực mật khẩu C2.</p>
                                </div>
                                <button
                                    v-if="payloadVisible"
                                    type="button"
                                    class="grid size-10 place-items-center rounded-lg border border-slate-300 bg-white text-slate-700"
                                    title="Ẩn thông tin tài khoản"
                                    @click="hidePayload"
                                >
                                    <EyeOff class="size-5" />
                                </button>
                            </header>
                            <div v-if="!payloadVisible" class="flex items-center justify-between gap-4 p-4">
                                <p class="select-none font-mono text-xl font-black tracking-[0.24em] text-slate-400">••••••••••••</p>
                                <button
                                    type="button"
                                    :disabled="payloadLoading"
                                    class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-lg bg-indigo-600 px-4 font-bold text-white disabled:opacity-50"
                                    @click="requestPayload"
                                >
                                    <LoaderCircle v-if="payloadLoading" class="size-4 animate-spin" />
                                    <Eye v-else class="size-4" />
                                    {{ payloadLoading ? 'Đang giải mã...' : 'Xem tài khoản' }}
                                </button>
                            </div>
                            <dl v-else class="grid gap-2 p-4 sm:grid-cols-2">
                                <div v-for="[key, value] in payloadEntries" :key="key" class="rounded-lg border border-slate-200 bg-white p-3">
                                    <dt class="text-xs font-bold text-slate-500">{{ payloadLabel(key) }}</dt>
                                    <dd class="mt-1 whitespace-pre-wrap break-words font-black text-slate-950">{{ value || '—' }}</dd>
                                </div>
                                <p v-if="payloadEntries.length === 0" class="text-sm text-slate-500">Đơn không có thông tin tài khoản.</p>
                            </dl>
                        </section>
                    </section>

                    <section v-else-if="workTab === 'progress'" class="grid gap-5">
                        <div class="grid gap-3">
                            <article
                                v-for="update in progressUpdates"
                                :key="update.id"
                                class="rounded-lg border p-4"
                                :class="update.type === 'completion' ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-white'"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <strong :class="update.type === 'completion' ? 'text-emerald-800' : 'text-slate-900'">
                                        {{ update.type === 'completion' ? 'Báo cáo hoàn thành' : 'Cập nhật tiến trình' }}
                                    </strong>
                                    <time class="text-xs text-slate-500">{{ dateTime(update.created_at) }}</time>
                                </div>
                                <p class="mt-1 text-xs font-bold text-slate-500">{{ progressAuthor(update) }}</p>
                                <p class="mt-3 whitespace-pre-wrap break-words text-sm leading-6 text-slate-800">{{ update.description }}</p>
                                <a v-if="update.image_url" :href="update.image_url" target="_blank" rel="noopener" class="mt-3 block w-fit">
                                    <img
                                        :src="update.image_url"
                                        alt="Ảnh cập nhật tiến trình"
                                        class="max-h-72 rounded-lg border border-slate-200 object-contain"
                                    />
                                </a>
                            </article>
                            <p
                                v-if="progressUpdates.length === 0"
                                class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500"
                            >
                                Chưa có cập nhật tiến trình.
                            </p>
                        </div>

                        <form class="grid gap-4 border-t border-slate-200 pt-5" @submit.prevent="storeProgress">
                            <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                                Nội dung tiến trình
                                <textarea
                                    v-model="progressDescription"
                                    required
                                    maxlength="5000"
                                    rows="4"
                                    class="rounded-lg border-2 border-slate-300 px-3 py-2 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                    placeholder="Ví dụ: Đã đăng nhập, đang thực hiện 50% công việc..."
                                ></textarea>
                            </label>
                            <label
                                class="grid cursor-pointer gap-2 rounded-lg border-2 border-dashed border-slate-300 p-4 text-sm font-bold text-slate-700 hover:border-emerald-400"
                            >
                                <span class="inline-flex items-center gap-2"
                                    ><ImagePlus class="size-5 text-emerald-600" /> Ảnh tiến trình (không bắt buộc)</span
                                >
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    class="text-sm font-normal"
                                    @change="selectImage($event, 'progress')"
                                />
                                <img
                                    v-if="progressImagePreview"
                                    :src="progressImagePreview"
                                    alt="Xem trước ảnh tiến trình"
                                    class="max-h-64 rounded-lg object-contain"
                                />
                            </label>
                            <button
                                :disabled="progressSaving || !progressDescription.trim()"
                                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-5 font-black text-white disabled:opacity-50 sm:justify-self-end"
                            >
                                <LoaderCircle v-if="progressSaving" class="size-5 animate-spin" />
                                <CheckCircle2 v-else class="size-5" />
                                {{ progressSaving ? 'Đang cập nhật...' : 'Báo tiến trình' }}
                            </button>
                        </form>
                    </section>

                    <section v-else class="grid gap-5">
                        <div class="rounded-lg border border-violet-200 bg-violet-50 p-4 text-sm leading-6 text-violet-900">
                            Mô tả rõ kết quả đã làm và tải lên <strong>ít nhất 1 ảnh xác minh</strong>. Không có ảnh thì hệ thống không cho gửi báo
                            hoàn thành.
                        </div>
                        <form class="grid gap-4" @submit.prevent="completeOrder">
                            <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                                Mô tả kết quả hoàn thành
                                <textarea
                                    v-model="completionDescription"
                                    required
                                    maxlength="5000"
                                    rows="6"
                                    class="rounded-lg border-2 border-slate-300 px-3 py-2 outline-none focus:border-violet-500 focus:ring-4 focus:ring-violet-100"
                                    placeholder="Mô tả công việc đã hoàn thành và thông tin khách cần kiểm tra..."
                                ></textarea>
                            </label>
                            <label
                                class="grid cursor-pointer gap-2 rounded-lg border-2 border-dashed border-violet-300 bg-violet-50/50 p-4 text-sm font-bold text-slate-700 hover:border-violet-500"
                            >
                                <span class="inline-flex items-center gap-2"
                                    ><Camera class="size-5 text-violet-700" /> Ảnh xác minh hoàn thành *</span
                                >
                                <input
                                    required
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    class="text-sm font-normal"
                                    @change="selectImage($event, 'completion')"
                                />
                                <img
                                    v-if="completionImagePreview"
                                    :src="completionImagePreview"
                                    alt="Xem trước ảnh xác minh"
                                    class="max-h-80 rounded-lg object-contain"
                                />
                            </label>
                            <button
                                :disabled="completionSaving || !completionDescription.trim() || !completionImage"
                                class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg bg-violet-600 px-5 font-black text-white disabled:cursor-not-allowed disabled:opacity-50 sm:justify-self-end"
                            >
                                <LoaderCircle v-if="completionSaving" class="size-5 animate-spin" />
                                <CheckCircle2 v-else class="size-5" />
                                {{ completionSaving ? 'Đang gửi báo cáo...' : 'Báo hoàn thành và gửi duyệt' }}
                            </button>
                        </form>
                    </section>
                </div>
            </section>
        </div>

        <SecondaryPasswordDialog
            :open="secondaryDialogOpen"
            :configured="secondaryConfigured"
            :loading="secondaryUnlocking"
            :personal="userStore.user?.role === 'ctv'"
            @close="secondaryDialogOpen = false"
            @submit="unlockSecondaryPassword"
        />
    </main>
</template>
