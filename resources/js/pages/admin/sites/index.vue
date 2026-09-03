<script setup lang="ts">
import DataTable from '@/components/shared/DataTable/index.vue';
import Modal from '@/components/shared/Modal/index.vue';
import { adminTenantService } from '@/services/admin-tenant.service';
import { adminUserService, type AdminUserListItem } from '@/services/admin-user.service';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { Globe2, LoaderCircle, Pencil, Plus, Power, RotateCcw, Search, ShieldCheck } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

type SiteRow = {
    id: number;
    name: string;
    slug: string;
    domain: string | null;
    status: 'active' | 'suspended';
    is_main: boolean;
    allow_below_cost: boolean;
    billing_user: { id: number; username: string; email: string } | null;
    billing_balance: number | null;
    users_count: number;
    orders_count: number;
    orders_today_count: number;
    orders_month_count: number;
    created_at: string | null;
};

type SiteForm = {
    name: string;
    slug: string;
    domain: string;
    billing_user_id: string;
    status: 'active' | 'suspended';
    allow_below_cost: boolean;
};

type ApiException = {
    response?: { data?: { message?: string; errors?: Record<string, string[]> } };
};

const route = useRoute();
const router = useRouter();
const sites = ref<SiteRow[]>([]);
const loading = ref(false);
const saving = ref(false);
const modalOpen = ref(false);
const editingSite = ref<SiteRow | null>(null);
const formError = ref('');
const fieldErrors = ref<Record<string, string[]>>({});
const billingUserSearch = ref('');
const billingUsers = ref<AdminUserListItem[]>([]);
const billingUsersLoading = ref(false);
const billingUserResultsOpen = ref(false);
let billingUserSearchTimer: ReturnType<typeof setTimeout> | undefined;
const filters = reactive({
    search: typeof route.query.search === 'string' ? route.query.search : '',
    status: typeof route.query.status === 'string' ? route.query.status : '',
    site_type: typeof route.query.site_type === 'string' ? route.query.site_type : '',
    per_page: typeof route.query.per_page === 'string' ? route.query.per_page : '20',
    page: Math.max(Number(route.query.page || 1), 1),
});
const pagination = reactive({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
    from: null as number | null,
    to: null as number | null,
});
const form = reactive<SiteForm>({
    name: '',
    slug: '',
    domain: '',
    billing_user_id: '',
    status: 'active',
    allow_below_cost: false,
});
const formInputClass =
    'mt-1.5 min-h-11 w-full rounded-xl border-2 border-slate-300 bg-slate-50 px-3 py-2 font-normal text-slate-950 outline-none transition placeholder:text-slate-400 hover:border-slate-400 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500';
const columns = [
    { id: 'site', accessorFn: (site: SiteRow) => site.name, header: 'Website' },
    { id: 'domain', accessorFn: (site: SiteRow) => site.domain, header: 'Domain' },
    { id: 'billing', accessorFn: (site: SiteRow) => site.billing_user?.username, header: 'Tài khoản giá vốn' },
    { id: 'balance', accessorFn: (site: SiteRow) => site.billing_balance, header: 'Số dư NapCarot' },
    { id: 'status', accessorFn: (site: SiteRow) => site.status, header: 'Trạng thái' },
    { id: 'statistics', accessorFn: (site: SiteRow) => site.orders_today_count, header: 'Đơn hàng' },
    { id: 'created_at', accessorFn: (site: SiteRow) => site.created_at, header: 'Ngày tạo' },
    { id: 'actions', accessorFn: (site: SiteRow) => site.id, header: 'Thao tác' },
];

const isEditing = computed(() => editingSite.value !== null);
const modalTitle = computed(() => (isEditing.value ? `Cập nhật ${editingSite.value?.name}` : 'Tạo website đại lý'));

const resetForm = (): void => {
    editingSite.value = null;
    formError.value = '';
    fieldErrors.value = {};
    Object.assign(form, {
        name: '',
        slug: '',
        domain: '',
        billing_user_id: '',
        status: 'active',
        allow_below_cost: false,
    });
    billingUserSearch.value = '';
    billingUsers.value = [];
    billingUserResultsOpen.value = false;
};

const billingUserLabel = (user: Pick<AdminUserListItem, 'id' | 'username' | 'email'>): string =>
    `#${user.id} · ${user.username || user.email || 'Tài khoản NapCarot'}`;

