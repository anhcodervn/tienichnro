<script setup lang="ts">
import { adminTopupService } from '@/services/admin-topup.service';
import { handleErrorResponse } from '@/utils/response';
import { echo } from '@laravel/echo-vue';
import {
    BadgeCheck,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    CircleDot,
    Clock3,
    Copy,
    Filter,
    FolderOpen,
    LoaderCircle,
    MoreVertical,
    Play,
    ReceiptText,
    RefreshCcw,
    RotateCcw,
    TriangleAlert,
    X,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch, type Component } from 'vue';
import OrderDetailModal from './components/OrderDetailModal.vue';
import OrderStatusBadge from './components/OrderStatusBadge.vue';
import type { ActionOption, OrderAction, OrderRow } from './types';

type Pagination = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

type OrderStatistics = {
    total: number;
    pending_payment: number;
    processing: number;
    failed: number;
};

type AdminTopupOrderUpdatedEvent = Pick<
    OrderRow,
    | 'id'
    | 'code'
    | 'payment_status'
    | 'order_status'
    | 'can_reorder'
    | 'can_sync_provider'
    | 'can_retry_provider_submission'
    | 'provider_reference'
    | 'failure_reason'
    | 'paid_at'
> & { updated_at: string };

const orders = ref<OrderRow[]>([]);
const orderDetails = ref<Record<string, OrderRow>>({});
const loading = ref(false);
const initialLoaded = ref(false);
const loadError = ref('');
const actingCode = ref<string | null>(null);
const selectedOrder = ref<OrderRow | null>(null);
const detailModalOpen = ref(false);
const detailModalLoading = ref(false);
const detailModalError = ref('');
const mobileFiltersOpen = ref(false);
const activeMenuCode = ref<string | null>(null);
const menuPosition = ref({ top: 0, right: 0 });
const copiedCode = ref<string | null>(null);
let copiedTimer: ReturnType<typeof setTimeout> | null = null;
let realtimeRefreshTimer: ReturnType<typeof setTimeout> | null = null;
let realtimeRefreshRunning = false;
let realtimeRefreshQueued = false;
let previousBodyOverflow = '';
const realtimeChannelName = 'admin.topup.orders';
const realtimeEventName = '.admin.topup.order.updated';
const pendingRealtimeCodes = new Set<string>();

const filters = reactive({ search: '', payment_status: '', order_status: '', per_page: 20, page: 1 });
const pagination = reactive<Pagination>({ current_page: 1, last_page: 1, per_page: 20, total: 0, from: null, to: null });
const statistics = reactive<OrderStatistics>({ total: 0, pending_payment: 0, processing: 0, failed: 0 });

const summaries = computed<{ label: string; value: number; icon: Component; classes: string; iconClasses: string }[]>(() => [
    {
        label: 'Tổng thẻ hôm nay',
        value: statistics.total,
        icon: FolderOpen,
        classes: 'border-sky-200/80 bg-gradient-to-br from-sky-50 to-white text-sky-700',
        iconClasses: 'bg-sky-100 text-sky-700',
    },
    {
        label: 'Thẻ chờ thanh toán',
        value: statistics.pending_payment,
        icon: Clock3,
        classes: 'border-amber-200/80 bg-gradient-to-br from-amber-50 to-white text-amber-700',
        iconClasses: 'bg-amber-100 text-amber-700',
    },
    {
        label: 'Thẻ đang xử lý',
        value: statistics.processing,
        icon: RefreshCcw,
        classes: 'border-indigo-200/80 bg-gradient-to-br from-indigo-50 to-white text-indigo-700',
        iconClasses: 'bg-indigo-100 text-indigo-700',
    },
    {
        label: 'Thẻ lỗi hôm nay',
        value: statistics.failed,
        icon: TriangleAlert,
        classes: 'border-rose-200/80 bg-gradient-to-br from-rose-50 to-white text-rose-700 ring-1 ring-rose-600/20',
        iconClasses: 'bg-rose-100 text-rose-700',
    },
]);

const formatMoney = (value: number | string): string => `${new Intl.NumberFormat('vi-VN').format(Number(value || 0))}đ`;
const formatDateTime = (value: string): string =>
    new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(
        new Date(value),
    );

