<script setup lang="ts">
import { adminMemberLevelService, type MemberLevel } from '@/services/admin-member-level.service';
import {
    adminUserService,
    type AdminPaginationMeta,
    type AdminUserDetailResponse,
    type AdminUserLog,
    type AdminUserWalletTransaction,
} from '@/services/admin-user.service';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { ArrowLeft, CheckCheck, Crown, KeyRound, ListChecks, LoaderCircle, Minus, Plus, Save, Wallet } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';

type TabKey = 'overview' | 'transactions' | 'logs';

type TabState<T> = {
    loading: boolean;
    loaded: boolean;
    items: T[];
    meta: AdminPaginationMeta;
};

const route = useRoute();
const userId = Number(route.params.user_id);

const loading = ref(false);
const adjustingWallet = ref(false);
const resettingPassword = ref(false);
const assigningLevel = ref(false);
const detail = ref<AdminUserDetailResponse | null>(null);
const levels = ref<MemberLevel[]>([]);
const activeTab = ref<TabKey>('overview');

const walletForm = reactive({
    type: 'add' as 'add' | 'subtract',
    amount: '',
    note: '',
});

const passwordForm = reactive({
    password: '',
    password_confirmation: '',
});

const levelForm = reactive({
    member_level_id: '' as number | '',
    expires_at: '',
    reason: '',
});

const transactionsState = reactive<TabState<AdminUserWalletTransaction>>({
    loading: false,
    loaded: false,
    items: [],
    meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 },
});

const logsState = reactive<TabState<AdminUserLog>>({
    loading: false,
    loaded: false,
    items: [],
    meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 },
});

const tabs = [
    { key: 'overview' as const, label: 'Tổng quan' },
    { key: 'transactions' as const, label: 'Dòng tiền' },
    { key: 'logs' as const, label: 'Hoạt động' },
];

const formatNumber = (value: number): string => new Intl.NumberFormat('vi-VN').format(value);

const formatCurrency = (value: number | null | undefined): string =>
    new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
    }).format(value ?? 0);

const formatDate = (value: string | null, includeTime = false): string => {
    if (!value) {
        return '--';
    }

    return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        ...(includeTime ? { hour: '2-digit', minute: '2-digit' } : {}),
    }).format(new Date(value));
};

const initials = computed(() => {
    const source = detail.value?.name || detail.value?.username || detail.value?.email || `U${userId}`;

    return source
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
});

const statCards = computed(() => {
    if (!detail.value) {
        return [];
    }

    return [
        {
            label: 'Tổng chi',
            value: formatCurrency(detail.value.stats.total_spent),
            icon: Wallet,
            iconClass: 'bg-emerald-50 text-emerald-600',
        },
        {
            label: 'Đơn nạp game',
            value: formatNumber(detail.value.stats.order_count),
            icon: ListChecks,
            iconClass: 'bg-sky-50 text-sky-600',
        },
        {
            label: 'Đơn hoàn tất',
            value: formatNumber(detail.value.stats.completed_order_count),
            icon: CheckCheck,
            iconClass: 'bg-slate-100 text-slate-700',
        },
    ];
});

