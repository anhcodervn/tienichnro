<script setup lang="ts">
import { adminTopupService } from '@/services/admin-topup.service';
import { computed, onMounted, ref } from 'vue';

const games = ref<any[]>([]);
const orders = ref<any[]>([]);
const loading = ref(true);
const paidRevenue = computed(() =>
    orders.value.filter((order) => order.payment_status === 'paid').reduce((sum, order) => sum + Number(order.total_amount), 0),
);
const processing = computed(() => orders.value.filter((order) => order.order_status === 'processing').length);

onMounted(async () => {
    try {
        const [gameResponse, orderResponse] = await Promise.all([
            adminTopupService.games({ per_page: 100 }),
            adminTopupService.orders({ per_page: 100 }),
        ]);
        games.value = gameResponse.data.data.data;
        orders.value = orderResponse.data.data.data;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <section class="space-y-7">
        <header class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <p class="text-sm font-semibold text-emerald-700">Trung tâm vận hành</p>
            <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-950">Topup Carot Teamobi</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-500">
                Quản lý catalog, theo dõi thanh toán và xử lý đơn topup trong một luồng có audit.
            </p>
            <div class="mt-6 flex flex-wrap gap-3">
                <RouterLink class="rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white" to="/admin/topup/orders"
                    >Xem đơn hàng</RouterLink
                ><RouterLink class="rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700" to="/admin/topup/games"
                    >Quản lý catalog</RouterLink
                >
            </div>
        </header>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="metric in [
                    { label: 'Game hoạt động', value: games.filter((item) => item.status === 'active').length },
                    { label: 'Đơn gần nhất', value: orders.length },
                    { label: 'Đang xử lý', value: processing },
                    { label: 'Doanh thu đã ghi nhận', value: paidRevenue.toLocaleString('vi-VN') + 'đ' },
                ]"
                :key="metric.label"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <p class="text-sm text-slate-500">{{ metric.label }}</p>
                <p class="mt-3 text-2xl font-bold text-slate-950">{{ loading ? '—' : metric.value }}</p>
            </article>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 p-5"><h2 class="font-bold">Đơn mới nhất</h2></div>
            <div class="divide-y divide-slate-100">
                <div
                    v-for="order in orders.slice(0, 8)"
                    :key="order.id"
                    class="flex flex-col gap-2 p-5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <strong>{{ order.code }}</strong>
                        <p class="mt-1 text-sm text-slate-500">{{ order.game }} · {{ order.package_name }}</p>
                    </div>
                    <div class="sm:text-right">
                        <strong>{{ Number(order.total_amount).toLocaleString('vi-VN') }}đ</strong>
                        <p class="mt-1 text-xs text-slate-400">{{ order.payment_status }} · {{ order.order_status }}</p>
                    </div>
                </div>
                <div v-if="!loading && !orders.length" class="p-8 text-center text-slate-500">Chưa có đơn hàng.</div>
            </div>
        </div>
    </section>
</template>
