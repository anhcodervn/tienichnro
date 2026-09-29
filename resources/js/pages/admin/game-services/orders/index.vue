<script setup lang="ts">
import {
    adminGameServiceService,
    type GameServiceOrder,
    type GameServiceOrderSettlement,
    type GameServiceOrderStatus,
} from '@/services/admin-game-service.service';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { Banknote, ClipboardList, Eye, HandCoins, LoaderCircle, ReceiptText, Save, Scale, Search, WalletCards, X } from 'lucide-vue-next';
import { onMounted, reactive, ref } from 'vue';

const orders = ref<GameServiceOrder[]>([]);
const loading = ref(false);
const saving = ref(false);
const selectedOrder = ref<GameServiceOrder | null>(null);
const collaborators = ref<Array<{ id: number; name: string }>>([]);
const settlement = ref<GameServiceOrderSettlement>({
    approved_orders: 0,
    settled_orders: 0,
    revenue: 0,
    collaborator_cost: 0,
    gross_profit: 0,
    estimated_tax: 0,
    net_profit: 0,
    loss_orders: 0,
    legacy_orders: 0,
});
const filters = reactive({ search: '', status: '' });
const editForm = reactive({ status: 'pending' as GameServiceOrderStatus, collaborator_id: null as number | null, admin_note: '' });
const inputClass =
    'min-h-11 w-full rounded-md border-2 border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-950 outline-none transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-100';
const money = (value: number): string => `${new Intl.NumberFormat('vi-VN').format(value)}đ`;
const dateTime = (value: string | null): string =>
    value ? new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : '—';
const statusLabels: Record<GameServiceOrderStatus, string> = {
    pending: 'Chờ xử lý',
    processing: 'Đang xử lý',
    review: 'Chờ admin duyệt',
    completed: 'Hoàn thành',
    failed: 'Thất bại',
    cancelled: 'Đã hủy',
};
const statusClasses: Record<GameServiceOrderStatus, string> = {
    pending: 'bg-amber-100 text-amber-700',
    processing: 'bg-sky-100 text-sky-700',
    review: 'bg-violet-100 text-violet-700',
    completed: 'bg-emerald-100 text-emerald-700',
    failed: 'bg-rose-100 text-rose-700',
    cancelled: 'bg-slate-100 text-slate-600',
};

