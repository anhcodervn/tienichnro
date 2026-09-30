<script setup lang="ts">
import { clientAffiliateService, type CollaboratorDashboardData } from '@/services/client-affiliate.service';
import { handleErrorResponse } from '@/utils/response';
import { Banknote, CircleDollarSign, Clock3, LoaderCircle, WalletCards } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

const data = ref<CollaboratorDashboardData | null>(null);
const loading = ref(true);
const money = (value: number): string => `${new Intl.NumberFormat('vi-VN').format(value)}đ`;
const dateTime = (value: string): string => new Date(value).toLocaleString('vi-VN');

const load = async (): Promise<void> => {
    loading.value = true;
    try {
        data.value = await clientAffiliateService.collaboratorDashboard();
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
        <div v-if="loading" class="grid min-h-72 place-items-center rounded-2xl border border-slate-200 bg-white">
            <LoaderCircle class="size-9 animate-spin text-emerald-600" />
        </div>
        <template v-else-if="data">
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-sky-200 bg-sky-50 p-5">
                    <CircleDollarSign class="size-6 text-sky-700" />
                    <p class="mt-3 text-sm font-bold text-sky-800">Doanh thu đơn</p>
                    <p class="text-3xl font-black text-sky-950">{{ money(data.revenue.order_revenue) }}</p>
                    <p class="mt-1 text-xs text-sky-700">Tổng tiền công của các đơn được giao</p>
                </article>
                <article class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                    <Clock3 class="size-6 text-amber-700" />
                    <p class="mt-3 text-sm font-bold text-amber-800">Tiền treo</p>
                    <p class="text-3xl font-black text-amber-950">{{ money(data.revenue.held) }}</p>
                    <p class="mt-1 text-xs text-amber-700">Đã giữ khi nhận đơn; mở khóa sau 3 ngày kể từ lúc hoàn thành</p>
                </article>
                <article class="rounded-2xl border border-violet-200 bg-violet-50 p-5">
                    <Banknote class="size-6 text-violet-700" />
                    <p class="mt-3 text-sm font-bold text-violet-800">Đã kết toán</p>
                    <p class="text-3xl font-black text-violet-950">{{ money(data.revenue.settled) }}</p>
                    <p class="mt-1 text-xs text-violet-700">Tiền công từ các đơn đã được admin duyệt</p>
                </article>
                <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                    <WalletCards class="size-6 text-emerald-700" />
                    <p class="mt-3 text-sm font-bold text-emerald-800">Có thể rút</p>
                    <p class="text-3xl font-black text-emerald-950">{{ money(data.revenue.available) }}</p>
                    <p class="mt-1 text-xs text-emerald-700">Đang giữ cho lệnh rút: {{ money(data.revenue.withdrawal_hold) }}</p>
                </article>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <header class="flex flex-col justify-between gap-3 border-b border-slate-200 p-5 sm:flex-row sm:items-center">
                    <div>
                        <h2 class="font-black">Thu nhập theo đơn gần đây</h2>
                        <p class="text-sm text-slate-500">Đơn hoàn thành được mở khóa sau 3 ngày nếu không bị hoàn tiền.</p>
                    </div>
                    <RouterLink
                        to="/dashboard/rut-tien"
                        class="inline-flex min-h-10 items-center justify-center rounded-xl bg-emerald-600 px-4 font-bold text-white"
                        >Rút tiền</RouterLink
                    >
                </header>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead class="bg-slate-50 text-left text-slate-500">
                            <tr>
                                <th class="px-5 py-3">Mã đơn</th>
                                <th class="px-5 py-3">Dịch vụ</th>
                                <th class="px-5 py-3">Thời gian</th>
                                <th class="px-5 py-3">Tiền công</th>
                                <th class="px-5 py-3">Kết toán</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="order in data.recent_orders" :key="order.code">
                                <td class="px-5 py-4 font-mono font-bold text-emerald-700">{{ order.code }}</td>
                                <td class="px-5 py-4">
                                    <p class="font-bold">{{ order.game_name }}</p>
                                    <p class="text-slate-500">{{ order.service_name }} · {{ order.package_name }}</p>
                                </td>
                                <td class="px-5 py-4 text-slate-500">{{ dateTime(order.created_at) }}</td>
                                <td class="px-5 py-4 font-black">{{ money(order.collaborator_amount ?? 0) }}</td>
                                <td class="px-5 py-4">
                                    <span
                                        class="rounded-full px-3 py-1 text-xs font-bold"
                                        :class="order.settled_at ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                        >{{ order.settled_at ? 'Đã kết toán' : 'Đang treo' }}</span
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="data.recent_orders.length === 0" class="p-10 text-center text-slate-500">Chưa có dữ liệu doanh thu.</p>
                </div>
            </section>
        </template>
    </main>
</template>
