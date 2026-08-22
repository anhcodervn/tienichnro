<script setup lang="ts">
import { adminTopupService } from '@/services/admin-topup.service';
import { onMounted, reactive, ref } from 'vue';

type OrderRow = Record<string, any>;
const orders = ref<OrderRow[]>([]);
const loading = ref(false);
const filters = reactive({ search: '', payment_status: '', order_status: '', per_page: 50 });

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
        <header><p class="text-sm font-semibold text-emerald-700">Order operations</p><h1 class="mt-1 text-2xl font-bold text-slate-950">Đơn nạp game</h1><p class="mt-2 text-sm text-slate-500">Payment status và order status được quản lý độc lập; thao tác nhạy cảm có audit log.</p></header>
        <form class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-4" @submit.prevent="load"><input v-model="filters.search" class="min-h-11 rounded-xl border border-slate-300 px-3 sm:col-span-2" placeholder="Mã đơn hoặc email"><select v-model="filters.payment_status" class="min-h-11 rounded-xl border border-slate-300 px-3"><option value="">Mọi thanh toán</option><option v-for="status in ['pending','paid','expired','cancelled','refunded']" :key="status">{{ status }}</option></select><select v-model="filters.order_status" class="min-h-11 rounded-xl border border-slate-300 px-3"><option value="">Mọi trạng thái đơn</option><option v-for="status in ['pending','processing','completed','failed','cancelled']" :key="status">{{ status }}</option></select><button class="min-h-11 rounded-xl bg-emerald-600 px-4 font-semibold text-white sm:col-start-4" type="submit">Lọc đơn</button></form>
        <div class="grid gap-4"><div v-if="loading" class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-slate-500">Đang tải...</div><article v-for="order in orders" v-else :key="order.id" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between"><div><div class="flex flex-wrap items-center gap-2"><strong class="text-lg">{{ order.code }}</strong><span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">{{ order.payment_status }}</span><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ order.order_status }}</span></div><p class="mt-2 text-sm text-slate-600">{{ order.game }} · {{ order.game_account }} · {{ order.package_name }} × {{ order.quantity }}</p><p class="mt-1 text-xs text-slate-400">{{ order.email }} · {{ new Date(order.created_at).toLocaleString('vi-VN') }}</p></div><div class="xl:text-right"><p class="text-xl font-bold">{{ Number(order.total_amount).toLocaleString('vi-VN') }}đ</p><div class="mt-3 flex flex-wrap gap-2 xl:justify-end"><button v-if="order.payment_status === 'pending'" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white" @click="act(order, 'mark_paid')">Đánh dấu đã trả</button><button v-if="order.payment_status === 'paid' && ['pending','failed'].includes(order.order_status)" class="rounded-lg bg-amber-500 px-3 py-2 text-xs font-semibold text-white" @click="act(order, 'process')">Xử lý</button><button v-if="order.order_status === 'processing'" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white" @click="act(order, 'complete')">Hoàn thành</button><button v-if="!['completed','cancelled'].includes(order.order_status)" class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700" @click="act(order, 'fail')">Thất bại</button><button v-if="!['completed','cancelled'].includes(order.order_status)" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600" @click="act(order, 'cancel')">Hủy</button></div></div></div></article><div v-if="!loading && !orders.length" class="rounded-2xl border border-dashed border-slate-300 p-10 text-center text-slate-500">Không có đơn phù hợp.</div></div>
    </section>
</template>
