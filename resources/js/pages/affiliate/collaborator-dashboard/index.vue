<script setup lang="ts">
import { clientAffiliateService, type CollaboratorDashboardData } from '@/services/client-affiliate.service';
import { handleErrorResponse } from '@/utils/response';
import { Banknote, CheckCircle2, Clock3, LoaderCircle, MessageSquareWarning, PlayCircle, WalletCards } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

const data = ref<CollaboratorDashboardData | null>(null);
const loading = ref(true);
const money = (value: number): string => `${new Intl.NumberFormat('vi-VN').format(value)}đ`;
const load = async (): Promise<void> => {
    loading.value = true;
    try {
        data.value = await clientAffiliateService.collaboratorDashboard();
        window.dispatchEvent(new CustomEvent('collaborator:summary', { detail: data.value }));
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};
onMounted(load);
</script>

<template>
    <main class="grid gap-5 p-4 sm:p-6">
        <section class="rounded-2xl bg-gradient-to-br from-emerald-600 via-emerald-700 to-slate-900 p-6 text-white shadow-lg sm:p-8">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-100">CTV dịch vụ game</p>
            <h1 class="mt-2 text-3xl font-black">Dashboard tổng quan</h1>
            <p class="mt-2 max-w-2xl text-emerald-50">Theo dõi đơn chờ nhận, tiến độ xử lý và thu nhập được admin kết toán.</p>
        </section>
        <div v-if="loading" class="grid min-h-72 place-items-center rounded-2xl border border-slate-200 bg-white">
            <LoaderCircle class="size-9 animate-spin text-emerald-600" />
        </div>
        <template v-else-if="data">
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                    <Clock3 class="size-6 text-amber-700" />
                    <p class="mt-3 text-sm font-bold text-amber-800">Đơn đang chờ</p>
                    <p class="text-3xl font-black text-amber-950">{{ data.orders.pending }}</p>
                </article>
                <article class="rounded-2xl border border-sky-200 bg-sky-50 p-5">
                    <PlayCircle class="size-6 text-sky-700" />
                    <p class="mt-3 text-sm font-bold text-sky-800">Đang làm</p>
                    <p class="text-3xl font-black text-sky-950">{{ data.orders.processing }}</p>
                </article>
                <article class="rounded-2xl border border-violet-200 bg-violet-50 p-5">
                    <MessageSquareWarning class="size-6 text-violet-700" />
                    <p class="mt-3 text-sm font-bold text-violet-800">Chờ admin duyệt</p>
                    <p class="text-3xl font-black text-violet-950">{{ data.orders.review }}</p>
                </article>
                <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                    <CheckCircle2 class="size-6 text-emerald-700" />
                    <p class="mt-3 text-sm font-bold text-emerald-800">Đã hoàn thành</p>
                    <p class="text-3xl font-black text-emerald-950">{{ data.orders.completed }}</p>
                </article>
            </section>
            <section class="grid gap-4 md:grid-cols-3">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <Banknote class="size-6 text-slate-700" />
                    <p class="mt-3 text-sm font-bold text-slate-500">Doanh thu đơn dự kiến</p>
                    <p class="text-2xl font-black">{{ money(data.revenue.order_revenue) }}</p>
                </article>
                <article class="rounded-2xl border border-amber-200 bg-white p-5 shadow-sm">
                    <WalletCards class="size-6 text-amber-700" />
                    <p class="mt-3 text-sm font-bold text-slate-500">Tiền đang treo</p>
                    <p class="text-2xl font-black text-amber-700">{{ money(data.revenue.held) }}</p>
                </article>
                <article class="rounded-2xl border border-emerald-200 bg-white p-5 shadow-sm">
                    <WalletCards class="size-6 text-emerald-700" />
                    <p class="mt-3 text-sm font-bold text-slate-500">Số dư có thể rút</p>
                    <p class="text-2xl font-black text-emerald-700">{{ money(data.revenue.available) }}</p>
                </article>
            </section>
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <header class="flex items-center justify-between border-b border-slate-200 p-5">
                    <div>
                        <h2 class="font-black">Đơn gần đây</h2>
                        <p class="text-xs text-slate-500">5 đơn chờ nhận hoặc đã được giao gần nhất.</p>
                    </div>
                    <RouterLink to="/dashboard/don-dich-vu" class="font-bold text-emerald-700">Xem tất cả</RouterLink>
                </header>
                <div class="divide-y divide-slate-100">
                    <div
                        v-for="order in data.recent_orders"
                        :key="order.code"
                        class="flex flex-col justify-between gap-2 p-4 sm:flex-row sm:items-center"
                    >
                        <div>
                            <p class="font-mono font-bold text-emerald-700">{{ order.code }}</p>
                            <p class="font-bold">{{ order.game_name }} · {{ order.service_name }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-black">{{ money(order.collaborator_amount ?? 0) }}</p>
                            <p class="text-xs text-slate-500">{{ order.status }}</p>
                        </div>
                    </div>
                </div>
            </section>
        </template>
    </main>
</template>
