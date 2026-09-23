<script setup lang="ts">
import Modal from '@/components/shared/Modal/index.vue';
import { adminTopupService } from '@/services/admin-topup.service';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

type ProviderRow = {
    id: number;
    name: string;
    slug: string;
    type: 'merchant_partner_card' | 'accnro' | 'manual';
    has_connection_config: boolean;
    packages_count?: number;
    supports_balance: boolean;
    balance: number | null;
    balance_currency: string | null;
    balance_status: 'unchecked' | 'success' | 'failed';
    balance_checked_at: string | null;
    balance_error_code: string | null;
    balance_error_message: string | null;
    payload_field_mapping?: Record<string, unknown>;
};

type ProviderConfigGuide = {
    title: string;
    description: string;
    domainExample: string | null;
    fields: Array<{
        key: string;
        required: boolean;
        description: string;
        example: string;
    }>;
};

const route = useRoute();
const router = useRouter();
const providers = ref<ProviderRow[]>([]);
const loading = ref(false);
const saving = ref(false);
const refreshingBalances = ref(false);
const editingId = ref<number | null>(null);
const connectionJsonError = ref('');
const mappingJsonError = ref('');
const servicesModalOpen = ref(false);
const servicesLoading = ref(false);
const servicesCopied = ref(false);
const selectedServicesProvider = ref<ProviderRow | null>(null);
const servicesResponse = ref<unknown>(null);
const servicesError = ref('');
const filters = reactive({
    search: typeof route.query.search === 'string' ? route.query.search : '',
    per_page: typeof route.query.per_page === 'string' ? route.query.per_page : '20',
    page: Math.max(Number(route.query.page || 1), 1),
});
const pagination = reactive({ current_page: 1, last_page: 1, total: 0, from: null as number | null, to: null as number | null });
const form = reactive({
    name: '',
    slug: '',
    type: 'merchant_partner_card' as ProviderRow['type'],
    balance_warning_threshold: 1000000,
    minimum_profit_percent: 5,
    connection_config_text: '',
    payload_field_mapping_text: '',
});

const payloadFieldMappingTemplate = (): string => JSON.stringify({ default: {}, services: {} }, null, 2);
const servicesResponseText = computed(() => (servicesResponse.value === null ? '' : JSON.stringify(servicesResponse.value, null, 2)));
let servicesCopiedTimer: ReturnType<typeof setTimeout> | null = null;
let servicesRequestSequence = 0;

const providerConfigGuides: Record<ProviderRow['type'], ProviderConfigGuide> = {
    merchant_partner_card: {
        title: 'API Merchant Partner Card',
        description: 'Dùng cho The9P, NapGame1S, NapFF hoặc provider có API tương thích chuẩn Merchant Partner Card.',
        domainExample: 'https://api.provider.example/rechargews',
        fields: [
            {
                key: 'base_url',
                required: true,
                description: 'URL endpoint nhận lệnh API của provider.',
                example: 'https://api.provider.example/rechargews',
            },
            { key: 'partner_id', required: true, description: 'Mã đại lý/đối tác do provider cấp.', example: 'partner_123' },
            { key: 'partner_key', required: true, description: 'Khóa bí mật dùng để ký request.', example: 'Nhập khóa do provider cấp' },
            {
                key: 'proxy',
                required: false,
                description: 'Proxy riêng nếu provider yêu cầu whitelist IP.',
                example: 'socks5://user:pass@proxy.example:1080',
            },
        ],
    },
    accnro: {
        title: 'API riêng của ACC NRO',
        description: 'Dùng riêng cho accnro.vn. Không dùng partner_key của chuẩn Merchant Partner Card.',
        domainExample: 'https://accnro.vn/api/v1/partner/recharge',
        fields: [
            {
                key: 'base_url',
                required: true,
                description: 'URL gốc của API; hệ thống tự nối /create, /query, /balance và /catalog.',
                example: 'https://accnro.vn/api/v1/partner/recharge',
            },
            { key: 'partner_id', required: true, description: 'Partner ID do ACC NRO cấp.', example: 'pk_partner_123' },
            { key: 'secret_key', required: true, description: 'Secret key do ACC NRO cấp.', example: 'sk_secret_key' },
            {
                key: 'proxy',
                required: false,
                description: 'Proxy riêng nếu tài khoản có giới hạn IP.',
                example: 'https://user:pass@proxy.example:8080',
            },
        ],
    },
    manual: {
        title: 'Provider xử lý thủ công',
        description: 'Không gọi API bên ngoài. Đơn hàng được quản trị viên tiếp nhận và xử lý thủ công.',
        domainExample: null,
        fields: [{ key: 'mode', required: true, description: 'Giữ giá trị manual để nhận biết provider thủ công.', example: 'manual' }],
    },
};

