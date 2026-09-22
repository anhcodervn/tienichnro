<script setup lang="ts">
import UploadImage from '@/components/shared/UpladImage/index.vue';
import { adminTopupService } from '@/services/admin-topup.service';
import { computed, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

type CatalogType = 'games' | 'servers' | 'packages';
type OptionRow = {
    id: number;
    name: string;
    game_id?: number;
    reward_label?: string;
    package_mode?: 'custom' | 'global';
};
type CatalogRow = Record<string, any> & { id: number; name: string; status: 'active' | 'inactive' };
type PaginationMeta = { current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
type CheckoutField = { key: string; label: string; placeholder: string; required: boolean; regex: string };

const defaultCheckoutFields = (): CheckoutField[] => [
    { key: 'game_account', label: 'Tài khoản game', placeholder: 'Tài khoản đăng nhập game', required: true, regex: '' },
    { key: 'game_character', label: 'Tên nhân vật', placeholder: 'Không bắt buộc', required: false, regex: '' },
];

const props = defineProps<{ catalogType: CatalogType }>();
const route = useRoute();
const router = useRouter();
const rows = ref<CatalogRow[]>([]);
const games = ref<OptionRow[]>([]);
const providers = ref<OptionRow[]>([]);
const loading = ref(false);
const saving = ref(false);
const editingId = ref<number | null>(null);
const pagination = reactive<PaginationMeta>({ current_page: 1, last_page: 1, per_page: 20, total: 0, from: null, to: null });
const filters = reactive({
    search: '',
    status: '',
    game_id: '',
    provider_id: '',
    min_price: '',
    max_price: '',
    per_page: '20',
    page: 1,
});
const form = reactive<Record<string, any>>({});

const pageContent = computed(
    () =>
        ({
            games: { eyebrow: 'Danh mục nạp game', title: 'Game', description: 'Tìm kiếm, lọc và quản lý các game đang cung cấp.' },
            servers: { eyebrow: 'Danh mục nạp game', title: 'Máy chủ', description: 'Quản lý máy chủ theo từng game.' },
            packages: { eyebrow: 'Danh mục nạp game', title: 'Gói nạp', description: 'Quản lý bảng giá, provider và trạng thái từng gói nạp.' },
        })[props.catalogType],
);

const customGames = computed(() => games.value.filter((game) => game.package_mode !== 'global'));
const discountPercent = computed(() => {
    const originalPrice = Number(form.original_price || 0);
    const salePrice = Number(form.price || 0);

    if (originalPrice <= 0 || salePrice > originalPrice) {
        return '0.00';
    }

    return (((originalPrice - salePrice) * 100) / originalPrice).toFixed(2);
});

const formatMoney = (value: unknown): string => `${new Intl.NumberFormat('vi-VN').format(Number(value || 0))}đ`;

const resetEditor = (): void => {
    editingId.value = null;
    Object.keys(form).forEach((key) => delete form[key]);

    if (props.catalogType === 'games') {
        Object.assign(form, {
            name: '',
            slug: '',
            short_name: '',
            reward_label: 'Thực nhận',
            provider_service_code: '',
            package_mode: 'custom',
            min_quantity: 1,
            max_quantity: 10,
            image: '',
            description: '',
            status: 'active',
            sort_order: 0,
            metadata: {},
            checkout_fields: defaultCheckoutFields(),
        });
    } else if (props.catalogType === 'servers') {
        Object.assign(form, { game_id: '', name: '', code: '', status: 'active', sort_order: 0, metadata: {} });
    } else {
        Object.assign(form, {
            game_id: '',
            provider_id: '',
            name: '',
            denomination: '',
            provider_price: '',
            original_price: '',
            price: '',
            description: '',
            bonus_text: '',
            status: 'active',
            sort_order: 0,
            metadata: {},
        });
    }
};

const readFiltersFromRoute = (): void => {
    const keys = ['search', 'status', 'game_id', 'provider_id', 'min_price', 'max_price', 'per_page'] as const;
    keys.forEach((key) => {
        const value = route.query[key];
        filters[key] = typeof value === 'string' ? value : key === 'per_page' ? '20' : '';
    });
    filters.page = Math.max(Number(route.query.page || 1), 1);
};

const queryParams = (): Record<string, string | number> => {
    const params: Record<string, string | number> = { page: filters.page, per_page: filters.per_page };
    const allowed =
        props.catalogType === 'games'
            ? ['search', 'status']
            : props.catalogType === 'servers'
              ? ['search', 'status', 'game_id']
              : ['search', 'status', 'game_id', 'provider_id', 'min_price', 'max_price'];

    allowed.forEach((key) => {
        const value = filters[key as keyof typeof filters];
        if (value !== '') {
            params[key] = value;
        }
    });

    return params;
};

const loadLookups = async (): Promise<void> => {
    if (props.catalogType === 'games') return;

    const requests: Promise<any>[] = [adminTopupService.games({ per_page: 100 })];
    if (props.catalogType === 'packages') {
        requests.push(adminTopupService.providers({ per_page: 100 }));
    }

    const responses = await Promise.all(requests);
    games.value = responses[0].data.data.data;
    if (props.catalogType === 'packages') {
        providers.value = responses[1].data.data.data;
    }
};

const fetchRows = async (): Promise<void> => {
    const request =
        props.catalogType === 'games'
            ? adminTopupService.games(queryParams())
            : props.catalogType === 'servers'
              ? adminTopupService.servers(queryParams())
              : adminTopupService.packages(queryParams());
    const response = await request;
    const payload = response.data.data;
    const meta = payload.meta ?? payload;
    rows.value = payload.data ?? [];
    Object.assign(pagination, {
        current_page: Number(meta.current_page || 1),
        last_page: Number(meta.last_page || 1),
        per_page: Number(meta.per_page || filters.per_page),
        total: Number(meta.total || 0),
        from: meta.from ?? null,
        to: meta.to ?? null,
    });
};

const refreshRows = async (): Promise<void> => {
    loading.value = true;
    try {
        await fetchRows();
    } finally {
        loading.value = false;
    }
};

const load = async (): Promise<void> => {
    loading.value = true;
    try {
        readFiltersFromRoute();
        await loadLookups();
        await fetchRows();
        resetEditor();
    } finally {
        loading.value = false;
    }
};

const syncFilters = async (): Promise<void> => {
    const query = queryParams();
    if (query.page === 1) {
        delete query.page;
    }
    if (query.per_page === '20') {
        delete query.per_page;
    }
    await router.replace({ query });
};

const applyFilters = async (): Promise<void> => {
    filters.page = 1;
    await syncFilters();
    await refreshRows();
};

const clearFilters = async (): Promise<void> => {
    Object.assign(filters, {
        search: '',
        status: '',
        game_id: '',
        provider_id: '',
        min_price: '',
        max_price: '',
        per_page: '20',
        page: 1,
    });
    await syncFilters();
    await refreshRows();
};

const changePage = async (page: number): Promise<void> => {
    if (page < 1 || page > pagination.last_page || page === pagination.current_page) {
        return;
    }
    filters.page = page;
    await syncFilters();
    await refreshRows();
};

const edit = (row: CatalogRow): void => {
    editingId.value = row.id;
    resetEditor();
    editingId.value = row.id;
    Object.keys(form).forEach((key) => {
        form[key] = row[key] ?? '';
    });
    if (props.catalogType === 'games') {
        form.checkout_fields = (row.checkout_fields?.length ? row.checkout_fields : defaultCheckoutFields()).map(
            (field: CheckoutField): CheckoutField => ({ ...field, regex: field.regex || '' }),
        );
    }
};

const addCheckoutField = (): void => {
    if (!Array.isArray(form.checkout_fields) || form.checkout_fields.length >= 6) {
        return;
    }

    form.checkout_fields.push({ key: '', label: '', placeholder: '', required: false, regex: '' });
};

const removeCheckoutField = (index: number): void => {
    if (!Array.isArray(form.checkout_fields) || form.checkout_fields.length <= 1) {
        return;
    }

    form.checkout_fields.splice(index, 1);
};

const payload = (): Record<string, unknown> => {
    if (props.catalogType === 'games') {
        return {
            name: form.name,
            slug: form.slug,
            short_name: form.short_name || null,
            reward_label: form.reward_label || 'Thực nhận',
            provider_service_code: form.provider_service_code?.trim() || null,
            package_mode: form.package_mode,
            min_quantity: Number(form.min_quantity),
            max_quantity: Number(form.max_quantity),
            image: form.image || null,
            description: form.description || null,
            status: form.status,
            sort_order: Number(form.sort_order),
            metadata: form.metadata ?? {},
            checkout_fields: (form.checkout_fields as CheckoutField[]).map((field) => ({
                key: field.key.trim(),
                label: field.label.trim(),
                placeholder: field.placeholder?.trim() || null,
                required: Boolean(field.required),
                regex: field.regex?.trim() || null,
            })),
        };
    }
    if (props.catalogType === 'servers') {
        return {
            game_id: Number(form.game_id),
            name: form.name,
            code: form.code || null,
            status: form.status,
            sort_order: Number(form.sort_order),
            metadata: form.metadata ?? {},
        };
    }
    return {
        game_id: Number(form.game_id),
        provider_id: form.provider_id ? Number(form.provider_id) : null,
        name: form.name,
        denomination: form.denomination === '' ? null : Number(form.denomination),
        provider_price: Number(form.provider_price),
        original_price: Number(form.original_price),
        price: Number(form.price),
        description: form.description || null,
        bonus_text: form.bonus_text || null,
        status: form.status,
        sort_order: Number(form.sort_order),
        metadata: form.metadata ?? {},
    };
};

const save = async (): Promise<void> => {
    saving.value = true;
    try {
        if (props.catalogType === 'games') {
            await adminTopupService.saveGame(editingId.value, payload());
        } else if (props.catalogType === 'servers') {
            await adminTopupService.saveServer(editingId.value, payload());
        } else {
            await adminTopupService.savePackage(editingId.value, payload());
        }
        resetEditor();
        await refreshRows();
    } finally {
        saving.value = false;
    }
};

const remove = async (row: CatalogRow): Promise<void> => {
    if (!window.confirm(`Xóa “${row.name}”? Thao tác này không thể hoàn tác.`)) {
        return;
    }
    if (props.catalogType === 'games') {
        await adminTopupService.deleteGame(row.id);
    } else if (props.catalogType === 'servers') {
        await adminTopupService.deleteServer(row.id);
    } else {
        await adminTopupService.deletePackage(row.id);
    }
    if (editingId.value === row.id) {
        resetEditor();
    }
    if (rows.value.length === 1 && filters.page > 1) {
        filters.page -= 1;
        await syncFilters();
    }
    await refreshRows();
};

watch(() => props.catalogType, load, { immediate: true });
</script>

<template>
    <section class="space-y-5">
        <header>
            <p class="text-sm font-semibold text-emerald-700">{{ pageContent.eyebrow }}</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-950">{{ pageContent.title }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ pageContent.description }}</p>
        </header>

        <div class="grid items-start gap-5 min-[1800px]:grid-cols-[minmax(0,1fr)_390px]">
            <div class="min-w-0 space-y-4">
                <form class="rounded-md border border-slate-200 bg-white p-4 shadow-sm" @submit.prevent="applyFilters">
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <label class="text-sm font-semibold text-slate-700 xl:col-span-2">
                            Tìm kiếm
                            <input
                                v-model.trim="filters.search"
                                class="mt-1.5 min-h-10 w-full rounded-md border border-slate-300 px-3 font-normal"
                                :placeholder="
                                    catalogType === 'games'
                                        ? 'Tên, slug, tên ngắn...'
                                        : catalogType === 'servers'
                                          ? 'Tên hoặc mã máy chủ...'
                                          : 'Tên gói nạp...'
                                "
                            />
                        </label>
                        <label class="text-sm font-semibold text-slate-700">
                            Trạng thái
                            <select v-model="filters.status" class="mt-1.5 min-h-10 w-full rounded-md border border-slate-300 px-3 font-normal">
                                <option value="">Tất cả</option>
                                <option value="active">Hoạt động</option>
                                <option value="inactive">Tạm tắt</option>
                            </select>
                        </label>
                        <label class="text-sm font-semibold text-slate-700">
                            Số dòng
                            <select v-model="filters.per_page" class="mt-1.5 min-h-10 w-full rounded-md border border-slate-300 px-3 font-normal">
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </label>
                        <label v-if="catalogType !== 'games'" class="text-sm font-semibold text-slate-700">
                            Game
                            <select v-model="filters.game_id" class="mt-1.5 min-h-10 w-full rounded-md border border-slate-300 px-3 font-normal">
                                <option value="">Tất cả game</option>
                                <option v-for="game in games" :key="game.id" :value="game.id">{{ game.name }}</option>
                            </select>
                        </label>
                        <template v-if="catalogType === 'packages'">
                            <label class="text-sm font-semibold text-slate-700">
                                Provider
                                <select
                                    v-model="filters.provider_id"
                                    class="mt-1.5 min-h-10 w-full rounded-md border border-slate-300 px-3 font-normal"
                                >
                                    <option value="">Tất cả provider</option>
                                    <option v-for="provider in providers" :key="provider.id" :value="provider.id">{{ provider.name }}</option>
                                </select>
                            </label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="text-sm font-semibold text-slate-700"
                                    >Giá từ<input
                                        v-model="filters.min_price"
                                        type="number"
                                        min="0"
                                        class="mt-1.5 min-h-10 w-full rounded-md border border-slate-300 px-3 font-normal"
                                /></label>
                                <label class="text-sm font-semibold text-slate-700"
                                    >Đến<input
                                        v-model="filters.max_price"
                                        type="number"
                                        min="0"
                                        class="mt-1.5 min-h-10 w-full rounded-md border border-slate-300 px-3 font-normal"
                                /></label>
                            </div>
                        </template>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="submit" class="min-h-10 rounded-md bg-emerald-600 px-5 text-sm font-semibold text-white hover:bg-emerald-700">
                            Áp dụng bộ lọc
                        </button>
                        <button
                            type="button"
                            class="min-h-10 rounded-md border border-slate-300 px-5 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                            @click="clearFilters"
                        >
                            Xóa bộ lọc
                        </button>
                    </div>
                </form>

                <div class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
                    <div v-if="loading" class="p-12 text-center text-sm text-slate-500">Đang tải dữ liệu...</div>
                    <div v-else-if="rows.length === 0" class="p-12 text-center text-sm text-slate-500">Không tìm thấy dữ liệu phù hợp.</div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-[960px] text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                                <tr v-if="catalogType === 'games'">
                                    <th class="p-4">Game</th>
                                    <th class="p-4">Slug</th>
                                    <th class="p-4">Đơn vị nhận</th>
                                    <th class="p-4">Mã provider</th>
                                    <th class="p-4">Loại gói</th>
                                    <th class="p-4">SL / tài khoản</th>
                                    <th class="p-4">Máy chủ</th>
                                    <th class="p-4">Gói nạp</th>
                                    <th class="p-4">Trạng thái</th>
                                    <th
                                        class="sticky right-0 z-10 whitespace-nowrap bg-slate-50 p-4 text-right shadow-[-8px_0_12px_-12px_rgba(15,23,42,0.35)]"
                                    >
                                        Thao tác
                                    </th>
                                </tr>
                                <tr v-else-if="catalogType === 'servers'">
                                    <th class="p-4">Máy chủ</th>
                                    <th class="p-4">Game</th>
                                    <th class="p-4">Mã</th>
                                    <th class="p-4">Trạng thái</th>
                                    <th
                                        class="sticky right-0 z-10 whitespace-nowrap bg-slate-50 p-4 text-right shadow-[-8px_0_12px_-12px_rgba(15,23,42,0.35)]"
                                    >
                                        Thao tác
                                    </th>
                                </tr>
                                <tr v-else>
                                    <th class="p-4">Tên</th>
                                    <th class="p-4">Thuộc game</th>
                                    <th class="p-4">Giá provider</th>
                                    <th class="p-4">Giá gốc riêng</th>
                                    <th class="p-4">Giá áp dụng</th>
                                    <th class="p-4">Trạng thái</th>
                                    <th
                                        class="sticky right-0 z-10 whitespace-nowrap bg-slate-50 p-4 text-right shadow-[-8px_0_12px_-12px_rgba(15,23,42,0.35)]"
                                    >
                                        Thao tác
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="row in rows" :key="row.id" class="group hover:bg-slate-50/70">
                                    <template v-if="catalogType === 'games'">
                                        <td class="p-4 font-semibold text-slate-950">
                                            <div class="flex items-center gap-3">
                                                <img
                                                    v-if="row.image"
                                                    :src="row.image"
                                                    :alt="row.name"
                                                    class="size-11 shrink-0 rounded-md border border-slate-200 object-cover"
                                                />
                                                <div>
                                                    {{ row.name }}
                                                    <div class="text-xs font-normal text-slate-500">{{ row.short_name || '—' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-4 font-mono text-xs">{{ row.slug }}</td>
                                        <td class="p-4">{{ row.reward_label || 'Thực nhận' }}</td>
                                        <td class="p-4 font-mono text-xs font-semibold text-slate-700">{{ row.provider_service_code || '—' }}</td>
                                        <td class="p-4">
                                            <span
                                                class="rounded-md px-2 py-1 text-xs font-semibold"
                                                :class="
                                                    row.package_mode === 'global' ? 'bg-violet-50 text-violet-700' : 'bg-slate-100 text-slate-600'
                                                "
                                            >
                                                {{ row.package_mode === 'global' ? 'Dùng gói Global' : 'Gói riêng' }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap p-4 font-semibold text-slate-700">
                                            {{ row.min_quantity }}–{{ row.max_quantity }}
                                        </td>
                                        <td class="p-4">{{ row.servers_count || 0 }}</td>
                                        <td class="p-4">{{ row.packages_count || 0 }}</td>
                                    </template>
                                    <template v-else-if="catalogType === 'servers'">
                                        <td class="p-4 font-semibold text-slate-950">{{ row.name }}</td>
                                        <td class="p-4">{{ row.game_name }}</td>
                                        <td class="p-4 font-mono text-xs">{{ row.code || '—' }}</td>
                                    </template>
                                    <template v-else>
                                        <td class="p-4 font-semibold text-slate-950">{{ row.name }}</td>
                                        <td class="p-4">
                                            {{ row.game_name }}
                                            <div class="text-xs text-slate-500">Provider: {{ row.provider_name || 'Chưa gán' }}</div>
                                        </td>
                                        <td class="p-4">{{ formatMoney(row.provider_price) }}</td>
                                        <td class="p-4">{{ formatMoney(row.original_price) }}</td>
                                        <td class="p-4 font-bold text-slate-950">
                                            {{ formatMoney(row.price) }}
                                            <div class="text-xs font-normal text-emerald-700">Riêng · giảm {{ row.discount_percent }}%</div>
                                        </td>
                                    </template>
                                    <td class="p-4">
                                        <span
                                            class="rounded-md px-2 py-1 text-xs font-semibold"
                                            :class="row.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                                            >{{ row.status === 'active' ? 'Hoạt động' : 'Tạm tắt' }}</span
                                        >
                                    </td>
                                    <td
                                        class="sticky right-0 z-10 whitespace-nowrap bg-white p-4 text-right shadow-[-8px_0_12px_-12px_rgba(15,23,42,0.35)] transition-colors group-hover:bg-slate-50"
                                    >
                                        <button type="button" class="mr-3 font-semibold text-emerald-700" @click="edit(row)">Sửa</button
                                        ><button type="button" class="font-semibold text-rose-600" @click="remove(row)">Xóa</button>
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
                    <h2 class="font-bold text-slate-950">
                        {{ editingId ? `Cập nhật ${pageContent.title.toLowerCase()}` : `Tạo ${pageContent.title.toLowerCase()} mới` }}
                    </h2>
                    <button v-if="editingId" type="button" class="text-sm font-semibold text-slate-500" @click="resetEditor">Hủy</button>
                </div>
                <div class="mt-5 grid gap-4">
                    <label v-if="catalogType !== 'games'" class="text-sm font-semibold text-slate-700"
                        >Game<select
                            v-model="form.game_id"
                            required
                            class="mt-1.5 min-h-11 w-full rounded-md border border-slate-300 px-3 font-normal"
                        >
                            <option value="" disabled>Chọn game</option>
                            <option v-for="game in catalogType === 'packages' ? customGames : games" :key="game.id" :value="game.id">
                                {{ game.name }}
                            </option>
                        </select></label
                    >
                    <label class="text-sm font-semibold text-slate-700"
                        >Tên<input
                            v-model.trim="form.name"
                            required
                            class="mt-1.5 min-h-11 w-full rounded-md border border-slate-300 px-3 font-normal"
                    /></label>
                    <template v-if="catalogType === 'games'">
                        <div class="rounded-md border border-slate-200 bg-slate-50 p-4">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-700">Ảnh game</p>
                                    <p class="mt-1 text-xs font-normal leading-5 text-slate-500">
                                        JPG, PNG hoặc WebP, tối đa 10 MB. Trình duyệt tự crop giữa và chuyển WebP đúng 512×512 trước khi tải lên.
                                    </p>
                                </div>
                                <button
                                    v-if="form.image"
                                    type="button"
                                    class="shrink-0 text-xs font-semibold text-rose-600 hover:text-rose-700"
                                    @click="form.image = ''"
                                >
                                    Xóa ảnh
                                </button>
                            </div>
                            <UploadImage
                                :accept="['image/jpeg', 'image/png', 'image/webp']"
                                :compress="false"
                                :image-src="form.image"
                                :name-image="form.slug || form.name || 'game'"
                                :square-size="512"
                                upload-url="/api/admin-api/games/image"
                                @uploaded="form.image = $event"
                            />
                        </div>
                        <label class="text-sm font-semibold text-slate-700"
                            >Slug<input
                                v-model.trim="form.slug"
                                required
                                class="mt-1.5 min-h-11 w-full rounded-md border border-slate-300 px-3 font-normal"
                        /></label>
                        <label class="text-sm font-semibold text-slate-700"
                            >Tên ngắn<input
                                v-model.trim="form.short_name"
                                class="mt-1.5 min-h-11 w-full rounded-md border border-slate-300 px-3 font-normal"
                        /></label>
                        <label class="text-sm font-semibold text-slate-700"
                            >Đơn vị thực nhận<input
                                v-model.trim="form.reward_label"
                                maxlength="60"
                                placeholder="Ví dụ: Lượng / Ngọc, Xu, Gem"
                                class="mt-1.5 min-h-11 w-full rounded-md border border-slate-300 px-3 font-normal"
                        /></label>
                        <label class="text-sm font-semibold text-slate-700"
                            >Mã dịch vụ provider của game<input
                                v-model.trim="form.provider_service_code"
                                maxlength="100"
                                placeholder="Ví dụ: nr"
                                class="mt-1.5 min-h-11 w-full rounded-md border border-slate-300 px-3 font-normal"
                            /><small class="mt-1.5 block font-normal leading-5 text-slate-500"
                                >Mã game do provider quy định. Gói riêng và tất cả gói Global của game này đều tự động dùng chung mã này.</small
                            ></label
                        >
                        <fieldset class="grid gap-3 rounded-md border border-violet-200 bg-violet-50/60 p-4">
                            <legend class="px-1 text-sm font-bold text-violet-900">Loại gói nạp</legend>
                            <label class="text-sm font-semibold text-slate-700"
                                >Chế độ package<select
                                    v-model="form.package_mode"
                                    class="mt-1.5 min-h-11 w-full rounded-md border border-slate-300 bg-white px-3 font-normal"
                                >
                                    <option value="custom">Dùng gói riêng từng game</option>
                                    <option value="global">Dùng gói nạp Global</option>
                                </select></label
                            >
                            <p class="text-xs leading-5 text-violet-700">
                                Provider lấy từ gói nạp, mã dịch vụ lấy từ Game, còn server và bảng thực nhận được cấu hình theo game. Giá bán lấy từ
                                gói Global được tự động đồng bộ theo mệnh giá.
                            </p>
                        </fieldset>
                        <fieldset class="rounded-md border border-emerald-200 bg-emerald-50/60 p-4">
                            <legend class="px-1 text-sm font-bold text-emerald-900">Giới hạn số lượng cho 1 tài khoản</legend>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="text-sm font-semibold text-slate-700"
                                    >Tối thiểu / tài khoản<input
                                        v-model.number="form.min_quantity"
                                        required
                                        type="number"
                                        min="1"
                                        max="10"
                                        class="mt-1.5 min-h-11 w-full rounded-md border border-slate-300 bg-white px-3 font-normal"
                                /></label>
                                <label class="text-sm font-semibold text-slate-700"
                                    >Tối đa / tài khoản<input
                                        v-model.number="form.max_quantity"
                                        required
                                        type="number"
                                        :min="Number(form.min_quantity || 1)"
                                        max="10"
                                        class="mt-1.5 min-h-11 w-full rounded-md border border-slate-300 bg-white px-3 font-normal"
                                /></label>
                            </div>
                            <p class="mt-2 text-xs leading-5 text-emerald-800">
                                Áp dụng chung cho mọi gói của game. Mỗi tài khoản trong đơn được kiểm tra riêng; không giới hạn tổng số lượng của cả
                                đơn.
                            </p>
                        </fieldset>
                        <fieldset class="rounded-md border border-slate-200 bg-slate-50 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <legend class="text-sm font-bold text-slate-800">Trường dữ liệu nhận hàng</legend>
                                    <p class="mt-1 text-xs leading-5 text-slate-500">
                                        Thứ tự ở đây cũng là thứ tự cột khi mua nhiều, ngăn cách bằng dấu <strong>|</strong>. Mã trường được giữ
                                        nguyên khi adapter tạo payload provider, ví dụ <code>username</code>, <code>account</code> hoặc
                                        <code>taikhoan</code>.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="rounded-md border border-emerald-300 bg-white px-3 py-2 text-xs font-semibold text-emerald-700 disabled:opacity-40"
                                    :disabled="form.checkout_fields?.length >= 6"
                                    @click="addCheckoutField"
                                >
                                    + Thêm trường
                                </button>
                            </div>
                            <div class="mt-4 space-y-3">
                                <div
                                    v-for="(field, index) in form.checkout_fields"
                                    :key="index"
                                    class="rounded-md border border-slate-200 bg-white p-3"
                                >
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <label class="text-xs font-semibold text-slate-600">
                                            Mã trường
                                            <input
                                                v-model.trim="field.key"
                                                required
                                                maxlength="40"
                                                pattern="[a-z][a-z0-9_]*"
                                                placeholder="Ví dụ: game_account"
                                                class="mt-1 min-h-10 w-full rounded-md border border-slate-300 px-3 font-mono font-normal"
                                            />
                                        </label>
                                        <label class="text-xs font-semibold text-slate-600">
                                            Nhãn hiển thị
                                            <input
                                                v-model.trim="field.label"
                                                required
                                                maxlength="80"
                                                placeholder="Ví dụ: Tài khoản game"
                                                class="mt-1 min-h-10 w-full rounded-md border border-slate-300 px-3 font-normal"
                                            />
                                        </label>
                                    </div>
                                    <label class="mt-3 block text-xs font-semibold text-slate-600">
                                        Chữ gợi ý
                                        <input
                                            v-model.trim="field.placeholder"
                                            maxlength="120"
                                            class="mt-1 min-h-10 w-full rounded-md border border-slate-300 px-3 font-normal"
                                        />
                                    </label>
                                    <label class="mt-3 block text-xs font-semibold text-slate-600">
                                        Regex kiểm tra định dạng
                                        <input
                                            v-model.trim="field.regex"
                                            maxlength="500"
                                            placeholder="Ví dụ: ^[a-z0-9_]{4,20}$"
                                            class="mt-1 min-h-10 w-full rounded-md border border-slate-300 px-3 font-mono font-normal"
                                        />
                                        <span class="mt-1 block font-normal leading-5 text-slate-500">
                                            Chỉ nhập nội dung pattern, không kèm dấu /. Để trống nếu không cần kiểm tra.
                                        </span>
                                    </label>
                                    <div class="mt-3 flex items-center justify-between gap-3">
                                        <label class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                                            <input v-model="field.required" type="checkbox" class="size-4 rounded border-slate-300" />
                                            Bắt buộc nhập
                                        </label>
                                        <button
                                            type="button"
                                            class="text-xs font-semibold text-rose-600 disabled:opacity-40"
                                            :disabled="form.checkout_fields.length <= 1"
                                            @click="removeCheckoutField(index)"
                                        >
                                            Xóa trường
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </fieldset>
                        <label class="text-sm font-semibold text-slate-700"
                            >Mô tả<textarea
                                v-model="form.description"
                                rows="4"
                                class="mt-1.5 w-full rounded-md border border-slate-300 px-3 py-2 font-normal"
                            ></textarea>
                        </label>
                    </template>
                    <template v-else-if="catalogType === 'servers'">
                        <label class="text-sm font-semibold text-slate-700"
                            >Mã máy chủ provider<input
                                v-model.trim="form.code"
                                placeholder="Ví dụ: 3"
                                class="mt-1.5 min-h-11 w-full rounded-md border border-slate-300 px-3 font-normal"
                            /><small class="mt-1.5 block font-normal text-slate-500"
                                >Giá trị server dùng chung và được adapter đặt vào đúng cấu trúc payload của provider.</small
                            ></label
                        >
                    </template>
                    <template v-else>
                        <label class="text-sm font-semibold text-slate-700"
                            >Provider<select
                                v-model="form.provider_id"
                                class="mt-1.5 min-h-11 w-full rounded-md border border-slate-300 px-3 font-normal"
                            >
                                <option value="">Chưa gán</option>
                                <option v-for="provider in providers" :key="provider.id" :value="provider.id">{{ provider.name }}</option>
                            </select></label
                        >
                        <label class="text-sm font-semibold text-slate-700"
                            >Mệnh giá<input
                                v-model="form.denomination"
                                type="number"
                                min="0"
                                class="mt-1.5 min-h-11 w-full rounded-md border border-slate-300 px-3 font-normal"
                        /></label>
                        <label class="text-sm font-semibold text-slate-700"
                            >Giá gốc provider<input
                                v-model="form.provider_price"
                                required
                                type="number"
                                min="0"
                                class="mt-1.5 min-h-11 w-full rounded-md border border-slate-300 px-3 font-normal"
                        /></label>
                        <label class="text-sm font-semibold text-slate-700"
                            >Giá gốc riêng/dự phòng<input
                                v-model="form.original_price"
                                required
                                type="number"
                                min="1"
                                class="mt-1.5 min-h-11 w-full rounded-md border border-slate-300 px-3 font-normal"
                        /></label>
                        <label class="text-sm font-semibold text-slate-700"
                            >Giá bán riêng/dự phòng<input
                                v-model="form.price"
                                required
                                type="number"
                                min="0"
                                class="mt-1.5 min-h-11 w-full rounded-md border border-slate-300 px-3 font-normal"
                        /></label>
                        <label class="text-sm font-semibold text-slate-700"
                            >Chiết khấu<input
                                :value="`${discountPercent}%`"
                                disabled
                                class="mt-1.5 min-h-11 w-full rounded-md border border-slate-200 bg-slate-100 px-3 font-semibold text-emerald-700"
                        /></label>
                    </template>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="text-sm font-semibold text-slate-700"
                            >Trạng thái<select
                                v-model="form.status"
                                class="mt-1.5 min-h-11 w-full rounded-md border border-slate-300 px-3 font-normal"
                            >
                                <option value="active">Hoạt động</option>
                                <option value="inactive">Tạm tắt</option>
                            </select></label
                        ><label class="text-sm font-semibold text-slate-700"
                            >Thứ tự<input
                                v-model="form.sort_order"
                                type="number"
                                min="0"
                                class="mt-1.5 min-h-11 w-full rounded-md border border-slate-300 px-3 font-normal"
                        /></label>
                    </div>
                    <button
                        type="submit"
                        class="min-h-11 rounded-md bg-emerald-600 px-4 font-semibold text-white hover:bg-emerald-700 disabled:cursor-wait disabled:opacity-60"
                        :disabled="saving"
                    >
                        {{ saving ? 'Đang lưu...' : editingId ? 'Lưu thay đổi' : 'Tạo mới' }}
                    </button>
                </div>
            </form>
        </div>
    </section>
</template>