const errorMessage = (error: unknown, fallback: string): string => {
    const candidate = error as { message?: string; response?: { data?: { message?: string } } };
    return candidate.response?.data?.message || candidate.message || fallback;
};

const primaryActionFor = (order: OrderRow): ActionOption => {
    if (order.payment_status === 'pending' && !['completed', 'cancelled'].includes(order.order_status)) {
        return { action: 'mark_paid', label: 'Đã nhận tiền', tone: 'primary' };
    }
    if (order.can_reorder) return { action: 'reorder', label: 'Đẩy lại thẻ lỗi', tone: 'primary' };
    if (order.can_retry_provider_submission) {
        return { action: 'retry_provider_submission', label: 'Đẩy lại qua provider', tone: 'primary' };
    }
    if (order.payment_status === 'paid' && order.order_status === 'pending') return { action: 'process', label: 'Xử lý đơn', tone: 'primary' };
    if (order.order_status === 'processing') return { action: 'complete', label: 'Hoàn thành', tone: 'primary' };
    return { action: 'detail', label: 'Xem chi tiết', tone: 'neutral' };
};

const secondaryActionsFor = (order: OrderRow): ActionOption[] => {
    const actions: ActionOption[] = [{ action: 'detail', label: 'Xem chi tiết', tone: 'neutral' }];
    if (order.can_sync_provider) actions.push({ action: 'complete', label: 'Hoàn thành thủ công', tone: 'neutral' });
    if (['pending', 'processing'].includes(order.order_status)) actions.push({ action: 'fail', label: 'Báo lỗi đơn', tone: 'danger' });
    if (!['completed', 'cancelled'].includes(order.order_status)) actions.push({ action: 'cancel', label: 'Hủy đơn', tone: 'danger' });
    return actions;
};

const closeMenu = (): void => {
    activeMenuCode.value = null;
};

const load = async (showLoading = true): Promise<void> => {
    if (showLoading) loading.value = true;
    loadError.value = '';
    closeMenu();
    try {
        const response = await adminTopupService.orders({
            search: filters.search || undefined,
            payment_status: filters.payment_status || undefined,
            order_status: filters.order_status || undefined,
            per_page: filters.per_page,
            page: filters.page,
        });
        const payload = response.data.data;
        const meta = payload.meta ?? payload;
        orders.value = payload.data ?? [];
        Object.assign(statistics, {
            total: Number(payload.statistics?.total || 0),
            pending_payment: Number(payload.statistics?.pending_payment || 0),
            processing: Number(payload.statistics?.processing || 0),
            failed: Number(payload.statistics?.failed || 0),
        });
        Object.assign(pagination, {
            current_page: Number(meta.current_page || 1),
            last_page: Number(meta.last_page || 1),
            per_page: Number(meta.per_page || filters.per_page),
            total: Number(meta.total || 0),
            from: meta.from ?? null,
            to: meta.to ?? null,
        });
    } catch (error) {
        loadError.value = errorMessage(error, 'Không thể tải danh sách đơn. Vui lòng thử lại.');
    } finally {
        if (showLoading) loading.value = false;
        initialLoaded.value = true;
    }
};

const applyRealtimeSnapshot = (event: AdminTopupOrderUpdatedEvent): void => {
    const snapshot: Partial<OrderRow> = {
        payment_status: event.payment_status,
        order_status: event.order_status,
        can_reorder: event.can_reorder,
        can_sync_provider: event.can_sync_provider,
        can_retry_provider_submission: event.can_retry_provider_submission,
        provider_reference: event.provider_reference,
        failure_reason: event.failure_reason,
        paid_at: event.paid_at,
    };

    orders.value = orders.value.map((order) => (order.code === event.code ? { ...order, ...snapshot } : order));

    if (orderDetails.value[event.code]) {
        orderDetails.value[event.code] = { ...orderDetails.value[event.code], ...snapshot };
    }

    if (selectedOrder.value?.code === event.code) {
        selectedOrder.value = { ...selectedOrder.value, ...snapshot };
    }
};