const merchantBaseUrlExample = (slug: string): string => {
    const knownProviderUrls: Record<string, string> = {
        the9p: 'https://the9p.com/api/rechargews',
        napgame1s: 'https://napgame1s.net/api/rechargews',
    };

    return knownProviderUrls[slug.trim().toLowerCase()] ?? 'https://api.provider.example/rechargews';
};

const selectedProviderConfigGuide = computed<ProviderConfigGuide>(() => {
    const guide = providerConfigGuides[form.type];

    if (form.type !== 'merchant_partner_card') return guide;

    const baseUrl = merchantBaseUrlExample(form.slug);

    return {
        ...guide,
        domainExample: baseUrl,
        fields: guide.fields.map((field) => (field.key === 'base_url' ? { ...field, example: baseUrl } : field)),
    };
});

const connectionConfigTemplate = (slug: string, type: ProviderRow['type'] = form.type): string => {
    if (type === 'accnro' || slug.trim().toLowerCase() === 'accnrovn') {
        return '{\n  "base_url": "https://accnro.vn/api/v1/partner/recharge",\n  "partner_id": "",\n  "secret_key": "",\n  "proxy": "",\n  "connect_timeout": 5,\n  "timeout": 20,\n  "max_status_checks": 20\n}';
    }

    if (type === 'manual') return '{\n  "mode": "manual"\n}';

    return JSON.stringify(
        {
            base_url: merchantBaseUrlExample(slug),
            partner_id: '',
            partner_key: '',
            proxy: '',
            connect_timeout: 5,
            timeout: 20,
            max_status_checks: 20,
        },
        null,
        2,
    );
};
const reset = (): void => {
    editingId.value = null;
    connectionJsonError.value = '';
    mappingJsonError.value = '';
    form.name = '';
    form.slug = '';
    form.type = 'merchant_partner_card';
    form.balance_warning_threshold = 1000000;
    form.minimum_profit_percent = 5;
    form.connection_config_text = connectionConfigTemplate(form.slug);
    form.payload_field_mapping_text = payloadFieldMappingTemplate();
};

const load = async (): Promise<void> => {
    loading.value = true;
    try {
        const response = await adminTopupService.providers({ search: filters.search || undefined, per_page: filters.per_page, page: filters.page });
        const payload = response.data.data;
        const meta = payload.meta ?? payload;
        providers.value = payload.data ?? [];
        Object.assign(pagination, {
            current_page: Number(meta.current_page || 1),
            last_page: Number(meta.last_page || 1),
            total: Number(meta.total || 0),
            from: meta.from ?? null,
            to: meta.to ?? null,
        });
        void refreshBalances();
    } finally {
        loading.value = false;
    }
};

const refreshBalances = async (): Promise<void> => {
    const providerIds = providers.value.filter((provider) => provider.supports_balance).map((provider) => provider.id);
    if (providerIds.length === 0 || refreshingBalances.value) return;

    refreshingBalances.value = true;
    try {
        const response = await adminTopupService.refreshProviderBalances(providerIds);
        const refreshed = new Map<number, ProviderRow>((response.data.data ?? []).map((provider: ProviderRow) => [provider.id, provider]));
        providers.value = providers.value.map((provider) => refreshed.get(provider.id) ?? provider);
    } finally {
        refreshingBalances.value = false;
    }
};

const formatBalance = (provider: ProviderRow): string =>
    provider.balance === null
        ? 'Chưa có dữ liệu'
        : `${new Intl.NumberFormat('vi-VN').format(provider.balance)} ${provider.balance_currency || 'VND'}`;

const formatCheckedAt = (value: string | null): string =>
    value ? new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : 'Chưa kiểm tra';

const openServices = (provider: ProviderRow): void => {
    servicesRequestSequence += 1;
    selectedServicesProvider.value = provider;
    servicesLoading.value = false;
    servicesResponse.value = null;
    servicesError.value = '';
    servicesCopied.value = false;
    servicesModalOpen.value = true;
};