const loadDetail = async (): Promise<void> => {
    loading.value = true;

    try {
        detail.value = await adminUserService.show(userId);
        levelForm.member_level_id = detail.value.member_level?.is_manual ? (detail.value.member_level.unlocked_level?.id ?? '') : '';
        levelForm.expires_at = detail.value.member_level?.manual_level_expires_at?.slice(0, 16) ?? '';
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

const loadLevels = async (): Promise<void> => {
    try {
        const levelCatalog = await adminMemberLevelService.catalog();
        levels.value = levelCatalog.levels;
    } catch (error) {
        handleErrorResponse(error);
    }
};

const submitLevelAssignment = async (): Promise<void> => {
    assigningLevel.value = true;

    try {
        await adminMemberLevelService.assignUser(userId, {
            member_level_id: levelForm.member_level_id === '' ? null : Number(levelForm.member_level_id),
            expires_at: levelForm.expires_at || null,
            reason: levelForm.reason || undefined,
        });
        levelForm.reason = '';
        handleSuccessResponse({ data: { status: true, message: 'Đã cập nhật level thủ công.' } });
        await loadDetail();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        assigningLevel.value = false;
    }
};

const applyTabResponse = <T,>(state: TabState<T>, response: { data: T[]; meta: AdminPaginationMeta }): void => {
    state.items = response.data;
    state.meta = response.meta;
    state.loaded = true;
};

const loadTransactions = async (page = 1): Promise<void> => {
    transactionsState.loading = true;

    try {
        const response = await adminUserService.walletTransactions(userId, { page, per_page: 10 });
        applyTabResponse(transactionsState, response);
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        transactionsState.loading = false;
    }
};

const loadLogs = async (page = 1): Promise<void> => {
    logsState.loading = true;

    try {
        const response = await adminUserService.logs(userId, { page, per_page: 10 });
        applyTabResponse(logsState, response);
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        logsState.loading = false;
    }
};

const openTab = async (tab: TabKey): Promise<void> => {
    activeTab.value = tab;

    if (tab === 'transactions' && !transactionsState.loaded) {
        await loadTransactions();
    }

    if (tab === 'logs' && !logsState.loaded) {
        await loadLogs();
    }
};

const submitWalletAdjust = async (): Promise<void> => {
    const amount = Number(walletForm.amount);

    if (!Number.isFinite(amount) || amount <= 0) {
        handleErrorResponse({
            response: {
                status: 422,
                data: {
                    errors: {
                        amount: ['Số tiền phải lớn hơn 0.'],
                    },
                },
            },
        });
        return;
    }

    adjustingWallet.value = true;

    try {
        const operation = walletForm.type;
        const response = await adminUserService.adjustWallet(userId, {
            type: operation,
            amount,
            note: walletForm.note || undefined,
        });

        if (detail.value) {
            detail.value.wallet = response.wallet;
        }

        walletForm.amount = '';
        walletForm.note = '';

        handleSuccessResponse({
            data: {
                status: true,
                message: operation === 'add' ? 'Đã cộng tiền cho người dùng.' : 'Đã trừ tiền khỏi người dùng.',
            },
        });

        await Promise.all([loadDetail(), loadTransactions(1)]);
        activeTab.value = 'transactions';
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        adjustingWallet.value = false;
    }
};

const submitPasswordReset = async (): Promise<void> => {
    if (passwordForm.password.trim() === '' || passwordForm.password_confirmation.trim() === '') {
        handleErrorResponse({
            response: {
                status: 422,
                data: {
                    errors: {
                        password: ['Vui lòng nhập đầy đủ mật khẩu mới và xác nhận mật khẩu.'],
                    },
                },
            },
        });
        return;
    }

    resettingPassword.value = true;

    try {
        await adminUserService.resetPassword(userId, {
            password: passwordForm.password,
            password_confirmation: passwordForm.password_confirmation,
        });

        passwordForm.password = '';
        passwordForm.password_confirmation = '';

        handleSuccessResponse({
            data: {
                status: true,
                message: 'Đã cấp lại mật khẩu cho người dùng.',
            },
        });
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        resettingPassword.value = false;
    }
};

const goToTabPage = async (tab: TabKey, page: number): Promise<void> => {
    if (tab === 'transactions') {
        await loadTransactions(page);
    }

    if (tab === 'logs') {
        await loadLogs(page);
    }
};

onMounted(async () => {
    await Promise.all([loadDetail(), loadLevels()]);
});
</script>

<template>
    <div class="space-y-5">
        <section
            class="overflow-hidden rounded-[10px] border border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(70,95,255,0.12),_transparent_28%),linear-gradient(180deg,_rgba(255,255,255,0.98)_0%,_rgba(255,255,255,0.96)_100%)] px-5 py-5 shadow-[0_16px_40px_rgba(15,23,42,0.06)]"
        >
            <RouterLink :to="{ name: 'admin.users.index' }" class="inline-flex items-center gap-2 text-sm font-semibold text-[#465fff]">
                <ArrowLeft class="h-4 w-4" />
                Quay lại danh sách thành viên
            </RouterLink>

            <div v-if="detail" class="mt-4 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex items-start gap-4">
                    <div
                        class="flex h-16 w-16 items-center justify-center rounded-[10px] bg-[linear-gradient(135deg,_#1f2937_0%,_#465fff_100%)] text-xl font-bold text-white"
                    >
                        {{ initials }}
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#465fff]">User workspace</p>
                        <h1 class="mt-2 text-[28px] font-black tracking-tight text-slate-950">
                            {{ detail.name || detail.username || `User #${detail.id}` }}
                        </h1>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex rounded-[8px] px-2.5 py-1 text-xs font-semibold"
                                :class="
                                    detail.status === 'active'
                                        ? 'border border-emerald-200 bg-emerald-50 text-emerald-700'
                                        : 'border border-orange-200 bg-orange-50 text-orange-700'
                                "
                            >
                                {{ detail.status === 'active' ? 'Hoạt động' : 'Tạm khóa' }}
                            </span>
                            <span class="text-sm text-slate-500">{{ detail.email || 'Chưa có email' }}</span>
                            <span class="text-sm text-slate-300">•</span>
                            <span class="text-sm text-slate-500">{{ detail.phone || 'Chưa có số điện thoại' }}</span>
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-[10px] border border-slate-200 bg-white px-4 py-3 shadow-sm">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Số dư ví</p>
                        <p class="mt-2 text-lg font-black tracking-tight text-slate-950">{{ formatCurrency(detail.wallet?.balance) }}</p>
                    </div>
                    <div class="rounded-[10px] border border-slate-200 bg-white px-4 py-3 shadow-sm">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Đăng nhập gần nhất</p>
                        <p class="mt-2 text-sm font-bold text-slate-950">{{ formatDate(detail.latest_login.at, true) }}</p>
                    </div>
                </div>
            </div>
        </section>

        <div
            v-if="loading"
            class="flex items-center justify-center gap-3 rounded-[10px] border border-slate-200 bg-white px-6 py-16 text-sm text-slate-500"
        >
            <LoaderCircle class="h-5 w-5 animate-spin" />
            Đang tải thông tin thành viên...
        </div>

        <template v-else-if="detail">
            <section class="rounded-[10px] border border-slate-200 bg-white shadow-[0_12px_32px_rgba(15,23,42,0.05)]">
                <div class="flex flex-wrap gap-2 border-b border-slate-200 px-4 py-3">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        class="rounded-[8px] px-3 py-2 text-sm font-semibold transition"
                        :class="
                            activeTab === tab.key
                                ? 'bg-[#465fff] text-white shadow-[0_10px_24px_rgba(70,95,255,0.2)]'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                        "
                        @click="openTab(tab.key)"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <div class="p-4">
                    <div v-if="activeTab === 'overview'" class="space-y-4">
                        <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            <article
                                v-for="card in statCards"
                                :key="card.label"
                                class="rounded-[10px] border border-slate-200 bg-white px-4 py-4 shadow-[0_12px_28px_rgba(15,23,42,0.05)]"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-[8px]" :class="card.iconClass">
                                        <component :is="card.icon" class="h-4 w-4" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-500">{{ card.label }}</p>
                                        <p class="mt-0.5 text-lg font-black tracking-tight text-slate-950">{{ card.value }}</p>
                                    </div>
                                </div>
                            </article>
                        </section>

                        <section
                            v-if="detail.member_level"
                            class="grid gap-4 rounded-[10px] border border-amber-200 bg-amber-50 p-4 xl:grid-cols-[minmax(0,1fr)_24rem]"
                        >
                            <div>
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-amber-700">Level đại lý</p>
                                        <h3 class="mt-1 flex items-center gap-2 text-xl font-black text-slate-950">
                                            <Crown class="h-5 w-5 text-amber-500" />{{ detail.member_level.effective_level?.name || 'Chưa có level' }}
                                        </h3>
                                        <p v-if="detail.member_level.is_temporarily_downgraded" class="mt-1 text-sm font-semibold text-amber-800">
                                            Đang tạm giảm từ {{ detail.member_level.unlocked_level?.name }} vì chưa đủ mức duy trì.
                                        </p>
                                    </div>
                                    <span
                                        v-if="detail.member_level.is_manual"
                                        class="rounded-md border border-violet-200 bg-violet-50 px-2.5 py-1 text-xs font-bold text-violet-700"
                                        >Admin khóa level</span
                                    >
                                </div>
                                <dl class="mt-4 grid gap-3 sm:grid-cols-3">
                                    <div class="rounded-md bg-white p-3">
                                        <dt class="text-xs font-semibold text-slate-500">Tổng nạp lịch sử</dt>
                                        <dd class="mt-1 font-black text-slate-950">
                                            {{ formatCurrency(detail.member_level.lifetime_completed_amount) }}
                                        </dd>
                                    </div>
                                    <div class="rounded-md bg-white p-3">
                                        <dt class="text-xs font-semibold text-slate-500">Nạp trong kỳ duy trì</dt>
                                        <dd class="mt-1 font-black text-slate-950">
                                            {{ formatCurrency(detail.member_level.rolling_completed_amount) }}
                                        </dd>
                                    </div>
                                    <div class="rounded-md bg-white p-3">
                                        <dt class="text-xs font-semibold text-slate-500">Còn thiếu duy trì</dt>
                                        <dd class="mt-1 font-black text-amber-700">
                                            {{ formatCurrency(detail.member_level.maintenance_remaining_amount) }}
                                        </dd>
                                    </div>
                                </dl>
                                <div v-if="detail.member_level_histories.length" class="mt-4 border-t border-amber-200 pt-3">
                                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-amber-700">Lịch sử level gần đây</p>
                                    <ul class="mt-2 grid gap-2 sm:grid-cols-2">
                                        <li
                                            v-for="history in detail.member_level_histories.slice(0, 6)"
                                            :key="history.id"
                                            class="flex items-center justify-between gap-3 rounded-md bg-white px-3 py-2 text-xs"
                                        >
                                            <span class="font-semibold text-slate-700"
                                                >{{ history.from_level?.name || 'Khởi tạo' }} → {{ history.to_level?.name || 'Tự động' }}</span
                                            >
                                            <time class="shrink-0 text-slate-400">{{ formatDate(history.created_at) }}</time>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <form class="grid gap-3 rounded-md border border-amber-200 bg-white p-3" @submit.prevent="submitLevelAssignment">
                                <label class="grid gap-1 text-sm font-semibold text-slate-700"
                                    >Level thủ công
                                    <select v-model="levelForm.member_level_id" class="rounded-md border-slate-300">
                                        <option value="">Tự động theo tổng nạp</option>
                                        <option v-for="level in levels" :key="level.id" :value="level.id">{{ level.name }}</option>
                                    </select>
                                </label>
                                <label class="grid gap-1 text-sm font-semibold text-slate-700"
                                    >Khóa đến ngày<input v-model="levelForm.expires_at" class="rounded-md border-slate-300" type="datetime-local"
                                /></label>
                                <label class="grid gap-1 text-sm font-semibold text-slate-700"
                                    >Lý do<input
                                        v-model="levelForm.reason"
                                        class="rounded-md border-slate-300"
                                        maxlength="500"
                                        placeholder="Tuyển đại lý, ưu đãi riêng..."
                                /></label>
                                <button
                                    class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md bg-amber-500 px-3 font-bold text-slate-950 disabled:opacity-50"
                                    :disabled="assigningLevel"
                                    type="submit"
                                >
                                    <LoaderCircle v-if="assigningLevel" class="h-4 w-4 animate-spin" /><Save v-else class="h-4 w-4" /> Lưu level
                                </button>
                            </form>
                        </section>

                        <section class="grid gap-4 xl:grid-cols-[0.9fr_1.1fr]">
                            <div class="space-y-4">
                                <article class="rounded-[10px] border border-slate-200 bg-slate-50 p-4">
                                    <h3 class="text-base font-bold text-slate-950">Điều chỉnh số dư</h3>
                                    <p class="mt-1 text-sm text-slate-500">Cộng hoặc trừ tiền trực tiếp vào ví chính của người dùng.</p>

                                    <div class="mt-4 grid gap-3">
                                        <div class="flex gap-2">
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-2 rounded-[8px] px-3 py-2 text-sm font-semibold transition"
                                                :class="
                                                    walletForm.type === 'add'
                                                        ? 'bg-emerald-600 text-white'
                                                        : 'border border-slate-200 bg-white text-slate-600'
                                                "
                                                @click="walletForm.type = 'add'"
                                            >
                                                <Plus class="h-4 w-4" />
                                                Cộng tiền
                                            </button>
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-2 rounded-[8px] px-3 py-2 text-sm font-semibold transition"
                                                :class="
                                                    walletForm.type === 'subtract'
                                                        ? 'bg-orange-500 text-white'
                                                        : 'border border-slate-200 bg-white text-slate-600'
                                                "
                                                @click="walletForm.type = 'subtract'"
                                            >
                                                <Minus class="h-4 w-4" />
                                                Trừ tiền
                                            </button>
                                        </div>

                                        <label class="grid gap-1">
                                            <span class="text-sm font-medium text-slate-600">Số tiền</span>
                                            <input
                                                v-model="walletForm.amount"
                                                type="number"
                                                min="1"
                                                placeholder="Nhập số tiền"
                                                class="rounded-[8px] border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-[#465fff]"
                                            />
                                        </label>

                                        <label class="grid gap-1">
                                            <span class="text-sm font-medium text-slate-600">Ghi chú</span>
                                            <textarea
                                                v-model="walletForm.note"
                                                rows="3"
                                                placeholder="Ví dụ: điều chỉnh công nợ, hoàn tiền thủ công..."
                                                class="rounded-[8px] border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-[#465fff]"
                                            />
                                        </label>

                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center gap-2 rounded-[8px] bg-[#465fff] px-4 py-2.5 text-sm font-semibold text-white shadow-[0_10px_20px_rgba(70,95,255,0.2)] transition disabled:cursor-not-allowed disabled:opacity-60"
                                            :disabled="adjustingWallet"
                                            @click="submitWalletAdjust"
                                        >
                                            <LoaderCircle v-if="adjustingWallet" class="h-4 w-4 animate-spin" />
                                            <Wallet v-else class="h-4 w-4" />
                                            Xác nhận điều chỉnh
                                        </button>
                                    </div>
                                </article>

                                <article class="rounded-[10px] border border-slate-200 bg-slate-50 p-4">
                                    <h3 class="text-base font-bold text-slate-950">Cấp lại mật khẩu</h3>
                                    <p class="mt-1 text-sm text-slate-500">
                                        Nhập mật khẩu mới cho người dùng. Sau khi cập nhật, các phiên đăng nhập khác sẽ bị đăng xuất.
                                    </p>

                                    <div class="mt-4 grid gap-3">
                                        <label class="grid gap-1">
                                            <span class="text-sm font-medium text-slate-600">Mật khẩu mới</span>
                                            <input
                                                v-model="passwordForm.password"
                                                type="password"
                                                placeholder="Nhập mật khẩu mới"
                                                class="rounded-[8px] border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-[#465fff]"
                                            />
                                        </label>

                                        <label class="grid gap-1">
                                            <span class="text-sm font-medium text-slate-600">Xác nhận mật khẩu</span>
                                            <input
                                                v-model="passwordForm.password_confirmation"
                                                type="password"
                                                placeholder="Nhập lại mật khẩu mới"
                                                class="rounded-[8px] border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-[#465fff]"
                                            />
                                        </label>

                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center gap-2 rounded-[8px] border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-[#465fff] hover:text-[#465fff] disabled:cursor-not-allowed disabled:opacity-60"
                                            :disabled="resettingPassword"
                                            @click="submitPasswordReset"
                                        >
                                            <LoaderCircle v-if="resettingPassword" class="h-4 w-4 animate-spin" />
                                            <KeyRound v-else class="h-4 w-4" />
                                            Cập nhật mật khẩu
                                        </button>
                                    </div>
                                </article>
                            </div>

                            <article class="rounded-[10px] border border-slate-200 bg-white p-4">
                                <h3 class="text-base font-bold text-slate-950">Thông tin tài khoản</h3>
                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                    <div class="rounded-[8px] bg-slate-50 px-4 py-3">
                                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Username</p>
                                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ detail.username || '--' }}</p>
                                    </div>
                                    <div class="rounded-[8px] bg-slate-50 px-4 py-3">
                                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Vai trò</p>
                                        <p class="mt-2 text-sm font-semibold uppercase text-slate-900">{{ detail.role }}</p>
                                    </div>
                                    <div class="rounded-[8px] bg-slate-50 px-4 py-3">
                                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Ngày tạo</p>
                                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ formatDate(detail.created_at, true) }}</p>
                                    </div>
                                    <div class="rounded-[8px] bg-slate-50 px-4 py-3">
                                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">IP gần nhất</p>
                                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ detail.latest_login.ip || '--' }}</p>
                                    </div>
                                    <div class="rounded-[8px] bg-slate-50 px-4 py-3">
                                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Hold balance</p>
                                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ formatCurrency(detail.wallet?.hold_balance) }}</p>
                                    </div>
                                </div>
                            </article>
                        </section>
                    </div>

                    <div v-else-if="activeTab === 'transactions'" class="space-y-4">
                        <div v-if="transactionsState.loading" class="flex items-center gap-2 text-sm text-slate-500">
                            <LoaderCircle class="h-4 w-4 animate-spin" />
                            Đang tải lịch sử dòng tiền...
                        </div>
                        <div v-else class="overflow-hidden rounded-[10px] border border-slate-200">
                            <table class="min-w-full">
                                <thead class="bg-slate-50 text-left text-sm font-semibold text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3">Mã</th>
                                        <th class="px-4 py-3">Loại</th>
                                        <th class="px-4 py-3">Số tiền</th>
                                        <th class="px-4 py-3">Trước / Sau</th>
                                        <th class="px-4 py-3">Nội dung</th>
                                        <th class="px-4 py-3">Thời gian</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="transactionsState.items.length === 0">
                                        <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">Chưa có giao dịch ví.</td>
                                    </tr>
                                    <tr v-for="item in transactionsState.items" :key="item.id" class="border-t border-slate-100 text-sm">
                                        <td class="px-4 py-3 font-semibold text-slate-700">{{ item.code }}</td>
                                        <td class="px-4 py-3 capitalize text-slate-600">{{ item.type }}</td>
                                        <td class="px-4 py-3 font-semibold" :class="item.amount >= 0 ? 'text-emerald-600' : 'text-orange-600'">
                                            {{ formatCurrency(item.amount) }}
                                        </td>
                                        <td class="px-4 py-3 text-slate-600">
                                            {{ formatCurrency(item.balance_before) }} → {{ formatCurrency(item.balance_after) }}
                                        </td>
                                        <td class="px-4 py-3 text-slate-600">{{ item.content || '--' }}</td>
                                        <td class="px-4 py-3 text-slate-600">{{ formatDate(item.created_at, true) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="flex justify-end">
                            <button
                                v-if="transactionsState.meta.current_page < transactionsState.meta.last_page"
                                type="button"
                                class="rounded-[8px] border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600"
                                @click="goToTabPage('transactions', transactionsState.meta.current_page + 1)"
                            >
                                Xem thêm
                            </button>
                        </div>
                    </div>

                    <div v-else-if="activeTab === 'logs'" class="space-y-4">
                        <div v-if="logsState.loading" class="flex items-center gap-2 text-sm text-slate-500">
                            <LoaderCircle class="h-4 w-4 animate-spin" />
                            Đang tải hoạt động...
                        </div>
                        <div v-else class="space-y-3">
                            <article
                                v-for="item in logsState.items"
                                :key="item.id"
                                class="rounded-[10px] border border-slate-200 bg-slate-50 px-4 py-4"
                            >
                                <div class="flex items-start gap-3">
                                    <div class="mt-0.5 flex h-8 w-8 items-center justify-center rounded-[8px] bg-white text-slate-600">
                                        <History class="h-4 w-4" />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-sm font-bold text-slate-950">{{ item.action }}</p>
                                            <span class="text-xs text-slate-400">{{ formatDate(item.created_at, true) }}</span>
                                        </div>
                                        <p class="mt-1 text-sm text-slate-600">{{ item.description || 'Không có mô tả' }}</p>
                                        <p class="mt-1 text-xs text-slate-400">IP: {{ item.ip || '--' }}</p>
                                    </div>
                                </div>
                            </article>
                            <div
                                v-if="logsState.items.length === 0"
                                class="rounded-[10px] border border-slate-200 px-4 py-10 text-center text-sm text-slate-500"
                            >
                                Chưa có lịch sử hoạt động.
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </template>
    </div>
</template>
