<script setup lang="ts">
import {
    adminMemberLevelService,
    type MemberLevel,
    type MemberLevelCatalog,
    type MemberLevelPackage,
    type MemberLevelPayload,
} from '@/services/admin-member-level.service';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { BadgeDollarSign, Crown, LoaderCircle, Plus, Save, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';

type OverrideDraft = {
    pricing_mode: 'discount' | 'fixed';
    discount_value: string;
    fixed_price: string;
    minimum_profit: string;
};

type PricePreview = {
    finalPrice: number;
    discountAmount: number;
    isFloorApplied: boolean;
    isIncomplete: boolean;
    isProviderPriceMissing: boolean;
};

const catalog = ref<MemberLevelCatalog>({ levels: [], games: [] });
const loading = ref(false);
const saving = ref(false);
const selectedLevelId = ref<number | null>(null);
const selectedGameId = ref<number | null>(null);
const editingLevelId = ref<number | null>(null);
const overrideDrafts = reactive<Record<number, OverrideDraft>>({});

const emptyForm = (): MemberLevelPayload => ({
    code: '',
    name: '',
    rank: 0,
    lifetime_threshold: 0,
    maintenance_amount: 0,
    maintenance_days: 31,
    default_discount_bps: 0,
    minimum_profit: 0,
    color: '#64748b',
    icon: 'crown',
    status: 'active',
    sort_order: 0,
});
const form = reactive<MemberLevelPayload>(emptyForm());
const selectedLevel = computed(() => catalog.value.levels.find((item) => item.id === selectedLevelId.value) ?? null);
const selectedGame = computed(() => catalog.value.games.find((item) => item.id === selectedGameId.value) ?? null);
const money = (value: number | null): string => `${new Intl.NumberFormat('vi-VN').format(value ?? 0)}đ`;
const notify = (message: string): void => handleSuccessResponse({ data: { status: true, message } });

const parseDraftNumber = (value: string, fallback: number): number => {
    if (value.trim() === '') return fallback;

    const parsedValue = Number(value);

    return Number.isFinite(parsedValue) ? Math.max(0, parsedValue) : fallback;
};

const previewPrice = (packageItem: MemberLevelPackage): PricePreview => {
    const retailPrice = Number(packageItem.price);
    const draft = overrideDrafts[packageItem.id];

    if (!draft) {
        return {
            finalPrice: retailPrice,
            discountAmount: 0,
            isFloorApplied: false,
            isIncomplete: false,
            isProviderPriceMissing: packageItem.provider_price === null,
        };
    }

    const defaultDiscount = (selectedLevel.value?.default_discount_bps ?? 0) / 100;
    const discountPercent = Math.min(100, parseDraftNumber(draft.discount_value, defaultDiscount));
    const isIncomplete = draft.pricing_mode === 'fixed' && draft.fixed_price.trim() === '';
    const candidatePrice =
        draft.pricing_mode === 'fixed'
            ? parseDraftNumber(draft.fixed_price, retailPrice)
            : retailPrice - Math.trunc((retailPrice * discountPercent) / 100);
    const minimumProfit = parseDraftNumber(draft.minimum_profit, selectedLevel.value?.minimum_profit ?? 0);
    const priceFloor = packageItem.provider_price === null ? retailPrice : Number(packageItem.provider_price) + minimumProfit;
    const finalPrice = Math.min(retailPrice, Math.max(0, candidatePrice, priceFloor));

    return {
        finalPrice,
        discountAmount: retailPrice - finalPrice,
        isFloorApplied: packageItem.provider_price !== null && priceFloor > candidatePrice && finalPrice === Math.min(retailPrice, priceFloor),
        isIncomplete,
        isProviderPriceMissing: packageItem.provider_price === null,
    };
};

const hydrateOverrides = (): void => {
    const overrides = new Map((selectedLevel.value?.package_prices ?? []).map((item) => [item.topup_package_id, item]));
    catalog.value.games
        .flatMap((game) => game.packages)
        .forEach((packageItem) => {
            const item = overrides.get(packageItem.id);
            overrideDrafts[packageItem.id] = {
                pricing_mode: item?.pricing_mode ?? 'discount',
                discount_value: item?.discount_basis_points != null ? String(item.discount_basis_points / 100) : '',
                fixed_price: item?.fixed_price != null ? String(item.fixed_price) : '',
                minimum_profit: item?.minimum_profit != null ? String(item.minimum_profit) : '',
            };
        });
};

const load = async (): Promise<void> => {
    loading.value = true;
    try {
        catalog.value = await adminMemberLevelService.catalog();
        selectedLevelId.value ??= catalog.value.levels[0]?.id ?? null;
        selectedGameId.value ??= catalog.value.games[0]?.id ?? null;
        hydrateOverrides();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

const selectLevel = (level: MemberLevel): void => {
    selectedLevelId.value = level.id;
    hydrateOverrides();
};

const createLevel = (): void => {
    editingLevelId.value = null;
    Object.assign(form, emptyForm(), { rank: catalog.value.levels.length, sort_order: catalog.value.levels.length });
};

const editLevel = (level: MemberLevel): void => {
    editingLevelId.value = level.id;
    Object.assign(form, {
        code: level.code,
        name: level.name,
        rank: level.rank,
        lifetime_threshold: level.lifetime_threshold,
        maintenance_amount: level.maintenance_amount,
        maintenance_days: level.maintenance_days,
        default_discount_bps: level.default_discount_bps,
        minimum_profit: level.minimum_profit,
        color: level.color,
        icon: level.icon,
        status: level.status,
        sort_order: level.sort_order,
    });
};

const saveLevel = async (): Promise<void> => {
    saving.value = true;
    try {
        if (editingLevelId.value) {
            await adminMemberLevelService.update(editingLevelId.value, { ...form });
            notify('Đã cập nhật level.');
        } else {
            await adminMemberLevelService.create({ ...form });
            notify('Đã tạo level.');
        }
        await load();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
};

const disableLevel = async (level: MemberLevel): Promise<void> => {
    try {
        await adminMemberLevelService.disable(level.id);
        notify('Đã tạm tắt level.');
        await load();
    } catch (error) {
        handleErrorResponse(error);
    }
};

const saveOverride = async (packageItem: MemberLevelPackage): Promise<void> => {
    if (!selectedLevel.value) return;
    const draft = overrideDrafts[packageItem.id];

    if (draft.pricing_mode === 'fixed' && draft.fixed_price.trim() === '') {
        handleErrorResponse({ message: `Vui lòng nhập giá cố định cho ${packageItem.name}.` });
        return;
    }

    const discountBasisPoints =
        draft.discount_value.trim() === '' ? selectedLevel.value.default_discount_bps : Math.round(Number(draft.discount_value) * 100);

    try {
        await adminMemberLevelService.savePackagePrice(selectedLevel.value.id, packageItem.id, {
            pricing_mode: draft.pricing_mode,
            discount_basis_points: draft.pricing_mode === 'discount' ? discountBasisPoints : null,
            fixed_price: draft.pricing_mode === 'fixed' ? Number(draft.fixed_price) : null,
            minimum_profit: draft.minimum_profit === '' ? null : Number(draft.minimum_profit),
            is_active: true,
        });
        notify(`Đã lưu giá ${packageItem.name}.`);
        await load();
    } catch (error) {
        handleErrorResponse(error);
    }
};

const deleteOverride = async (packageItem: MemberLevelPackage): Promise<void> => {
    if (!selectedLevel.value) return;
    try {
        await adminMemberLevelService.deletePackagePrice(selectedLevel.value.id, packageItem.id);
        notify(`Đã xóa giá riêng ${packageItem.name}.`);
        await load();
    } catch (error) {
        handleErrorResponse(error);
    }
};

onMounted(load);
</script>

<template>
    <main class="grid gap-5 p-4 sm:p-6">
        <header class="flex flex-col gap-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-amber-600">Đại lý & thành viên</p>
                <h1 class="mt-1 text-2xl font-black text-slate-950">Level và bảng giá</h1>
                <p class="mt-1 text-sm text-slate-500">Mở khóa vĩnh viễn theo tổng nạp, duy trì quyền lợi trong cửa sổ ngày tùy chỉnh.</p>
            </div>
            <button
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-slate-950 px-4 font-bold text-white hover:bg-slate-800"
                type="button"
                @click="createLevel"
            >
                <Plus class="h-4 w-4" /> Thêm level
            </button>
        </header>

        <div v-if="loading" class="grid min-h-64 place-items-center rounded-lg border border-slate-200 bg-white">
            <LoaderCircle class="h-8 w-8 animate-spin text-slate-400" />
        </div>

        <template v-else>
            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="level in catalog.levels"
                    :key="level.id"
                    class="rounded-lg border bg-white p-4 shadow-sm"
                    :class="selectedLevelId === level.id ? 'border-amber-400 ring-2 ring-amber-100' : 'border-slate-200'"
                >
                    <button class="w-full text-left" type="button" @click="selectLevel(level)">
                        <div class="flex items-start justify-between gap-3">
                            <span class="grid h-11 w-11 place-items-center rounded-lg text-white" :style="{ backgroundColor: level.color }"
                                ><Crown class="h-5 w-5" /></span
                            ><span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-bold">Hạng {{ level.rank }}</span>
                        </div>
                        <h2 class="mt-3 text-lg font-black text-slate-950">{{ level.name }}</h2>
                        <dl class="mt-3 grid gap-1.5 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">Mở khóa</dt>
                                <dd class="font-bold">{{ money(level.lifetime_threshold) }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">Duy trì</dt>
                                <dd class="font-bold">{{ money(level.maintenance_amount) }}/{{ level.maintenance_days }} ngày</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">Giảm mặc định</dt>
                                <dd class="font-bold text-emerald-700">{{ level.default_discount_bps / 100 }}%</dd>
                            </div>
                        </dl>
                    </button>
                    <div class="mt-4 flex gap-2">
                        <button
                            class="rounded-md border border-slate-200 px-3 py-2 text-xs font-bold hover:bg-slate-50"
                            type="button"
                            @click="editLevel(level)"
                        >
                            Sửa</button
                        ><button
                            v-if="level.status === 'active'"
                            class="rounded-md border border-rose-200 px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-50"
                            type="button"
                            @click="disableLevel(level)"
                        >
                            Tạm tắt
                        </button>
                    </div>
                </article>
            </section>

            <section class="grid gap-5 xl:grid-cols-[22rem_minmax(0,1fr)]">
                <form class="grid content-start gap-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="saveLevel">
                    <h2 class="font-black text-slate-950">{{ editingLevelId ? 'Chỉnh sửa level' : 'Tạo level' }}</h2>
                    <label class="grid gap-1 text-sm font-bold"
                        >Tên level<input v-model="form.name" class="rounded-md border-slate-300" required
                    /></label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="grid gap-1 text-sm font-bold"
                            >Mã<input v-model="form.code" class="rounded-md border-slate-300" required /></label
                        ><label class="grid gap-1 text-sm font-bold"
                            >Hạng<input v-model.number="form.rank" class="rounded-md border-slate-300" min="0" type="number" required
                        /></label>
                    </div>
                    <label class="grid gap-1 text-sm font-bold"
                        >Tổng nạp mở khóa<input
                            v-model.number="form.lifetime_threshold"
                            class="rounded-md border-slate-300"
                            min="0"
                            type="number"
                            required
                    /></label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="grid gap-1 text-sm font-bold"
                            >Nạp duy trì<input
                                v-model.number="form.maintenance_amount"
                                class="rounded-md border-slate-300"
                                min="0"
                                type="number"
                                required /></label
                        ><label class="grid gap-1 text-sm font-bold"
                            >Số ngày<input v-model.number="form.maintenance_days" class="rounded-md border-slate-300" min="1" type="number" required
                        /></label>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="grid gap-1 text-sm font-bold"
                            >Giảm mặc định (%)<input
                                :value="form.default_discount_bps / 100"
                                class="rounded-md border-slate-300"
                                max="100"
                                min="0"
                                step="0.01"
                                type="number"
                                @input="form.default_discount_bps = Math.round(Number(($event.target as HTMLInputElement).value) * 100)" /></label
                        ><label class="grid gap-1 text-sm font-bold"
                            >Lãi tối thiểu<input v-model.number="form.minimum_profit" class="rounded-md border-slate-300" min="0" type="number"
                        /></label>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="grid gap-1 text-sm font-bold"
                            >Màu<input v-model="form.color" class="h-10 w-full rounded-md border-slate-300" type="color" /></label
                        ><label class="grid gap-1 text-sm font-bold">Icon<input v-model="form.icon" class="rounded-md border-slate-300" /></label>
                    </div>
                    <button
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-amber-500 px-4 font-black hover:bg-amber-400 disabled:opacity-50"
                        :disabled="saving"
                        type="submit"
                    >
                        <LoaderCircle v-if="saving" class="h-4 w-4 animate-spin" /><Save v-else class="h-4 w-4" /> Lưu level
                    </button>
                </form>

                <section class="min-w-0 rounded-lg border border-slate-200 bg-white shadow-sm">
                    <header class="flex flex-col gap-3 border-b border-slate-200 p-5 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="flex items-center gap-2 font-black">
                                <BadgeDollarSign class="h-5 w-5 text-emerald-600" /> Giá riêng {{ selectedLevel?.name }}
                            </h2>
                            <p class="mt-1 text-sm text-slate-500">Giá luôn được chặn bởi giá vốn cộng lợi nhuận tối thiểu.</p>
                        </div>
                        <select v-model="selectedGameId" class="rounded-md border-slate-300 text-sm">
                            <option v-for="game in catalog.games" :key="game.id" :value="game.id">{{ game.name }}</option>
                        </select>
                    </header>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1040px] text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Gói</th>
                                    <th class="px-4 py-3">Giá lẻ / vốn</th>
                                    <th class="px-4 py-3">Kiểu</th>
                                    <th class="px-4 py-3">Mức áp dụng</th>
                                    <th class="px-4 py-3">Lãi tối thiểu</th>
                                    <th class="px-4 py-3">Giá sau giảm</th>
                                    <th class="px-4 py-3 text-right">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="packageItem in selectedGame?.packages ?? []" :key="packageItem.id">
                                    <td class="px-4 py-3 font-bold">{{ packageItem.name }}</td>
                                    <td class="px-4 py-3">{{ money(packageItem.price) }} / {{ money(packageItem.provider_price) }}</td>
                                    <td class="px-4 py-3">
                                        <select v-model="overrideDrafts[packageItem.id].pricing_mode" class="rounded-md border-slate-300">
                                            <option value="discount">Giảm %</option>
                                            <option value="fixed">Giá cố định</option>
                                        </select>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input
                                            v-if="overrideDrafts[packageItem.id].pricing_mode === 'discount'"
                                            v-model="overrideDrafts[packageItem.id].discount_value"
                                            class="w-28 rounded-md border-slate-300"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            :placeholder="`Mặc định ${selectedLevel?.default_discount_bps ? selectedLevel.default_discount_bps / 100 : 0}%`"
                                            type="number"
                                        /><input
                                            v-else
                                            v-model="overrideDrafts[packageItem.id].fixed_price"
                                            class="w-36 rounded-md border-slate-300"
                                            min="0"
                                            placeholder="Giá bán"
                                            type="number"
                                        />
                                    </td>
                                    <td class="px-4 py-3">
                                        <input
                                            v-model="overrideDrafts[packageItem.id].minimum_profit"
                                            class="w-32 rounded-md border-slate-300"
                                            min="0"
                                            placeholder="Mặc định"
                                            type="number"
                                        />
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-black tabular-nums text-emerald-700">{{ money(previewPrice(packageItem).finalPrice) }}</div>
                                        <div class="mt-1 text-xs text-slate-500">
                                            <span v-if="previewPrice(packageItem).isIncomplete">Nhập giá cố định</span>
                                            <span v-else-if="previewPrice(packageItem).discountAmount > 0">
                                                Giảm {{ money(previewPrice(packageItem).discountAmount) }}
                                            </span>
                                            <span v-else>Không giảm</span>
                                        </div>
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            <span
                                                v-if="previewPrice(packageItem).isFloorApplied"
                                                class="inline-flex rounded bg-amber-50 px-1.5 py-0.5 text-[11px] font-bold text-amber-700"
                                            >
                                                Chạm giá sàn
                                            </span>
                                            <span
                                                v-if="previewPrice(packageItem).isProviderPriceMissing"
                                                class="inline-flex rounded bg-rose-50 px-1.5 py-0.5 text-[11px] font-bold text-rose-700"
                                            >
                                                Chưa có giá vốn
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <button class="rounded-md bg-slate-950 p-2 text-white" type="button" @click="saveOverride(packageItem)">
                                                <Save class="h-4 w-4" /></button
                                            ><button
                                                class="rounded-md border border-rose-200 p-2 text-rose-700"
                                                type="button"
                                                @click="deleteOverride(packageItem)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>
        </template>
    </main>
</template>
