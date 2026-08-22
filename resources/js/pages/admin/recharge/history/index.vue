<script setup lang="ts">
import { adminRechargeHistoryService, type AdminRechargeHistoryItem } from '@/services/admin-recharge-history.service';
import { handleErrorResponse } from '@/utils/response';
import {
    BadgeCheck,
    CalendarClock,
    CalendarRange,
    ChevronLeft,
    ChevronRight,
    CircleDollarSign,
    Filter,
    Landmark,
    LoaderCircle,
    RefreshCcw,
    ScanSearch,
    Search,
    ShieldAlert,
} from 'lucide-vue-next';
import { onMounted, reactive, ref } from 'vue';
import { RouterLink } from 'vue-router';

type Filters = {
    search: string;
    user_id: string;
    status: string;
    bank_code: string;
    date_from: string;
    date_to: string;
    per_page: number;
    page: number;
};

const loading = ref(false);
const items = ref<AdminRechargeHistoryItem[]>([]);
const filters = reactive<Filters>({
    search: '',
    user_id: '',
    status: '',
    bank_code: '',
    date_from: '',
    date_to: '',
    per_page: 15,
    page: 1,
});

const meta = reactive({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
});

const stats = reactive({
    total_amount: 0,
    today_count: 0,
    pending_count: 0,
    matched_count: 0,
    success_count: 0,
    failed_count: 0,
});

const formatCurrency = (value: number): string =>
    new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
    }).format(value);

const formatNumber = (value: number): string => new Intl.NumberFormat('vi-VN').format(value);

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return '--';
    }

    return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
};

const statusLabel = (status: string): string =>
    ({
        pending: 'Chờ xử lý',
        processing: 'Đang đối soát',
        paid: 'Đã cộng tiền',
        failed: 'Thất bại',
        cancelled: 'Đã hủy',
        expired: 'Hết hạn',
    })[status] ?? status;

const statusClass = (status: string): string =>
    ({
        pending: 'border border-amber-200 bg-amber-50 text-amber-700',
        processing: 'border border-sky-200 bg-sky-50 text-sky-700',
        paid: 'border border-emerald-200 bg-emerald-50 text-emerald-700',
        failed: 'border border-rose-200 bg-rose-50 text-rose-700',
        cancelled: 'border border-slate-200 bg-slate-100 text-slate-600',
        expired: 'border border-slate-200 bg-slate-100 text-slate-600',
    })[status] ?? 'border border-slate-200 bg-slate-100 text-slate-600';

