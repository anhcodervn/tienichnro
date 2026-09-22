<script setup lang="ts">
import Modal from '@/components/shared/Modal/index.vue';
import { adminUserService, type AdminUserDiscountItem, type AdminUserListItem, type AdminUserPricingMode } from '@/services/admin-user.service';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { BadgePercent, Check, ChevronLeft, ChevronRight, Layers3, LoaderCircle, Search, Sparkles, Users } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';

const rows = ref<AdminUserDiscountItem[]>([]);
const loading = ref(false);
const saving = ref(false);
const modalOpen = ref(false);
const candidateLoading = ref(false);
const candidates = ref<AdminUserListItem[]>([]);
const selectedIds = ref<number[]>([]);
const selectedCandidateIds = ref<number[]>([]);
const candidateSearch = ref('');
const formError = ref('');

const filters = reactive({
    search: '',
    rule_status: '' as '' | 'active' | 'inactive',
    scope: '' as '' | 'packages' | 'global',
    per_page: 15,
    page: 1,
});
const meta = reactive({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
const stats = reactive({ discounted_users: 0, active_users: 0, package_rules: 0, global_rules: 0 });
const bulkForm = reactive({
    scope: 'all' as 'packages' | 'global' | 'all',
    discount_percent: 5,
});

const allPageSelected = computed(() => rows.value.length > 0 && rows.value.every((row) => selectedIds.value.includes(row.id)));
const activeRuleCount = (row: AdminUserDiscountItem): number => row.active_package_rules_count + row.active_global_package_rules_count;
const totalRuleCount = (row: AdminUserDiscountItem): number => row.package_rules_count + row.global_rules_count;

const formatNumber = (value: number): string => new Intl.NumberFormat('vi-VN').format(value);
const formatDate = (value: string | null): string => {
    if (!value) return '--';

    return new Intl.DateTimeFormat('vi-VN', {
        hour: '2-digit',
        minute: '2-digit',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(value));
};
const pricingModeLabel = (mode: AdminUserPricingMode): string => {
    if (mode === 'fixed') return 'Giá cố định';
    if (mode === 'profit') return 'Theo lợi nhuận';

    return 'Chiết khấu %';
};

const fetchRows = async (): Promise<void> => {
    loading.value = true;

    try {
        const response = await adminUserService.discounts({
            search: filters.search || undefined,
            rule_status: filters.rule_status || undefined,
            scope: filters.scope || undefined,
            per_page: filters.per_page,
            page: filters.page,
        });
        rows.value = response.data;
        Object.assign(meta, response.meta);
        Object.assign(stats, response.stats);
        selectedIds.value = selectedIds.value.filter((id) => rows.value.some((row) => row.id === id));
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

const applyFilters = async (): Promise<void> => {
    filters.page = 1;
    await fetchRows();
};

const resetFilters = async (): Promise<void> => {
    filters.search = '';
    filters.rule_status = '';
    filters.scope = '';
    filters.page = 1;
    await fetchRows();
};

const goToPage = async (page: number): Promise<void> => {
    if (page < 1 || page > meta.last_page || page === meta.current_page) return;
    filters.page = page;
    await fetchRows();
};

const togglePage = (): void => {
    if (allPageSelected.value) {
        selectedIds.value = [];
        return;
    }

    selectedIds.value = rows.value.map((row) => row.id);
};

const toggleSelected = (id: number): void => {
    selectedIds.value = selectedIds.value.includes(id) ? selectedIds.value.filter((selectedId) => selectedId !== id) : [...selectedIds.value, id];
};

const loadCandidates = async (): Promise<void> => {
    candidateLoading.value = true;

    try {
        const response = await adminUserService.list({
            search: candidateSearch.value || undefined,
            role: 'user',
            per_page: 100,
            page: 1,
        });
        candidates.value = response.data;
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        candidateLoading.value = false;
    }
};

const openBulkModal = async (): Promise<void> => {
    selectedCandidateIds.value = [...selectedIds.value];
    formError.value = '';
    modalOpen.value = true;
    await loadCandidates();
};

const toggleCandidate = (id: number): void => {
    selectedCandidateIds.value = selectedCandidateIds.value.includes(id)
        ? selectedCandidateIds.value.filter((selectedId) => selectedId !== id)
        : [...selectedCandidateIds.value, id];
};

const submitBulk = async (): Promise<void> => {
    formError.value = '';

    if (selectedCandidateIds.value.length === 0) {
        formError.value = 'Hãy chọn ít nhất một người dùng.';
        return;
    }

    const percent = Number(bulkForm.discount_percent);
    if (!Number.isFinite(percent) || percent <= 0 || percent > 100) {
        formError.value = 'Phần trăm chiết khấu phải lớn hơn 0 và không vượt quá 100.';
        return;
    }

    saving.value = true;
    try {
        const result = await adminUserService.bulkSetDiscounts({
            user_ids: selectedCandidateIds.value,
            scope: bulkForm.scope,
            discount_percent: percent,
        });
        handleSuccessResponse({
            data: {
                status: true,
                message: `Đã cập nhật ${result.users_updated} user với ${formatNumber(result.package_rules_upserted + result.global_rules_upserted)} rule giá.`,
            },
        });
        modalOpen.value = false;
        selectedIds.value = [];
        await fetchRows();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
};

watch(
    () => filters.per_page,
    async () => {
        filters.page = 1;
        await fetchRows();
    },
);

onMounted(fetchRows);
</script>

<template>
    <div class="space-y-6">
        <section
            class="relative overflow-hidden rounded-[10px] border border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(70,95,255,0.14),_transparent_30%),linear-gradient(180deg,_#ffffff_0%,_#fbfcff_100%)] p-5 shadow-[0_16px_40px_rgba(15,23,42,0.06)]"
        >
            <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-2xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#465fff]">User pricing</p>
                    <h1 class="mt-2 text-[28px] font-black tracking-tight text-slate-950">Quản lý user chiết khấu</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-500">
                        Theo dõi toàn bộ người dùng đang có giá riêng và áp dụng một mức chiết khấu cho nhiều tài khoản cùng lúc.
                    </p>
                </div>
                <button
                    type="button"
                    class="ui-focus inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-[#465fff] px-5 text-sm font-black text-white shadow-[0_12px_28px_rgba(70,95,255,0.28)] transition hover:bg-[#3348e8]"
                    @click="openBulkModal"
                >
                    <Sparkles class="h-4 w-4" /> Set chiết khấu hàng loạt
                </button>
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">User có giá riêng</p>
                        <p class="mt-1 text-3xl font-black">{{ formatNumber(stats.discounted_users) }}</p>
                    </div>
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-indigo-50 text-indigo-600"><Users class="h-5 w-5" /></span>
                </div>
            </article>
            <article class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Đang áp dụng</p>
                        <p class="mt-1 text-3xl font-black">{{ formatNumber(stats.active_users) }}</p>
                    </div>
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-emerald-50 text-emerald-600"><Check class="h-5 w-5" /></span>
                </div>
            </article>
            <article class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Rule gói riêng</p>
                        <p class="mt-1 text-3xl font-black">{{ formatNumber(stats.package_rules) }}</p>
                    </div>
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-blue-50 text-blue-600"><Layers3 class="h-5 w-5" /></span>
                </div>
            </article>
            <article class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Rule Global</p>
                        <p class="mt-1 text-3xl font-black">{{ formatNumber(stats.global_rules) }}</p>
                    </div>
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-amber-50 text-amber-600"><BadgePercent class="h-5 w-5" /></span>
                </div>
            </article>
        </section>

        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid gap-3 lg:grid-cols-[1fr_180px_180px_auto]">
                <label class="flex min-h-11 items-center gap-3 rounded-lg border border-slate-300 bg-white px-3">
                    <Search class="h-4 w-4 text-slate-400" />
                    <input
                        v-model="filters.search"
                        class="w-full border-0 bg-transparent p-0 text-sm outline-none"
                        placeholder="Tìm ID, tên, email, số điện thoại..."
                        @keyup.enter="applyFilters"
                    />
                </label>
                <select v-model="filters.scope" class="ui-focus min-h-11 rounded-lg border border-slate-300 bg-white px-3 text-sm">
                    <option value="">Tất cả phạm vi</option>
                    <option value="packages">Gói riêng</option>
                    <option value="global">Gói Global</option>
                </select>
                <select v-model="filters.rule_status" class="ui-focus min-h-11 rounded-lg border border-slate-300 bg-white px-3 text-sm">
                    <option value="">Mọi trạng thái</option>
                    <option value="active">Đang áp dụng</option>
                    <option value="inactive">Đã tắt</option>
                </select>
                <div class="flex gap-2">
                    <button type="button" class="ui-focus min-h-11 rounded-lg bg-slate-900 px-5 text-sm font-bold text-white" @click="applyFilters">
                        Lọc
                    </button>
                    <button
                        type="button"
                        class="ui-focus min-h-11 rounded-lg border border-slate-300 px-4 text-sm font-bold text-slate-600"
                        @click="resetFilters"
                    >
                        Đặt lại
                    </button>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-[0_16px_40px_rgba(15,23,42,0.06)]">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                <div>
                    <h2 class="text-lg font-black text-slate-950">Danh sách user đã set chiết khấu</h2>
                    <p class="text-sm text-slate-500">{{ formatNumber(meta.total) }} tài khoản phù hợp.</p>
                </div>
                <button
                    v-if="selectedIds.length"
                    type="button"
                    class="ui-focus rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-bold text-indigo-700"
                    @click="openBulkModal"
                >
                    Set cho {{ selectedIds.length }} user đã chọn
                </button>
            </div>

            <div v-if="loading" class="flex items-center justify-center gap-3 py-16 text-sm text-slate-500">
                <LoaderCircle class="h-5 w-5 animate-spin" /> Đang tải dữ liệu...
            </div>
            <div v-else class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="w-12 px-5 py-4">
                                <input type="checkbox" :checked="allPageSelected" class="rounded border-slate-300" @change="togglePage" />
                            </th>
                            <th class="min-w-[240px] px-5 py-4">Người dùng</th>
                            <th class="px-5 py-4">Mức chiết khấu</th>
                            <th class="px-5 py-4">Gói riêng</th>
                            <th class="px-5 py-4">Global</th>
                            <th class="px-5 py-4">Trạng thái rule</th>
                            <th class="px-5 py-4">Cập nhật</th>
                            <th class="px-5 py-4 text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="rows.length === 0">
                            <td colspan="8" class="px-5 py-16 text-center text-sm text-slate-500">Chưa có user nào được thiết lập giá riêng.</td>
                        </tr>
                        <tr v-for="row in rows" :key="row.id" class="border-t border-slate-100 hover:bg-slate-50/70">
                            <td class="px-5 py-4">
                                <input
                                    type="checkbox"
                                    :checked="selectedIds.includes(row.id)"
                                    class="rounded border-slate-300"
                                    @change="toggleSelected(row.id)"
                                />
                            </td>
                            <td class="px-5 py-4">
                                <RouterLink
                                    :to="{ name: 'admin.users.show', params: { user_id: row.id }, query: { tab: 'pricing' } }"
                                    class="font-bold text-slate-950 hover:text-[#465fff]"
                                    >{{ row.name || row.username || `User #${row.id}` }}</RouterLink
                                >
                                <p class="mt-0.5 text-xs text-slate-500">#{{ row.id }} · {{ row.email || row.phone || 'Chưa có liên hệ' }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <div v-if="row.discount_rates.length" class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="rate in row.discount_rates"
                                        :key="rate"
                                        class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-black text-emerald-700"
                                        >{{ rate }}%</span
                                    >
                                </div>
                                <span v-else class="text-xs font-semibold text-slate-500">{{
                                    row.pricing_modes.map(pricingModeLabel).join(', ')
                                }}</span>
                            </td>
                            <td class="px-5 py-4 text-sm">
                                <strong>{{ row.active_package_rules_count }}</strong
                                ><span class="text-slate-400"> / {{ row.package_rules_count }} rule</span>
                            </td>
                            <td class="px-5 py-4 text-sm">
                                <strong>{{ row.active_global_rules_count }}</strong
                                ><span class="text-slate-400"> / {{ row.global_rules_count }} rule</span>
                            </td>
                            <td class="px-5 py-4">
                                <span
                                    class="rounded-full px-2.5 py-1 text-xs font-bold"
                                    :class="activeRuleCount(row) ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                                    >{{ activeRuleCount(row) ? `${activeRuleCount(row)}/${totalRuleCount(row)} đang bật` : 'Đã tắt' }}</span
                                >
                            </td>
                            <td class="px-5 py-4 text-sm text-slate-500">{{ formatDate(row.updated_at) }}</td>
                            <td class="px-5 py-4 text-right">
                                <RouterLink
                                    :to="{ name: 'admin.users.show', params: { user_id: row.id }, query: { tab: 'pricing' } }"
                                    class="inline-flex rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 hover:border-indigo-300 hover:text-indigo-700"
                                    >Chi tiết</RouterLink
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-slate-500">Trang {{ meta.current_page }}/{{ meta.last_page }} · {{ formatNumber(meta.total) }} user</p>
                <div class="flex items-center gap-2">
                    <select v-model="filters.per_page" class="ui-focus min-h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm">
                        <option :value="15">15 / trang</option>
                        <option :value="30">30 / trang</option>
                        <option :value="50">50 / trang</option>
                    </select>
                    <button
                        type="button"
                        :disabled="meta.current_page <= 1"
                        class="ui-focus grid h-10 w-10 place-items-center rounded-lg border border-slate-300 disabled:opacity-40"
                        @click="goToPage(meta.current_page - 1)"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        :disabled="meta.current_page >= meta.last_page"
                        class="ui-focus grid h-10 w-10 place-items-center rounded-lg border border-slate-300 disabled:opacity-40"
                        @click="goToPage(meta.current_page + 1)"
                    >
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </section>

        <Modal :model-value="modalOpen" panel-class="max-w-2xl" @update:model-value="modalOpen = $event">
            <template #header
                ><div class="border-b border-slate-200 px-6 py-5 pr-16">
                    <h2 class="text-lg font-black text-slate-950">Set chiết khấu hàng loạt</h2>
                    <p class="mt-1 text-sm text-slate-500">Chọn user và áp dụng cùng một mức giảm cho toàn bộ gói đang bán.</p>
                </div></template
            >
            <form class="grid gap-5 p-6" @submit.prevent="submitBulk">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="grid gap-1.5 text-sm font-bold text-slate-700"
                        >Phạm vi<select
                            v-model="bulkForm.scope"
                            class="ui-focus min-h-11 rounded-lg border border-slate-300 bg-white px-3 font-normal"
                        >
                            <option value="all">Gói riêng và Global</option>
                            <option value="packages">Chỉ gói riêng</option>
                            <option value="global">Chỉ gói Global</option>
                        </select></label
                    >
                    <label class="grid gap-1.5 text-sm font-bold text-slate-700"
                        >Phần trăm chiết khấu
                        <div class="relative">
                            <input
                                v-model.number="bulkForm.discount_percent"
                                type="number"
                                min="0.01"
                                max="100"
                                step="0.01"
                                required
                                class="ui-focus min-h-11 w-full rounded-lg border border-slate-300 px-3 pr-10 text-right font-black"
                            /><span class="pointer-events-none absolute inset-y-0 right-3 flex items-center font-bold text-slate-400">%</span>
                        </div></label
                    >
                </div>

                <div class="rounded-xl border border-slate-200">
                    <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-black text-slate-900">Chọn người dùng</p>
                            <p class="text-xs text-slate-500">Đã chọn {{ selectedCandidateIds.length }} user</p>
                        </div>
                        <label class="flex min-h-10 items-center gap-2 rounded-lg border border-slate-300 px-3"
                            ><Search class="h-4 w-4 text-slate-400" /><input
                                v-model="candidateSearch"
                                class="w-full border-0 p-0 text-sm outline-none"
                                placeholder="Tìm user..."
                                @keyup.enter.prevent="loadCandidates"
                            /><button type="button" class="text-xs font-bold text-indigo-600" @click="loadCandidates">Tìm</button></label
                        >
                    </div>
                    <div v-if="candidateLoading" class="flex items-center justify-center gap-2 py-12 text-sm text-slate-500">
                        <LoaderCircle class="h-4 w-4 animate-spin" /> Đang tải user...
                    </div>
                    <div v-else class="max-h-64 divide-y divide-slate-100 overflow-y-auto">
                        <label v-for="user in candidates" :key="user.id" class="flex cursor-pointer items-center gap-3 px-4 py-3 hover:bg-slate-50"
                            ><input
                                type="checkbox"
                                :checked="selectedCandidateIds.includes(user.id)"
                                class="rounded border-slate-300"
                                @change="toggleCandidate(user.id)"
                            /><span class="min-w-0"
                                ><strong class="block truncate text-sm text-slate-900">{{ user.name || user.username || `User #${user.id}` }}</strong
                                ><span class="block truncate text-xs text-slate-500"
                                    >#{{ user.id }} · {{ user.email || user.phone || 'Chưa có liên hệ' }}</span
                                ></span
                            ></label
                        >
                        <p v-if="candidates.length === 0" class="py-10 text-center text-sm text-slate-500">Không tìm thấy user phù hợp.</p>
                    </div>
                </div>

                <p class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs font-semibold leading-5 text-amber-800">
                    Thao tác này sẽ ghi đè rule giá hiện tại của các gói trong phạm vi đã chọn sang kiểu chiết khấu phần trăm.
                </p>
                <p v-if="formError" class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">{{ formError }}</p>
                <div class="grid gap-3 border-t border-slate-200 pt-4 sm:grid-cols-2">
                    <button
                        type="button"
                        class="ui-focus min-h-11 rounded-lg border border-slate-300 font-bold text-slate-700"
                        @click="modalOpen = false"
                    >
                        Hủy</button
                    ><button
                        type="submit"
                        :disabled="saving"
                        class="ui-focus inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-[#465fff] px-4 font-black text-white disabled:opacity-50"
                    >
                        <LoaderCircle v-if="saving" class="h-4 w-4 animate-spin" /><Sparkles v-else class="h-4 w-4" />Áp dụng cho
                        {{ selectedCandidateIds.length }} user
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
