<script setup lang="ts">
import { adminTopupService } from '@/services/admin-topup.service';
import { onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

type ProviderRow = {
    id: number;
    name: string;
    slug: string;
    has_connection_config: boolean;
    packages_count?: number;
    supports_balance: boolean;
    balance: number | null;
    balance_currency: string | null;
    balance_status: 'unchecked' | 'success' | 'failed';
    balance_checked_at: string | null;
    balance_error_code: string | null;
    balance_error_message: string | null;
};

const route = useRoute();
const router = useRouter();
const providers = ref<ProviderRow[]>([]);
const loading = ref(false);
const saving = ref(false);
const refreshingBalances = ref(false);
const editingId = ref<number | null>(null);
const connectionJsonError = ref('');
const filters = reactive({
    search: typeof route.query.search === 'string' ? route.query.search : '',
    per_page: typeof route.query.per_page === 'string' ? route.query.per_page : '20',
    page: Math.max(Number(route.query.page || 1), 1),
});
const pagination = reactive({ current_page: 1, last_page: 1, total: 0, from: null as number | null, to: null as number | null });
const form = reactive({ name: '', slug: '', balance_warning_threshold: 1000000, connection_config_text: '' });

const connectionConfigTemplate = (slug: string): string => {
    if (slug.trim().toLowerCase() === 'accnrovn') {
        return '{\n  "base_url": "https://accnro.vn/api/v1/partner/recharge",\n  "partner_id": "",\n  "secret_key": "",\n  "connect_timeout": 5,\n  "timeout": 20,\n  "max_status_checks": 20\n}';
    }

    return '{\n  "base_url": "https://the9p.com/api/rechargews",\n  "partner_id": "",\n  "partner_key": "",\n  "connect_timeout": 5,\n  "timeout": 20,\n  "max_status_checks": 20\n}';
};
const reset = (): void => {
    editingId.value = null;
    connectionJsonError.value = '';
    form.name = '';
    form.slug = '';
    form.balance_warning_threshold = 1000000;
    form.connection_config_text = connectionConfigTemplate(form.slug);
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
    const response = await adminTopupService.provider(row.id);
    const provider = response.data.data;
    editingId.value = provider.id;
    form.name = provider.name;
    form.slug = provider.slug;
    const connectionConfig = { ...(provider.connection_config || {}) };
    form.balance_warning_threshold = Number(connectionConfig.balance_warning_threshold ?? 1000000);
    delete connectionConfig.balance_warning_threshold;
    form.connection_config_text = JSON.stringify(connectionConfig, null, 2);
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

const save = async (): Promise<void> => {
    const parsedConnectionConfig = connectionConfig();
    if (!parsedConnectionConfig) return;
    if (!Number.isInteger(form.balance_warning_threshold) || form.balance_warning_threshold < 0 || form.balance_warning_threshold > 1000000000000) {
        connectionJsonError.value = 'Ngưỡng cảnh báo phải là số nguyên từ 0 đến 1.000.000.000.000đ.';
        return;
    }
    saving.value = true;
    try {
        await adminTopupService.saveProvider(editingId.value, {
            name: form.name,
            slug: form.slug,
            connection_config: { ...parsedConnectionConfig, balance_warning_threshold: form.balance_warning_threshold },
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
    () => form.slug,
    (slug, previousSlug) => {
        if (editingId.value !== null) return;
        if (form.connection_config_text === connectionConfigTemplate(previousSlug)) {
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
                        <table class="w-full min-w-[900px] text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th class="p-4">Tên provider</th>
                                    <th class="p-4">Slug</th>
                                    <th class="p-4">Kết nối</th>
                                    <th class="p-4">Số dư provider</th>
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
    </section>
</template>