const loadBillingUsers = async (): Promise<void> => {
    billingUsersLoading.value = true;
    try {
        const payload = await adminUserService.list({
            search: billingUserSearch.value.trim() || undefined,
            role: 'user',
            status: 'active',
            per_page: 10,
        });
        billingUsers.value = payload.data;
        billingUserResultsOpen.value = true;
    } catch (error) {
        billingUsers.value = [];
        handleErrorResponse(error as Parameters<typeof handleErrorResponse>[0]);
    } finally {
        billingUsersLoading.value = false;
    }
};

const handleBillingUserInput = (): void => {
    form.billing_user_id = '';
    billingUserResultsOpen.value = true;
    clearTimeout(billingUserSearchTimer);
    billingUserSearchTimer = setTimeout(loadBillingUsers, 300);
};

const selectBillingUser = (user: AdminUserListItem): void => {
    form.billing_user_id = String(user.id);
    billingUserSearch.value = billingUserLabel(user);
    billingUserResultsOpen.value = false;
};

const load = async (): Promise<void> => {
    loading.value = true;
    try {
        const response = await adminTenantService.list({
            search: filters.search || undefined,
            status: filters.status || undefined,
            site_type: filters.site_type || undefined,
            per_page: Number(filters.per_page),
            page: filters.page,
        });
        const payload = response.data.data;
        sites.value = payload.sites ?? [];
        Object.assign(pagination, {
            current_page: Number(payload.meta?.current_page || 1),
            last_page: Number(payload.meta?.last_page || 1),
            per_page: Number(payload.meta?.per_page || filters.per_page),
            total: Number(payload.meta?.total || 0),
            from: payload.meta?.from ?? null,
            to: payload.meta?.to ?? null,
        });
    } catch (error) {
        sites.value = [];
        handleErrorResponse(error as Parameters<typeof handleErrorResponse>[0]);
    } finally {
        loading.value = false;
    }
};

const syncQuery = async (): Promise<void> => {
    const query: Record<string, string | number> = {};
    if (filters.search.trim()) query.search = filters.search.trim();
    if (filters.status) query.status = filters.status;
    if (filters.site_type) query.site_type = filters.site_type;
    if (filters.per_page !== '20') query.per_page = filters.per_page;
    if (filters.page > 1) query.page = filters.page;
    await router.replace({ query });
};

const applyFilters = async (): Promise<void> => {
    filters.page = 1;
    await syncQuery();
    await load();
};

const clearFilters = async (): Promise<void> => {
    Object.assign(filters, { search: '', status: '', site_type: '', per_page: '20', page: 1 });
    await syncQuery();
    await load();
};

const changePage = async (page: number): Promise<void> => {
    if (page < 1 || page > pagination.last_page || page === pagination.current_page || loading.value) return;
    filters.page = page;
    await syncQuery();
    await load();
};

const openCreateModal = (): void => {
    resetForm();
    modalOpen.value = true;
    void loadBillingUsers();
};

const openEditModal = (site: SiteRow): void => {
    resetForm();
    editingSite.value = site;
    Object.assign(form, {
        name: site.name,
        slug: site.slug,
        domain: site.domain ?? '',
        billing_user_id: site.billing_user?.id ? String(site.billing_user.id) : '',
        status: site.status,
        allow_below_cost: site.allow_below_cost,
    });
    if (site.billing_user) {
        billingUserSearch.value = billingUserLabel(site.billing_user);
    }
    modalOpen.value = true;
};

const submit = async (): Promise<void> => {
    saving.value = true;
    formError.value = '';
    fieldErrors.value = {};

    try {
        let response;
        if (editingSite.value) {
            const payload: Record<string, unknown> = { name: form.name };
            if (!editingSite.value.is_main) {
                Object.assign(payload, {
                    slug: form.slug,
                    domain: form.domain,
                    billing_user_id: Number(form.billing_user_id),
                    status: form.status,
                    allow_below_cost: form.allow_below_cost,
                });
            }
            response = await adminTenantService.update(editingSite.value.id, payload);
        } else {
            response = await adminTenantService.create({
                name: form.name,
                slug: form.slug,
                domain: form.domain,
                billing_user_id: Number(form.billing_user_id),
                allow_below_cost: form.allow_below_cost,
            });
            filters.page = 1;
        }

        modalOpen.value = false;
        handleSuccessResponse(response);
        await syncQuery();
        await load();
    } catch (exception) {
        const payload = (exception as ApiException).response?.data;
        formError.value = payload?.message || 'Không thể lưu thông tin website.';
        fieldErrors.value = payload?.errors ?? {};
    } finally {
        saving.value = false;
    }
};