async function fetchItems(): Promise<void> {
    loading.value = true;

    try {
        const response = await adminRechargeHistoryService.list({
            search: filters.search || undefined,
            user_id: filters.user_id || undefined,
            status: filters.status || undefined,
            bank_code: filters.bank_code || undefined,
            date_from: filters.date_from || undefined,
            date_to: filters.date_to || undefined,
            per_page: filters.per_page,
            page: filters.page,
        });

        items.value = response.data;
        meta.current_page = response.meta.current_page;
        meta.last_page = response.meta.last_page;
        meta.per_page = response.meta.per_page;
        meta.total = response.meta.total;
        stats.total_amount = response.stats.total_amount;
        stats.today_count = response.stats.today_count;
        stats.pending_count = response.stats.pending_count;
        stats.matched_count = response.stats.matched_count;
        stats.success_count = response.stats.success_count;
        stats.failed_count = response.stats.failed_count;
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
}

async function applyFilters(): Promise<void> {
    filters.page = 1;
    await fetchItems();
}

async function resetFilters(): Promise<void> {
    filters.search = '';
    filters.user_id = '';
    filters.status = '';
    filters.bank_code = '';
    filters.date_from = '';
    filters.date_to = '';
    filters.per_page = 15;
    filters.page = 1;
    await fetchItems();
}

async function goToPage(page: number): Promise<void> {
    if (page < 1 || page > meta.last_page || page === meta.current_page) {
        return;
    }

    filters.page = page;
    await fetchItems();
}

onMounted(async () => {
    await fetchItems();
});
</script>

<template>
    <div class="space-y-5">
        <section class="rounded-[10px] border border-slate-200 bg-white p-2 shadow-sm">
            <div class="flex flex-wrap gap-2">
                <RouterLink
                    :to="{ name: 'admin.recharge.config' }"
                    class="inline-flex items-center rounded-[10px] px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100"
                >
                    Cấu hình nạp tiền
                </RouterLink>
                <RouterLink
                    :to="{ name: 'admin.recharge.history' }"
                    class="inline-flex items-center rounded-[10px] bg-indigo-600 px-4 py-2 text-sm font-semibold text-white"
                >
                    Lịch sử nạp tiền
                </RouterLink>
            </div>
        </section>

        <section
            class="overflow-hidden rounded-[10px] border border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(79,70,229,0.12),_transparent_28%),linear-gradient(180deg,_rgba(255,255,255,0.98)_0%,_rgba(255,255,255,0.96)_100%)] px-5 py-5 shadow-[0_16px_40px_rgba(15,23,42,0.06)]"
        >
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="mt-1 text-xs font-semibold uppercase tracking-[0.3em] text-indigo-600">Recharge workspace</p>
                    <h1 class="mt-2 text-[28px] font-black tracking-tight text-slate-950">Lịch sử nạp tiền</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                        Theo dõi toàn bộ yêu cầu nạp tiền, trạng thái đối soát và người dùng đã gửi nội dung chuyển khoản gì vào hệ thống.
                    </p>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-[8px] border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 shadow-sm"
                    @click="fetchItems"
                >
                    <RefreshCcw class="h-4 w-4" />
                    Làm mới
                </button>
            </div>
        </section>

        <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            <article class="rounded-[10px] border border-slate-200 bg-white px-4 py-4 shadow-[0_12px_28px_rgba(15,23,42,0.05)]">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Tổng tiền yêu cầu</p>
                        <p class="mt-2 text-2xl font-black tracking-tight text-slate-950">{{ formatCurrency(stats.total_amount) }}</p>
                    </div>
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"
                        ><CircleDollarSign class="h-5 w-5"
                    /></span>
                </div>
            </article>
            <article class="rounded-[10px] border border-slate-200 bg-white px-4 py-4 shadow-[0_12px_28px_rgba(15,23,42,0.05)]">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Yêu cầu hôm nay</p>
                        <p class="mt-2 text-2xl font-black tracking-tight text-slate-950">{{ formatNumber(stats.today_count) }}</p>
                    </div>
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-sky-50 text-sky-600"
                        ><CalendarClock class="h-5 w-5"
                    /></span>
                </div>
            </article>
            <article class="rounded-[10px] border border-slate-200 bg-white px-4 py-4 shadow-[0_12px_28px_rgba(15,23,42,0.05)]">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Chờ xử lý</p>
                        <p class="mt-2 text-2xl font-black tracking-tight text-amber-600">{{ formatNumber(stats.pending_count) }}</p>
                    </div>
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600"
                        ><ShieldAlert class="h-5 w-5"
                    /></span>
                </div>
            </article>
            <article class="rounded-[10px] border border-slate-200 bg-white px-4 py-4 shadow-[0_12px_28px_rgba(15,23,42,0.05)]">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Đang đối soát</p>
                        <p class="mt-2 text-2xl font-black tracking-tight text-sky-600">{{ formatNumber(stats.matched_count) }}</p>
                    </div>
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600"
                        ><ScanSearch class="h-5 w-5"
                    /></span>
                </div>
            </article>
            <article class="rounded-[10px] border border-slate-200 bg-white px-4 py-4 shadow-[0_12px_28px_rgba(15,23,42,0.05)]">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Đã cộng / lỗi</p>
                        <p class="mt-2 text-2xl font-black tracking-tight text-slate-950">
                            <span class="text-emerald-600">{{ formatNumber(stats.success_count) }}</span> /
                            <span class="text-rose-600">{{ formatNumber(stats.failed_count) }}</span>
                        </p>
                    </div>
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"
                        ><BadgeCheck class="h-5 w-5"
                    /></span>
                </div>
            </article>
        </section>

        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-[0_12px_32px_rgba(15,23,42,0.05)]">
            <div class="grid gap-3 xl:grid-cols-[1.5fr_repeat(4,_minmax(0,_0.9fr))_auto]">
                <label class="flex items-center gap-3 rounded-[8px] border border-slate-200 bg-slate-50 px-3 py-2.5">
                    <Search class="h-4 w-4 text-slate-400" />
                    <input
                        v-model="filters.search"
                        type="text"
                        placeholder="Tìm mã GD, nội dung, email, số điện thoại..."
                        class="w-full border-0 bg-transparent p-0 text-sm text-slate-700 outline-none placeholder:text-slate-400"
                        @keyup.enter="applyFilters"
                    />
                </label>

                <input
                    v-model="filters.user_id"
                    type="number"
                    min="1"
                    placeholder="User ID"
                    class="rounded-[8px] border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-600 outline-none"
                />

                <input
                    v-model="filters.bank_code"
                    type="text"
                    placeholder="Ngân hàng"
                    class="rounded-[8px] border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-600 outline-none"
                />

                <select
                    v-model="filters.status"
                    class="rounded-[8px] border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-600 outline-none"
                >
                    <option value="">Trạng thái</option>
                    <option value="pending">Chờ xử lý</option>
                    <option value="processing">Đang đối soát</option>
                    <option value="paid">Đã cộng tiền</option>
                    <option value="failed">Thất bại</option>
                    <option value="cancelled">Đã hủy</option>
                    <option value="expired">Hết hạn</option>
                </select>

                <label class="flex items-center gap-3 rounded-[8px] border border-slate-200 bg-white px-3 py-2.5">
                    <CalendarRange class="h-4 w-4 text-slate-400" />
                    <input v-model="filters.date_from" type="date" class="w-full border-0 bg-transparent p-0 text-sm text-slate-600 outline-none" />
                </label>

                <div class="flex gap-3">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-[8px] border border-indigo-500 px-4 py-2.5 text-sm font-semibold text-indigo-600 transition hover:bg-indigo-50"
                        @click="applyFilters"
                    >
                        <Filter class="h-4 w-4" />
                        Lọc
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-[8px] bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-[0_10px_20px_rgba(79,70,229,0.22)]"
                        @click="resetFilters"
                    >
                        Đặt lại
                    </button>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-[0_16px_40px_rgba(15,23,42,0.06)]">
            <div class="border-b border-slate-200 px-4 py-4">
                <h2 class="text-xl font-black tracking-tight text-slate-950">Danh sách giao dịch nạp tiền</h2>
                <p class="mt-1 text-sm text-slate-500">Hiển thị {{ formatNumber(meta.total) }} giao dịch phù hợp với bộ lọc hiện tại.</p>
            </div>

            <div v-if="loading" class="flex items-center justify-center gap-3 px-6 py-16 text-sm text-slate-500">
                <LoaderCircle class="h-5 w-5 animate-spin" />
                Đang tải lịch sử nạp tiền...
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-slate-50 text-left text-sm font-semibold text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Mã GD</th>
                            <th class="px-4 py-3">Người dùng</th>
                            <th class="px-4 py-3">Ngân hàng nhận</th>
                            <th class="px-4 py-3">Số tiền</th>
                            <th class="px-4 py-3">Nội dung</th>
                            <th class="px-4 py-3">Trạng thái</th>
                            <th class="px-4 py-3">Xác nhận</th>
                            <th class="px-4 py-3">Thời gian</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="items.length === 0">
                            <td colspan="8" class="px-4 py-12 text-center text-sm text-slate-500">Chưa có giao dịch nạp tiền nào.</td>
                        </tr>
                        <tr v-for="item in items" :key="item.id" class="border-t border-slate-100 text-sm">
                            <td class="px-4 py-3 font-semibold text-slate-700">{{ item.transaction_code }}</td>
                            <td class="px-4 py-3">
                                <RouterLink
                                    v-if="item.user"
                                    :to="{ name: 'admin.users.show', params: { user_id: item.user.id } }"
                                    class="font-semibold text-slate-900 hover:text-indigo-600"
                                >
                                    {{ item.user.name }}
                                </RouterLink>
                                <p class="text-slate-500">{{ item.user?.email || '--' }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2 text-slate-700">
                                    <Landmark class="h-4 w-4 text-slate-400" />
                                    <div>
                                        <p class="font-semibold">{{ item.bank_name || '--' }}</p>
                                        <p class="text-xs text-slate-500">{{ item.account_number || '--' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 font-semibold text-slate-900">{{ formatCurrency(item.amount) }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ item.content || '--' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-[8px] px-2.5 py-1 text-xs font-semibold" :class="statusClass(item.status)">
                                    {{ statusLabel(item.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ formatDateTime(item.confirmed_at) }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ formatDateTime(item.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-4 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-slate-500">
                    Hiển thị {{ meta.total === 0 ? 0 : (meta.current_page - 1) * meta.per_page + 1 }}-{{
                        Math.min(meta.current_page * meta.per_page, meta.total)
                    }}
                    trong tổng số {{ formatNumber(meta.total) }} giao dịch
                </p>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-[8px] border border-slate-200 bg-white text-slate-500 disabled:opacity-40"
                        :disabled="meta.current_page === 1"
                        @click="goToPage(meta.current_page - 1)"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <span class="px-2 text-sm font-semibold text-slate-600">{{ meta.current_page }} / {{ meta.last_page }}</span>
                    <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-[8px] border border-slate-200 bg-white text-slate-500 disabled:opacity-40"
                        :disabled="meta.current_page === meta.last_page"
                        @click="goToPage(meta.current_page + 1)"
                    >
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </section>
    </div>
</template>