const flushRealtimeRefresh = async (): Promise<void> => {
    if (realtimeRefreshRunning) {
        realtimeRefreshQueued = true;
        return;
    }

    realtimeRefreshRunning = true;
    const changedCodes = [...pendingRealtimeCodes];
    pendingRealtimeCodes.clear();
    realtimeRefreshTimer = null;

    try {
        await load(false);

        for (const code of changedCodes) {
            delete orderDetails.value[code];

            if (!detailModalOpen.value || selectedOrder.value?.code !== code) continue;

            try {
                const response = await adminTopupService.order(code);
                orderDetails.value[code] = response.data.data;
                selectedOrder.value = orderDetails.value[code];
                detailModalError.value = '';
            } catch (error) {
                detailModalError.value = errorMessage(error, 'Không thể đồng bộ chi tiết đơn theo thời gian thực.');
            }
        }
    } finally {
        realtimeRefreshRunning = false;

        if (realtimeRefreshQueued || pendingRealtimeCodes.size > 0) {
            realtimeRefreshQueued = false;
            if (realtimeRefreshTimer) clearTimeout(realtimeRefreshTimer);
            realtimeRefreshTimer = setTimeout(() => void flushRealtimeRefresh(), 180);
        }
    }
};

const handleRealtimeOrderUpdated = (event: AdminTopupOrderUpdatedEvent): void => {
    applyRealtimeSnapshot(event);
    pendingRealtimeCodes.add(event.code);

    if (realtimeRefreshTimer) clearTimeout(realtimeRefreshTimer);
    realtimeRefreshTimer = setTimeout(() => void flushRealtimeRefresh(), 180);
};

const applyFilters = async (): Promise<void> => {
    filters.page = 1;
    mobileFiltersOpen.value = false;
    await load();
};

const clearFilters = async (): Promise<void> => {
    Object.assign(filters, { search: '', payment_status: '', order_status: '', per_page: 20, page: 1 });
    await load();
};

const changePage = async (page: number): Promise<void> => {
    if (page < 1 || page > pagination.last_page || page === pagination.current_page) return;
    filters.page = page;
    await load();
};

const openDetailModal = async (order: OrderRow): Promise<void> => {
    closeMenu();
    selectedOrder.value = orderDetails.value[order.code] ?? order;
    detailModalOpen.value = true;
    detailModalError.value = '';
    if (orderDetails.value[order.code]) return;
    detailModalLoading.value = true;
    try {
        const response = await adminTopupService.order(order.code);
        orderDetails.value[order.code] = response.data.data;
        selectedOrder.value = orderDetails.value[order.code];
    } catch (error) {
        detailModalError.value = errorMessage(error, 'Không thể tải dữ liệu chi tiết.');
    } finally {
        detailModalLoading.value = false;
    }
};

const retryDetailModal = async (): Promise<void> => {
    if (!selectedOrder.value) return;
    delete orderDetails.value[selectedOrder.value.code];
    await openDetailModal(selectedOrder.value);
};

const copyCode = async (code: string): Promise<void> => {
    try {
        await navigator.clipboard.writeText(code);
        copiedCode.value = code;
        if (copiedTimer) clearTimeout(copiedTimer);
        copiedTimer = setTimeout(() => (copiedCode.value = null), 1600);
    } catch {
        await Swal.fire({ icon: 'warning', title: 'Không thể sao chép', text: `Mã đơn: ${code}` });
    }
};

const toggleMenu = async (event: MouseEvent, order: OrderRow): Promise<void> => {
    if (activeMenuCode.value === order.code) return closeMenu();
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    const estimatedMenuHeight = 142;
    const top = rect.bottom + estimatedMenuHeight + 12 > window.innerHeight ? Math.max(12, rect.top - estimatedMenuHeight - 8) : rect.bottom + 8;
    menuPosition.value = { top, right: Math.max(12, window.innerWidth - rect.right) };
    activeMenuCode.value = order.code;
    await nextTick();
};

