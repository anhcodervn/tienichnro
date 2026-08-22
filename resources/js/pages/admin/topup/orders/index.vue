<script setup lang="ts">
import { adminTopupService } from '@/services/admin-topup.service';
import { BadgeCheck, CircleDollarSign, CircleX, Clock3, LoaderCircle, ReceiptText, TriangleAlert } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';

type OrderRow = Record<string, any>;
const orders = ref<OrderRow[]>([]);
const loading = ref(false);
const filters = reactive({ search: '', payment_status: '', order_status: '', per_page: 50 });
const summaries = computed(() => [
    {
        label: 'Đơn đang hiển thị',
        value: orders.value.length,
        icon: ReceiptText,
        class: 'bg-sky-50 text-sky-700 ring-sky-600/20',
    },
    {
        label: 'Chờ thanh toán',
        value: orders.value.filter((order) => order.payment_status === 'pending').length,
        icon: Clock3,
        class: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    },
    {
        label: 'Đang xử lý',
        value: orders.value.filter((order) => order.order_status === 'processing').length,
        icon: LoaderCircle,
        class: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
    },
    {
        label: 'Đơn lỗi',
        value: orders.value.filter((order) => order.order_status === 'failed').length,
        icon: TriangleAlert,
        class: 'bg-rose-50 text-rose-700 ring-rose-600/20',
    },
]);

