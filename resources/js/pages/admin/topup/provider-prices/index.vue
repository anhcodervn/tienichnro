<script setup lang="ts">
import { adminSettingService } from '@/services/admin-setting.service';
import { adminTopupService } from '@/services/admin-topup.service';
import type { TaxSettingType } from '@/types/setting.type';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { AlertTriangle, CircleDollarSign, Filter, LoaderCircle, RefreshCw, Search, ServerCog } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import ProviderPriceCell from './components/ProviderPriceCell.vue';
import ProviderPriceModal from './components/ProviderPriceModal.vue';
import { formatMoney, providerQuote, type PriceRow, type Provider, type ProviderSelection } from './types';

const rows = ref<PriceRow[]>([]);
const providers = ref<Provider[]>([]);
const loading = ref(false);
const refreshing = ref(false);
const saving = ref(false);
const taxSettings = ref<TaxSettingType>({
    tax_enabled: false,
    tax_calculation_type: 'revenue',
    vat_rate: '1.0000',
    pit_rate: '0.5000',
});
const filters = reactive({ search: '', scope: 'all', provider_id: '' as string | number, status: 'all' });
const modal = reactive<{ open: boolean; row: PriceRow | null; providerId: number | null }>({ open: false, row: null, providerId: null });

const normalizeRows = (packageRows: PriceRow[], providerRows: Provider[]): PriceRow[] =>
    packageRows.map((row) => ({
        ...row,
        provider_prices: Object.fromEntries(providerRows.map((provider) => [String(provider.id), row.provider_prices[String(provider.id)] ?? null])),
    }));

const matchesStatus = (row: PriceRow): boolean => {
    if (filters.status === 'all') return true;
    if (filters.status === 'unselected') return row.provider_id === null;
    if (filters.status === 'loss') return row.provider_id !== null && row.price - row.provider_price <= 0;
    if (filters.status === 'cheapest') return row.provider_id !== null && row.provider_id === row.best_provider_id;
    if (filters.status === 'cheaper') {
        const bestPrice = row.best_provider_id ? providerQuote(row, row.best_provider_id) : null;
        return row.provider_id !== null && row.best_provider_id !== row.provider_id && bestPrice !== null && bestPrice < row.provider_price;
    }
    if (filters.status === 'provider_error') {
        return providers.value.some((provider) => provider.price_sync_status === 'failed');
    }

    return true;
};

const visibleRows = computed(() => rows.value.filter(matchesStatus));
const summary = computed(() => ({
    total: rows.value.length,
    cheaper: rows.value.filter((row) => {
        const bestPrice = row.best_provider_id ? providerQuote(row, row.best_provider_id) : null;
        return row.provider_id !== null && row.best_provider_id !== row.provider_id && bestPrice !== null && bestPrice < row.provider_price;
    }).length,
    cheapest: rows.value.filter((row) => row.provider_id !== null && row.provider_id === row.best_provider_id).length,
}));
const latestConnectionCheck = computed(() => {
    const checked = providers.value
        .map((provider) => provider.price_synced_at)
        .filter((value): value is string => Boolean(value))
        .sort()
        .at(-1);
    if (!checked) return 'Chưa đồng bộ giá';

    return `Cập nhật giá ${new Intl.DateTimeFormat('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit' }).format(new Date(checked))}`;
});

const rowKey = (row: PriceRow): string => `${row.scope}-${row.id}`;
const providerStatus = (provider: Provider): { label: string; classes: string } => {
    if (provider.price_sync_status === 'success') return { label: 'Đã lấy giá', classes: 'bg-emerald-500' };
    if (provider.price_sync_status === 'failed') return { label: 'API lỗi', classes: 'bg-rose-500' };
    return { label: 'Chưa đồng bộ', classes: 'bg-slate-400' };
};

const load = async (background = false): Promise<void> => {
    if (background) refreshing.value = true;
    else loading.value = true;

    try {
        const response = await adminTopupService.providerPrices({
            search: filters.search || undefined,
            scope: filters.scope === 'all' ? undefined : filters.scope,
            provider_id: filters.provider_id || undefined,
        });
        providers.value = response.data.data.providers;
        rows.value = normalizeRows(response.data.data.packages, providers.value);
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
        refreshing.value = false;
    }
};

const loadTaxSettings = async (): Promise<void> => {
    try {
        const response = await adminSettingService.getTax();
        taxSettings.value = response.settings;
    } catch (error) {
        handleErrorResponse(error);
    }
};