const confirmationFor = (order: OrderRow, action: Exclude<OrderAction, 'detail'>): { title: string; text: string; confirm: string } =>
    ({
        mark_paid: {
            title: 'Xác nhận đã nhận tiền?',
            text: `Đơn ${order.code} sẽ chuyển sang đã thanh toán và được đưa vào xử lý.`,
            confirm: 'Đã nhận tiền',
        },
        process: { title: 'Xử lý đơn này?', text: `Đơn ${order.code} sẽ được đưa vào queue provider.`, confirm: 'Xử lý đơn' },
        reorder: {
            title: 'Đẩy lại thẻ lỗi?',
            text: `Chỉ các lượt provider đã xác nhận thất bại của đơn ${order.code} được gửi lại.`,
            confirm: 'Đẩy lại thẻ lỗi',
        },
        retry_provider_submission: {
            title: 'Đẩy lại đơn qua provider?',
            text: `Hệ thống sẽ kiểm tra lại số dư provider và tự động gửi đơn ${order.code} qua API nếu số dư đã đủ.`,
            confirm: 'Đẩy lại qua provider',
        },
        sync_provider: {
            title: 'Kiểm tra lại trạng thái provider?',
            text: `Hệ thống sẽ gọi provider để kiểm tra ngay trạng thái thực tế của đơn ${order.code}.`,
            confirm: 'Kiểm tra ngay',
        },
        complete: { title: 'Đánh dấu hoàn thành?', text: `Xác nhận toàn bộ đơn ${order.code} đã hoàn thành.`, confirm: 'Hoàn thành' },
        fail: { title: 'Báo lỗi đơn?', text: `Đơn ${order.code} sẽ chuyển sang trạng thái lỗi.`, confirm: 'Báo lỗi' },
        cancel: { title: 'Hủy đơn?', text: `Thao tác này sẽ hủy đơn ${order.code}.`, confirm: 'Hủy đơn' },
    })[action];

const act = async (order: OrderRow, action: OrderAction): Promise<void> => {
    closeMenu();
    if (action === 'detail') return openDetailModal(order);
    if (actingCode.value !== null) return;
    const confirmation = confirmationFor(order, action);
    const needsReason = ['fail', 'cancel'].includes(action);
    const result = await Swal.fire({
        icon: needsReason ? 'warning' : 'question',
        title: confirmation.title,
        text: confirmation.text,
        input: needsReason ? 'textarea' : undefined,
        inputLabel: needsReason ? 'Lý do thao tác' : undefined,
        inputPlaceholder: needsReason ? 'Nhập lý do để lưu audit...' : undefined,
        inputValidator: needsReason ? (value) => (!String(value || '').trim() ? 'Vui lòng nhập lý do.' : undefined) : undefined,
        showCancelButton: true,
        confirmButtonText: confirmation.confirm,
        cancelButtonText: 'Đóng',
        confirmButtonColor: needsReason ? '#be123c' : '#4f46e5',
        reverseButtons: true,
    });
    if (!result.isConfirmed) return;
    actingCode.value = order.code;
    try {
        const response = await adminTopupService.updateOrder(order.code, action, needsReason ? String(result.value).trim() : undefined);
        const updatedOrder = response.data.data as OrderRow;
        delete orderDetails.value[order.code];

        if (detailModalOpen.value && selectedOrder.value?.code === order.code) {
            orderDetails.value[order.code] = updatedOrder;
            selectedOrder.value = updatedOrder;
        }

        await load();
        await Swal.fire({
            icon: 'success',
            title: 'Thao tác thành công',
            text: response.data?.message || `Đã cập nhật đơn ${order.code}.`,
            timer: 1800,
            showConfirmButton: false,
        });
    } catch (error) {
        handleErrorResponse(error as Parameters<typeof handleErrorResponse>[0]);
    } finally {
        actingCode.value = null;
    }
};

const runPrimaryAction = async (order: OrderRow): Promise<void> => act(order, primaryActionFor(order).action);
const activeMenuOrder = computed(() => orders.value.find((order) => order.code === activeMenuCode.value) ?? null);
const handleEscape = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') closeMenu();
};

watch(detailModalOpen, (isOpen) => {
    if (isOpen) {
        previousBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return;
    }
    document.body.style.overflow = previousBodyOverflow;
});