const fetchServices = async (): Promise<void> => {
    if (!selectedServicesProvider.value || servicesLoading.value) return;

    const requestSequence = ++servicesRequestSequence;
    servicesLoading.value = true;
    servicesError.value = '';
    servicesCopied.value = false;

    try {
        const response = await adminTopupService.providerServices(selectedServicesProvider.value.id);
        if (requestSequence === servicesRequestSequence) servicesResponse.value = response.data;
    } catch (error) {
        if (requestSequence !== servicesRequestSequence) return;

        const apiError = error as { response?: { data?: { message?: string } }; message?: string };
        servicesResponse.value = null;
        servicesError.value = apiError.response?.data?.message || apiError.message || 'Không thể lấy services từ provider.';
    } finally {
        if (requestSequence === servicesRequestSequence) servicesLoading.value = false;
    }
};

const copyServicesResponse = async (): Promise<void> => {
    if (!servicesResponseText.value) return;

    try {
        await navigator.clipboard.writeText(servicesResponseText.value);
        servicesCopied.value = true;

        if (servicesCopiedTimer) clearTimeout(servicesCopiedTimer);
        servicesCopiedTimer = setTimeout(() => (servicesCopied.value = false), 1800);
    } catch {
        servicesError.value = 'Không thể sao chép tự động. Hãy chọn nội dung JSON và sao chép thủ công.';
    }
};

const syncQuery = async (): Promise<void> => {
    const query: Record<string, string | number> = {};
    if (filters.search) query.search = filters.search;
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
    Object.assign(filters, { search: '', per_page: '20', page: 1 });
    await syncQuery();
    await load();
};
const changePage = async (page: number): Promise<void> => {
    if (page < 1 || page > pagination.last_page || page === pagination.current_page) return;
    filters.page = page;
    await syncQuery();
    await load();
};

const edit = async (row: ProviderRow): Promise<void> => {
    connectionJsonError.value = '';
    mappingJsonError.value = '';
    const response = await adminTopupService.provider(row.id);
    const provider = response.data.data;
    editingId.value = provider.id;
    form.name = provider.name;
    form.slug = provider.slug;
    form.type = provider.type;
    const connectionConfig = { ...(provider.connection_config || {}) };
    form.balance_warning_threshold = Number(connectionConfig.balance_warning_threshold ?? 1000000);
    form.minimum_profit_percent = Number(connectionConfig.minimum_profit_percent ?? 0);
    delete connectionConfig.balance_warning_threshold;
    delete connectionConfig.minimum_profit_percent;
    form.connection_config_text = JSON.stringify(connectionConfig, null, 2);
    form.payload_field_mapping_text = JSON.stringify(provider.payload_field_mapping || { default: {}, services: {} }, null, 2);
};

const connectionConfig = (): Record<string, unknown> | null => {
    connectionJsonError.value = '';
    try {
        const parsed = JSON.parse(form.connection_config_text);
        if (parsed === null || Array.isArray(parsed) || typeof parsed !== 'object') {
            connectionJsonError.value = 'Cấu hình phải là một JSON object.';
            return null;
        }
        return parsed;
    } catch {
        connectionJsonError.value = 'JSON không hợp lệ. Vui lòng kiểm tra dấu phẩy, ngoặc và dấu nháy.';
        return null;
    }
};

const payloadFieldMapping = (): Record<string, unknown> | null => {
    mappingJsonError.value = '';
    try {
        const parsed = JSON.parse(form.payload_field_mapping_text);
        if (parsed === null || Array.isArray(parsed) || typeof parsed !== 'object') {
            mappingJsonError.value = 'Mapping field phải là một JSON object.';
            return null;
        }
        return parsed;
    } catch {
        mappingJsonError.value = 'JSON mapping field không hợp lệ.';
        return null;
    }
};

const save = async (): Promise<void> => {
    const parsedConnectionConfig = connectionConfig();
    const parsedPayloadFieldMapping = payloadFieldMapping();
    if (!parsedConnectionConfig || !parsedPayloadFieldMapping) return;
    if (!Number.isInteger(form.balance_warning_threshold) || form.balance_warning_threshold < 0 || form.balance_warning_threshold > 1000000000000) {
        connectionJsonError.value = 'Ngưỡng cảnh báo phải là số nguyên từ 0 đến 1.000.000.000.000đ.';
        return;
    }
    if (!Number.isFinite(form.minimum_profit_percent) || form.minimum_profit_percent < 0 || form.minimum_profit_percent > 99.99) {
        connectionJsonError.value = 'Phần trăm lợi nhuận tối thiểu phải từ 0 đến 99,99%.';
        return;
    }
    saving.value = true;
    try {
        await adminTopupService.saveProvider(editingId.value, {
            name: form.name,
            slug: form.slug,
            type: form.type,
            connection_config: {
                ...parsedConnectionConfig,
                balance_warning_threshold: form.balance_warning_threshold,
                minimum_profit_percent: form.minimum_profit_percent,
            },
            payload_field_mapping: parsedPayloadFieldMapping,
        });
        reset();
        await load();
    } finally {
        saving.value = false;
    }
};