const refreshPrices = async (showFeedback = true): Promise<void> => {
    if (refreshing.value) return;
    refreshing.value = true;

    try {
        const response = await adminTopupService.refreshProviderPrices({
            search: filters.search || undefined,
            scope: filters.scope === 'all' ? undefined : filters.scope,
            provider_id: filters.provider_id || undefined,
        });
        providers.value = response.data.data.providers;
        rows.value = normalizeRows(response.data.data.packages, providers.value);
        if (showFeedback) handleSuccessResponse(response);
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        refreshing.value = false;
    }
};

const clearFilters = async (): Promise<void> => {
    Object.assign(filters, { search: '', scope: 'all', provider_id: '', status: 'all' });
    await load();
};

const openProviderModal = (row: PriceRow, provider: Provider): void => {
    modal.row = row;
    modal.providerId = provider.id;
    modal.open = true;
};

const closeProviderModal = (): void => {
    if (saving.value) return;
    modal.open = false;
};

const saveProviderSelection = async (selection: ProviderSelection): Promise<void> => {
    if (!modal.row) return;

    saving.value = true;
    try {
        const response = await adminTopupService.selectPackageProvider(
            modal.row.scope,
            modal.row.id,
            selection.providerId,
            selection.providerPrice,
            selection.salePrice,
        );
        Object.assign(modal.row, normalizeRows([response.data.data], providers.value)[0]);
        modal.open = false;
        handleSuccessResponse(response);
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
};

onMounted(() => {
    void Promise.all([load(), loadTaxSettings()]).then(() => refreshPrices(false));
});
</script>

<template>
    <main class="grid gap-5 p-4 sm:p-6">
        <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="flex items-center gap-1.5 text-xs font-black uppercase tracking-[0.16em] text-blue-700">
                    <ServerCog class="h-4 w-4" /> Topup Operations
                </p>
                <h1 class="mt-2 text-2xl font-black text-slate-950 sm:text-3xl">So sánh giá provider</h1>
                <p class="mt-1 text-sm text-slate-500">So sánh giá nguồn từ các provider và chọn nguồn tối ưu cho từng gói nạp.</p>
            </div>
            <div class="flex flex-col items-start gap-2 sm:flex-row sm:items-center">
                <span class="text-xs font-semibold text-slate-500">{{ latestConnectionCheck }}</span>
                <button
                    type="button"
                    :disabled="refreshing"
                    class="ui-focus inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-blue-200 bg-white px-4 font-black text-blue-700 hover:bg-blue-50 disabled:opacity-50"
                    @click="refreshPrices()"
                >
                    <RefreshCw class="h-4 w-4" :class="refreshing && 'animate-spin'" /> Làm mới giá
                </button>
            </div>
        </header>

        <section class="grid gap-3 sm:grid-cols-3">
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-bold uppercase text-slate-500">Gói đang hoạt động</p>
                <p class="mt-1 text-2xl font-black text-slate-950">{{ summary.total }}</p>
            </article>
            <article class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-xs font-bold uppercase text-emerald-700">Đang dùng giá rẻ nhất</p>
                <p class="mt-1 text-2xl font-black text-emerald-950">{{ summary.cheapest }}</p>
            </article>
            <article class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <p class="text-xs font-bold uppercase text-amber-700">Có nguồn rẻ hơn</p>
                <p class="mt-1 text-2xl font-black text-amber-950">{{ summary.cheaper }}</p>
            </article>
        </section>

        <form
            class="grid gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm xl:grid-cols-[minmax(16rem,1fr)_13rem_15rem_14rem_auto]"
            @submit.prevent="load()"
        >
            <label class="relative"
                ><span class="sr-only">Tìm game hoặc tên gói</span
                ><Search class="pointer-events-none absolute left-3 top-3.5 h-4 w-4 text-slate-400" /><input
                    v-model.trim="filters.search"
                    class="ui-focus min-h-11 w-full rounded-lg border-slate-300 pl-10 text-sm"
                    placeholder="Tìm game hoặc tên gói..."
            /></label>
            <select v-model="filters.scope" aria-label="Loại gói" class="ui-focus min-h-11 rounded-lg border-slate-300 text-sm font-semibold">
                <option value="all">Tất cả loại gói</option>
                <option value="package">Gói riêng</option>
                <option value="global">Gói Global</option>
            </select>
            <select v-model="filters.provider_id" aria-label="Provider" class="ui-focus min-h-11 rounded-lg border-slate-300 text-sm font-semibold">
                <option value="">Tất cả provider</option>
                <option v-for="provider in providers" :key="provider.id" :value="provider.id">{{ provider.name }}</option>
            </select>
            <select v-model="filters.status" aria-label="Trạng thái" class="ui-focus min-h-11 rounded-lg border-slate-300 text-sm font-semibold">
                <option value="all">Tất cả trạng thái</option>
                <option value="cheaper">Có nguồn rẻ hơn</option>
                <option value="cheapest">Đang dùng rẻ nhất</option>
                <option value="loss">Không có lãi</option>
                <option value="unselected">Chưa gắn nguồn</option>
                <option value="provider_error">Provider có lỗi</option>
            </select>
            <div class="flex gap-2">
                <button
                    class="ui-focus inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 font-black text-white hover:bg-blue-700"
                >
                    <Filter class="h-4 w-4" /> Lọc</button
                ><button
                    type="button"
                    class="ui-focus min-h-11 rounded-lg border border-slate-300 px-3 text-sm font-bold text-slate-600 hover:bg-slate-50"
                    @click="clearFilters"
                >
                    Xóa
                </button>
            </div>
        </form>

        <div v-if="loading" class="grid min-h-64 place-items-center rounded-xl border border-slate-200 bg-white">
            <LoaderCircle class="h-8 w-8 animate-spin text-blue-500" />
        </div>
        <section v-else class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full min-w-max border-separate border-spacing-0 text-sm">
                    <thead class="text-left text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="sticky left-0 z-30 min-w-72 border-b border-r border-slate-200 bg-slate-50 p-4">Loại gói / Tên gói</th>
                            <th class="sticky left-72 z-30 min-w-44 border-b border-r border-slate-200 bg-slate-50 p-4 text-right">Hiện tại</th>
                            <th v-for="provider in providers" :key="provider.id" class="min-w-72 border-b border-r border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-center gap-3">
                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-slate-900 text-xs font-black text-white">{{
                                        provider.name.slice(0, 2).toUpperCase()
                                    }}</span>
                                    <div class="min-w-0">
                                        <p class="truncate font-black text-slate-900">{{ provider.name }}</p>
                                        <p
                                            class="mt-1 flex items-center gap-1.5 text-[11px] font-semibold normal-case text-slate-500"
                                            :title="provider.price_sync_error_message ?? 'Trạng thái đồng bộ bảng giá gần nhất'"
                                        >
                                            <span class="h-2 w-2 rounded-full" :class="providerStatus(provider).classes" />{{
                                                providerStatus(provider).label
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in visibleRows" :key="rowKey(row)" class="group">
                            <td class="sticky left-0 z-20 border-b border-r border-slate-200 bg-white p-4 align-top group-hover:bg-slate-50">
                                <span
                                    class="rounded-md px-2 py-1 text-[10px] font-black uppercase"
                                    :class="row.scope === 'global' ? 'bg-violet-100 text-violet-700' : 'bg-blue-100 text-blue-700'"
                                    >{{ row.scope === 'global' ? 'Global' : row.game_name }}</span
                                >
                                <p class="mt-2 max-w-64 font-black text-slate-950">{{ row.name }}</p>
                                <p class="mt-1 text-xs font-semibold text-slate-500">
                                    {{ row.provider_name ? `Nguồn: ${row.provider_name}` : 'Chưa chọn nguồn' }}
                                </p>
                            </td>
                            <td
                                class="sticky left-72 z-20 border-b border-r border-slate-200 bg-white p-4 text-right align-top group-hover:bg-slate-50"
                            >
                                <p class="font-black text-slate-950">{{ formatMoney(row.denomination) }}</p>
                                <p class="mt-1 text-xs font-semibold text-slate-500">Giá bán</p>
                                <p class="font-bold text-blue-700">{{ formatMoney(row.price) }}</p>
                            </td>
                            <td v-for="provider in providers" :key="provider.id" class="border-b border-r border-slate-200 p-2 align-top">
                                <ProviderPriceCell :row="row" :provider="provider" :disabled="saving" @select="openProviderModal" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="visibleRows.length === 0" class="grid place-items-center gap-2 p-12 text-center text-slate-500">
                <CircleDollarSign class="h-10 w-10" />
                <p>Không có gói đang hoạt động phù hợp bộ lọc.</p>
            </div>
            <div
                v-else-if="providers.length === 0"
                class="flex items-center gap-2 border-t border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-800"
            >
                <AlertTriangle class="h-4 w-4" /> Hãy tạo provider trước để nhập và so sánh báo giá.
            </div>
        </section>

        <ProviderPriceModal
            :open="modal.open"
            :row="modal.row"
            :providers="providers"
            :tax-settings="taxSettings"
            :initial-provider-id="modal.providerId"
            :saving="saving"
            @close="closeProviderModal"
            @submit="saveProviderSelection"
        />
    </main>
</template>
