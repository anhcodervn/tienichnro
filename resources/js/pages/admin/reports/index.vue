<script setup lang="ts">
import { adminReportingService, type AdminTopupReport, type ReportBreakdown } from '@/services/admin-reporting.service';
import { handleErrorResponse } from '@/utils/response';
import {
    ArrowDownRight,
    ArrowUpRight,
    BadgeCheck,
    ChartNoAxesCombined,
    CircleDollarSign,
    Clock3,
    Gamepad2,
    LoaderCircle,
    PackageCheck,
    ReceiptText,
    RefreshCcw,
    ServerCog,
    ShieldCheck,
    TriangleAlert,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

type BreakdownKey = 'games' | 'providers' | 'packages';

const route = useRoute();
const router = useRouter();
const loading = ref(true);
const report = ref<AdminTopupReport | null>(null);
const activeBreakdown = ref<BreakdownKey>('games');

const toDateInput = (date: Date): string => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const today = new Date();
const defaultFrom = new Date(today);
defaultFrom.setDate(defaultFrom.getDate() - 29);

const filters = reactive({
    from: typeof route.query.from === 'string' ? route.query.from : toDateInput(defaultFrom),
    to: typeof route.query.to === 'string' ? route.query.to : toDateInput(today),
});

const money = (value: number): string =>
    new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(value);
const number = (value: number): string => new Intl.NumberFormat('vi-VN').format(value);
const shortDate = (value: string): string =>
    new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit' }).format(new Date(`${value}T00:00:00`));
const dateTime = (value: string | null): string =>
    value
        ? new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(
              new Date(value),
          )
        : '--';

const maxTrendRevenue = computed(() => Math.max(...(report.value?.trend.map((item) => item.revenue) ?? [0]), 1));
const breakdownRows = computed<ReportBreakdown[]>(() => report.value?.breakdowns[activeBreakdown.value] ?? []);
const breakdownTabs: Array<{ key: BreakdownKey; label: string; icon: typeof Gamepad2 }> = [
    { key: 'games', label: 'Theo game', icon: Gamepad2 },
    { key: 'providers', label: 'Theo provider', icon: ServerCog },
    { key: 'packages', label: 'Theo gói', icon: PackageCheck },
];

const metricCards = computed(() => {
    if (!report.value) return [];

    return [
        {
            label: 'Doanh thu thành công',
            value: money(report.value.summary.revenue),
            growth: report.value.growth.revenue,
            icon: CircleDollarSign,
            tone: 'emerald',
        },
        {
            label: 'Đơn thành công',
            value: number(report.value.summary.successful_orders),
            growth: report.value.growth.successful_orders,
            icon: ReceiptText,
            tone: 'sky',
        },
        {
            label: 'Lượt nạp thành công',
            value: number(report.value.summary.successful_units),
            growth: report.value.growth.successful_units,
            icon: BadgeCheck,
            tone: 'indigo',
        },
        {
            label: 'Giá trị đơn trung bình',
            value: money(report.value.summary.average_order_value),
            growth: report.value.growth.average_order_value,
            icon: ChartNoAxesCombined,
            tone: 'violet',
        },
    ];
});

const toneClasses: Record<string, string> = {
    emerald: 'bg-emerald-50 text-emerald-700',
    sky: 'bg-sky-50 text-sky-700',
    indigo: 'bg-indigo-50 text-indigo-700',
    violet: 'bg-violet-50 text-violet-700',
};

const growthLabel = (percentage: number | null): string => {
    if (percentage === null) return 'Kỳ trước chưa có dữ liệu';
    if (percentage === 0) return 'Không đổi so với kỳ trước';
    return `${percentage > 0 ? '+' : ''}${percentage.toLocaleString('vi-VN')}% so với kỳ trước`;
};

async function loadReport(): Promise<void> {
    if (!filters.from || !filters.to || filters.from > filters.to) return;
    loading.value = true;

    try {
        const response = await adminReportingService.topup({ from: filters.from, to: filters.to });
        report.value = response.data.data;
        await router.replace({ query: { from: filters.from, to: filters.to } });
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
}

async function applyPreset(days: number): Promise<void> {
    const end = new Date();
    const start = new Date(end);
    start.setDate(start.getDate() - (days - 1));
    filters.from = toDateInput(start);
    filters.to = toDateInput(end);
    await loadReport();
}

onMounted(loadReport);
</script>

<template>
    <main class="space-y-6">
        <section class="overflow-hidden rounded-3xl bg-slate-950 p-6 text-white shadow-sm sm:p-8">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-2xl">
                    <div
                        class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-bold text-emerald-300 ring-1 ring-white/10"
                    >
                        <ChartNoAxesCombined class="h-4 w-4" /> Trung tâm báo cáo
                    </div>
                    <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">Tăng trưởng và doanh thu topup</h1>
                    <p class="mt-3 text-sm leading-6 text-slate-300">
                        Doanh thu chỉ ghi nhận khi đơn đã thanh toán và hoàn thành thành công. Các đơn chờ, lỗi hoặc hoàn tiền không được cộng vào
                        KPI.
                    </p>
                </div>
                <div class="rounded-2xl bg-white/10 p-4 ring-1 ring-white/10 backdrop-blur">
                    <div class="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                        <label class="grid gap-1.5 text-xs font-semibold text-slate-300">
                            Từ ngày
                            <input
                                v-model="filters.from"
                                type="date"
                                :max="filters.to"
                                class="rounded-xl border-white/10 bg-white px-3 py-2.5 text-sm text-slate-900"
                            />
                        </label>
                        <label class="grid gap-1.5 text-xs font-semibold text-slate-300">
                            Đến ngày
                            <input
                                v-model="filters.to"
                                type="date"
                                :min="filters.from"
                                class="rounded-xl border-white/10 bg-white px-3 py-2.5 text-sm text-slate-900"
                            />
                        </label>
                        <button
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-500 px-4 py-2.5 text-sm font-bold text-slate-950 transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="loading || !filters.from || !filters.to || filters.from > filters.to"
                            @click="loadReport"
                        >
                            <LoaderCircle v-if="loading" class="h-4 w-4 animate-spin" />
                            <RefreshCcw v-else class="h-4 w-4" /> Cập nhật
                        </button>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-for="preset in [7, 30, 90]"
                            :key="preset"
                            class="rounded-lg bg-white/10 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-white/20"
                            @click="applyPreset(preset)"
                        >
                            {{ preset }} ngày
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <div v-if="loading && !report" class="grid min-h-72 place-items-center rounded-3xl border border-slate-200 bg-white">
            <div class="text-center text-slate-500">
                <LoaderCircle class="mx-auto h-8 w-8 animate-spin text-emerald-600" />
                <p class="mt-3 text-sm font-semibold">Đang tổng hợp dữ liệu...</p>
            </div>
        </div>

        <template v-else-if="report">
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article v-for="metric in metricCards" :key="metric.label" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-slate-500">{{ metric.label }}</p>
                            <p class="mt-3 text-2xl font-black tracking-tight text-slate-950">{{ metric.value }}</p>
                        </div>
                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl" :class="toneClasses[metric.tone]"
                            ><component :is="metric.icon" class="h-5 w-5"
                        /></span>
                    </div>
                    <div
                        class="mt-4 flex items-center gap-1.5 text-xs font-bold"
                        :class="
                            metric.growth.percentage_change === null || metric.growth.percentage_change === 0
                                ? 'text-slate-500'
                                : metric.growth.percentage_change > 0
                                  ? 'text-emerald-700'
                                  : 'text-rose-700'
                        "
                    >
                        <ArrowUpRight v-if="metric.growth.percentage_change !== null && metric.growth.percentage_change > 0" class="h-4 w-4" />
                        <ArrowDownRight v-else-if="metric.growth.percentage_change !== null && metric.growth.percentage_change < 0" class="h-4 w-4" />
                        {{ growthLabel(metric.growth.percentage_change) }}
                    </div>
                </article>
            </section>

            <section class="grid gap-5 xl:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-black text-slate-950">Doanh thu theo ngày</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ report.period.from }} — {{ report.period.to }}</p>
                        </div>
                        <span class="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700">{{ report.period.days }} ngày</span>
                    </div>
                    <div class="mt-8 overflow-x-auto pb-2">
                        <div
                            class="flex h-64 items-end gap-2 border-b border-slate-200"
                            :style="{ minWidth: `${Math.max(report.trend.length * 34, 620)}px` }"
                        >
                            <div
                                v-for="item in report.trend"
                                :key="item.date"
                                class="group flex h-full min-w-6 flex-1 flex-col items-center justify-end gap-2"
                                :title="`${shortDate(item.date)}: ${money(item.revenue)} · ${item.successful_units} lượt`"
                            >
                                <div class="relative flex w-full flex-1 items-end justify-center">
                                    <span
                                        class="absolute bottom-full z-10 mb-2 hidden whitespace-nowrap rounded-lg bg-slate-950 px-2 py-1 text-[11px] font-semibold text-white shadow-lg group-hover:block"
                                        >{{ money(item.revenue) }}</span
                                    >
                                    <div
                                        class="w-full max-w-6 rounded-t-md bg-gradient-to-t from-emerald-600 to-emerald-400 transition group-hover:from-emerald-500 group-hover:to-emerald-300"
                                        :style="{ height: `${Math.max((item.revenue / maxTrendRevenue) * 100, item.revenue > 0 ? 4 : 0)}%` }"
                                    ></div>
                                </div>
                                <span class="text-[10px] font-semibold text-slate-400">{{ shortDate(item.date) }}</span>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-black text-slate-950">Tình trạng đơn tạo trong kỳ</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ number(report.status_overview.created_orders) }} đơn được tạo</p>
                        </div>
                        <ShieldCheck class="h-7 w-7 text-emerald-600" />
                    </div>
                    <div class="mt-6 grid gap-3">
                        <div class="flex items-center justify-between rounded-xl bg-emerald-50 px-4 py-3 text-sm">
                            <span class="flex items-center gap-2 font-semibold text-emerald-800"><BadgeCheck class="h-4 w-4" /> Hoàn thành</span
                            ><strong class="text-emerald-900">{{ number(report.status_overview.completed_orders) }}</strong>
                        </div>
                        <div class="flex items-center justify-between rounded-xl bg-sky-50 px-4 py-3 text-sm">
                            <span class="flex items-center gap-2 font-semibold text-sky-800"><LoaderCircle class="h-4 w-4" /> Đang xử lý</span
                            ><strong class="text-sky-900">{{ number(report.status_overview.processing_orders) }}</strong>
                        </div>
                        <div class="flex items-center justify-between rounded-xl bg-amber-50 px-4 py-3 text-sm">
                            <span class="flex items-center gap-2 font-semibold text-amber-800"><Clock3 class="h-4 w-4" /> Chờ xử lý</span
                            ><strong class="text-amber-900">{{ number(report.status_overview.pending_orders) }}</strong>
                        </div>
                        <div class="flex items-center justify-between rounded-xl bg-rose-50 px-4 py-3 text-sm">
                            <span class="flex items-center gap-2 font-semibold text-rose-800"><TriangleAlert class="h-4 w-4" /> Thất bại</span
                            ><strong class="text-rose-900">{{ number(report.status_overview.failed_orders) }}</strong>
                        </div>
                    </div>
                    <div class="mt-5 rounded-xl border border-slate-200 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tỷ lệ hoàn thành</p>
                        <div class="mt-2 flex items-end justify-between gap-3">
                            <strong class="text-3xl font-black text-slate-950">{{ report.summary.completion_rate }}%</strong
                            ><span class="text-xs text-slate-500">theo ngày tạo đơn</span>
                        </div>
                    </div>
                </article>
            </section>

            <section class="grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]">
                <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 p-5 sm:p-6">
                        <h2 class="text-lg font-black text-slate-950">Phân tích doanh thu</h2>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button
                                v-for="tab in breakdownTabs"
                                :key="tab.key"
                                class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-bold transition"
                                :class="activeBreakdown === tab.key ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                @click="activeBreakdown = tab.key"
                            >
                                <component :is="tab.icon" class="h-4 w-4" />{{ tab.label }}
                            </button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-5 py-3">Tên</th>
                                    <th class="px-5 py-3 text-right">Đơn</th>
                                    <th class="px-5 py-3 text-right">Lượt</th>
                                    <th class="px-5 py-3 text-right">Doanh thu</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="row in breakdownRows" :key="`${activeBreakdown}-${row.id}-${row.name}`">
                                    <td class="px-5 py-4 font-bold text-slate-800">{{ row.name }}</td>
                                    <td class="px-5 py-4 text-right text-slate-600">{{ number(row.successful_orders) }}</td>
                                    <td class="px-5 py-4 text-right text-slate-600">{{ number(row.successful_units) }}</td>
                                    <td class="px-5 py-4 text-right font-black text-emerald-700">{{ money(row.revenue) }}</td>
                                </tr>
                                <tr v-if="!breakdownRows.length">
                                    <td colspan="4" class="px-5 py-10 text-center text-slate-500">Chưa có đơn thành công trong kỳ.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 p-5 sm:p-6">
                        <h2 class="text-lg font-black text-slate-950">Đơn thành công gần nhất</h2>
                        <p class="mt-1 text-sm text-slate-500">Trong khoảng báo cáo đã chọn</p>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <div v-for="order in report.recent_successful_orders" :key="order.code" class="p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <RouterLink
                                        :to="{ name: 'admin.topup.orders', query: { search: order.code } }"
                                        class="font-black text-slate-900 hover:text-emerald-700"
                                        >{{ order.code }}</RouterLink
                                    >
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ order.game || 'Không xác định' }} · {{ order.provider || 'Không xác định' }}
                                    </p>
                                </div>
                                <strong class="whitespace-nowrap text-sm text-emerald-700">{{ money(order.revenue) }}</strong>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                                <span>{{ order.package }} · {{ order.successful_units }} lượt</span><span>{{ dateTime(order.completed_at) }}</span>
                            </div>
                        </div>
                        <div v-if="!report.recent_successful_orders.length" class="p-10 text-center text-sm text-slate-500">
                            Chưa có đơn thành công.
                        </div>
                    </div>
                </article>
            </section>

            <div class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                <ShieldCheck class="mt-0.5 h-5 w-5 shrink-0" />
                <div>
                    <strong>Nguyên tắc ghi nhận</strong>
                    <p class="mt-1 leading-6 text-emerald-800">
                        {{ report.criteria }} Kỳ so sánh: {{ report.period.previous_from }} — {{ report.period.previous_to }}.
                    </p>
                </div>
            </div>
        </template>
    </main>
</template>
