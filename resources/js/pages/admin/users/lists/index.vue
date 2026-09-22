<script setup lang="ts">
import { adminUserService, type AdminUserListItem } from '@/services/admin-user.service';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import {
    CalendarRange,
    ChevronLeft,
    ChevronRight,
    Eye,
    Filter,
    LoaderCircle,
    Lock,
    MoreHorizontal,
    Plus,
    RefreshCcw,
    Search,
    ShieldCheck,
    UserPlus,
    Users,
    Wallet,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';

type SummaryCard = {
    key: string;
    label: string;
    value: string;
    description: string;
    icon: typeof Users;
    iconClass: string;
    accentClass: string;
};

type UserFilters = {
    search: string;
    status: '' | 'active' | 'blocked';
    role: string;
    date_from: string;
    date_to: string;
    per_page: number;
    page: number;
};

const loading = ref(false);
const togglingUserId = ref<number | null>(null);
const users = ref<AdminUserListItem[]>([]);
const meta = reactive({
    current_page: 1,
    last_page: 1,
    per_page: 6,
    total: 0,
});
const stats = reactive({
    total_users: 0,
    new_today: 0,
    active_users: 0,
    blocked_users: 0,
    total_user_wallet_balance: 0,
});

const filters = reactive<UserFilters>({
    search: '',
    status: '',
    role: '',
    date_from: '',
    date_to: '',
    per_page: 6,
    page: 1,
});

const formatNumber = (value: number): string => {
    return new Intl.NumberFormat('vi-VN').format(value);
};

const formatCurrency = (value: number | null): string => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
    }).format(value ?? 0);
};

const formatDate = (value: string | null, options?: Intl.DateTimeFormatOptions): string => {
    if (!value) {
        return '--';
    }

    return new Intl.DateTimeFormat(
        'vi-VN',
        options ?? {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        },
    ).format(new Date(value));
};

const formatRelativeDate = (value: string | null): string => {
    if (!value) {
        return 'Chưa đăng nhập';
    }

    return new Intl.DateTimeFormat('vi-VN', {
        hour: '2-digit',
        minute: '2-digit',
        day: '2-digit',
        month: '2-digit',
    }).format(new Date(value));
};

const initialsFor = (user: AdminUserListItem): string => {
    const source = user.name || user.username || user.email || `U${user.id}`;

    return source
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
};

const statusStyles: Record<string, string> = {
    active: 'border border-emerald-200 bg-emerald-50 text-emerald-700',
    blocked: 'border border-orange-200 bg-orange-50 text-orange-700',
    inactive: 'border border-slate-200 bg-slate-100 text-slate-600',
};

const summaryCards = computed<SummaryCard[]>(() => {
    const totalUsers = stats.total_users || meta.total;
    const activeRate = totalUsers > 0 ? (stats.active_users / totalUsers) * 100 : 0;
    const blockedRate = totalUsers > 0 ? (stats.blocked_users / totalUsers) * 100 : 0;

    return [
        {
            key: 'total',
            label: 'Tổng thành viên',
            value: formatNumber(totalUsers),
            description: 'Tất cả tài khoản đã đăng ký trong hệ thống.',
            icon: Users,
            iconClass: 'bg-[linear-gradient(135deg,_#9b8cff_0%,_#5b7cff_100%)] text-white',
            accentClass: 'text-slate-500',
        },
        {
            key: 'new',
            label: 'Mới hôm nay',
            value: formatNumber(stats.new_today),
            description: `${stats.new_today > 0 ? '+' : ''}${formatNumber(stats.new_today)} tài khoản mới trong ngày.`,
            icon: UserPlus,
            iconClass: 'bg-[linear-gradient(135deg,_#67d17d_0%,_#17b26a_100%)] text-white',
            accentClass: 'text-emerald-600',
        },
        {
            key: 'active',
            label: 'Đang hoạt động',
            value: formatNumber(stats.active_users),
            description: `${activeRate.toFixed(1)}% tổng thành viên đang hoạt động.`,
            icon: ShieldCheck,
            iconClass: 'bg-[linear-gradient(135deg,_#6fb4ff_0%,_#3478f6_100%)] text-white',
            accentClass: 'text-blue-600',
        },
        {
            key: 'blocked',
            label: 'Tạm khóa',
            value: formatNumber(stats.blocked_users),
            description: `${blockedRate.toFixed(1)}% tổng thành viên đang bị giới hạn.`,
            icon: Lock,
            iconClass: 'bg-[linear-gradient(135deg,_#ffbb63_0%,_#ff8a1f_100%)] text-white',
            accentClass: 'text-orange-600',
        },
        {
            key: 'wallet',
            label: 'Tổng số dư user',
            value: formatCurrency(stats.total_user_wallet_balance),
            description: 'Tổng tiền còn trong ví user, không bao gồm tài khoản admin.',
            icon: Wallet,
            iconClass: 'bg-[linear-gradient(135deg,_#38bdf8_0%,_#0f766e_100%)] text-white',
            accentClass: 'text-teal-700',
        },
    ];
});