const remove = async (row: ProviderRow): Promise<void> => {
    if (!window.confirm(`Xóa provider “${row.name}”?`)) return;
    await adminTopupService.deleteProvider(row.id);
    if (editingId.value === row.id) reset();
    if (providers.value.length === 1 && filters.page > 1) {
        filters.page -= 1;
        await syncQuery();
    }
    await load();
};

watch(
    () => [form.slug, form.type] as const,
    ([slug], [previousSlug, previousType]) => {
        if (editingId.value !== null) return;
        if (form.connection_config_text === connectionConfigTemplate(previousSlug, previousType)) {
            form.connection_config_text = connectionConfigTemplate(slug);
        }
    },
);

reset();
onMounted(load);
</script>

<template>
    <section class="space-y-5">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-emerald-700">Danh mục nạp game</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950">Nhà cung cấp</h1>
                <p class="mt-1 text-sm text-slate-500">Tìm kiếm provider và quản lý JSON kết nối tới hệ thống bên thứ ba.</p>
            </div>
            <button
                type="button"
                class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 shadow-sm disabled:cursor-wait disabled:opacity-60"
                :disabled="refreshingBalances"
                @click="refreshBalances"
            >
                {{ refreshingBalances ? 'Đang kiểm tra số dư...' : 'Cập nhật số dư' }}
            </button>
        </header>
        <div class="grid items-start gap-5 min-[1800px]:grid-cols-[minmax(0,1fr)_400px]">
            <div class="min-w-0 space-y-4">
                <form class="rounded-md border border-slate-200 bg-white p-4 shadow-sm" @submit.prevent="applyFilters">
                    <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_140px_auto] sm:items-end">
                        <label class="text-sm font-semibold text-slate-700"
                            >Tìm kiếm<input
                                v-model.trim="filters.search"
                                class="mt-1.5 min-h-10 w-full rounded-md border border-slate-300 px-3 font-normal"
                                placeholder="Tên hoặc slug provider..."
                        /></label>
                        <label class="text-sm font-semibold text-slate-700"
                            >Số dòng<select
                                v-model="filters.per_page"
                                class="mt-1.5 min-h-10 w-full rounded-md border border-slate-300 px-3 font-normal"
                            >
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select></label
                        >
                        <div class="flex gap-2">
                            <button type="submit" class="min-h-10 rounded-md bg-emerald-600 px-4 text-sm font-semibold text-white">Tìm</button
                            ><button
                                type="button"
                                class="min-h-10 rounded-md border border-slate-300 px-4 text-sm font-semibold text-slate-600"
                                @click="clearFilters"
                            >
                                Xóa lọc
                            </button>
                        </div>
                    </div>
                </form>
                <div class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
                    <div v-if="loading" class="p-10 text-center text-slate-500">Đang tải provider...</div>
                    <div v-else-if="providers.length === 0" class="p-10 text-center text-slate-500">Không tìm thấy provider phù hợp.</div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-[1040px] text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th class="p-4">Tên provider</th>
                                    <th class="p-4">Slug</th>
                                    <th class="p-4">Loại kết nối</th>
                                    <th class="p-4">Kết nối</th>
                                    <th class="p-4">Số dư provider</th>
                                    <th class="p-4">Services</th>
                                    <th class="p-4">Gói nạp</th>
                                    <th
                                        class="sticky right-0 z-10 whitespace-nowrap bg-slate-50 p-4 text-right shadow-[-8px_0_12px_-12px_rgba(15,23,42,0.35)]"
                                    >
                                        Thao tác
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="provider in providers" :key="provider.id" class="group hover:bg-slate-50/70">
                                    <td class="p-4 font-semibold text-slate-950">{{ provider.name }}</td>
                                    <td class="p-4 font-mono text-xs text-slate-600">{{ provider.slug }}</td>
                                    <td class="p-4 text-xs font-semibold text-slate-600">
                                        {{
                                            provider.type === 'merchant_partner_card'
                                                ? 'Merchant Partner Card'
                                                : provider.type === 'accnro'
                                                  ? 'ACC NRO'
                                                  : 'Thủ công'
                                        }}
                                    </td>
                                    <td class="p-4">
                                        <span
                                            class="rounded-md px-2 py-1 text-xs font-semibold"
                                            :class="provider.has_connection_config ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
                                            >{{ provider.has_connection_config ? 'Đã cấu hình' : 'Chưa cấu hình' }}</span
                                        >
                                    </td>
                                    <td class="p-4">
                                        <div class="min-w-[210px]">
                                            <div class="flex items-center gap-2">
                                                <span
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg"
                                                    :class="
                                                        provider.balance_status === 'success'
                                                            ? 'bg-emerald-50 text-emerald-700'
                                                            : provider.balance_status === 'failed'
                                                              ? 'bg-rose-50 text-rose-700'
                                                              : 'bg-slate-100 text-slate-500'
                                                    "
                                                    aria-hidden="true"
                                                    >₫</span
                                                >
                                                <div>
                                                    <p class="font-bold text-slate-950">{{ formatBalance(provider) }}</p>
                                                    <p class="mt-0.5 text-xs text-slate-500">
                                                        {{
                                                            refreshingBalances && provider.supports_balance
                                                                ? 'Đang cập nhật...'
                                                                : formatCheckedAt(provider.balance_checked_at)
                                                        }}
                                                    </p>
                                                </div>
                                            </div>
                                            <div
                                                v-if="provider.balance_status === 'failed'"
                                                class="mt-2 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-2 text-xs leading-5 text-rose-700"
                                            >
                                                <p class="font-bold">{{ provider.balance_error_code }}</p>
                                                <p>{{ provider.balance_error_message }}</p>
                                                <p v-if="provider.balance !== null" class="mt-1 font-semibold">Đang hiển thị số dư gần nhất.</p>
                                            </div>
                                            <span
                                                v-else-if="provider.balance_status === 'success'"
                                                class="mt-2 inline-flex rounded-full bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-700"
                                            >
                                                Kết nối tốt
                                            </span>
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <button
                                            type="button"
                                            class="whitespace-nowrap rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-bold text-indigo-700 hover:bg-indigo-100 disabled:cursor-not-allowed disabled:opacity-50"
                                            :disabled="provider.type === 'manual'"
                                            @click="openServices(provider)"
                                        >
                                            {{ provider.type === 'manual' ? 'Không hỗ trợ' : 'Xem services' }}
                                        </button>
                                    </td>
                                    <td class="p-4 font-semibold">{{ provider.packages_count || 0 }}</td>
                                    <td
                                        class="sticky right-0 z-10 whitespace-nowrap bg-white p-4 text-right shadow-[-8px_0_12px_-12px_rgba(15,23,42,0.35)] transition-colors group-hover:bg-slate-50"
                                    >
                                        <button type="button" class="mr-3 font-semibold text-emerald-700" @click="edit(provider)">Sửa</button
                                        ><button type="button" class="font-semibold text-rose-600" @click="remove(provider)">Xóa</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <footer
                        class="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <span>Hiển thị {{ pagination.from || 0 }}–{{ pagination.to || 0 }} trong {{ pagination.total }} kết quả</span>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="rounded-md border border-slate-300 px-3 py-2 font-semibold disabled:opacity-40"
                                :disabled="pagination.current_page <= 1"
                                @click="changePage(pagination.current_page - 1)"
                            >
                                Trước</button
                            ><span>Trang {{ pagination.current_page }}/{{ pagination.last_page }}</span
                            ><button
                                type="button"
                                class="rounded-md border border-slate-300 px-3 py-2 font-semibold disabled:opacity-40"
                                :disabled="pagination.current_page >= pagination.last_page"
                                @click="changePage(pagination.current_page + 1)"
                            >
                                Sau
                            </button>
                        </div>
                    </footer>
                </div>
            </div>
            <form class="h-fit rounded-md border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="save">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="font-bold text-slate-950">{{ editingId ? 'Cập nhật provider' : 'Tạo provider mới' }}</h2>
                    <button v-if="editingId" type="button" class="text-sm font-semibold text-slate-500" @click="reset">Hủy</button>
                </div>
                <div class="mt-5 grid gap-4">
                    <label class="text-sm font-semibold text-slate-700"
                        >Tên<input v-model.trim="form.name" required class="mt-2 min-h-11 w-full rounded-md border border-slate-300 px-3"
                    /></label>
                    <label class="text-sm font-semibold text-slate-700"
                        >Slug<input
                            v-model.trim="form.slug"
                            required
                            pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                            class="mt-2 min-h-11 w-full rounded-md border border-slate-300 px-3"
                    /></label>
                    <label class="text-sm font-semibold text-slate-700"
                        >Loại kết nối<select v-model="form.type" class="mt-2 min-h-11 w-full rounded-md border border-slate-300 px-3 font-normal">
                            <option value="merchant_partner_card">Merchant Partner Card (the9p, napgame1s, napff...)</option>
                            <option value="accnro">ACC NRO riêng</option>
                            <option value="manual">Xử lý thủ công</option>
                        </select></label
                    >
                    <section class="rounded-lg border border-sky-200 bg-sky-50/70 p-3" aria-live="polite">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-bold text-sky-950">Cần nhập gì? · {{ selectedProviderConfigGuide.title }}</p>
                                <p class="mt-1 text-xs leading-5 text-sky-800">{{ selectedProviderConfigGuide.description }}</p>
                            </div>
                            <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-bold text-sky-700 shadow-sm">Theo loại đã chọn</span>
                        </div>
                        <div v-if="selectedProviderConfigGuide.domainExample" class="mt-3 rounded-md border border-sky-200 bg-white px-3 py-2">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Ví dụ base_url</p>
                            <code class="mt-1 block break-all text-xs font-semibold text-sky-800">{{
                                selectedProviderConfigGuide.domainExample
                            }}</code>
                            <p v-if="form.type === 'merchant_partner_card'" class="mt-1 text-[11px] leading-4 text-amber-700">
                                Domain <code>.example</code> chỉ là mẫu; thay bằng domain thật do provider cung cấp trước khi lưu.
                            </p>
                        </div>
                        <ul class="mt-3 grid gap-2">
                            <li
                                v-for="field in selectedProviderConfigGuide.fields"
                                :key="field.key"
                                class="rounded-md border border-sky-100 bg-white px-3 py-2"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <code class="text-xs font-bold text-slate-900">{{ field.key }}</code>
                                    <span
                                        class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide"
                                        :class="field.required ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-600'"
                                    >
                                        {{ field.required ? 'Bắt buộc' : 'Tùy chọn' }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs leading-5 text-slate-600">{{ field.description }}</p>
                                <p class="mt-1 break-all text-[11px] text-slate-500">
                                    Ví dụ: <code>{{ field.example }}</code>
                                </p>
                            </li>
                        </ul>
                        <p v-if="form.type !== 'manual'" class="mt-3 text-[11px] leading-4 text-sky-800">
                            Các trường thời gian chờ đã có sẵn trong JSON mẫu: <code>connect_timeout</code> 5 giây, <code>timeout</code> 20 giây và
                            <code>max_status_checks</code> 20 lần.
                        </p>
                    </section>
                    <label class="text-sm font-semibold text-slate-700"
                        >Ngưỡng cảnh báo số dư
                        <div class="relative mt-2">
                            <input
                                v-model.number="form.balance_warning_threshold"
                                type="number"
                                required
                                min="0"
                                max="1000000000000"
                                step="1000"
                                class="min-h-11 w-full rounded-md border border-slate-300 px-3 pr-12"
                            />
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm font-semibold text-slate-500"
                                >đ</span
                            >
                        </div>
                        <small class="mt-1.5 block font-normal text-slate-500">Mặc định 1.000.000đ. Nhập 0 để tắt cảnh báo Discord.</small>
                    </label>
                    <label class="text-sm font-semibold text-slate-700"
                        >Lợi nhuận tối thiểu tự động
                        <div class="relative mt-2">
                            <input
                                v-model.number="form.minimum_profit_percent"
                                type="number"
                                required
                                min="0"
                                max="99.99"
                                step="0.01"
                                class="min-h-11 w-full rounded-md border border-slate-300 px-3 pr-10"
                            />
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm font-semibold text-slate-500"
                                >%</span
                            >
                        </div>
                        <small class="mt-1.5 block font-normal leading-5 text-slate-500">
                            Cron chỉ tăng giá bán khi biên lãi thấp hơn mức này. Công thức: (giá bán − giá provider) / giá bán. Nhập 0 để tắt tự động
                            tăng giá cho provider này.
                        </small>
                    </label>
                    <label class="text-sm font-semibold text-slate-700"
                        >JSON cấu hình kết nối<textarea
                            v-model="form.connection_config_text"
                            rows="14"
                            required
                            spellcheck="false"
                            class="mt-2 w-full rounded-md border border-slate-300 px-3 py-3 font-mono text-xs leading-5"
                        ></textarea
                        ><small class="mt-1.5 block font-normal text-slate-500"
                            >Secret hiển thị ******** khi sửa. Giữ nguyên chuỗi này để bảo toàn giá trị cũ.</small
                        ><small v-if="connectionJsonError" class="mt-1.5 block font-semibold text-rose-600">{{ connectionJsonError }}</small></label
                    >
                    <label class="text-sm font-semibold text-slate-700"
                        >JSON mapping field payload<textarea
                            v-model="form.payload_field_mapping_text"
                            rows="10"
                            required
                            spellcheck="false"
                            class="mt-2 w-full rounded-md border border-slate-300 px-3 py-3 font-mono text-xs leading-5"
                        ></textarea
                        ><small class="mt-1.5 block font-normal leading-5 text-slate-500">
                            Field trong game giữ nguyên; provider đổi tên khi gửi. Ví dụ service HSO:
                            <code>{ "services": { "hso": { "username": "user_account", "character": "charactor" } } }</code>
                        </small>
                        <small v-if="mappingJsonError" class="mt-1.5 block font-semibold text-rose-600">{{ mappingJsonError }}</small></label
                    >
                    <button
                        type="submit"
                        class="min-h-11 rounded-md bg-emerald-600 px-4 font-semibold text-white hover:bg-emerald-700 disabled:cursor-wait disabled:opacity-60"
                        :disabled="saving"
                    >
                        {{ saving ? 'Đang lưu...' : editingId ? 'Lưu thay đổi' : 'Tạo provider' }}
                    </button>
                </div>
            </form>
        </div>

        <Modal v-model="servicesModalOpen" panel-class="max-w-5xl">
            <template #header>
                <header class="border-b border-slate-200 px-5 py-4 pr-14">
                    <p class="text-xs font-bold uppercase tracking-wider text-indigo-600">Provider services</p>
                    <h2 class="mt-1 text-lg font-bold text-slate-950">
                        {{ selectedServicesProvider?.name || 'Provider' }}
                    </h2>
                    <p v-if="selectedServicesProvider" class="mt-1 font-mono text-xs text-slate-500">
                        {{ selectedServicesProvider.slug }}
                    </p>
                </header>
            </template>

            <div class="grid gap-4 p-5">
                <div class="rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm leading-6 text-sky-900">
                    Request được gửi từ server của website để dùng IP đã whitelist. Response provider được lọc các khóa nhạy cảm trước khi hiển thị.
                </div>
                <div
                    v-if="servicesError"
                    class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700"
                    role="alert"
                >
                    {{ servicesError }}
                </div>
                <pre
                    v-if="servicesResponse !== null"
                    class="max-h-[60vh] overflow-auto rounded-md bg-slate-950 p-4 text-xs leading-5 text-slate-100"
                    >{{ servicesResponseText }}</pre
                >
                <div v-else class="rounded-md border border-dashed border-slate-300 px-5 py-12 text-center text-sm text-slate-500">
                    {{ servicesLoading ? 'Đang gọi API provider...' : 'Bấm “Lấy services” để gọi API provider.' }}
                </div>
            </div>

            <template #footer>
                <footer class="flex flex-wrap justify-end gap-2 border-t border-slate-200 px-5 py-4">
                    <button
                        type="button"
                        class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700"
                        @click="servicesModalOpen = false"
                    >
                        Đóng
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="servicesResponse === null"
                        @click="copyServicesResponse"
                    >
                        {{ servicesCopied ? 'Đã sao chép' : 'Sao chép response' }}
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-wait disabled:opacity-60"
                        :disabled="servicesLoading"
                        @click="fetchServices"
                    >
                        {{ servicesLoading ? 'Đang lấy...' : 'Lấy services' }}
                    </button>
                </footer>
            </template>
        </Modal>
        <p class="sr-only" aria-live="polite">{{ servicesCopied ? 'Đã sao chép response.' : '' }}</p>
    </section>
</template>