const load = async (): Promise<void> => {
    loading.value = true;
    try {
        const response = await adminGameServiceService.orders({
            search: filters.search || undefined,
            status: filters.status || undefined,
            per_page: 100,
        });
        orders.value = response.data.data.data;
        settlement.value = response.data.data.summary;
        if (collaborators.value.length === 0) {
            collaborators.value = (await adminGameServiceService.chatCollaborators()).data.data;
        }
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

const openOrder = (order: GameServiceOrder): void => {
    selectedOrder.value = order;
    editForm.status = order.status;
    editForm.collaborator_id = order.collaborator_id;
    editForm.admin_note = order.admin_note ?? '';
};

const saveOrder = async (): Promise<void> => {
    if (!selectedOrder.value) return;
    saving.value = true;
    try {
        const response = await adminGameServiceService.updateOrder(selectedOrder.value.code, {
            status: editForm.status,
            collaborator_id: editForm.collaborator_id,
            admin_note: editForm.admin_note || null,
        });
        selectedOrder.value = response.data.data;
        handleSuccessResponse({ data: { status: true, message: 'Đã cập nhật đơn dịch vụ.' } });
        await load();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
};

onMounted(load);
</script>

<template>
    <main class="grid gap-5 p-4 sm:p-6">
        <header class="flex flex-col gap-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-start gap-3">
                <span class="grid size-11 shrink-0 place-items-center rounded-lg bg-indigo-100 text-indigo-700"
                    ><ClipboardList class="size-6"
                /></span>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-indigo-600">Dịch vụ game</p>
                    <h1 class="mt-1 text-2xl font-black text-slate-950">Quản lý đơn order</h1>
                    <p class="mt-1 text-sm text-slate-500">Theo dõi payload, giá snapshot và cập nhật trạng thái xử lý đơn dịch vụ.</p>
                </div>
            </div>
            <form class="grid gap-2 sm:grid-cols-[minmax(14rem,1fr)_11rem_auto]" @submit.prevent="load">
                <label class="relative"
                    ><Search class="absolute left-3 top-3.5 size-4 text-slate-400" /><input
                        v-model.trim="filters.search"
                        :class="[inputClass, 'pl-9']"
                        placeholder="Mã đơn, email, dịch vụ..."
                /></label>
                <select v-model="filters.status" :class="inputClass">
                    <option value="">Mọi trạng thái</option>
                    <option v-for="(label, status) in statusLabels" :key="status" :value="status">{{ label }}</option>
                </select>
                <button class="min-h-11 rounded-md bg-slate-950 px-4 text-sm font-bold text-white">Lọc</button>
            </form>
        </header>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5" aria-label="Kết toán các đơn dịch vụ đã duyệt">
            <article class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                <div class="flex items-center justify-between gap-3 text-emerald-700">
                    <span class="text-xs font-black uppercase tracking-wide">Doanh thu</span><Banknote class="size-5" />
                </div>
                <p class="mt-2 text-xl font-black text-emerald-800">{{ money(settlement.revenue) }}</p>
                <p class="mt-1 text-xs text-emerald-700">{{ settlement.settled_orders }}/{{ settlement.approved_orders }} đơn đã duyệt có snapshot</p>
            </article>
            <article class="rounded-lg border border-sky-200 bg-sky-50 p-4">
                <div class="flex items-center justify-between gap-3 text-sky-700">
                    <span class="text-xs font-black uppercase tracking-wide">Chi phí trả CTV</span><HandCoins class="size-5" />
                </div>
                <p class="mt-2 text-xl font-black text-sky-800">{{ money(settlement.collaborator_cost) }}</p>
                <p class="mt-1 text-xs text-sky-700">Theo giá CTV đã snapshot</p>
            </article>
            <article class="rounded-lg border border-indigo-200 bg-indigo-50 p-4">
                <div class="flex items-center justify-between gap-3 text-indigo-700">
                    <span class="text-xs font-black uppercase tracking-wide">Sau trả CTV</span><WalletCards class="size-5" />
                </div>
                <p class="mt-2 text-xl font-black text-indigo-800">{{ money(settlement.gross_profit) }}</p>
                <p class="mt-1 text-xs text-indigo-700">Doanh thu trừ chi phí CTV</p>
            </article>
            <article class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                <div class="flex items-center justify-between gap-3 text-amber-700">
                    <span class="text-xs font-black uppercase tracking-wide">Thuế dự kiến</span><ReceiptText class="size-5" />
                </div>
                <p class="mt-2 text-xl font-black text-amber-800">{{ money(settlement.estimated_tax) }}</p>
                <p class="mt-1 text-xs text-amber-700">VAT và thuế TNCN</p>
            </article>
            <article
                class="rounded-lg border p-4"
                :class="settlement.net_profit < 0 ? 'border-rose-200 bg-rose-50' : 'border-emerald-200 bg-emerald-50'"
            >
                <div class="flex items-center justify-between gap-3" :class="settlement.net_profit < 0 ? 'text-rose-700' : 'text-emerald-700'">
                    <span class="text-xs font-black uppercase tracking-wide">Lãi / lỗ ròng</span><Scale class="size-5" />
                </div>
                <p class="mt-2 text-xl font-black" :class="settlement.net_profit < 0 ? 'text-rose-800' : 'text-emerald-800'">
                    {{ money(settlement.net_profit) }}
                </p>
                <p class="mt-1 text-xs" :class="settlement.net_profit < 0 ? 'text-rose-700' : 'text-emerald-700'">
                    {{ settlement.loss_orders }} đơn lỗ<span v-if="settlement.legacy_orders">
                        · {{ settlement.legacy_orders }} đơn cũ thiếu snapshot</span
                    >
                </p>
            </article>
        </section>

        <div v-if="loading" class="grid min-h-64 place-items-center rounded-lg border border-slate-200 bg-white">
            <LoaderCircle class="size-8 animate-spin text-slate-400" />
        </div>
        <section v-else class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-[1320px] text-left text-sm" data-order-settlement-table>
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Đơn</th>
                            <th class="px-4 py-3">Game / dịch vụ</th>
                            <th class="px-4 py-3">Gói / giá</th>
                            <th class="px-4 py-3 text-right">Doanh thu</th>
                            <th class="px-4 py-3 text-right">Trả CTV</th>
                            <th class="px-4 py-3 text-right">Sau CTV</th>
                            <th class="px-4 py-3 text-right">Thuế</th>
                            <th class="px-4 py-3 text-right">Lãi / lỗ</th>
                            <th class="px-4 py-3">Trạng thái</th>
                            <th class="px-4 py-3 text-right">Chi tiết</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="order in orders" :key="order.id" class="hover:bg-slate-50">
                            <td class="px-4 py-4">
                                <strong class="font-mono text-slate-950">{{ order.code }}</strong>
                                <p class="mt-1 text-xs text-slate-500">{{ order.email || 'Không có email' }} · {{ dateTime(order.created_at) }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <strong>{{ order.game_name }}</strong>
                                <p class="text-xs text-slate-500">{{ order.service_name }} · {{ order.server_name || 'Không chọn máy chủ' }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <strong>{{ order.package_name }}</strong>
                                <p class="text-xs text-slate-500">{{ order.price_label }} · SL {{ order.quantity }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-right font-black text-emerald-700">{{ money(order.total_amount) }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-right font-bold text-sky-700">
                                {{ order.collaborator_total_cost !== null ? money(order.collaborator_total_cost) : '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-right font-bold text-indigo-700">
                                {{ order.gross_profit !== null ? money(order.gross_profit) : '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-right font-bold text-amber-700">
                                {{ order.estimated_tax !== null ? money(order.estimated_tax) : '—' }}
                            </td>
                            <td
                                class="whitespace-nowrap px-4 py-4 text-right font-black"
                                :class="order.net_profit === null ? 'text-slate-400' : order.net_profit < 0 ? 'text-rose-700' : 'text-emerald-700'"
                            >
                                {{ order.net_profit !== null ? money(order.net_profit) : '—' }}
                            </td>
                            <td class="px-4 py-4">
                                <span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="statusClasses[order.status]">{{
                                    statusLabels[order.status]
                                }}</span>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <button type="button" class="font-bold text-indigo-700" @click="openOrder(order)">
                                    <Eye class="inline size-4" /> Xem
                                </button>
                            </td>
                        </tr>
                        <tr v-if="orders.length === 0">
                            <td colspan="10" class="px-4 py-12 text-center text-slate-500">Chưa có đơn dịch vụ phù hợp.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div v-if="selectedOrder" class="fixed inset-0 z-[90] grid place-items-center bg-slate-950/55 p-3" @click.self="selectedOrder = null">
            <section class="flex max-h-[calc(100dvh-1.5rem)] w-full max-w-3xl flex-col overflow-hidden rounded-lg bg-white shadow-2xl">
                <header class="flex items-start justify-between gap-4 border-b border-slate-200 p-5">
                    <div>
                        <p class="font-mono text-sm font-bold text-indigo-700">{{ selectedOrder.code }}</p>
                        <h2 class="mt-1 text-xl font-black">{{ selectedOrder.service_name }}</h2>
                        <p class="text-sm text-slate-500">
                            {{ selectedOrder.game_name }} · {{ selectedOrder.package_name }} · {{ selectedOrder.price_label }}
                        </p>
                    </div>
                    <button type="button" class="grid size-10 place-items-center rounded-md border border-slate-200" @click="selectedOrder = null">
                        <X class="size-5" />
                    </button>
                </header>
                <div class="grid min-h-0 gap-5 overflow-y-auto p-5 lg:grid-cols-2">
                    <div class="grid content-start gap-4">
                        <dl class="grid gap-2 rounded-md border border-slate-200 bg-slate-50 p-4 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">Máy chủ</dt>
                                <dd class="font-bold">{{ selectedOrder.server_name || '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">Đơn giá</dt>
                                <dd class="font-bold">{{ money(selectedOrder.unit_price) }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">Số lượng</dt>
                                <dd class="font-bold">{{ selectedOrder.quantity }}</dd>
                            </div>
                            <div class="flex justify-between gap-3 border-t border-slate-200 pt-2">
                                <dt class="font-bold">Tổng tiền</dt>
                                <dd class="text-lg font-black text-emerald-700">{{ money(selectedOrder.total_amount) }}</dd>
                            </div>
                            <template v-if="selectedOrder.collaborator_total_cost !== null && selectedOrder.net_profit !== null">
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500">Chi phí trả CTV</dt>
                                    <dd class="font-bold text-sky-700">{{ money(selectedOrder.collaborator_total_cost) }}</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500">Sau trả CTV</dt>
                                    <dd class="font-bold text-indigo-700">{{ money(selectedOrder.gross_profit ?? 0) }}</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500">Thuế dự kiến</dt>
                                    <dd class="font-bold text-amber-700">{{ money(selectedOrder.estimated_tax ?? 0) }}</dd>
                                </div>
                                <div class="flex justify-between gap-3 border-t border-slate-200 pt-2">
                                    <dt class="font-bold">Lãi / lỗ ròng</dt>
                                    <dd class="font-black" :class="selectedOrder.net_profit < 0 ? 'text-rose-700' : 'text-emerald-700'">
                                        {{ money(selectedOrder.net_profit) }}
                                    </dd>
                                </div>
                            </template>
                            <p v-else class="border-t border-slate-200 pt-2 text-xs text-slate-500">Đơn cũ chưa có snapshot kết toán.</p>
                        </dl>
                        <div>
                            <h3 class="mb-2 text-sm font-black">Payload khách gửi</h3>
                            <dl class="grid gap-2 rounded-md border border-slate-200 p-4 text-sm">
                                <div v-for="(value, key) in selectedOrder.payload" :key="key" class="grid grid-cols-[9rem_minmax(0,1fr)] gap-3">
                                    <dt class="break-words font-mono text-xs text-slate-500">{{ key }}</dt>
                                    <dd class="break-words font-bold text-slate-900">{{ value }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                    <form class="grid content-start gap-4" @submit.prevent="saveOrder">
                        <label class="grid gap-1 text-sm font-bold"
                            >CTV nhận đơn<select v-model="editForm.collaborator_id" :class="inputClass">
                                <option :value="null">Chưa giao CTV</option>
                                <option v-for="collaborator in collaborators" :key="collaborator.id" :value="collaborator.id">
                                    {{ collaborator.name }}
                                </option>
                            </select></label
                        >
                        <label class="grid gap-1 text-sm font-bold"
                            >Trạng thái<select v-model="editForm.status" :class="inputClass">
                                <option v-for="(label, status) in statusLabels" :key="status" :value="status">{{ label }}</option>
                            </select></label
                        ><label class="grid gap-1 text-sm font-bold"
                            >Ghi chú quản trị<textarea
                                v-model="editForm.admin_note"
                                rows="6"
                                :class="inputClass"
                                placeholder="Kết quả xử lý hoặc lý do thất bại..."
                            ></textarea>
                        </label>
                        <p class="text-xs leading-5 text-slate-500">
                            Bắt đầu xử lý: {{ dateTime(selectedOrder.processing_at) }}<br />Hoàn thành: {{ dateTime(selectedOrder.completed_at) }}
                        </p>
                        <button
                            :disabled="saving"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-indigo-600 px-4 font-bold text-white disabled:opacity-50"
                        >
                            <Save class="size-4" /> {{ saving ? 'Đang lưu...' : 'Cập nhật đơn' }}
                        </button>
                    </form>
                </div>
            </section>
        </div>
    </main>
</template>
