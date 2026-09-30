<script setup lang="ts">
import { adminTopupService } from '@/services/admin-topup.service';
import { useUserStore } from '@/stores/user.store';
import { echo } from '@laravel/echo-vue';
import { BadgeCheck, CircleDollarSign, CircleX, Clock3, Gamepad2, LoaderCircle, ReceiptText, TriangleAlert } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const games = ref<any[]>([]);
const orders = ref<any[]>([]);
const loading = ref(true);
const userStore = useUserStore();
const realtimeChannelName = computed(() =>
    userStore.user?.capabilities?.platform_admin && userStore.user?.capabilities?.multi_site
        ? 'admin.platform.topup.orders'
        : `admin.sites.${userStore.user?.site?.id}.topup.orders`,
);
const realtimeEventName = '.admin.topup.order.updated';
let realtimeRefreshTimer: ReturnType<typeof setTimeout> | null = null;
const paidRevenue = computed(() =>
    orders.value
        .filter((order) => order.payment_status === 'paid' && order.order_status === 'completed')
        .reduce((sum, order) => sum + Number(order.total_amount), 0),
);
const processing = computed(() => orders.value.filter((order) => order.order_status === 'processing').length);
const needsAttention = computed(() => orders.value.filter((order) => order.payment_status === 'pending' || order.order_status === 'failed').length);
const metricCards = computed(() => [
    {
        label: 'Game hoạt động',
        value: games.value.filter((item) => item.status === 'active').length,
        icon: Gamepad2,
        iconClass: 'bg-indigo-50 text-indigo-600',
        valueClass: 'text-slate-950',
    },
    {
        label: 'Đơn gần nhất',
        value: orders.value.length,
        icon: ReceiptText,
        iconClass: 'bg-sky-50 text-sky-600',
        valueClass: 'text-slate-950',
    },
    {
        label: 'Đang xử lý',
        value: processing.value,
        icon: LoaderCircle,
        iconClass: 'bg-amber-50 text-amber-600',
        valueClass: processing.value > 0 ? 'text-amber-600' : 'text-slate-950',
    },
    {
        label: 'Doanh thu hoàn thành gần đây',
        value: `${paidRevenue.value.toLocaleString('vi-VN')}đ`,
        icon: CircleDollarSign,
        iconClass: 'bg-emerald-50 text-emerald-600',
        valueClass: 'text-emerald-600',
    },
]);

const paymentStatus = (status: string) => {
    if (status === 'paid') return { label: 'Đã thanh toán', class: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', icon: BadgeCheck };
    if (status === 'pending') return { label: 'Chờ thanh toán', class: 'bg-amber-50 text-amber-700 ring-amber-600/20', icon: Clock3 };
    return { label: status || 'Chưa rõ', class: 'bg-slate-100 text-slate-700 ring-slate-500/20', icon: TriangleAlert };
};

const orderStatus = (status: string) => {
    if (status === 'completed') return { label: 'Hoàn thành', class: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', icon: BadgeCheck };
    if (status === 'processing') return { label: 'Đang xử lý', class: 'bg-sky-50 text-sky-700 ring-sky-600/20', icon: LoaderCircle };
    if (status === 'failed') return { label: 'Thất bại', class: 'bg-rose-50 text-rose-700 ring-rose-600/20', icon: CircleX };
    return { label: status || 'Chờ xử lý', class: 'bg-slate-100 text-slate-700 ring-slate-500/20', icon: Clock3 };
};

const loadOrders = async (): Promise<void> => {
    const response = await adminTopupService.orders({ per_page: 100 });
    orders.value = response.data.data.data;
};

const handleRealtimeOrderUpdated = (event: { code: string; payment_status: string; order_status: string }): void => {
    orders.value = orders.value.map((order) =>
        order.code === event.code ? { ...order, payment_status: event.payment_status, order_status: event.order_status } : order,
    );

    if (realtimeRefreshTimer) clearTimeout(realtimeRefreshTimer);
    realtimeRefreshTimer = setTimeout(() => void loadOrders().catch(() => undefined), 180);
};

onMounted(async () => {
    const realtimeChannel = echo().private(realtimeChannelName.value);
    realtimeChannel.listen(realtimeEventName, handleRealtimeOrderUpdated);
    realtimeChannel.subscribed(() => {
        if (!loading.value) void loadOrders().catch(() => undefined);
    });

    try {
        if (userStore.user?.capabilities?.platform_admin) {
            const [gameResponse] = await Promise.all([adminTopupService.games({ per_page: 100 }), loadOrders()]);
            games.value = gameResponse.data.data.data;
        } else {
            await loadOrders();
        }
    } finally {
        loading.value = false;
    }
});

onBeforeUnmount(() => {
    echo().private(realtimeChannelName.value).stopListening(realtimeEventName, handleRealtimeOrderUpdated);
    echo().leave(realtimeChannelName.value);
    if (realtimeRefreshTimer) clearTimeout(realtimeRefreshTimer);
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
                <RouterLink class="rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700" to="/admin/reports/topup"
                    >Xem báo cáo doanh thu</RouterLink
                >
                <span
                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-2 text-xs font-bold ring-1 ring-inset"
                    :class="needsAttention > 0 ? 'bg-rose-50 text-rose-700 ring-rose-600/20' : 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'"
                >
                    <TriangleAlert v-if="needsAttention > 0" class="h-4 w-4" />
                    <BadgeCheck v-else class="h-4 w-4" />
                    {{ loading ? 'Đang kiểm tra' : needsAttention > 0 ? `${needsAttention} mục cần theo dõi` : 'Vận hành ổn định' }}
                </span>
            </div>
        </header>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="metric in metricCards"
                :key="metric.label"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-slate-500">{{ metric.label }}</p>
                        <p class="mt-3 text-2xl font-black tracking-tight" :class="metric.valueClass">{{ loading ? '—' : metric.value }}</p>
                    </div>
                    <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl" :class="metric.iconClass">
                        <component :is="metric.icon" class="h-5 w-5" aria-hidden="true" />
                    </span>
                </div>
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
                        <div class="mt-2 flex flex-wrap gap-1.5 sm:justify-end">
                            <span
                                class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-bold ring-1 ring-inset"
                                :class="paymentStatus(order.payment_status).class"
                            >
                                <component :is="paymentStatus(order.payment_status).icon" class="h-3.5 w-3.5" aria-hidden="true" />
                                {{ paymentStatus(order.payment_status).label }}
                            </span>
                            <span
                                class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-bold ring-1 ring-inset"
                                :class="orderStatus(order.order_status).class"
                            >
                                <component :is="orderStatus(order.order_status).icon" class="h-3.5 w-3.5" aria-hidden="true" />
                                {{ orderStatus(order.order_status).label }}
                            </span>
                        </div>
                    </div>
                </div>
                <div v-if="!loading && !orders.length" class="p-8 text-center text-slate-500">Chưa có đơn hàng.</div>
            </div>
        </div>
    </section>
</template>