onMounted(() => {
    document.addEventListener('click', closeMenu);
    window.addEventListener('resize', closeMenu);
    window.addEventListener('scroll', closeMenu, true);
    window.addEventListener('keydown', handleEscape);
    const realtimeChannel = echo().private(realtimeChannelName);
    realtimeChannel.listen(realtimeEventName, handleRealtimeOrderUpdated);
    realtimeChannel.subscribed(() => {
        if (initialLoaded.value) void load(false);
    });
    void load();
});
onBeforeUnmount(() => {
    document.removeEventListener('click', closeMenu);
    window.removeEventListener('resize', closeMenu);
    window.removeEventListener('scroll', closeMenu, true);
    window.removeEventListener('keydown', handleEscape);
    echo().private(realtimeChannelName).stopListening(realtimeEventName, handleRealtimeOrderUpdated);
    echo().leave(realtimeChannelName);
    document.body.style.overflow = previousBodyOverflow;
    if (copiedTimer) clearTimeout(copiedTimer);
    if (realtimeRefreshTimer) clearTimeout(realtimeRefreshTimer);
});
</script>

<template>
    <section class="space-y-5">
        <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-bold text-indigo-600">Order operations</p>
                <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Đơn nạp game</h1>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">
                    Theo dõi thanh toán, tiến độ provider và xử lý từng đơn từ một bảng duy nhất.
                </p>
            </div>
            <button
                type="button"
                class="inline-flex min-h-11 items-center justify-center gap-2 self-start rounded-xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-700 shadow-sm transition hover:border-indigo-200 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50 lg:self-auto"
                :disabled="loading"
                @click="load"
            >
                <RefreshCcw class="h-4 w-4" :class="loading ? 'animate-spin' : ''" />Làm mới dữ liệu
            </button>
        </header>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Thống kê số lượng thẻ hôm nay">
            <article
                v-for="summary in summaries"
                :key="summary.label"
                class="flex min-h-24 items-center gap-4 rounded-2xl border p-4 shadow-[0_8px_24px_-18px_rgba(15,23,42,0.28)]"
                :class="summary.classes"
            >
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl shadow-sm" :class="summary.iconClasses"
                    ><component :is="summary.icon" class="h-6 w-6"
                /></span>
                <div>
                    <p class="text-sm font-semibold opacity-90">{{ summary.label }}</p>
                    <strong class="mt-1 block text-2xl font-black tabular-nums leading-none">{{
                        !initialLoaded && loading ? '—' : summary.value
                    }}</strong
                    ><span class="mt-1 block text-xs opacity-70">thẻ</span>
                </div>
            </article>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-[0_12px_35px_-24px_rgba(15,23,42,0.35)] sm:p-4">
            <button
                type="button"
                class="flex min-h-11 w-full items-center justify-between rounded-xl bg-slate-50 px-3 text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 lg:hidden"
                :aria-expanded="mobileFiltersOpen"
                @click="mobileFiltersOpen = !mobileFiltersOpen"
            >
                <span class="inline-flex items-center gap-2"><Filter class="h-4 w-4" />Bộ lọc đơn hàng</span
                ><ChevronDown class="h-4 w-4 transition" :class="mobileFiltersOpen ? 'rotate-180' : ''" />
            </button>
            <form
                :class="mobileFiltersOpen ? 'grid' : 'hidden lg:grid'"
                class="mt-3 gap-3 lg:mt-0 lg:grid-cols-[minmax(260px,1fr)_190px_190px_110px_auto] lg:items-end"
                @submit.prevent="applyFilters"
            >
                <label class="text-sm font-bold text-slate-700"
                    >Tìm đơn<span class="relative mt-1.5 block"
                        ><Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" /><input
                            v-model.trim="filters.search"
                            class="min-h-11 w-full rounded-xl border-slate-200 bg-slate-50 pl-9 pr-3 font-normal outline-none transition placeholder:text-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-100"
                            placeholder="Mã đơn hoặc email..." /></span
                ></label>
                <label class="text-sm font-bold text-slate-700"
                    >Thanh toán<select
                        v-model="filters.payment_status"
                        class="mt-1.5 min-h-11 w-full rounded-xl border-slate-200 bg-slate-50 px-3 font-normal focus:border-indigo-500 focus:ring-indigo-100"
                    >
                        <option value="">Tất cả</option>
                        <option value="pending">Chờ thanh toán</option>
                        <option value="paid">Đã thanh toán</option>
                        <option value="refunded">Đã hoàn tiền</option>
                        <option value="expired">Hết hạn</option>
                        <option value="cancelled">Đã hủy</option>
                    </select></label
                >
                <label class="text-sm font-bold text-slate-700"
                    >Xử lý<select
                        v-model="filters.order_status"
                        class="mt-1.5 min-h-11 w-full rounded-xl border-slate-200 bg-slate-50 px-3 font-normal focus:border-indigo-500 focus:ring-indigo-100"
                    >
                        <option value="">Tất cả</option>
                        <option value="pending">Chờ xử lý</option>
                        <option value="processing">Đang xử lý</option>
                        <option value="completed">Hoàn thành</option>
                        <option value="failed">Báo lỗi</option>
                        <option value="cancelled">Đã hủy</option>
                    </select></label
                >
                <label class="text-sm font-bold text-slate-700"
                    >Số dòng<select
                        v-model.number="filters.per_page"
                        class="mt-1.5 min-h-11 w-full rounded-xl border-slate-200 bg-slate-50 px-3 font-normal focus:border-indigo-500 focus:ring-indigo-100"
                    >
                        <option :value="10">10</option>
                        <option :value="20">20</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select></label
                >
                <div class="flex gap-2">
                    <button
                        type="submit"
                        class="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 lg:flex-none"
                    >
                        <Filter class="h-4 w-4" />Áp dụng</button
                    ><button
                        type="button"
                        class="grid h-11 w-11 place-items-center rounded-xl border border-slate-200 text-slate-600 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        aria-label="Xóa bộ lọc"
                        title="Xóa bộ lọc"
                        @click="clearFilters"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
            </form>
        </div>

        <section
            class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_14px_40px_-28px_rgba(15,23,42,0.38)]"
            aria-label="Bảng đơn nạp game"
            :aria-busy="loading"
        >
            <div v-if="loading && initialLoaded" class="absolute inset-x-0 top-0 z-10 h-0.5 overflow-hidden bg-indigo-100">
                <span class="block h-full w-1/3 animate-pulse bg-indigo-600" />
            </div>
            <div v-if="loadError" class="m-4 rounded-xl border border-rose-200 bg-rose-50 p-4" role="alert">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-bold text-rose-800">Không tải được danh sách đơn</p>
                        <p class="mt-1 text-sm text-rose-700">{{ loadError }}</p>
                    </div>
                    <button type="button" class="min-h-10 rounded-lg bg-rose-700 px-4 text-sm font-bold text-white" @click="load">Thử lại</button>
                </div>
            </div>
            <div v-else-if="loading && !initialLoaded" class="space-y-3 p-4" aria-label="Đang tải đơn hàng">
                <div v-for="index in 6" :key="index" class="h-16 animate-pulse rounded-xl bg-slate-100" />
            </div>
            <div v-else-if="orders.length === 0" class="grid min-h-72 place-items-center px-5 text-center">
                <div>
                    <ReceiptText class="mx-auto h-10 w-10 text-slate-300" />
                    <p class="mt-3 font-bold text-slate-800">Không có đơn phù hợp</p>
                    <p class="mt-1 text-sm text-slate-500">Thử thay đổi từ khóa hoặc bộ lọc trạng thái.</p>
                    <button type="button" class="mt-4 text-sm font-bold text-indigo-700 hover:underline" @click="clearFilters">Xóa bộ lọc</button>
                </div>
            </div>

            <template v-else>
                <div class="hidden overflow-x-auto lg:block">
                    <table class="w-full min-w-[1180px] text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50/80 text-xs font-black uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="w-[205px] px-4 py-3.5">Đơn hàng</th>
                                <th class="w-[230px] px-4 py-3.5">Game / Tài khoản</th>
                                <th class="w-[190px] px-4 py-3.5">Gói nạp</th>
                                <th class="w-[175px] px-4 py-3.5">Thanh toán</th>
                                <th class="w-[190px] px-4 py-3.5">Xử lý provider</th>
                                <th class="px-4 py-3.5 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="order in orders"
                                :key="order.id"
                                class="cursor-pointer align-top transition-colors focus-within:bg-indigo-50/35 hover:bg-indigo-50/35"
                                tabindex="0"
                                @click="openDetailModal(order)"
                                @keydown.enter="openDetailModal(order)"
                            >
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-1">
                                        <span class="font-mono text-sm font-black text-indigo-700">{{ order.code }}</span
                                        ><button
                                            type="button"
                                            class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-white hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                            :aria-label="`Sao chép mã đơn ${order.code}`"
                                            @click.stop="copyCode(order.code)"
                                        >
                                            <BadgeCheck v-if="copiedCode === order.code" class="h-4 w-4 text-emerald-600" /><Copy
                                                v-else
                                                class="h-3.5 w-3.5"
                                            />
                                        </button>
                                    </div>
                                    <p class="mt-1 max-w-[185px] truncate text-xs text-slate-500" :title="order.email">{{ order.email }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ formatDateTime(order.created_at) }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-bold text-slate-900">{{ order.game || 'Chưa xác định game' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ order.server || 'Mọi máy chủ' }}</p>
                                    <p
                                        class="mt-2 inline-flex max-w-[205px] rounded-lg bg-slate-100 px-2 py-1 font-mono text-xs font-semibold text-slate-700"
                                    >
                                        {{ order.game_account || 'Chưa có tài khoản' }}
                                    </p>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-bold text-slate-900">{{ order.package_name }}</p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Số lượng: <strong class="text-slate-700">{{ order.quantity }}</strong>
                                    </p>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="text-base font-black tabular-nums text-slate-950">{{ formatMoney(order.total_amount) }}</p>
                                    <OrderStatusBadge class="mt-2" kind="payment" :status="order.payment_status" />
                                </td>
                                <td class="px-4 py-4">
                                    <OrderStatusBadge kind="order" :status="order.order_status" />
                                    <p
                                        v-if="order.provider_reference"
                                        class="mt-2 max-w-[175px] truncate font-mono text-xs text-slate-500"
                                        :title="order.provider_reference"
                                    >
                                        Ref: {{ order.provider_reference }}
                                    </p>
                                    <p
                                        v-if="order.failure_reason"
                                        class="mt-2 line-clamp-2 text-xs leading-5 text-rose-600"
                                        :title="order.failure_reason"
                                    >
                                        {{ order.failure_reason }}
                                    </p>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl px-3.5 text-sm font-bold shadow-sm transition focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                                            :class="
                                                primaryActionFor(order).action === 'detail'
                                                    ? 'border border-slate-200 bg-white text-slate-700 hover:border-indigo-200 hover:text-indigo-700'
                                                    : 'bg-indigo-600 text-white hover:bg-indigo-700'
                                            "
                                            :disabled="actingCode === order.code"
                                            @click.stop="runPrimaryAction(order)"
                                        >
                                            <LoaderCircle v-if="actingCode === order.code" class="h-4 w-4 animate-spin" /><RotateCcw
                                                v-else-if="primaryActionFor(order).action === 'reorder'"
                                                class="h-4 w-4"
                                            /><Play v-else class="h-4 w-4" />{{ primaryActionFor(order).label }}</button
                                        ><button
                                            type="button"
                                            class="grid h-10 w-10 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:border-indigo-200 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                            :aria-expanded="activeMenuCode === order.code"
                                            :aria-label="`Thêm thao tác cho đơn ${order.code}`"
                                            @click.stop="toggleMenu($event, order)"
                                        >
                                            <MoreVertical class="h-5 w-5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="divide-y divide-slate-100 lg:hidden">
                    <article
                        v-for="order in orders"
                        :key="order.id"
                        class="cursor-pointer p-4 transition hover:bg-indigo-50/30"
                        @click="openDetailModal(order)"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1">
                                    <p class="truncate font-mono text-sm font-black text-indigo-700">{{ order.code }}</p>
                                    <button
                                        type="button"
                                        class="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-slate-400"
                                        aria-label="Sao chép mã đơn"
                                        @click.stop="copyCode(order.code)"
                                    >
                                        <BadgeCheck v-if="copiedCode === order.code" class="h-4 w-4 text-emerald-600" /><Copy
                                            v-else
                                            class="h-4 w-4"
                                        />
                                    </button>
                                </div>
                                <p class="truncate text-xs text-slate-500">{{ order.email }}</p>
                            </div>
                            <p class="shrink-0 text-base font-black tabular-nums text-slate-950">{{ formatMoney(order.total_amount) }}</p>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-3 rounded-xl bg-slate-50 p-3 text-sm">
                            <div>
                                <p class="text-xs text-slate-500">Game / tài khoản</p>
                                <p class="mt-1 font-bold text-slate-800">{{ order.game || 'Chưa xác định' }}</p>
                                <p class="mt-0.5 truncate font-mono text-xs text-slate-600">{{ order.game_account || '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500">Gói nạp</p>
                                <p class="mt-1 font-bold text-slate-800">{{ order.package_name }}</p>
                                <p class="mt-0.5 text-xs text-slate-600">Số lượng: {{ order.quantity }}</p>
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <OrderStatusBadge kind="payment" :status="order.payment_status" /><OrderStatusBadge
                                kind="order"
                                :status="order.order_status"
                            />
                        </div>
                        <div class="mt-4 flex items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl bg-indigo-600 px-3 text-sm font-bold text-white shadow-sm disabled:opacity-50"
                                :disabled="actingCode === order.code"
                                @click.stop="runPrimaryAction(order)"
                            >
                                <LoaderCircle v-if="actingCode === order.code" class="h-4 w-4 animate-spin" /><CircleDot v-else class="h-4 w-4" />{{
                                    primaryActionFor(order).label
                                }}</button
                            ><button
                                type="button"
                                class="grid h-11 w-11 shrink-0 place-items-center rounded-xl border border-slate-200 text-slate-600"
                                aria-label="Thêm thao tác"
                                @click.stop="toggleMenu($event, order)"
                            >
                                <MoreVertical class="h-5 w-5" />
                            </button>
                        </div>
                    </article>
                </div>
            </template>

            <footer
                v-if="!loadError && initialLoaded"
                class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3.5 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between"
            >
                <span>Hiển thị {{ pagination.from || 0 }}–{{ pagination.to || 0 }} trong tổng số {{ pagination.total }} đơn</span>
                <div class="flex items-center justify-between gap-2 sm:justify-end">
                    <button
                        type="button"
                        class="grid h-10 w-10 place-items-center rounded-xl border border-slate-200 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="pagination.current_page <= 1 || loading"
                        aria-label="Trang trước"
                        @click="changePage(pagination.current_page - 1)"
                    >
                        <ChevronLeft class="h-4 w-4" /></button
                    ><span class="min-w-24 text-center font-bold text-slate-700">Trang {{ pagination.current_page }}/{{ pagination.last_page }}</span
                    ><button
                        type="button"
                        class="grid h-10 w-10 place-items-center rounded-xl border border-slate-200 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="pagination.current_page >= pagination.last_page || loading"
                        aria-label="Trang sau"
                        @click="changePage(pagination.current_page + 1)"
                    >
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>
            </footer>
        </section>

        <Teleport to="body">
            <div
                v-if="activeMenuOrder"
                class="fixed z-[60] w-52 overflow-hidden rounded-xl border border-slate-200 bg-white p-1.5 shadow-[0_18px_50px_-12px_rgba(15,23,42,0.35)]"
                :style="{ top: `${menuPosition.top}px`, right: `${menuPosition.right}px` }"
                role="menu"
                @click.stop
            >
                <button
                    v-for="option in secondaryActionsFor(activeMenuOrder)"
                    :key="option.action"
                    type="button"
                    class="flex min-h-10 w-full items-center rounded-lg px-3 text-left text-sm font-semibold transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500"
                    :class="option.tone === 'danger' ? 'text-rose-700 hover:bg-rose-50' : 'text-slate-700'"
                    role="menuitem"
                    @click="act(activeMenuOrder, option.action)"
                >
                    {{ option.label }}
                </button>
            </div>
        </Teleport>

        <p class="sr-only" aria-live="polite">{{ copiedCode ? `Đã sao chép mã đơn ${copiedCode}` : '' }}</p>
        <OrderDetailModal
            :open="detailModalOpen"
            :order="selectedOrder"
            :loading="detailModalLoading"
            :error="detailModalError"
            :primary-action="selectedOrder ? primaryActionFor(selectedOrder) : null"
            :acting="selectedOrder ? actingCode === selectedOrder.code : false"
            @close="detailModalOpen = false"
            @retry="retryDetailModal"
            @copy="copyCode"
            @action="selectedOrder && act(selectedOrder, $event)"
        />
    </section>
</template>
