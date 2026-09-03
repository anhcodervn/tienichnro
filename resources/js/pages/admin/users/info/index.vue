<script setup lang="ts">
import {
    adminUserService,
    type AdminPaginationMeta,
    type AdminUserDetailResponse,
    type AdminUserGlobalPackagePreview,
    type AdminUserLog,
    type AdminUserPackagePrice,
    type AdminUserPricingResponse,
    type AdminUserWalletTransaction,
} from '@/services/admin-user.service';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import {
    ArrowLeft,
    BadgePercent,
    CheckCheck,
    History,
    KeyRound,
    ListChecks,
    LoaderCircle,
    Minus,
    Plus,
    RotateCcw,
    Save,
    Wallet,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';

type TabKey = 'overview' | 'pricing' | 'transactions' | 'logs';

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
const pricesLoading = ref(false);
const pricesLoaded = ref(false);
const savingPriceId = ref<number | null>(null);
const resettingPriceId = ref<number | null>(null);
const savingGlobalPriceId = ref<number | null>(null);
const resettingGlobalPriceId = ref<number | null>(null);
const detail = ref<AdminUserDetailResponse | null>(null);
const priceRows = ref<AdminUserPackagePrice[]>([]);
const globalPackageRows = ref<AdminUserGlobalPackagePreview[]>([]);
const selectedPricingScope = ref('global');
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
    { key: 'pricing' as const, label: 'Chiết khấu' },
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

const gamePricingScopes = computed(() => {
    const games = new Map<string, number>();

    priceRows.value.forEach((row) => {
        const game = row.game || 'Chưa phân loại';
        games.set(game, (games.get(game) ?? 0) + 1);
    });

    return Array.from(games, ([label, count]) => ({ value: `game:${label}`, label, count })).sort((left, right) =>
        left.label.localeCompare(right.label, 'vi'),
    );
});

const selectedGameName = computed(() => selectedPricingScope.value.replace(/^game:/, ''));
const selectedGamePriceRows = computed(() => priceRows.value.filter((row) => (row.game || 'Chưa phân loại') === selectedGameName.value));

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
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
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

const loadPrices = async (): Promise<void> => {
    pricesLoading.value = true;

    try {
        applyPricingResponse(await adminUserService.prices(userId));
        pricesLoaded.value = true;
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        pricesLoading.value = false;
    }
};

const applyPricingResponse = (response: AdminUserPricingResponse): void => {
    priceRows.value = response.prices;
    globalPackageRows.value = response.global_packages;

    if (selectedPricingScope.value !== 'global' && !gamePricingScopes.value.some((scope) => scope.value === selectedPricingScope.value)) {
        selectedPricingScope.value = 'global';
    }
};

const savePrice = async (row: AdminUserPackagePrice): Promise<void> => {
    savingPriceId.value = row.package_id;

    try {
        applyPricingResponse(
            await adminUserService.updatePrice(userId, row.package_id, {
                pricing_mode: row.pricing_mode,
                discount_percent: row.pricing_mode === 'discount' ? Number(row.discount_percent) : null,
                fixed_price: row.pricing_mode === 'fixed' ? Number(row.fixed_price) : null,
                minimum_profit: Number(row.minimum_profit),
                is_active: row.is_active,
            }),
        );
        handleSuccessResponse({ data: { status: true, message: 'Đã lưu giá riêng cho thành viên.' } });
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        savingPriceId.value = null;
    }
};

const resetPrice = async (row: AdminUserPackagePrice): Promise<void> => {
    resettingPriceId.value = row.package_id;

    try {
        applyPricingResponse(await adminUserService.deletePrice(userId, row.package_id));
        handleSuccessResponse({ data: { status: true, message: 'Đã đưa gói về giá mặc định của website.' } });
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        resettingPriceId.value = null;
    }
};

const saveGlobalPrice = async (row: AdminUserGlobalPackagePreview): Promise<void> => {
    savingGlobalPriceId.value = row.id;

    try {
        applyPricingResponse(
            await adminUserService.updateGlobalPrice(userId, row.id, {
                pricing_mode: row.pricing_mode,
                discount_percent: row.pricing_mode === 'discount' ? Number(row.discount_percent) : null,
                fixed_price: row.pricing_mode === 'fixed' ? Number(row.fixed_price) : null,
                minimum_profit: Number(row.minimum_profit),
                is_active: row.is_active,
            }),
        );
        handleSuccessResponse({ data: { status: true, message: 'Đã lưu chiết khấu Global cho thành viên.' } });
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        savingGlobalPriceId.value = null;
    }
};

const resetGlobalPrice = async (row: AdminUserGlobalPackagePreview): Promise<void> => {
    resettingGlobalPriceId.value = row.id;

    try {
        applyPricingResponse(await adminUserService.deleteGlobalPrice(userId, row.id));
        handleSuccessResponse({ data: { status: true, message: 'Đã xóa chiết khấu Global của thành viên.' } });
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        resettingGlobalPriceId.value = null;
    }
};

const openTab = async (tab: TabKey): Promise<void> => {
    activeTab.value = tab;

    if (tab === 'pricing' && !pricesLoaded.value) {
        await loadPrices();
    }

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

onMounted(loadDetail);
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

                    <div v-else-if="activeTab === 'pricing'" class="space-y-4">
                        <div class="rounded-[10px] border border-indigo-200 bg-indigo-50 px-4 py-4">
                            <div class="flex items-start gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[8px] bg-white text-[#465fff] shadow-sm">
                                    <BadgePercent class="h-4 w-4" />
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-950">Giá riêng theo từng thành viên</h3>
                                    <p class="mt-1 text-sm leading-6 text-slate-600">
                                        Giá này áp dụng khi thành viên đặt trên website hoặc qua API. Nếu thành viên là tài khoản thanh toán của
                                        website đại lý, mức giá này trở thành giá vốn cho tất cả website thuộc thành viên đó.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div v-if="pricesLoading" class="flex items-center justify-center gap-2 py-12 text-sm text-slate-500">
                            <LoaderCircle class="h-4 w-4 animate-spin" />
                            Đang tải bảng giá thành viên...
                        </div>

                        <div v-else class="grid gap-5">
                            <nav class="rounded-[10px] border-2 border-slate-300 bg-white p-3" aria-label="Phạm vi bảng giá">
                                <p class="px-1 pb-2 text-xs font-black uppercase tracking-[0.16em] text-slate-500">Chọn phạm vi set giá</p>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-[8px] border-2 px-3.5 py-2.5 text-sm font-bold transition"
                                        :class="
                                            selectedPricingScope === 'global'
                                                ? 'border-indigo-600 bg-indigo-600 text-white shadow-sm'
                                                : 'border-slate-300 bg-white text-slate-700 hover:border-indigo-300 hover:text-indigo-700'
                                        "
                                        @click="selectedPricingScope = 'global'"
                                    >
                                        <BadgePercent class="h-4 w-4" />Global
                                        <span class="rounded bg-white/20 px-1.5 py-0.5 text-[11px]">Tất cả game</span>
                                    </button>
                                    <button
                                        v-for="scope in gamePricingScopes"
                                        :key="scope.value"
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-[8px] border-2 px-3.5 py-2.5 text-sm font-bold transition"
                                        :class="
                                            selectedPricingScope === scope.value
                                                ? 'border-slate-900 bg-slate-900 text-white shadow-sm'
                                                : 'border-slate-300 bg-white text-slate-700 hover:border-slate-500'
                                        "
                                        @click="selectedPricingScope = scope.value"
                                    >
                                        {{ scope.label }}
                                        <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[11px] text-slate-600">{{ scope.count }} gói</span>
                                    </button>
                                </div>
                            </nav>

                            <section
                                v-if="selectedPricingScope === 'global'"
                                class="overflow-hidden rounded-[10px] border-2 border-indigo-200 bg-white"
                            >
                                <div class="border-b border-indigo-200 bg-indigo-50 px-4 py-4">
                                    <h3 class="font-black text-indigo-950">Giá riêng từng gói Global</h3>
                                    <p class="mt-1 text-sm leading-6 text-indigo-800">
                                        Mỗi gói Global có mức giảm hoặc giá cố định riêng cho thành viên này. Các game sử dụng đúng gói Global sẽ tự
                                        nhận mức giá tương ứng; giá riêng tại game vẫn được ưu tiên cao hơn.
                                    </p>
                                </div>
                                <div>
                                    <div class="flex flex-wrap items-center justify-between gap-2 bg-slate-50 px-4 py-3">
                                        <div>
                                            <h4 class="text-sm font-black text-slate-900">Các gói Global đang hoạt động</h4>
                                            <p class="mt-1 text-xs text-slate-500">Chỉnh riêng từng dòng giống bảng giá gói thường.</p>
                                        </div>
                                        <span class="rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700">
                                            {{ globalPackageRows.length }} gói
                                        </span>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full min-w-[1120px]">
                                            <thead
                                                class="border-y border-slate-200 bg-white text-left text-xs font-bold uppercase tracking-wide text-slate-500"
                                            >
                                                <tr>
                                                    <th class="px-4 py-3">Gói Global</th>
                                                    <th class="px-4 py-3">Mệnh giá</th>
                                                    <th class="px-4 py-3">Giá chuẩn</th>
                                                    <th class="px-4 py-3">Cách tính</th>
                                                    <th class="px-4 py-3">Mức giá</th>
                                                    <th class="px-4 py-3">Lãi tối thiểu</th>
                                                    <th class="px-4 py-3">Giá thành viên</th>
                                                    <th class="px-4 py-3 text-center">Áp dụng</th>
                                                    <th class="px-4 py-3 text-right">Thao tác</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-if="globalPackageRows.length === 0">
                                                    <td colspan="9" class="px-4 py-10 text-center text-sm text-slate-500">
                                                        Chưa có gói Global đang hoạt động. Hãy kiểm tra trạng thái gói trong quản lý nạp game.
                                                    </td>
                                                </tr>
                                                <tr
                                                    v-for="row in globalPackageRows"
                                                    :key="row.id"
                                                    class="border-t border-slate-200 align-top text-sm"
                                                >
                                                    <td class="px-4 py-3 font-bold text-slate-900">{{ row.name }}</td>
                                                    <td class="px-4 py-3 font-semibold text-slate-700">{{ formatCurrency(row.denomination) }}</td>
                                                    <td class="px-4 py-3 font-semibold text-slate-700">{{ formatCurrency(row.base_price) }}</td>
                                                    <td class="px-4 py-3">
                                                        <select
                                                            v-model="row.pricing_mode"
                                                            class="w-32 rounded-[8px] border-2 border-slate-300 bg-white px-2.5 py-2 text-sm font-medium text-slate-700 outline-none focus:border-[#465fff]"
                                                        >
                                                            <option value="discount">Giảm theo %</option>
                                                            <option value="fixed">Giá cố định</option>
                                                        </select>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <div v-if="row.pricing_mode === 'discount'" class="relative w-28">
                                                            <input
                                                                v-model.number="row.discount_percent"
                                                                type="number"
                                                                min="0"
                                                                max="100"
                                                                step="0.01"
                                                                class="w-full rounded-[8px] border-2 border-slate-300 bg-white px-2.5 py-2 pr-7 text-sm text-slate-700 outline-none focus:border-[#465fff]"
                                                            />
                                                            <span class="pointer-events-none absolute right-3 top-2 text-sm text-slate-400">%</span>
                                                        </div>
                                                        <input
                                                            v-else
                                                            v-model.number="row.fixed_price"
                                                            type="number"
                                                            min="0"
                                                            class="w-32 rounded-[8px] border-2 border-slate-300 bg-white px-2.5 py-2 text-sm text-slate-700 outline-none focus:border-[#465fff]"
                                                        />
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <input
                                                            v-model.number="row.minimum_profit"
                                                            type="number"
                                                            min="0"
                                                            class="w-28 rounded-[8px] border-2 border-slate-300 bg-white px-2.5 py-2 text-sm text-slate-700 outline-none focus:border-[#465fff]"
                                                        />
                                                    </td>
                                                    <td class="px-4 py-3 font-black text-indigo-600">{{ formatCurrency(row.member_price) }}</td>
                                                    <td class="px-4 py-3 text-center">
                                                        <input
                                                            v-model="row.is_active"
                                                            type="checkbox"
                                                            class="h-5 w-5 rounded border-2 border-slate-400 text-[#465fff] focus:ring-[#465fff]"
                                                        />
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <div class="flex justify-end gap-2">
                                                            <button
                                                                type="button"
                                                                class="inline-flex items-center gap-1.5 rounded-[8px] bg-indigo-600 px-3 py-2 text-xs font-bold text-white disabled:opacity-60"
                                                                :disabled="savingGlobalPriceId === row.id || resettingGlobalPriceId === row.id"
                                                                @click="saveGlobalPrice(row)"
                                                            >
                                                                <LoaderCircle
                                                                    v-if="savingGlobalPriceId === row.id"
                                                                    class="h-3.5 w-3.5 animate-spin"
                                                                /><Save v-else class="h-3.5 w-3.5" />Lưu
                                                            </button>
                                                            <button
                                                                type="button"
                                                                title="Xóa giá riêng"
                                                                class="inline-flex items-center justify-center rounded-[8px] border-2 border-slate-300 bg-white p-2 text-slate-600 hover:border-orange-300 hover:text-orange-600 disabled:opacity-60"
                                                                :disabled="savingGlobalPriceId === row.id || resettingGlobalPriceId === row.id"
                                                                @click="resetGlobalPrice(row)"
                                                            >
                                                                <LoaderCircle
                                                                    v-if="resettingGlobalPriceId === row.id"
                                                                    class="h-3.5 w-3.5 animate-spin"
                                                                /><RotateCcw v-else class="h-3.5 w-3.5" />
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </section>

                            <section v-else class="overflow-hidden rounded-[10px] border-2 border-slate-300 bg-white">
                                <div class="border-b border-slate-200 bg-slate-50 px-4 py-4">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Giá riêng theo game</p>
                                            <h3 class="mt-1 text-lg font-black text-slate-950">{{ selectedGameName }}</h3>
                                        </div>
                                        <span class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-bold text-slate-600">
                                            {{ selectedGamePriceRows.length }} gói nạp
                                        </span>
                                    </div>
                                    <p class="mt-2 text-sm text-slate-600">Chỉ lưu tại đây khi muốn ghi đè mức Global cho riêng game hoặc gói này.</p>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full min-w-[1080px]">
                                        <thead class="bg-slate-100 text-left text-xs font-bold uppercase tracking-wide text-slate-600">
                                            <tr>
                                                <th class="px-3 py-3">Gói nạp</th>
                                                <th class="px-3 py-3">Mệnh giá</th>
                                                <th class="px-3 py-3">Giá chuẩn</th>
                                                <th class="px-3 py-3">Cách tính</th>
                                                <th class="px-3 py-3">Mức giá</th>
                                                <th class="px-3 py-3">Lãi tối thiểu</th>
                                                <th class="px-3 py-3">Giá thành viên</th>
                                                <th class="px-3 py-3 text-center">Áp dụng</th>
                                                <th class="px-3 py-3 text-right">Thao tác</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-if="selectedGamePriceRows.length === 0">
                                                <td colspan="9" class="px-4 py-12 text-center text-sm text-slate-500">
                                                    Chưa có gói nạp đang hoạt động.
                                                </td>
                                            </tr>
                                            <tr
                                                v-for="row in selectedGamePriceRows"
                                                :key="row.package_id"
                                                class="border-t border-slate-200 align-top text-sm"
                                            >
                                                <td class="px-3 py-3">
                                                    <p class="font-bold text-slate-900">{{ row.package }}</p>
                                                </td>
                                                <td class="whitespace-nowrap px-3 py-3 font-semibold text-slate-700">
                                                    {{ formatCurrency(row.denomination) }}
                                                </td>
                                                <td class="whitespace-nowrap px-3 py-3 font-semibold text-slate-700">
                                                    {{ formatCurrency(row.base_price) }}
                                                </td>
                                                <td class="px-3 py-3">
                                                    <select
                                                        v-model="row.pricing_mode"
                                                        class="w-32 rounded-[8px] border-2 border-slate-300 bg-white px-2.5 py-2 text-sm font-medium text-slate-700 outline-none focus:border-[#465fff]"
                                                    >
                                                        <option value="discount">Giảm theo %</option>
                                                        <option value="fixed">Giá cố định</option>
                                                    </select>
                                                </td>
                                                <td class="px-3 py-3">
                                                    <div v-if="row.pricing_mode === 'discount'" class="relative w-28">
                                                        <input
                                                            v-model.number="row.discount_percent"
                                                            type="number"
                                                            min="0"
                                                            max="100"
                                                            step="0.01"
                                                            class="w-full rounded-[8px] border-2 border-slate-300 bg-white px-2.5 py-2 pr-7 text-sm text-slate-700 outline-none focus:border-[#465fff]"
                                                        />
                                                        <span class="pointer-events-none absolute right-3 top-2 text-sm text-slate-400">%</span>
                                                    </div>
                                                    <input
                                                        v-else
                                                        v-model.number="row.fixed_price"
                                                        type="number"
                                                        min="0"
                                                        class="w-32 rounded-[8px] border-2 border-slate-300 bg-white px-2.5 py-2 text-sm text-slate-700 outline-none focus:border-[#465fff]"
                                                    />
                                                </td>
                                                <td class="px-3 py-3">
                                                    <input
                                                        v-model.number="row.minimum_profit"
                                                        type="number"
                                                        min="0"
                                                        class="w-28 rounded-[8px] border-2 border-slate-300 bg-white px-2.5 py-2 text-sm text-slate-700 outline-none focus:border-[#465fff]"
                                                    />
                                                </td>
                                                <td class="whitespace-nowrap px-3 py-3">
                                                    <p class="font-black text-[#465fff]">{{ formatCurrency(row.member_price) }}</p>
                                                    <span
                                                        v-if="row.pricing_source === 'global'"
                                                        class="mt-1 inline-flex rounded bg-indigo-50 px-2 py-0.5 text-[11px] font-bold text-indigo-700"
                                                        >Đang theo Global</span
                                                    >
                                                    <p v-if="row.discount_amount > 0" class="mt-1 text-xs font-semibold text-emerald-600">
                                                        Giảm {{ formatCurrency(row.discount_amount) }}
                                                    </p>
                                                </td>
                                                <td class="px-3 py-3 text-center">
                                                    <input
                                                        v-model="row.is_active"
                                                        type="checkbox"
                                                        class="h-5 w-5 rounded border-2 border-slate-400 text-[#465fff] focus:ring-[#465fff]"
                                                    />
                                                </td>
                                                <td class="px-3 py-3">
                                                    <div class="flex justify-end gap-2">
                                                        <button
                                                            type="button"
                                                            class="inline-flex items-center gap-1.5 rounded-[8px] bg-[#465fff] px-3 py-2 text-xs font-bold text-white disabled:cursor-not-allowed disabled:opacity-60"
                                                            :disabled="savingPriceId === row.package_id || resettingPriceId === row.package_id"
                                                            @click="savePrice(row)"
                                                        >
                                                            <LoaderCircle v-if="savingPriceId === row.package_id" class="h-3.5 w-3.5 animate-spin" />
                                                            <Save v-else class="h-3.5 w-3.5" />
                                                            Lưu
                                                        </button>
                                                        <button
                                                            type="button"
                                                            title="Xóa giá riêng"
                                                            class="inline-flex items-center justify-center rounded-[8px] border-2 border-slate-300 bg-white p-2 text-slate-600 hover:border-orange-300 hover:text-orange-600 disabled:cursor-not-allowed disabled:opacity-60"
                                                            :disabled="savingPriceId === row.package_id || resettingPriceId === row.package_id"
                                                            @click="resetPrice(row)"
                                                        >
                                                            <LoaderCircle
                                                                v-if="resettingPriceId === row.package_id"
                                                                class="h-3.5 w-3.5 animate-spin"
                                                            />
                                                            <RotateCcw v-else class="h-3.5 w-3.5" />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        </div>
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