const paymentStatus = (status: string) => {
    if (status === 'paid') return { label: 'Đã thanh toán', class: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', icon: BadgeCheck };
    if (status === 'pending') return { label: 'Chờ thanh toán', class: 'bg-amber-50 text-amber-700 ring-amber-600/20', icon: Clock3 };
    if (status === 'refunded') return { label: 'Đã hoàn tiền', class: 'bg-sky-50 text-sky-700 ring-sky-600/20', icon: CircleDollarSign };
    return { label: status || 'Chưa rõ', class: 'bg-slate-100 text-slate-700 ring-slate-500/20', icon: TriangleAlert };
};

const orderStatus = (status: string) => {
    if (status === 'completed') return { label: 'Hoàn thành', class: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', icon: BadgeCheck };
    if (status === 'processing') return { label: 'Đang xử lý', class: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20', icon: LoaderCircle };
    if (status === 'failed') return { label: 'Thất bại', class: 'bg-rose-50 text-rose-700 ring-rose-600/20', icon: CircleX };
    return { label: status || 'Chờ xử lý', class: 'bg-slate-100 text-slate-700 ring-slate-500/20', icon: Clock3 };
};

const load = async () => {
    loading.value = true;
    try {
        const response = await adminTopupService.orders(filters);
        orders.value = response.data.data.data;
    } finally {
        loading.value = false;
    }
};

const act = async (order: OrderRow, action: string) => {
    const needsReason = ['fail', 'cancel'].includes(action);
    const reason = needsReason ? window.prompt('Nhập lý do để lưu audit:') : undefined;
    if (needsReason && !reason) return;
    if (!window.confirm(`Xác nhận thao tác “${action}” cho đơn ${order.code}?`)) return;
    await adminTopupService.updateOrder(order.id, action, reason || undefined);
    await load();
};

onMounted(load);
</script>

<template>
    <section class="space-y-6">
        <header>
            <p class="text-sm font-semibold text-emerald-700">Order operations</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-950">Đơn nạp game</h1>
            <p class="mt-2 text-sm text-slate-500">Payment status và order status được quản lý độc lập; thao tác nhạy cảm có audit log.</p>
        </header>
        <div class="flex flex-wrap gap-2" aria-label="Tổng quan đơn hàng đang hiển thị">
            <span
                v-for="summary in summaries"
                :key="summary.label"
                class="inline-flex items-center gap-2 rounded-full px-3 py-2 text-xs font-bold ring-1 ring-inset"
                :class="summary.class"
            >
                <component :is="summary.icon" class="h-4 w-4" aria-hidden="true" />
                {{ summary.label }}
                <strong class="rounded-full bg-white/80 px-2 py-0.5 text-sm">{{ loading ? '—' : summary.value }}</strong>
            </span>
        </div>
        <form class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-4" @submit.prevent="load">
            <input
                v-model="filters.search"
                class="min-h-11 rounded-xl border border-slate-300 px-3 sm:col-span-2"
                placeholder="Mã đơn hoặc email"
            /><select v-model="filters.payment_status" class="min-h-11 rounded-xl border border-slate-300 px-3">
                <option value="">Mọi thanh toán</option>
                <option v-for="status in ['pending', 'paid', 'expired', 'cancelled', 'refunded']" :key="status">{{ status }}</option></select
            ><select v-model="filters.order_status" class="min-h-11 rounded-xl border border-slate-300 px-3">
                <option value="">Mọi trạng thái đơn</option>
                <option v-for="status in ['pending', 'processing', 'completed', 'failed', 'cancelled']" :key="status">{{ status }}</option></select
            ><button class="min-h-11 rounded-xl bg-emerald-600 px-4 font-semibold text-white sm:col-start-4" type="submit">Lọc đơn</button>
        </form>
        <div class="grid gap-4">
            <div v-if="loading" class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-slate-500">Đang tải...</div>
            <article v-for="order in orders" v-else :key="order.id" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <strong class="text-lg">{{ order.code }}</strong>
                            <span
                                class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold ring-1 ring-inset"
                                :class="paymentStatus(order.payment_status).class"
                            >
                                <component :is="paymentStatus(order.payment_status).icon" class="h-3.5 w-3.5" aria-hidden="true" />
                                {{ paymentStatus(order.payment_status).label }}
                            </span>
                            <span
                                class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold ring-1 ring-inset"
                                :class="orderStatus(order.order_status).class"
                            >
                                <component :is="orderStatus(order.order_status).icon" class="h-3.5 w-3.5" aria-hidden="true" />
                                {{ orderStatus(order.order_status).label }}
                            </span>
                        </div>
                        <p class="mt-2 text-sm text-slate-600">
                            {{ order.game }} · {{ order.game_account }} · {{ order.package_name }} × {{ order.quantity }}
                        </p>
                        <p class="mt-1 text-xs text-slate-400">{{ order.email }} · {{ new Date(order.created_at).toLocaleString('vi-VN') }}</p>
                    </div>
                    <div class="xl:text-right">
                        <p class="text-xl font-bold">{{ Number(order.total_amount).toLocaleString('vi-VN') }}đ</p>
                        <div class="mt-3 flex flex-wrap gap-2 xl:justify-end">
                            <button
                                v-if="order.payment_status === 'pending'"
                                class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white"
                                @click="act(order, 'mark_paid')"
                            >
                                Đánh dấu đã trả</button
                            ><button
                                v-if="order.payment_status === 'paid' && ['pending', 'failed'].includes(order.order_status)"
                                class="rounded-lg bg-amber-500 px-3 py-2 text-xs font-semibold text-white"
                                @click="act(order, 'process')"
                            >
                                Xử lý</button
                            ><button
                                v-if="order.order_status === 'processing'"
                                class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white"
                                @click="act(order, 'complete')"
                            >
                                Hoàn thành</button
                            ><button
                                v-if="!['completed', 'cancelled'].includes(order.order_status)"
                                class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700"
                                @click="act(order, 'fail')"
                            >
                                Thất bại</button
                            ><button
                                v-if="!['completed', 'cancelled'].includes(order.order_status)"
                                class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600"
                                @click="act(order, 'cancel')"
                            >
                                Hủy
                            </button>
                        </div>
                    </div>
                </div>
            </article>
            <div v-if="!loading && !orders.length" class="rounded-2xl border border-dashed border-slate-300 p-10 text-center text-slate-500">
                Không có đơn phù hợp.
            </div>
        </div>
    </section>
</template>