const toggleStatus = async (site: SiteRow): Promise<void> => {
    if (site.is_main) return;
    try {
        const response = await adminTenantService.update(site.id, { status: site.status === 'active' ? 'suspended' : 'active' });
        handleSuccessResponse(response);
        await load();
    } catch (error) {
        handleErrorResponse(error as Parameters<typeof handleErrorResponse>[0]);
    }
};

const formatDate = (value: string | null): string =>
    value ? new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : '—';

const formatMoney = (value: number | null): string => (value === null ? '—' : `${new Intl.NumberFormat('vi-VN').format(value)}đ`);

watch(modalOpen, (isOpen) => {
    if (!isOpen && !saving.value) resetForm();
});

onMounted(load);
</script>

<template>
    <section class="space-y-5">
        <header class="flex flex-col gap-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600"
                    ><Globe2 class="h-6 w-6"
                /></span>
                <div>
                    <p class="text-sm font-semibold text-blue-600">Hệ thống website mẹ – con</p>
                    <h1 class="text-2xl font-black text-slate-950">Website đại lý</h1>
                    <p class="mt-1 text-sm text-slate-500">Quản lý domain, tài khoản giá vốn, trạng thái và dữ liệu của từng website.</p>
                </div>
            </div>
            <button
                type="button"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100"
                @click="openCreateModal"
            >
                <Plus class="h-4 w-4" />Thêm website
            </button>
        </header>

        <form class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" @submit.prevent="applyFilters">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_180px_180px_130px_auto] xl:items-end">
                <label class="text-sm font-semibold text-slate-700">
                    Tìm kiếm
                    <span class="relative mt-1.5 block">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="filters.search"
                            class="min-h-11 w-full rounded-xl border-2 border-slate-300 bg-slate-50 py-2 pl-10 pr-3 font-normal outline-none transition focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                            placeholder="Tên, slug, domain, tài khoản..."
                        />
                    </span>
                </label>
                <label class="text-sm font-semibold text-slate-700"
                    >Loại website<select v-model="filters.site_type" :class="formInputClass">
                        <option value="">Tất cả</option>
                        <option value="main">Website mẹ</option>
                        <option value="child">Website con</option>
                    </select></label
                >
                <label class="text-sm font-semibold text-slate-700"
                    >Trạng thái<select v-model="filters.status" :class="formInputClass">
                        <option value="">Tất cả</option>
                        <option value="active">Hoạt động</option>
                        <option value="suspended">Tạm ngừng</option>
                    </select></label
                >
                <label class="text-sm font-semibold text-slate-700"
                    >Số dòng<select v-model="filters.per_page" :class="formInputClass">
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select></label
                >
                <div class="flex gap-2 md:col-span-2 xl:col-span-1">
                    <button type="submit" class="min-h-11 flex-1 rounded-xl bg-slate-950 px-4 text-sm font-bold text-white hover:bg-slate-800">
                        Lọc
                    </button>
                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center justify-center rounded-xl border-2 border-slate-300 bg-white px-3 text-slate-600 hover:bg-slate-50"
                        title="Xóa bộ lọc"
                        @click="clearFilters"
                    >
                        <RotateCcw class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </form>

        <section class="space-y-3">
            <DataTable
                :data="sites"
                :columns="columns"
                :loading="loading"
                :current-page="pagination.current_page"
                :total-pages="pagination.last_page"
                :go-to-page="changePage"
                class-custom="min-w-[1260px]"
                empty-text="Không tìm thấy website phù hợp."
            >
                <template #site="{ row }"
                    ><div class="flex min-w-[180px] items-start gap-3">
                        <span class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600"
                            ><ShieldCheck class="h-4 w-4"
                        /></span>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <strong class="text-slate-950">{{ row.name }}</strong
                                ><span v-if="row.is_main" class="rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-bold text-blue-700"
                                    >Website mẹ</span
                                >
                            </div>
                            <p class="mt-1 font-mono text-xs text-slate-500">{{ row.slug }}</p>
                        </div>
                    </div></template
                >
                <template #domain="{ row }"
                    ><span class="font-mono text-xs font-semibold text-slate-700">{{ row.domain || '—' }}</span></template
                >
                <template #billing="{ row }"
                    ><div v-if="row.billing_user" class="min-w-[160px]">
                        <p class="font-semibold text-slate-900">#{{ row.billing_user.id }} · {{ row.billing_user.username }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">{{ row.billing_user.email }}</p>
                    </div>
                    <span v-else class="text-sm text-slate-400">Website chính</span></template
                >
                <template #balance="{ row }"
                    ><div v-if="!row.is_main" class="min-w-[130px]">
                        <strong class="text-sm text-emerald-700">{{ formatMoney(row.billing_balance) }}</strong>
                        <p class="mt-0.5 text-[11px] text-slate-500">Ví trên napcarot.com</p>
                    </div>
                    <span v-else class="text-xs text-slate-400">Ví hệ thống</span></template
                >
                <template #status="{ row }"
                    ><div class="grid gap-1.5">
                        <span
                            class="w-fit rounded-full px-2.5 py-1 text-xs font-bold"
                            :class="row.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
                            >{{ row.status === 'active' ? 'Hoạt động' : 'Tạm ngừng' }}</span
                        ><span v-if="!row.is_main" class="text-[11px] text-slate-500">{{
                            row.allow_below_cost ? 'Cho bán dưới giá vốn' : 'Chặn bán dưới giá vốn'
                        }}</span>
                    </div></template
                >
                <template #statistics="{ row }"
                    ><div class="min-w-[145px] text-xs text-slate-600">
                        <p>
                            Hôm nay: <strong class="text-blue-700">{{ row.orders_today_count || 0 }}</strong> đơn
                        </p>
                        <p class="mt-1">
                            Tháng này: <strong class="text-slate-900">{{ row.orders_month_count || 0 }}</strong> đơn
                        </p>
                        <p class="mt-1 text-[11px] text-slate-500">
                            Tổng {{ row.orders_count || 0 }} đơn · {{ row.users_count || 0 }} người dùng
                        </p>
                    </div></template
                >
                <template #created_at="{ row }"
                    ><span class="whitespace-nowrap text-xs text-slate-600">{{ formatDate(row.created_at) }}</span></template
                >
                <template #actions="{ row }"
                    ><div class="flex min-w-[150px] justify-end gap-2">
                        <button
                            type="button"
                            class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700"
                            @click="openEditModal(row)"
                        >
                            <Pencil class="h-3.5 w-3.5" />Sửa</button
                        ><button
                            v-if="!row.is_main"
                            type="button"
                            class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border px-3 text-xs font-bold"
                            :class="
                                row.status === 'active'
                                    ? 'border-rose-200 text-rose-600 hover:bg-rose-50'
                                    : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50'
                            "
                            @click="toggleStatus(row)"
                        >
                            <Power class="h-3.5 w-3.5" />{{ row.status === 'active' ? 'Tạm ngừng' : 'Kích hoạt' }}
                        </button>
                    </div></template
                >
            </DataTable>
            <p class="text-sm text-slate-500">
                Hiển thị {{ pagination.from || 0 }}–{{ pagination.to || 0 }} trong tổng số {{ pagination.total }} website
            </p>
        </section>

        <Modal v-model="modalOpen" panel-class="max-w-4xl">
            <template #header
                ><div class="border-b border-slate-200 px-5 py-4 pr-16 sm:px-6">
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-blue-600">{{ isEditing ? 'Chỉnh sửa website' : 'Website mới' }}</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">{{ modalTitle }}</h2>
                </div></template
            >
            <form id="tenant-site-form" class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6" @submit.prevent="submit">
                <div v-if="formError" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700 sm:col-span-2">
                    {{ formError }}
                </div>
                <label class="text-sm font-semibold text-slate-700"
                    >Tên website<input v-model.trim="form.name" required :class="formInputClass" /><span
                        v-if="fieldErrors.name"
                        class="mt-1 block text-xs text-rose-600"
                        >{{ fieldErrors.name[0] }}</span
                    ></label
                >
                <template v-if="!editingSite?.is_main">
                    <label class="text-sm font-semibold text-slate-700"
                        >Mã website<input v-model.trim="form.slug" required :class="formInputClass" placeholder="dailycarot" /><span
                            v-if="fieldErrors.slug"
                            class="mt-1 block text-xs text-rose-600"
                            >{{ fieldErrors.slug[0] }}</span
                        ></label
                    >
                    <label class="text-sm font-semibold text-slate-700"
                        >Domain<input v-model.trim="form.domain" required :class="formInputClass" placeholder="dailycarot.vn" /><span
                            v-if="fieldErrors.domain"
                            class="mt-1 block text-xs text-rose-600"
                            >{{ fieldErrors.domain[0] }}</span
                        ></label
                    >
                    <div class="relative text-sm font-semibold text-slate-700 sm:col-span-2">
                        <label for="billing-user-search">Tài khoản đăng ký trên NapCarot</label>
                        <span class="relative mt-1.5 block">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                id="billing-user-search"
                                v-model="billingUserSearch"
                                required
                                type="search"
                                class="min-h-11 w-full rounded-xl border-2 border-slate-300 bg-slate-50 py-2 pl-10 pr-10 font-normal text-slate-950 outline-none transition placeholder:text-slate-400 hover:border-slate-400 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                                placeholder="Nhập ID, username, email hoặc số điện thoại"
                                autocomplete="off"
                                @input="handleBillingUserInput"
                                @focus="loadBillingUsers"
                                @blur="billingUserResultsOpen = false"
                            />
                            <LoaderCircle
                                v-if="billingUsersLoading"
                                class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-blue-600"
                            />
                        </span>
                        <div
                            v-if="billingUserResultsOpen"
                            class="absolute z-30 mt-2 max-h-64 w-full overflow-y-auto rounded-xl border-2 border-slate-300 bg-white p-1.5 shadow-xl"
                        >
                            <button
                                v-for="user in billingUsers"
                                :key="user.id"
                                type="button"
                                class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-left hover:bg-blue-50"
                                @mousedown.prevent="selectBillingUser(user)"
                            >
                                <span class="min-w-0">
                                    <strong class="block truncate text-sm text-slate-950">{{ billingUserLabel(user) }}</strong>
                                    <span class="mt-0.5 block truncate text-xs font-normal text-slate-500">
                                        {{ user.email || 'Chưa có email' }}<template v-if="user.phone"> · {{ user.phone }}</template>
                                    </span>
                                </span>
                                <span class="shrink-0 text-xs font-bold text-emerald-700">{{ formatMoney(user.wallet_balance) }}</span>
                            </button>
                            <p v-if="!billingUsersLoading && billingUsers.length === 0" class="px-3 py-4 text-center text-xs font-normal text-slate-500">
                                Không tìm thấy tài khoản người dùng đang hoạt động.
                            </p>
                        </div>
                        <input v-model="form.billing_user_id" required type="hidden" />
                        <span v-if="fieldErrors.billing_user_id" class="mt-1 block text-xs text-rose-600">{{ fieldErrors.billing_user_id[0] }}</span>
                        <p v-else-if="!isEditing" class="mt-1.5 text-xs font-normal text-slate-500">
                            Tài khoản này sẽ được gắn làm tài khoản thanh toán và được sao chép thành admin của website mới.
                        </p>
                    </div>
                </template>
                <div v-else class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
                    Domain, slug và tài khoản giá vốn của website mẹ được khóa để tránh làm gián đoạn NapCarot.
                </div>
                <label v-if="isEditing && !editingSite?.is_main" class="text-sm font-semibold text-slate-700"
                    >Trạng thái<select v-model="form.status" :class="formInputClass">
                        <option value="active">Hoạt động</option>
                        <option value="suspended">Tạm ngừng</option>
                    </select></label
                >
                <label
                    v-if="!editingSite?.is_main"
                    class="flex min-h-11 items-center gap-3 self-end rounded-xl border-2 border-slate-300 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700"
                    ><input
                        v-model="form.allow_below_cost"
                        type="checkbox"
                        class="h-4 w-4 rounded border-2 border-slate-400 text-blue-600 focus:ring-2 focus:ring-blue-200"
                    />Cho phép bán dưới giá vốn</label
                >
            </form>
            <template #footer
                ><div class="flex w-full flex-col-reverse gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
                    <button
                        type="button"
                        class="min-h-11 rounded-xl border-2 border-slate-300 bg-white px-5 text-sm font-bold text-slate-700 hover:bg-slate-100"
                        :disabled="saving"
                        @click="modalOpen = false"
                    >
                        Hủy</button
                    ><button
                        form="tenant-site-form"
                        type="submit"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 text-sm font-bold text-white hover:bg-blue-700 disabled:cursor-wait disabled:opacity-60"
                        :disabled="saving"
                    >
                        <LoaderCircle v-if="saving" class="h-4 w-4 animate-spin" /><span>{{
                            saving ? 'Đang lưu...' : isEditing ? 'Lưu thay đổi' : 'Tạo website'
                        }}</span>
                    </button>
                </div></template
            >
        </Modal>
    </section>
</template>
