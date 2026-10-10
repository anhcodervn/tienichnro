<script setup lang="ts">
import DataTable from '@/components/shared/DataTable/index.vue';
import { adminLicense, type LicenseKey, type LicensePlan, type LicenseProduct } from '@/services/admin-license.service';
import { handleErrorResponse } from '@/utils/response';
import { Dialog, DialogDescription, DialogPanel, DialogTitle } from '@headlessui/vue';
import { isAxiosError } from 'axios';
import { AlertCircle, Check, Download, KeyRound, LoaderCircle, Monitor, Package, Plus, RefreshCw, Search, ShieldCheck, X } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import {
    availableLicensePlans,
    canCloseLicenseDialog,
    effectiveSessionStatus,
    licenseActions,
    licenseCatalogRows,
    licenseExpiryLabel,
} from './presentation';

const inputClass =
    'min-h-11 w-full min-w-0 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-normal text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/15 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100';
const buttonClass =
    'inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800';
const primaryClass =
    'inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50';
const destructiveClass = primaryClass.replace('bg-blue-600', 'bg-rose-600').replace('hover:bg-blue-700', 'hover:bg-rose-700');
const badgeClass = 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset';
const statusStyles: Record<string, string> = {
    unused: 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700',
    active: 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-300 dark:ring-emerald-800',
    suspended: 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:ring-amber-800',
    revoked: 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-950 dark:text-rose-300 dark:ring-rose-800',
    expired: 'bg-orange-50 text-orange-700 ring-orange-200 dark:bg-orange-950 dark:text-orange-300 dark:ring-orange-800',
    terminated: 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700',
};
const props = withDefaults(defineProps<{ section?: 'keys' | 'products' | 'plans' }>(), { section: 'keys' });
const section = computed(() => props.section);
const pageTitle = computed(() => ({ keys: 'License keys', products: 'Sản phẩm / tool', plans: 'Gói license' })[section.value]);
const catalogFilters = reactive({ search: '', status: '', product_id: '' });
const catalogPage = ref(1);
const catalogRows = computed(() => licenseCatalogRows(section.value === 'products' ? products.value : plans.value, catalogFilters));
const catalogLastPage = computed(() => Math.max(1, Math.ceil(catalogRows.value.length / 20)));
const catalogData = computed(() => catalogRows.value.slice((catalogPage.value - 1) * 20, catalogPage.value * 20));
async function goToCatalogPage(value: number) {
    catalogPage.value = value;
}
function resetCatalogFilters() {
    Object.assign(catalogFilters, { search: '', status: '', product_id: '' });
}
const products = ref<LicenseProduct[]>([]);
const plans = ref<LicensePlan[]>([]);
const keys = ref<LicenseKey[]>([]);
const loading = ref(true);
const loadingKeys = ref(true);
const failed = ref(false);
const saving = ref(false);
const inspectingId = ref<number | null>(null);
const page = ref(1);
const lastPage = ref(1);
const totalKeys = ref(0);
const filters = reactive({ search: '', status: '', product_id: '' });
const modal = ref<'product' | 'plan' | 'issue' | 'manage' | 'detail' | 'issued' | null>(null);
const detailTab = ref<'devices' | 'sessions' | 'history'>('devices');
const editingId = ref<number | null>(null);
const detail = ref<LicenseKey | null>(null);
const issued = ref<{ id: number; key: string }[]>([]);
const keysSaved = ref(false);
const revokeConfirmed = ref(false);
const formErrors = ref<string[]>([]);
const notice = ref('');
const product = reactive<Omit<LicenseProduct, 'id'>>({
    name: '',
    product_code: '',
    description: '',
    minimum_version: '1.0.0',
    is_active: true,
    heartbeat_interval: 20,
    lease_duration: 60,
    transfer_cooldown: 1800,
    max_active_devices: 1,
    offline_grace: 0,
});
const plan = reactive<Omit<LicensePlan, 'id'>>({
    product_id: 0,
    name: '',
    duration_days: 30,
    price: 0,
    is_active: true,
    max_active_devices: 1,
    transfer_cooldown: 1800,
});
const issue = reactive({ plan_id: 0, user_id: null as number | null, quantity: 1 });
const action = reactive({ action: 'extend', reason: '', days: 30 as number | null, device_uuid: null as string | null });
const statuses: Record<string, string> = {
    unused: 'Chưa kích hoạt',
    active: 'Đang hiệu lực',
    suspended: 'Tạm khóa',
    revoked: 'Đã thu hồi',
    expired: 'Hết hạn',
    terminated: 'Đã đóng',
};
const actions: Record<string, string> = {
    suspend: 'Tạm khóa key',
    resume: 'Mở khóa key',
    extend: 'Gia hạn',
    'revoke-session': 'Ngắt phiên sử dụng',
    'reset-device': 'Reset thiết bị',
    transfer: 'Chuyển thiết bị',
    revoke: 'Thu hồi vĩnh viễn',
};
const eventLabels: Record<string, string> = {
    issued: 'Đã cấp key',
    activated: 'Kích hoạt thiết bị',
    transferred: 'Chuyển thiết bị',
    deactivated: 'Đóng phiên',
    ...actions,
};
const availablePlans = computed(() => availableLicensePlans(products.value, plans.value));
const availableActions = computed(() => (detail.value ? licenseActions(detail.value) : []));
const targetDevices = computed(() => detail.value?.devices?.filter((device) => device.device_uuid !== detail.value?.current_device_uuid) || []);
const headings = computed(
    () =>
        ({
            product: editingId.value ? 'Chỉnh sửa sản phẩm' : 'Thêm sản phẩm',
            plan: editingId.value ? 'Chỉnh sửa gói license' : 'Thêm gói license',
            issue: 'Tạo license key',
            manage: 'Quản lý license',
            detail: 'Chi tiết license',
            issued: 'License key đã tạo',
        })[modal.value || 'detail'],
);
const descriptions = computed(
    () =>
        ({
            product: 'Cấu hình tool và thời gian duy trì phiên sử dụng.',
            plan: 'Thiết lập thời hạn, giá và thời gian chờ chuyển máy.',
            issue: 'Chọn gói và số lượng key cần cấp cho người sử dụng.',
            manage: 'Chọn thao tác và ghi lại lý do thay đổi.',
            detail: 'Theo dõi thiết bị, phiên sử dụng và lịch sử của key.',
            issued: 'Key đầy đủ chỉ hiển thị tại đây. Hãy lưu trước khi đóng.',
        })[modal.value || 'detail'],
);
const primaryLabel = computed(() =>
    section.value === 'products' ? 'Thêm sản phẩm' : section.value === 'plans' ? 'Thêm gói license' : 'Tạo license key',
);
const canCreate = computed(
    () =>
        !loading.value && (section.value === 'products' || (section.value === 'plans' ? products.value.length > 0 : availablePlans.value.length > 0)),
);
const dangerousAction = computed(() => ['revoke', 'reset-device'].includes(action.action));
const canSave = computed(
    () =>
        !saving.value &&
        (modal.value !== 'issue' || availablePlans.value.some((item) => item.id === issue.plan_id)) &&
        (modal.value !== 'manage' || (availableActions.value.includes(action.action) && (action.action !== 'revoke' || revokeConfirmed.value))),
);
const date = (value: string | null) => (value ? new Date(value).toLocaleString('vi-VN') : '—');
const productName = (id: number) => products.value.find((item) => item.id === id)?.name || String(id);
const columns = [
    { accessorKey: 'key_prefix', header: 'License key' },
    { accessorKey: 'product_name', header: 'Sản phẩm / gói' },
    { accessorKey: 'status', header: 'Trạng thái key' },
    { accessorKey: 'expires_at', header: 'Thời hạn' },
    { id: 'actions', header: 'Thao tác' },
];
const productColumns = [
    { accessorKey: 'name', header: 'Sản phẩm / tool' },
    { accessorKey: 'product_code', header: 'Mã sản phẩm' },
    { accessorKey: 'is_active', header: 'Trạng thái' },
    { accessorKey: 'minimum_version', header: 'Phiên bản tối thiểu' },
    { accessorKey: 'heartbeat_interval', header: 'Heartbeat' },
    { accessorKey: 'lease_duration', header: 'Lease' },
    { accessorKey: 'transfer_cooldown', header: 'Chờ chuyển máy' },
    { id: 'actions', header: 'Thao tác' },
];
const planColumns = [
    { accessorKey: 'name', header: 'Gói license' },
    { accessorKey: 'product_id', header: 'Sản phẩm / tool' },
    { accessorKey: 'duration_days', header: 'Thời hạn' },
    { accessorKey: 'price', header: 'Giá' },
    { accessorKey: 'is_active', header: 'Trạng thái' },
    { accessorKey: 'transfer_cooldown', header: 'Chờ chuyển máy' },
    { id: 'actions', header: 'Thao tác' },
];
let keyRequest = 0;
async function loadKeys(next = page.value) {
    const request = ++keyRequest;
    loadingKeys.value = true;
    try {
        const result = await adminLicense.keys({ page: next, ...filters });
        if (request !== keyRequest) return;
        keys.value = result.data;
        page.value = next;
        lastPage.value = Math.max(1, result.meta.last_page);
        totalKeys.value = result.meta.total;
        failed.value = false;
    } catch (error) {
        if (request === keyRequest) {
            failed.value = true;
            handleErrorResponse(error);
        }
    } finally {
        if (request === keyRequest) loadingKeys.value = false;
    }
}
async function load() {
    loading.value = true;
    failed.value = false;
    try {
        [products.value, plans.value] = await Promise.all([adminLicense.products(), adminLicense.plans()]);
        await loadKeys();
    } catch (error) {
        failed.value = true;
        loadingKeys.value = false;
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
}
function openModal(value: typeof modal.value) {
    formErrors.value = [];
    revokeConfirmed.value = false;
    modal.value = value;
}
function editProduct(item?: LicenseProduct) {
    editingId.value = item?.id ?? null;
    Object.assign(
        product,
        item || {
            name: '',
            product_code: '',
            description: '',
            minimum_version: '1.0.0',
            is_active: true,
            heartbeat_interval: 20,
            lease_duration: 60,
            transfer_cooldown: 1800,
            max_active_devices: 1,
            offline_grace: 0,
        },
    );
    openModal('product');
}
function editPlan(item?: LicensePlan) {
    editingId.value = item?.id ?? null;
    Object.assign(
        plan,
        item || {
            product_id: products.value[0]?.id || 0,
            name: '',
            duration_days: 30,
            price: 0,
            is_active: true,
            max_active_devices: 1,
            transfer_cooldown: 1800,
        },
    );
    openModal('plan');
}
function openIssue() {
    Object.assign(issue, { plan_id: availablePlans.value[0]?.id || 0, user_id: null, quantity: 1 });
    keysSaved.value = false;
    openModal('issue');
}
function create() {
    if (section.value === 'products') editProduct();
    else if (section.value === 'plans') editPlan();
    else openIssue();
}
async function inspect(item: LicenseKey, manage = false) {
    if (inspectingId.value !== null) return;
    inspectingId.value = item.id;
    try {
        detail.value = await adminLicense.detail(item.id);
        Object.assign(action, {
            action: availableActions.value[0] || 'extend',
            reason: '',
            days: 30,
            device_uuid: targetDevices.value[0]?.device_uuid || null,
        });
        detailTab.value = 'devices';
        openModal(manage && availableActions.value.length ? 'manage' : 'detail');
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        inspectingId.value = null;
    }
}
function close() {
    if (canCloseLicenseDialog(saving.value, modal.value, keysSaved.value)) {
        modal.value = null;
        issued.value = [];
        formErrors.value = [];
    }
}
async function save() {
    if (!canSave.value) return;
    saving.value = true;
    formErrors.value = [];
    try {
        if (modal.value === 'product') await adminLicense.saveProduct(product, editingId.value);
        if (modal.value === 'plan') await adminLicense.savePlan(plan, editingId.value);
        if (modal.value === 'manage' && detail.value)
            await adminLicense.manage(detail.value.id, {
                ...action,
                days: action.action === 'extend' ? action.days : null,
                device_uuid: action.action === 'transfer' ? action.device_uuid : null,
            });
        if (modal.value === 'issue') {
            issued.value = await adminLicense.issue(issue);
            keysSaved.value = false;
            modal.value = 'issued';
        } else {
            notice.value = 'Đã lưu thay đổi.';
            modal.value = null;
        }
        await load();
    } catch (error) {
        if (isAxiosError<{ errors?: Record<string, string[]>; message?: string }>(error) && error.response?.status === 422)
            formErrors.value = error.response.data.errors
                ? Object.values(error.response.data.errors).flat()
                : [error.response.data.message || 'Vui lòng kiểm tra lại thông tin.'];
        else handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
}
function downloadKeys() {
    const url = URL.createObjectURL(
        new Blob([issued.value.map((item) => `${item.id}: ${item.key}`).join('\n')], { type: 'text/plain;charset=utf-8' }),
    );
    const link = document.createElement('a');
    link.href = url;
    link.download = 'license-keys.txt';
    link.click();
    URL.revokeObjectURL(url);
    keysSaved.value = true;
}
async function resetFilters() {
    Object.assign(filters, { search: '', status: '', product_id: '' });
    await loadKeys(1);
}
watch([section, () => catalogFilters.search, () => catalogFilters.status, () => catalogFilters.product_id], () => {
    catalogPage.value = 1;
});
watch(catalogLastPage, (value) => {
    catalogPage.value = Math.min(catalogPage.value, value);
});
watch(section, () => {
    modal.value = null;
    notice.value = '';
    resetCatalogFilters();
});
onMounted(load);
</script>

<template>
    <div class="space-y-5 text-slate-900 dark:text-slate-100">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-3">
                <span
                    class="grid size-11 shrink-0 place-items-center rounded-xl border border-blue-100 bg-blue-50 text-blue-600 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-300"
                    ><KeyRound class="size-5"
                /></span>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">{{ pageTitle }}</h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Cấp key và quản lý quyền sử dụng cho các tool nội bộ.</p>
                </div>
            </div>
            <div class="flex shrink-0 gap-2">
                <button :class="buttonClass" :disabled="loading || loadingKeys" @click="load">
                    <RefreshCw class="size-4" :class="{ 'animate-spin': loading }" /><span>Làm mới</span></button
                ><button :class="primaryClass" :disabled="!canCreate" @click="create"><Plus class="size-4" />{{ primaryLabel }}</button>
            </div>
        </header>
        <div
            v-if="notice"
            role="status"
            class="flex items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200"
        >
            <span class="flex items-center gap-2"><Check class="size-4" />{{ notice }}</span
            ><button aria-label="Ẩn thông báo" class="rounded p-1" @click="notice = ''"><X class="size-4" /></button>
        </div>
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div
                v-if="failed"
                role="alert"
                class="m-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-300"
            >
                <span class="flex items-center gap-2"><AlertCircle class="size-4" />Không tải được dữ liệu. Vui lòng thử lại.</span
                ><button :class="buttonClass" @click="load">Thử lại</button>
            </div>
            <template v-if="section === 'keys'">
                <form
                    class="grid items-end gap-3 border-b border-slate-100 p-4 dark:border-slate-800 sm:grid-cols-2 xl:grid-cols-[minmax(220px,1fr)_180px_200px_auto]"
                    @submit.prevent="loadKeys(1)"
                >
                    <label class="grid gap-1.5 text-xs font-semibold text-slate-600 dark:text-slate-400"
                        >Tìm license key
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-3 top-3.5 size-4 text-slate-400" /><input
                                v-model="filters.search"
                                :class="[inputClass, 'pl-9']"
                                maxlength="32"
                                placeholder="Nhập prefix, ví dụ ABCD-1234"
                            /></div
                    ></label>
                    <label class="grid gap-1.5 text-xs font-semibold text-slate-600 dark:text-slate-400"
                        >Trạng thái<select v-model="filters.status" :class="inputClass">
                            <option value="">Tất cả trạng thái</option>
                            <option v-for="value in ['unused', 'active', 'suspended', 'revoked', 'expired']" :key="value" :value="value">
                                {{ statuses[value] }}
                            </option>
                        </select></label
                    >
                    <label class="grid gap-1.5 text-xs font-semibold text-slate-600 dark:text-slate-400"
                        >Sản phẩm<select v-model="filters.product_id" :class="inputClass">
                            <option value="">Tất cả sản phẩm</option>
                            <option v-for="item in products" :key="item.id" :value="String(item.id)">{{ item.name }}</option>
                        </select></label
                    >
                    <div class="flex gap-2">
                        <button :class="[buttonClass, 'min-h-11']" :disabled="loadingKeys">Lọc</button
                        ><button type="button" :class="[buttonClass, 'min-h-11']" :disabled="loadingKeys" @click="resetFilters">Đặt lại</button>
                    </div>
                </form>
                <div v-if="!loading && !products.length && !failed" class="flex flex-col items-center gap-3 px-6 py-14 text-center">
                    <span class="grid size-14 place-items-center rounded-2xl bg-blue-50 text-blue-500 dark:bg-blue-950"
                        ><Package class="size-6"
                    /></span>
                    <h2 class="font-semibold">Bắt đầu với sản phẩm đầu tiên</h2>
                    <p class="max-w-md text-sm leading-6 text-slate-500 dark:text-slate-400">
                        Thêm tool, tạo gói license rồi cấp key cho người sử dụng.
                    </p>
                    <button :class="primaryClass" @click="editProduct()"><Plus class="size-4" />Thêm sản phẩm</button>
                </div>
                <template v-else>
                    <DataTable
                        class="license-table"
                        :data="failed ? [] : keys"
                        :columns="columns"
                        :loading="loadingKeys"
                        :current-page="page"
                        :total-pages="lastPage"
                        :go-to-page="loadKeys"
                        empty-text="Chưa có license key phù hợp với bộ lọc."
                    >
                        <template #key_prefix="{ row }"
                            ><div class="flex items-center gap-3">
                                <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-800"
                                    ><KeyRound class="size-4"
                                /></span>
                                <div>
                                    <code class="whitespace-nowrap text-sm font-semibold text-slate-900 dark:text-slate-100"
                                        >{{ row.key_prefix }}…</code
                                    >
                                    <p class="mt-1 text-xs text-slate-400">
                                        #{{ row.id }} · {{ row.user_id ? `Chủ sở hữu #${row.user_id}` : 'Chưa gán chủ sở hữu' }}
                                    </p>
                                </div>
                            </div></template
                        >
                        <template #product_name="{ row }"
                            ><p class="max-w-xs font-medium text-slate-800 dark:text-slate-200">{{ row.product_name }}</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ row.plan_name }}</p></template
                        >
                        <template #status="{ row }"
                            ><span :class="[badgeClass, statusStyles[row.status]]">{{ statuses[row.status] || row.status }}</span>
                            <p class="mt-2 flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                                <span class="size-1.5 rounded-full" :class="row.online ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600'" />{{
                                    row.online ? 'Thiết bị online' : 'Thiết bị offline'
                                }}
                            </p></template
                        >
                        <template #expires_at="{ row }"
                            ><p class="whitespace-nowrap font-medium">{{ licenseExpiryLabel(row) }}</p>
                            <p v-if="!row.activated_at" class="mt-1 text-xs text-slate-400">Tính hạn từ lần kích hoạt đầu</p></template
                        >
                        <template #actions="{ row }"
                            ><div class="flex items-center gap-2 whitespace-nowrap">
                                <button :class="buttonClass" :disabled="inspectingId !== null" @click="inspect(row)">
                                    <LoaderCircle v-if="inspectingId === row.id" class="size-3.5 animate-spin" />Chi tiết</button
                                ><button
                                    v-if="row.status !== 'revoked'"
                                    :class="buttonClass"
                                    :disabled="inspectingId !== null"
                                    @click="inspect(row, true)"
                                >
                                    Quản lý
                                </button>
                            </div></template
                        >
                    </DataTable>
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 px-4 py-3 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400"
                    >
                        <span>{{ totalKeys }} key khớp bộ lọc · Trang {{ page }}/{{ lastPage }}</span
                        ><span>Key đầy đủ chỉ hiển thị khi tạo</span>
                    </div>
                </template>
            </template>
            <template v-else>
                <div class="grid items-end gap-3 border-b border-slate-100 p-4 dark:border-slate-800 sm:grid-cols-2 xl:grid-cols-4">
                    <label class="grid gap-1.5 text-xs font-semibold text-slate-600 dark:text-slate-400"
                        >Tìm kiếm
                        <input
                            v-model="catalogFilters.search"
                            :class="inputClass"
                            :placeholder="section === 'products' ? 'Tên hoặc mã sản phẩm' : 'Tên gói license'"
                        />
                    </label>
                    <label class="grid gap-1.5 text-xs font-semibold text-slate-600 dark:text-slate-400"
                        >Trạng thái
                        <select v-model="catalogFilters.status" :class="inputClass">
                            <option value="">Tất cả trạng thái</option>
                            <option value="active">Đang bật</option>
                            <option value="inactive">Đã tắt</option>
                        </select>
                    </label>
                    <label v-if="section === 'plans'" class="grid gap-1.5 text-xs font-semibold text-slate-600 dark:text-slate-400"
                        >Sản phẩm
                        <select v-model="catalogFilters.product_id" :class="inputClass">
                            <option value="">Tất cả sản phẩm</option>
                            <option v-for="item in products" :key="item.id" :value="String(item.id)">{{ item.name }}</option>
                        </select>
                    </label>
                    <div><button :class="[buttonClass, 'min-h-11']" @click="resetCatalogFilters">Đặt lại</button></div>
                </div>
                <DataTable
                    :key="section"
                    class="license-table"
                    :data="failed ? [] : catalogData"
                    :columns="section === 'products' ? productColumns : planColumns"
                    :loading="loading"
                    :current-page="catalogPage"
                    :total-pages="catalogLastPage"
                    :go-to-page="goToCatalogPage"
                    empty-text="Chưa có dữ liệu phù hợp với bộ lọc."
                >
                    <template #name="{ row }"
                        ><p class="font-medium text-slate-900 dark:text-slate-100">{{ row.name }}</p>
                        <p v-if="section === 'products'" class="mt-1 max-w-xs text-xs text-slate-500 dark:text-slate-400">
                            {{ row.description || 'Chưa có mô tả' }}
                        </p></template
                    >
                    <template #product_code="{ row }"
                        ><code class="whitespace-nowrap">{{ row.product_code }}</code></template
                    >
                    <template #product_id="{ row }">{{ productName(row.product_id) }}</template>
                    <template #is_active="{ row }"
                        ><span :class="[badgeClass, row.is_active ? statusStyles.active : statusStyles.unused]">{{
                            row.is_active ? 'Đang bật' : 'Đã tắt'
                        }}</span></template
                    >
                    <template #heartbeat_interval="{ row }"
                        ><span class="whitespace-nowrap">{{ row.heartbeat_interval }} giây</span></template
                    >
                    <template #lease_duration="{ row }"
                        ><span class="whitespace-nowrap">{{ row.lease_duration }} giây</span></template
                    >
                    <template #transfer_cooldown="{ row }"
                        ><span class="whitespace-nowrap">{{ row.transfer_cooldown / 60 }} phút</span></template
                    >
                    <template #duration_days="{ row }"
                        ><span class="whitespace-nowrap">{{ row.duration_days === null ? 'Vĩnh viễn' : `${row.duration_days} ngày` }}</span></template
                    >
                    <template #price="{ row }"
                        ><span class="whitespace-nowrap">{{ Number(row.price).toLocaleString('vi-VN') }} đ</span></template
                    >
                    <template #actions="{ row }"
                        ><button :class="buttonClass" @click="section === 'products' ? editProduct(row) : editPlan(row)">Chỉnh sửa</button></template
                    >
                </DataTable>
                <div class="border-t border-slate-100 px-4 py-3 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                    {{ catalogRows.length }} mục khớp bộ lọc · Trang {{ catalogPage }}/{{ catalogLastPage }}
                </div>
            </template>
        </section>

        <Dialog :open="modal !== null" class="relative z-50" @close="close">
            <div class="fixed inset-0 bg-slate-950/50 backdrop-blur-sm" aria-hidden="true" />
            <div class="fixed inset-0 overflow-y-auto p-3 sm:p-6">
                <div class="flex min-h-full items-center justify-center">
                    <DialogPanel
                        class="flex max-h-[calc(100dvh-1.5rem)] w-full min-w-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white text-slate-900 shadow-2xl dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 sm:max-h-[calc(100dvh-3rem)]"
                        :class="modal === 'detail' ? 'max-w-4xl' : 'max-w-2xl'"
                    >
                        <header
                            class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 dark:border-slate-700 sm:px-6"
                        >
                            <div>
                                <DialogTitle class="text-lg font-semibold">{{ headings }}</DialogTitle
                                ><DialogDescription class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ descriptions }}</DialogDescription>
                            </div>
                            <button
                                class="grid size-9 shrink-0 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 disabled:opacity-30 dark:hover:bg-slate-800"
                                :disabled="!canCloseLicenseDialog(saving, modal, keysSaved)"
                                aria-label="Đóng cửa sổ"
                                @click="close"
                            >
                                <X class="size-5" />
                            </button>
                        </header>
                        <div class="min-h-0 overflow-y-auto px-5 py-5 sm:px-6">
                            <div
                                v-if="formErrors.length"
                                role="alert"
                                class="mb-5 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-300"
                            >
                                <p class="font-semibold">Kiểm tra lại thông tin</p>
                                <ul class="mt-2 list-inside list-disc space-y-1">
                                    <li v-for="error in formErrors" :key="error">{{ error }}</li>
                                </ul>
                            </div>
                            <form
                                v-if="['product', 'plan', 'issue', 'manage'].includes(modal || '')"
                                id="license-form"
                                class="space-y-5"
                                @submit.prevent="save"
                            >
                                <div v-if="modal === 'product'" class="grid gap-3 sm:grid-cols-2">
                                    <label class="grid gap-1.5 text-sm font-medium"
                                        >Tên tool<input v-model="product.name" required maxlength="120" :class="inputClass"
                                    /></label>
                                    <label class="grid gap-1.5 text-sm font-medium"
                                        >Mã sản phẩm<input
                                            v-model="product.product_code"
                                            required
                                            pattern="[A-Z0-9_]+"
                                            :class="inputClass"
                                            placeholder="NRO_MANAGER"
                                    /></label>
                                    <label class="grid gap-1.5 text-sm font-medium sm:col-span-2"
                                        >Mô tả<textarea v-model="product.description" :class="inputClass" />
                                    </label>
                                    <label class="grid gap-1.5 text-sm font-medium"
                                        >Phiên bản tối thiểu<input v-model="product.minimum_version" required :class="inputClass"
                                    /></label>
                                    <label class="grid gap-1.5 text-sm font-medium"
                                        >Heartbeat (giây)<input
                                            v-model.number="product.heartbeat_interval"
                                            type="number"
                                            min="5"
                                            max="300"
                                            required
                                            :class="inputClass"
                                    /></label>
                                    <label class="grid gap-1.5 text-sm font-medium"
                                        >Lease (giây)<input
                                            v-model.number="product.lease_duration"
                                            type="number"
                                            :min="product.heartbeat_interval + 1"
                                            max="900"
                                            required
                                            :class="inputClass"
                                    /></label>
                                    <label class="grid gap-1.5 text-sm font-medium"
                                        >Cooldown mặc định (giây)<input
                                            v-model.number="product.transfer_cooldown"
                                            type="number"
                                            min="0"
                                            max="604800"
                                            required
                                            :class="inputClass"
                                    /></label>
                                    <label class="flex items-center gap-2 text-sm"
                                        ><input v-model="product.is_active" type="checkbox" /> Bật sản phẩm</label
                                    >
                                </div>
                                <div v-if="modal === 'plan'" class="grid gap-3 sm:grid-cols-2">
                                    <label class="grid gap-1.5 text-sm font-medium"
                                        >Sản phẩm<select v-model.number="plan.product_id" :class="inputClass" required>
                                            <option v-for="item in products" :key="item.id" :value="item.id">{{ item.name }}</option>
                                        </select></label
                                    >
                                    <label class="grid gap-1.5 text-sm font-medium"
                                        >Tên gói<input v-model="plan.name" required :class="inputClass"
                                    /></label>
                                    <label class="grid gap-1.5 text-sm font-medium"
                                        >Thời hạn (ngày, trống = vĩnh viễn)<input
                                            :value="plan.duration_days"
                                            type="number"
                                            min="1"
                                            max="36500"
                                            :class="inputClass"
                                            @input="
                                                plan.duration_days = ($event.target as HTMLInputElement).value
                                                    ? Number(($event.target as HTMLInputElement).value)
                                                    : null
                                            "
                                    /></label>
                                    <label class="grid gap-1.5 text-sm font-medium"
                                        >Giá (VNĐ)<input v-model.number="plan.price" required type="number" min="0" :class="inputClass"
                                    /></label>
                                    <label class="grid gap-1.5 text-sm font-medium"
                                        >Cooldown chuyển máy (giây)<input
                                            v-model.number="plan.transfer_cooldown"
                                            required
                                            type="number"
                                            min="0"
                                            max="604800"
                                            :class="inputClass"
                                    /></label>
                                    <label class="flex items-center gap-2 text-sm"><input v-model="plan.is_active" type="checkbox" /> Bật gói</label>
                                </div>
                                <div v-if="modal === 'issue'" class="grid gap-3">
                                    <label class="grid gap-1.5 text-sm font-medium"
                                        >Gói<select v-model.number="issue.plan_id" required :class="inputClass">
                                            <option v-for="item in availablePlans" :key="item.id" :value="item.id">
                                                {{ productName(item.product_id) }} · {{ item.name }}
                                            </option>
                                        </select></label
                                    >
                                    <label class="grid gap-1.5 text-sm font-medium"
                                        >ID chủ sở hữu (tùy chọn)<input
                                            :value="issue.user_id"
                                            type="number"
                                            min="1"
                                            :class="inputClass"
                                            @input="
                                                issue.user_id = ($event.target as HTMLInputElement).value
                                                    ? Number(($event.target as HTMLInputElement).value)
                                                    : null
                                            "
                                    /></label>
                                    <label class="grid gap-1.5 text-sm font-medium"
                                        >Số lượng (1–100)<input
                                            v-model.number="issue.quantity"
                                            required
                                            type="number"
                                            min="1"
                                            max="100"
                                            :class="inputClass"
                                    /></label>
                                    <p class="text-sm text-slate-500">
                                        Gán chủ sở hữu để cho phép chuyển máy có xác minh. Key đầy đủ chỉ xuất hiện sau khi tạo.
                                    </p>
                                </div>

                                <div v-if="modal === 'manage' && detail" class="space-y-5">
                                    <div
                                        class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800"
                                    >
                                        <div>
                                            <code class="font-semibold">{{ detail.key_prefix }}…</code>
                                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                {{ detail.product_name }} · {{ detail.plan_name }}
                                            </p>
                                        </div>
                                        <span :class="[badgeClass, statusStyles[detail.status]]">{{ statuses[detail.status] }}</span>
                                    </div>
                                    <label class="grid gap-1.5 text-sm font-medium"
                                        >Thao tác<select v-model="action.action" :class="inputClass" @change="revokeConfirmed = false">
                                            <option v-for="value in availableActions" :key="value" :value="value">{{ actions[value] }}</option>
                                        </select></label
                                    >
                                    <label v-if="action.action === 'extend'" class="grid gap-1.5 text-sm font-medium"
                                        >Số ngày gia hạn<input
                                            v-model.number="action.days"
                                            type="number"
                                            required
                                            min="1"
                                            max="36500"
                                            :class="inputClass"
                                    /></label>
                                    <label v-if="action.action === 'transfer'" class="grid gap-1.5 text-sm font-medium"
                                        >Thiết bị đích<select v-model="action.device_uuid" required :class="inputClass">
                                            <option v-for="device in targetDevices" :key="device.device_uuid" :value="device.device_uuid">
                                                {{ device.device_name }} — {{ device.device_uuid }}
                                            </option>
                                        </select></label
                                    >
                                    <label class="grid gap-1.5 text-sm font-medium"
                                        >Lý do thay đổi <span class="sr-only">bắt buộc</span
                                        ><textarea
                                            v-model="action.reason"
                                            required
                                            minlength="3"
                                            maxlength="255"
                                            rows="3"
                                            :class="inputClass"
                                            placeholder="Ghi rõ lý do để lưu vào lịch sử quản lý."
                                        />
                                    </label>
                                    <div
                                        v-if="dangerousAction"
                                        class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm leading-6 text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200"
                                    >
                                        <p>
                                            {{
                                                action.action === 'revoke'
                                                    ? 'Key sẽ bị thu hồi vĩnh viễn và không thể mở lại. Tất cả phiên đang sử dụng sẽ mất quyền.'
                                                    : 'Phiên cũ sẽ bị thu hồi. Máy kế tiếp có key hợp lệ có thể đăng ký lại thiết bị.'
                                            }}
                                        </p>
                                        <label v-if="action.action === 'revoke'" class="mt-3 flex items-start gap-2 font-medium"
                                            ><input
                                                v-model="revokeConfirmed"
                                                type="checkbox"
                                                class="mt-1 rounded border-amber-300 text-amber-700 focus:ring-amber-500"
                                            />Tôi xác nhận thu hồi vĩnh viễn key này.</label
                                        >
                                    </div>
                                </div>
                            </form>
                            <div v-if="modal === 'issued'" class="space-y-4">
                                <div
                                    class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200"
                                >
                                    <ShieldCheck class="mt-0.5 size-5 shrink-0" />
                                    <div>
                                        <p class="font-semibold">Đã tạo {{ issued.length }} license key</p>
                                        <p class="mt-1 text-sm leading-6">
                                            Tải file hoặc sao chép key và lưu ở nơi an toàn. Sau khi đóng, bạn chỉ có thể xem prefix của key.
                                        </p>
                                    </div>
                                </div>
                                <label class="grid gap-2 text-sm font-medium"
                                    >Danh sách key<textarea
                                        readonly
                                        :value="issued.map((item) => `${item.id}: ${item.key}`).join('\n')"
                                        :class="[inputClass, 'font-mono text-xs leading-7']"
                                        rows="8"
                                        spellcheck="false"
                                    /></label
                                ><button :class="primaryClass" @click="downloadKeys"><Download class="size-4" />Tải file key (.txt)</button
                                ><label class="flex items-start gap-2 text-sm text-slate-600 dark:text-slate-300"
                                    ><input
                                        v-model="keysSaved"
                                        type="checkbox"
                                        class="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                    />Tôi đã lưu các key và có thể đóng cửa sổ này.</label
                                >
                            </div>
                            <div v-if="modal === 'detail' && detail" class="space-y-5">
                                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <code class="text-base font-semibold">{{ detail.key_prefix }}…</code>
                                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                                {{ detail.product_name }} · {{ detail.plan_name }}
                                            </p>
                                        </div>
                                        <span :class="[badgeClass, statusStyles[detail.status]]">{{ statuses[detail.status] }}</span>
                                    </div>
                                    <dl class="mt-4 grid gap-3 border-t border-slate-200 pt-3 dark:border-slate-700 sm:grid-cols-3">
                                        <div>
                                            <dt class="text-xs text-slate-500 dark:text-slate-400">Thời hạn</dt>
                                            <dd class="mt-1 text-sm font-medium">{{ licenseExpiryLabel(detail) }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs text-slate-500 dark:text-slate-400">Chủ sở hữu</dt>
                                            <dd class="mt-1 text-sm font-medium">
                                                {{ detail.user_id ? `Tài khoản #${detail.user_id}` : 'Chưa gán' }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs text-slate-500 dark:text-slate-400">Kết nối</dt>
                                            <dd class="mt-1 text-sm font-medium" :class="detail.online ? 'text-emerald-600' : ''">
                                                {{ detail.online ? 'Thiết bị online' : 'Thiết bị offline' }}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                                <nav class="flex gap-1 overflow-x-auto rounded-lg bg-slate-100 p-1 dark:bg-slate-800" aria-label="Chi tiết license">
                                    <button
                                        v-for="(label, value) in { devices: 'Thiết bị', sessions: 'Phiên sử dụng', history: 'Lịch sử' }"
                                        :key="value"
                                        :aria-pressed="detailTab === value"
                                        class="min-h-10 flex-1 whitespace-nowrap rounded-md px-3 text-sm font-medium"
                                        :class="
                                            detailTab === value
                                                ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-white'
                                                : 'text-slate-500 dark:text-slate-400'
                                        "
                                        @click="detailTab = value"
                                    >
                                        {{ label }}
                                    </button>
                                </nav>
                                <template v-if="detailTab === 'devices'"
                                    ><p v-if="!detail.devices?.length" class="py-8 text-center text-sm text-slate-500">
                                        Chưa có thiết bị kích hoạt key này.
                                    </p>
                                    <article
                                        v-for="device in detail.devices"
                                        :key="device.device_uuid"
                                        class="flex items-start gap-3 rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                                    >
                                        <Monitor class="mt-1 size-5 shrink-0 text-slate-400" />
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <h3 class="text-sm font-semibold">{{ device.device_name }}</h3>
                                                <span
                                                    v-if="device.device_uuid === detail.current_device_uuid"
                                                    :class="[badgeClass, statusStyles.active]"
                                                    >Thiết bị được cấp quyền</span
                                                >
                                            </div>
                                            <p class="mt-2 break-all font-mono text-xs text-slate-400">{{ device.device_uuid }}</p>
                                            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                                Kết nối lần cuối: {{ date(device.last_seen_at) }}
                                            </p>
                                        </div>
                                    </article></template
                                >
                                <template v-if="detailTab === 'sessions'"
                                    ><p class="text-xs text-slate-500">Hiển thị tối đa 50 phiên gần nhất.</p>
                                    <p v-if="!detail.sessions?.length" class="py-8 text-center text-sm text-slate-500">Chưa có phiên sử dụng.</p>
                                    <article
                                        v-for="session in detail.sessions"
                                        :key="session.id"
                                        class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                                    >
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <code class="break-all text-xs text-slate-500 dark:text-slate-400">{{ session.id }}</code
                                            ><span :class="[badgeClass, statusStyles[effectiveSessionStatus(session)]]">{{
                                                statuses[effectiveSessionStatus(session)] || session.status
                                            }}</span>
                                        </div>
                                        <p class="mt-2 text-sm">Lease đến {{ date(session.lease_expires_at) }}</p>
                                        <p
                                            v-if="session.revocation_reason"
                                            class="mt-2 rounded-md bg-slate-50 p-2 text-xs text-slate-500 dark:bg-slate-800 dark:text-slate-400"
                                        >
                                            {{ session.revocation_reason }}
                                        </p>
                                    </article></template
                                >
                                <template v-if="detailTab === 'history'"
                                    ><p class="text-xs text-slate-500">Hiển thị tối đa 100 sự kiện gần nhất.</p>
                                    <p v-if="!detail.events?.length" class="py-8 text-center text-sm text-slate-500">Chưa có lịch sử hoạt động.</p>
                                    <ol class="space-y-0">
                                        <li
                                            v-for="event in detail.events"
                                            :key="event.id"
                                            class="relative border-l border-slate-200 pb-5 pl-5 last:pb-0 dark:border-slate-700"
                                        >
                                            <span
                                                class="absolute -left-1 top-1.5 size-2 rounded-full bg-blue-400 ring-4 ring-white dark:ring-slate-900"
                                            />
                                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                                <p class="text-sm font-medium">{{ eventLabels[event.event] || event.event }}</p>
                                                <time class="text-xs text-slate-400">{{ date(event.created_at) }}</time>
                                            </div>
                                            <p v-if="event.reason" class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">
                                                {{ event.reason }}
                                            </p>
                                        </li>
                                    </ol></template
                                >
                            </div>
                        </div>
                        <footer
                            class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 dark:border-slate-700 dark:bg-slate-800 sm:px-6"
                        >
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{
                                    modal === 'issued'
                                        ? 'Lưu key trước khi đóng.'
                                        : modal === 'detail'
                                          ? `License #${detail?.id}`
                                          : 'Thay đổi sẽ được lưu vào hệ thống.'
                                }}
                            </p>
                            <div class="ml-auto flex flex-wrap justify-end gap-2">
                                <button :class="buttonClass" :disabled="!canCloseLicenseDialog(saving, modal, keysSaved)" @click="close">
                                    {{ modal === 'issued' || modal === 'detail' ? 'Đóng' : 'Hủy' }}</button
                                ><button
                                    v-if="['product', 'plan', 'issue', 'manage'].includes(modal || '')"
                                    form="license-form"
                                    type="submit"
                                    :class="modal === 'manage' && action.action === 'revoke' ? destructiveClass : primaryClass"
                                    :disabled="!canSave"
                                >
                                    <LoaderCircle v-if="saving" class="size-4 animate-spin" />{{
                                        saving
                                            ? 'Đang lưu…'
                                            : modal === 'issue'
                                              ? 'Tạo key'
                                              : modal === 'manage'
                                                ? actions[action.action]
                                                : 'Lưu thay đổi'
                                    }}
                                </button>
                            </div>
                        </footer>
                    </DialogPanel>
                </div>
            </div>
        </Dialog>
    </div>
</template>

<style scoped>
.license-table :deep(> div:first-child) {
    @apply rounded-none border-0 shadow-none dark:bg-slate-900;
}
.license-table :deep(thead) {
    @apply bg-slate-50 dark:bg-slate-800;
}
.license-table :deep(th) {
    @apply px-4 py-3 normal-case tracking-normal text-slate-500 dark:text-slate-400;
}
.license-table :deep(td) {
    @apply px-4 py-4 align-middle text-slate-700 dark:text-slate-300;
}
.license-table :deep(tbody) {
    @apply divide-slate-100 dark:divide-slate-800;
}
.license-table :deep(tbody tr:hover) {
    @apply bg-slate-50 dark:bg-slate-800/50;
}
.license-table :deep(> div:last-child:not(:first-child)) {
    @apply px-4;
}
</style>
