<script setup lang="ts">
import { adminReportingService, type AdminGameServiceReport, type GameServiceReportBreakdown } from '@/services/admin-reporting.service';
import { handleErrorResponse } from '@/utils/response';
import { BadgeCheck, Clock3, HandCoins, LoaderCircle, RefreshCcw, RotateCcw, WalletCards } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const report = ref<AdminGameServiceReport | null>(null);
const loading = ref(true);
const breakdownType = ref<'games' | 'services'>('games');
const toDateInput = (date: Date): string => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};
const today = new Date();
const start = new Date(today);
start.setDate(start.getDate() - 29);
const filters = reactive({
    from: typeof route.query.from === 'string' ? route.query.from : toDateInput(start),
    to: typeof route.query.to === 'string' ? route.query.to : toDateInput(today),
});
const money = (value: number): string =>
    new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(value);
const number = (value: number): string => new Intl.NumberFormat('vi-VN').format(value);
const dateTime = (value: string | null): string => (value ? new Date(value).toLocaleString('vi-VN') : '—');
const breakdownRows = computed<GameServiceReportBreakdown[]>(() => report.value?.breakdowns[breakdownType.value] ?? []);

const loadReport = async (): Promise<void> => {
    if (!filters.from || !filters.to || filters.from > filters.to) return;
    loading.value = true;
    try {
        report.value = (await adminReportingService.gameServices(filters)).data.data;
        await router.replace({ query: { from: filters.from, to: filters.to } });
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

onMounted(loadReport);
</script>

<template>
    <main class="grid gap-6">
        <section class="overflow-hidden rounded-3xl bg-slate-950 p-6 text-white shadow-sm sm:p-8">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-2xl">
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-300">Báo cáo doanh thu</p>
                    <h1 class="mt-3 text-3xl font-black tracking-tight">Đối soát dịch vụ game</h1>
                    <p class="mt-3 text-sm leading-6 text-slate-300">
                        Tách riêng tiền CTV đang làm, tiền chờ đủ 3 ngày, tiền đã kết toán và lợi nhuận của đơn được duyệt hoàn thành.
                    </p>
                </div>
                <div class="grid gap-3 rounded-2xl bg-white/10 p-4 ring-1 ring-white/10 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                    <label class="grid gap-1.5 text-xs font-bold text-slate-300">
                        Từ ngày<input v-model="filters.from" type="date" :max="filters.to" class="rounded-xl bg-white text-slate-950" />
                    </label>
                    <label class="grid gap-1.5 text-xs font-bold text-slate-300">
                        Đến ngày<input v-model="filters.to" type="date" :min="filters.from" class="rounded-xl bg-white text-slate-950" />
                    </label>
                    <button
                        class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-emerald-500 px-4 text-sm font-black text-slate-950 disabled:opacity-50"
                        :disabled="loading || !filters.from || !filters.to || filters.from > filters.to"
                        @click="loadReport"
                    >
                        <LoaderCircle v-if="loading" class="size-4 animate-spin" /><RefreshCcw v-else class="size-4" /> Cập nhật
                    </button>
                </div>
            </div>
        </section>

        <div v-if="loading && !report" class="grid min-h-72 place-items-center rounded-2xl border border-slate-200 bg-white">
            <LoaderCircle class="size-9 animate-spin text-emerald-600" />
        </div>

        <template v-else-if="report">
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-bold text-slate-500">Tổng đơn tạo trong kỳ</p>
                    <p class="mt-2 text-3xl font-black text-slate-950">{{ number(report.orders.total_orders) }}</p>
                </article>
                <article class="rounded-2xl border border-violet-200 bg-violet-50 p-5">
                    <p class="text-sm font-bold text-violet-700">CTV đã báo hoàn thành</p>
                    <p class="mt-2 text-3xl font-black text-violet-950">{{ number(report.orders.reported_completion_orders) }}</p>
                    <p class="mt-1 text-xs text-violet-700">Đang chờ duyệt + đã được duyệt</p>
                </article>
                <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                    <p class="text-sm font-bold text-emerald-700">Đơn đã duyệt hoàn thành</p>
                    <p class="mt-2 text-3xl font-black text-emerald-950">{{ number(report.orders.completed_orders) }}</p>
                    <p class="mt-1 text-xs text-emerald-700">Tỷ lệ {{ report.orders.completion_rate }}%</p>
                </article>
                <article class="rounded-2xl border border-rose-200 bg-rose-50 p-5">
                    <p class="text-sm font-bold text-rose-700">Thất bại / đã hủy</p>
                    <p class="mt-2 text-3xl font-black text-rose-950">
                        {{ number(report.orders.failed_orders + report.orders.cancelled_orders) }}
                    </p>
                </article>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-sky-200 bg-sky-50 p-5">
                    <HandCoins class="size-6 text-sky-700" />
                    <p class="mt-3 text-sm font-bold text-sky-800">CTV đang bị giữ khi làm đơn</p>
                    <p class="mt-2 text-2xl font-black text-sky-950">{{ money(report.funds.working_hold) }}</p>
                    <p class="mt-1 text-xs text-sky-700">Đơn đang làm và chờ duyệt</p>
                </article>
                <article class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                    <Clock3 class="size-6 text-amber-700" />
                    <p class="mt-3 text-sm font-bold text-amber-800">Hoàn thành, chưa kết toán</p>
                    <p class="mt-2 text-2xl font-black text-amber-950">{{ money(report.funds.pending_settlement) }}</p>
                    <p class="mt-1 text-xs text-amber-700">Đang trong thời gian chờ 3 ngày</p>
                </article>
                <article class="rounded-2xl border border-violet-200 bg-violet-50 p-5">
                    <WalletCards class="size-6 text-violet-700" />
                    <p class="mt-3 text-sm font-bold text-violet-800">Tổng tiền chưa kết toán</p>
                    <p class="mt-2 text-2xl font-black text-violet-950">{{ money(report.funds.total_unsettled) }}</p>
                    <p class="mt-1 text-xs text-violet-700">Đang làm + hoàn thành chờ 3 ngày</p>
                </article>
                <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                    <WalletCards class="size-6 text-emerald-700" />
                    <p class="mt-3 text-sm font-bold text-emerald-800">Đã kết toán cho CTV</p>
                    <p class="mt-2 text-2xl font-black text-emerald-950">{{ money(report.funds.settled) }}</p>
                </article>
                <article class="rounded-2xl border border-rose-200 bg-rose-50 p-5">
                    <RotateCcw class="size-6 text-rose-700" />
                    <p class="mt-3 text-sm font-bold text-rose-800">Đã thu hồi do hoàn tiền</p>
                    <p class="mt-2 text-2xl font-black text-rose-950">{{ money(report.funds.reversed) }}</p>
                </article>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <article class="rounded-2xl border border-slate-200 bg-white p-5">
                    <p class="text-sm text-slate-500">Giá trị đơn hoàn thành</p>
                    <p class="mt-2 text-2xl font-black">{{ money(report.financials.revenue) }}</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5">
                    <p class="text-sm text-slate-500">Chi phí trả CTV</p>
                    <p class="mt-2 text-2xl font-black text-amber-700">{{ money(report.financials.collaborator_cost) }}</p>
                </article>
                <article class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5">
                    <p class="text-sm text-indigo-700">Sau khi trả CTV</p>
                    <p class="mt-2 text-2xl font-black text-indigo-950">{{ money(report.financials.after_collaborator) }}</p>
                </article>
                <article class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                    <p class="text-sm text-amber-700">Thuế dự kiến</p>
                    <p class="mt-2 text-2xl font-black text-amber-950">{{ money(report.financials.estimated_tax) }}</p>
                </article>
                <article
                    class="rounded-2xl border p-5"
                    :class="report.financials.net_profit < 0 ? 'border-rose-200 bg-rose-50' : 'border-emerald-200 bg-emerald-50'"
                >
                    <p class="text-sm text-slate-600">Lãi ròng dự kiến</p>
                    <p class="mt-2 text-2xl font-black" :class="report.financials.net_profit < 0 ? 'text-rose-700' : 'text-emerald-700'">
                        {{ money(report.financials.net_profit) }}
                    </p>
                </article>
            </section>

            <section class="grid gap-5 xl:grid-cols-[minmax(0,1.4fr)_minmax(320px,1fr)]">
                <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-5">
                        <div>
                            <h2 class="font-black">Đối soát theo danh mục</h2>
                            <p class="text-sm text-slate-500">Chỉ gồm đơn đã duyệt hoàn thành</p>
                        </div>
                        <div class="flex gap-2">
                            <button
                                class="rounded-lg px-3 py-2 text-sm font-bold"
                                :class="breakdownType === 'games' ? 'bg-slate-950 text-white' : 'bg-slate-100'"
                                @click="breakdownType = 'games'"
                            >
                                Theo game</button
                            ><button
                                class="rounded-lg px-3 py-2 text-sm font-bold"
                                :class="breakdownType === 'services' ? 'bg-slate-950 text-white' : 'bg-slate-100'"
                                @click="breakdownType = 'services'"
                            >
                                Theo dịch vụ
                            </button>
                        </div>
                    </header>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[820px] text-sm">
                            <thead class="bg-slate-50 text-left text-slate-500">
                                <tr>
                                    <th class="px-5 py-3">Tên</th>
                                    <th class="px-5 py-3 text-right">Đơn</th>
                                    <th class="px-5 py-3 text-right">Giá trị</th>
                                    <th class="px-5 py-3 text-right">Trả CTV</th>
                                    <th class="px-5 py-3 text-right">Sau CTV</th>
                                    <th class="px-5 py-3 text-right">Thuế</th>
                                    <th class="px-5 py-3 text-right">Lãi ròng</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="row in breakdownRows" :key="row.name">
                                    <td class="px-5 py-4 font-bold">{{ row.name }}</td>
                                    <td class="px-5 py-4 text-right">{{ number(row.completed_orders) }}</td>
                                    <td class="px-5 py-4 text-right font-bold">{{ money(row.revenue) }}</td>
                                    <td class="px-5 py-4 text-right text-amber-700">{{ money(row.collaborator_cost) }}</td>
                                    <td class="px-5 py-4 text-right text-indigo-700">{{ money(row.after_collaborator) }}</td>
                                    <td class="px-5 py-4 text-right">{{ money(row.estimated_tax) }}</td>
                                    <td class="px-5 py-4 text-right font-black" :class="row.net_profit < 0 ? 'text-rose-700' : 'text-emerald-700'">
                                        {{ money(row.net_profit) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-if="breakdownRows.length === 0" class="p-10 text-center text-slate-500">Chưa có đơn hoàn thành trong kỳ.</p>
                    </div>
                </article>

                <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <header class="border-b border-slate-200 p-5"><h2 class="font-black">Đơn hoàn thành gần nhất</h2></header>
                    <div class="divide-y divide-slate-100">
                        <div v-for="order in report.recent_completed_orders" :key="order.code" class="p-5">
                            <div class="flex justify-between gap-3">
                                <RouterLink
                                    :to="{ name: 'admin.game-services.orders', query: { search: order.code } }"
                                    class="font-mono font-black text-emerald-700"
                                    >{{ order.code }}</RouterLink
                                ><strong>{{ money(order.revenue) }}</strong>
                            </div>
                            <p class="mt-1 text-sm text-slate-600">{{ order.game }} · {{ order.service }}</p>
                            <div class="mt-3 flex justify-between gap-3 text-xs text-slate-500">
                                <span>{{ order.settled_at ? 'Đã kết toán CTV' : 'Chưa kết toán CTV' }}</span
                                ><span>{{ dateTime(order.completed_at) }}</span>
                            </div>
                        </div>
                        <p v-if="report.recent_completed_orders.length === 0" class="p-10 text-center text-slate-500">Chưa có dữ liệu.</p>
                    </div>
                </article>
            </section>

            <section class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                <BadgeCheck class="mt-0.5 size-5 shrink-0" />
                <div>
                    <strong>Nguyên tắc đối soát</strong>
                    <p class="mt-1 leading-6 text-emerald-800">{{ report.criteria }}</p>
                </div>
            </section>
        </template>
    </main>
</template>