const activeRateLabel = computed(() => {
    const totalUsers = stats.total_users || meta.total;

    if (totalUsers === 0) {
        return '0.0%';
    }

    return `${((stats.active_users / totalUsers) * 100).toFixed(1)}%`;
});

const visiblePages = computed<number[]>(() => {
    const pages = new Set<number>();
    const start = Math.max(1, meta.current_page - 1);
    const end = Math.min(meta.last_page, meta.current_page + 1);

    pages.add(1);

    for (let page = start; page <= end; page += 1) {
        pages.add(page);
    }

    pages.add(meta.last_page);

    return Array.from(pages).sort((left, right) => left - right);
});

const fetchUsers = async (): Promise<void> => {
    loading.value = true;

    try {
        const response = await adminUserService.list({
            search: filters.search || undefined,
            status: filters.status || undefined,
            role: filters.role || undefined,
            date_from: filters.date_from || undefined,
            date_to: filters.date_to || undefined,
            per_page: filters.per_page,
            page: filters.page,
        });

        users.value = response.data;
        meta.current_page = response.meta.current_page;
        meta.last_page = response.meta.last_page;
        meta.per_page = response.meta.per_page;
        meta.total = response.meta.total;
        stats.total_users = response.stats.total_users;
        stats.new_today = response.stats.new_today;
        stats.active_users = response.stats.active_users;
        stats.blocked_users = response.stats.blocked_users;
        stats.total_user_wallet_balance = response.stats.total_user_wallet_balance;
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

const applyFilters = async (): Promise<void> => {
    filters.page = 1;
    await fetchUsers();
};

const resetFilters = async (): Promise<void> => {
    filters.search = '';
    filters.status = '';
    filters.role = '';
    filters.date_from = '';
    filters.date_to = '';
    filters.per_page = 6;
    filters.page = 1;
    await fetchUsers();
};

const goToPage = async (page: number): Promise<void> => {
    if (page < 1 || page > meta.last_page || page === meta.current_page) {
        return;
    }

    filters.page = page;
    await fetchUsers();
};

const toggleStatus = async (user: AdminUserListItem): Promise<void> => {
    togglingUserId.value = user.id;

    try {
        const nextStatus: 'active' | 'blocked' = user.status === 'active' ? 'blocked' : 'active';
        await adminUserService.updateStatus(user.id, nextStatus);
        user.status = nextStatus;
        handleSuccessResponse({
            data: {
                status: true,
                message: nextStatus === 'active' ? 'Da mo khoa tai khoan.' : 'Da tam khoa tai khoan.',
            },
        });
        await fetchUsers();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        togglingUserId.value = null;
    }
};

watch(
    () => filters.per_page,
    async () => {
        filters.page = 1;
        await fetchUsers();
    },
);

onMounted(async () => {
    await fetchUsers();
});
</script>

<template>
    <div class="space-y-6">
        <section
            class="relative overflow-hidden rounded-[10px] border border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(70,95,255,0.12),_transparent_28%),linear-gradient(180deg,_rgba(255,255,255,0.98)_0%,_rgba(255,255,255,0.96)_100%)] px-5 py-5 shadow-[0_16px_40px_rgba(15,23,42,0.06)]"
        >
            <div
                class="absolute inset-y-0 right-0 hidden w-1/3 bg-[radial-gradient(circle_at_center,_rgba(249,115,22,0.08),_transparent_60%)] lg:block"
            />

            <div class="relative flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-2xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#465fff]">Admin workspace</p>
                    <h1 class="mt-2 text-[28px] font-black tracking-tight text-slate-950">Quản lý thành viên</h1>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-slate-500">
                        Theo dõi, tìm kiếm và quản lý tài khoản thành viên với bộ lọc và thao tác nhanh.
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:min-w-[320px]">
                    <div class="rounded-[10px] border border-slate-200 bg-white px-4 py-3 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Tỷ lệ active</p>
                        <p class="mt-2 text-3xl font-black tracking-tight text-slate-950">{{ activeRateLabel }}</p>
                        <p class="mt-1 text-xs text-emerald-600">Tỷ lệ hoạt động hiện tại của hệ thống.</p>
                    </div>

                    <div class="rounded-[10px] border border-slate-200 bg-white px-4 py-3 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Cập nhật</p>
                        <p class="mt-2 text-base font-semibold text-slate-950">{{ formatRelativeDate(new Date().toISOString()) }}</p>
                        <button type="button" class="mt-2 inline-flex items-center gap-2 text-sm font-semibold text-[#465fff]" @click="fetchUsers">
                            <RefreshCcw class="h-4 w-4" />
                            Làm mới dữ liệu
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <article
                v-for="card in summaryCards"
                :key="card.key"
                class="rounded-[10px] border border-slate-200 bg-white px-4 py-4 shadow-[0_12px_28px_rgba(15,23,42,0.05)]"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-500">{{ card.label }}</p>
                        <p
                            class="mt-1 break-words font-black tracking-tight text-slate-950"
                            :class="card.key === 'wallet' ? 'text-xl' : 'text-[28px]'"
                        >
                            {{ card.value }}
                        </p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-[10px]" :class="card.iconClass">
                        <component :is="card.icon" class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-2 text-xs leading-5" :class="card.accentClass">{{ card.description }}</p>
            </article>
        </section>

        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-[0_12px_32px_rgba(15,23,42,0.05)]">
            <div class="grid gap-3 xl:grid-cols-[1.5fr_repeat(2,_minmax(0,_0.75fr))_repeat(2,_minmax(0,_0.85fr))_auto]">
                <label class="flex items-center gap-3 rounded-[8px] border border-slate-200 bg-slate-50 px-3 py-2.5">
                    <Search class="h-4 w-4 text-slate-400" />
                    <input
                        v-model="filters.search"
                        type="text"
                        placeholder="Tìm tên, email, số điện thoại..."
                        class="w-full border-0 bg-transparent p-0 text-sm text-slate-700 outline-none placeholder:text-slate-400"
                        @keyup.enter="applyFilters"
                    />
                </label>

                <select
                    v-model="filters.status"
                    class="rounded-[8px] border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-600 outline-none transition focus:border-[#465fff]"
                >
                    <option value="">Trạng thái</option>
                    <option value="active">Hoạt động</option>
                    <option value="blocked">Tạm khóa</option>
                </select>

                <select
                    v-model="filters.role"
                    class="rounded-[8px] border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-600 outline-none transition focus:border-[#465fff]"
                >
                    <option value="">Vai trò</option>
                    <option value="admin">Admin</option>
                    <option value="user">User</option>
                </select>

                <label class="flex items-center gap-3 rounded-[8px] border border-slate-200 bg-white px-3 py-2.5">
                    <CalendarRange class="h-4 w-4 text-slate-400" />
                    <input v-model="filters.date_from" type="date" class="w-full border-0 bg-transparent p-0 text-sm text-slate-600 outline-none" />
                </label>

                <label class="flex items-center gap-3 rounded-[8px] border border-slate-200 bg-white px-3 py-2.5">
                    <CalendarRange class="h-4 w-4 text-slate-400" />
                    <input v-model="filters.date_to" type="date" class="w-full border-0 bg-transparent p-0 text-sm text-slate-600 outline-none" />
                </label>

                <div class="flex gap-3">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-[8px] border border-[#465fff] px-4 py-2.5 text-sm font-semibold text-[#465fff] transition hover:bg-[#eef2ff]"
                        @click="applyFilters"
                    >
                        <Filter class="h-4 w-4" />
                        Lọc
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-[8px] bg-[linear-gradient(135deg,_#4c63ff_0%,_#2f4bff_100%)] px-4 py-2.5 text-sm font-semibold text-white shadow-[0_10px_20px_rgba(70,95,255,0.22)] transition hover:translate-y-[-1px]"
                        @click="resetFilters"
                    >
                        <Plus class="h-4 w-4" />
                        Làm mới
                    </button>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-[0_16px_40px_rgba(15,23,42,0.06)]">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-xl font-black tracking-tight text-slate-950">Danh sách thành viên</h2>
                    <span class="rounded-[8px] border border-[#cfd6ff] bg-[#eef2ff] px-2.5 py-1 text-xs font-semibold text-[#465fff]">
                        {{ formatNumber(meta.total) }} thành viên
                    </span>
                </div>

                <div class="flex items-center gap-3 rounded-[10px] border border-slate-200 bg-slate-50 px-3 py-2.5">
                    <div class="flex h-9 w-9 items-center justify-center rounded-[8px] bg-white text-emerald-500 shadow-sm">
                        <ShieldCheck class="h-4 w-4" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Tỷ lệ hoạt động</p>
                        <div class="flex items-center gap-2">
                            <span class="text-lg font-black tracking-tight text-slate-950">{{ activeRateLabel }}</span>
                            <span class="text-xs font-semibold text-emerald-600">ổn định</span>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="loading" class="flex items-center justify-center gap-3 px-6 py-16 text-sm font-medium text-slate-500">
                <LoaderCircle class="h-5 w-5 animate-spin" />
                Đang tải danh sách thành viên...
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full table-fixed">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/80 text-left text-sm font-semibold text-slate-500">
                            <th class="w-[90px] px-6 py-4">ID</th>
                            <th class="min-w-[280px] px-6 py-4">Thành viên</th>
                            <th class="w-[170px] px-6 py-4">Số điện thoại</th>
                            <th class="w-[160px] px-6 py-4">Số dư</th>
                            <th class="w-[150px] px-6 py-4">Trạng thái</th>
                            <th class="w-[140px] px-6 py-4">Ngày tham gia</th>
                            <th class="w-[170px] px-6 py-4 text-right">Thao tác</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-if="users.length === 0">
                            <td colspan="7" class="px-6 py-16 text-center text-sm text-slate-500">
                                Không có thành viên phù hợp với bộ lọc hiện tại.
                            </td>
                        </tr>

                        <tr v-for="(user, index) in users" :key="user.id" class="border-b border-slate-100 transition hover:bg-slate-50/80">
                            <td class="px-6 py-5 text-sm font-semibold text-slate-600">{{ String(user.id) }}</td>
                            <td class="px-6 py-5">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-11 w-11 items-center justify-center rounded-[10px] text-sm font-bold text-white shadow-sm"
                                        :class="
                                            index % 2 === 0
                                                ? 'bg-[linear-gradient(135deg,_#1f2937_0%,_#465fff_100%)]'
                                                : 'bg-[linear-gradient(135deg,_#f97316_0%,_#fb7185_100%)]'
                                        "
                                    >
                                        {{ initialsFor(user) }}
                                    </div>

                                    <div class="min-w-0">
                                        <RouterLink
                                            :to="{ name: 'admin.users.show', params: { user_id: user.id } }"
                                            class="block truncate text-sm font-bold text-slate-950 transition hover:text-[#465fff]"
                                        >
                                            {{ user.name || user.username || 'Khách hàng #' + user.id }}
                                        </RouterLink>
                                        <p class="truncate text-sm text-slate-500">{{ user.email || 'Chưa cập nhật email' }}</p>
                                        <p class="mt-1 truncate text-xs uppercase tracking-[0.18em] text-slate-400">{{ user.role }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-5 text-sm text-slate-600">{{ user.phone || '--' }}</td>
                            <td class="px-6 py-5 text-sm font-semibold text-slate-700">{{ formatCurrency(user.wallet_balance) }}</td>
                            <td class="px-6 py-5">
                                <span
                                    class="inline-flex rounded-[8px] px-2.5 py-1 text-xs font-semibold"
                                    :class="statusStyles[user.status] || statusStyles.inactive"
                                >
                                    {{ user.status === 'active' ? 'Hoạt động' : user.status === 'blocked' ? 'Tạm khóa' : 'Không hoạt động' }}
                                </span>
                            </td>
                            <td class="px-6 py-5 text-sm text-slate-600">{{ formatDate(user.created_at) }}</td>
                            <td class="px-6 py-5">
                                <div class="flex items-center justify-end gap-2">
                                    <RouterLink
                                        :to="{ name: 'admin.users.show', params: { user_id: user.id } }"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-[8px] border border-slate-200 bg-white text-slate-500 transition hover:border-[#465fff] hover:text-[#465fff]"
                                    >
                                        <Eye class="h-4 w-4" />
                                    </RouterLink>

                                    <button
                                        type="button"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-[8px] border transition"
                                        :class="
                                            user.status === 'active'
                                                ? 'border-orange-200 bg-orange-50 text-orange-600 hover:border-orange-300'
                                                : 'border-emerald-200 bg-emerald-50 text-emerald-600 hover:border-emerald-300'
                                        "
                                        :disabled="togglingUserId === user.id"
                                        @click="toggleStatus(user)"
                                    >
                                        <LoaderCircle v-if="togglingUserId === user.id" class="h-4 w-4 animate-spin" />
                                        <Lock v-else class="h-4 w-4" />
                                    </button>

                                    <RouterLink
                                        :to="{ name: 'admin.users.wallet-transaction.show', params: { user_id: user.id } }"
                                        type="button"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-[8px] border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                                    >
                                        <Wallet class="h-4 w-4" />
                                    </RouterLink>

                                    <button
                                        type="button"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-[8px] border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                                    >
                                        <MoreHorizontal class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-4 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-slate-500">
                    Hiển thị
                    {{ meta.total === 0 ? 0 : (meta.current_page - 1) * meta.per_page + 1 }}-{{
                        Math.min(meta.current_page * meta.per_page, meta.total)
                    }}
                    trong tổng số {{ formatNumber(meta.total) }} thành viên
                </p>

                <div class="flex flex-wrap items-center gap-3">
                    <select
                        v-model="filters.per_page"
                        class="rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600 outline-none"
                    >
                        <option :value="6">6 / trang</option>
                        <option :value="10">10 / trang</option>
                        <option :value="20">20 / trang</option>
                    </select>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-[8px] border border-slate-200 bg-white text-slate-500 transition disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="meta.current_page === 1"
                            @click="goToPage(meta.current_page - 1)"
                        >
                            <ChevronLeft class="h-4 w-4" />
                        </button>

                        <template v-for="page in visiblePages" :key="page">
                            <span v-if="page > 1 && !visiblePages.includes(page - 1)" class="px-1 text-sm text-slate-400"> ... </span>

                            <button
                                type="button"
                                class="inline-flex h-9 min-w-9 items-center justify-center rounded-[8px] border px-3 text-sm font-semibold transition"
                                :class="
                                    page === meta.current_page
                                        ? 'border-[#465fff] bg-[#465fff] text-white shadow-[0_10px_30px_rgba(70,95,255,0.24)]'
                                        : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'
                                "
                                @click="goToPage(page)"
                            >
                                {{ page }}
                            </button>
                        </template>

                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-[8px] border border-slate-200 bg-white text-slate-500 transition disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="meta.current_page === meta.last_page"
                            @click="goToPage(meta.current_page + 1)"
                        >
                            <ChevronRight class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
