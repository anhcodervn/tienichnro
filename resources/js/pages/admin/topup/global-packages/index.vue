<script setup lang="ts">
import { adminGlobalPackageService, type GlobalPackageCatalog, type GlobalTopupPackage } from '@/services/admin-global-package.service';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { BadgeDollarSign, Boxes, LoaderCircle, Plus, Save, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';

type LevelDraft = { pricing_mode: 'discount' | 'fixed'; discount_value: string; fixed_price: string; minimum_profit: string };
type Preview = { minimum: number; maximum: number; floorApplied: boolean };

const catalog = ref<GlobalPackageCatalog>({ global_packages: [], levels: [] });
const loading = ref(false);
const saving = ref(false);
const editingId = ref<number | null>(null);
const selectedLevelId = ref<number | null>(null);
const drafts = reactive<Record<number, LevelDraft>>({});
const form = reactive({
    name: '',
    code: '',
    denomination: '' as string | number,
    price: '' as string | number,
    original_price: '' as string | number,
    description: '',
    status: 'active' as 'active' | 'inactive',
    sort_order: 0,
});

const selectedLevel = computed(() => catalog.value.levels.find((level) => level.id === selectedLevelId.value) ?? null);
const formFieldClass =
    'min-h-11 w-full rounded-md border-2 border-slate-300 bg-slate-50 px-3 py-2 text-slate-950 outline-none transition hover:border-slate-400 focus:border-violet-500 focus:bg-white focus:ring-4 focus:ring-violet-100';
const compactFieldClass =
    'min-h-10 w-full rounded-md border-2 border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-950 outline-none transition hover:border-slate-400 focus:border-violet-500 focus:bg-white focus:ring-4 focus:ring-violet-100';
const money = (value: number | null): string => `${new Intl.NumberFormat('vi-VN').format(value ?? 0)}đ`;
const notify = (message: string): void => handleSuccessResponse({ data: { status: true, message } });

const resetForm = (): void => {
    editingId.value = null;
    Object.assign(form, {
        name: '',
        code: '',
        denomination: '',
        price: '',
        original_price: '',
        description: '',
        status: 'active',
        sort_order: catalog.value.global_packages.length,
    });
};

const hydrateDrafts = (): void => {
    if (!selectedLevel.value) return;

    for (const globalPackage of catalog.value.global_packages) {
        const override = globalPackage.level_prices.find((price) => price.member_level_id === selectedLevel.value?.id);
        drafts[globalPackage.id] = {
            pricing_mode: override?.pricing_mode ?? 'discount',
            discount_value: override?.discount_basis_points != null ? String(override.discount_basis_points / 100) : '',
            fixed_price: override?.fixed_price != null ? String(override.fixed_price) : '',
            minimum_profit: override?.minimum_profit != null ? String(override.minimum_profit) : '',
        };
    }
};

const load = async (): Promise<void> => {
    loading.value = true;
    try {
        catalog.value = await adminGlobalPackageService.catalog();
        selectedLevelId.value = catalog.value.levels.some((level) => level.id === selectedLevelId.value)
            ? selectedLevelId.value
            : (catalog.value.levels[0]?.id ?? null);
        hydrateDrafts();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

const edit = (globalPackage: GlobalTopupPackage): void => {
    editingId.value = globalPackage.id;
    Object.assign(form, {
        name: globalPackage.name,
        code: globalPackage.code,
        denomination: globalPackage.denomination,
        price: globalPackage.price,
        original_price: globalPackage.original_price,
        description: globalPackage.description ?? '',
        status: globalPackage.status,
        sort_order: globalPackage.sort_order,
    });
};

const save = async (): Promise<void> => {
    saving.value = true;
    try {
        await adminGlobalPackageService.save(editingId.value, {
            ...form,
            denomination: Number(form.denomination),
            price: Number(form.price),
            original_price: Number(form.original_price),
            description: form.description || null,
            sort_order: Number(form.sort_order),
        });
        notify(editingId.value ? 'Đã cập nhật gói nạp Global.' : 'Đã tạo gói nạp Global.');
        resetForm();
        await load();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
};

const remove = async (globalPackage: GlobalTopupPackage): Promise<void> => {
    if (!window.confirm(`Xóa gói Global “${globalPackage.name}”?`)) return;

    try {
        await adminGlobalPackageService.delete(globalPackage.id);
        notify('Đã xóa gói nạp Global.');
        if (editingId.value === globalPackage.id) resetForm();
        await load();
    } catch (error) {
        handleErrorResponse(error);
    }
};

const numberOr = (value: string, fallback: number): number => {
    const parsed = Number(value);
    return value.trim() !== '' && Number.isFinite(parsed) ? Math.max(0, parsed) : fallback;
};

const preview = (globalPackage: GlobalTopupPackage): Preview => {
    const draft = drafts[globalPackage.id];
    const level = selectedLevel.value;
    if (!draft || !level) return { minimum: globalPackage.price, maximum: globalPackage.price, floorApplied: false };

    const discount = Math.min(100, numberOr(draft.discount_value, level.default_discount_bps / 100));
    const candidate =
        draft.pricing_mode === 'fixed'
            ? numberOr(draft.fixed_price, globalPackage.price)
            : globalPackage.price - Math.trunc((globalPackage.price * discount) / 100);
    const profit = numberOr(draft.minimum_profit, level.minimum_profit);
    const minCost = globalPackage.provider_price_min == null ? null : Number(globalPackage.provider_price_min);
    const maxCost = globalPackage.provider_price_max == null ? null : Number(globalPackage.provider_price_max);
    const finalPrice = (cost: number | null): number =>
        cost == null ? Math.min(globalPackage.price, candidate) : Math.min(globalPackage.price, Math.max(candidate, cost + profit));

    return {
        minimum: finalPrice(minCost),
        maximum: finalPrice(maxCost),
        floorApplied: maxCost != null && maxCost + profit > candidate,
    };
};

const saveLevelPrice = async (globalPackage: GlobalTopupPackage): Promise<void> => {
    const level = selectedLevel.value;
    const draft = drafts[globalPackage.id];
    if (!level || !draft) return;
    if (draft.pricing_mode === 'fixed' && draft.fixed_price.trim() === '') {
        handleErrorResponse({ message: `Vui lòng nhập giá cố định cho ${globalPackage.name}.` });
        return;
    }

    try {
        await adminGlobalPackageService.saveLevelPrice(globalPackage.id, level.id, {
            pricing_mode: draft.pricing_mode,
            discount_basis_points:
                draft.pricing_mode === 'discount' ? Math.round(numberOr(draft.discount_value, level.default_discount_bps / 100) * 100) : null,
            fixed_price: draft.pricing_mode === 'fixed' ? Number(draft.fixed_price) : null,
            minimum_profit: draft.minimum_profit.trim() === '' ? null : Number(draft.minimum_profit),
            is_active: true,
        });
        notify(`Đã lưu giá ${globalPackage.name} cho ${level.name}.`);
        await load();
    } catch (error) {
        handleErrorResponse(error);
    }
};

const resetLevelPrice = async (globalPackage: GlobalTopupPackage): Promise<void> => {
    if (!selectedLevel.value) return;
    try {
        await adminGlobalPackageService.deleteLevelPrice(globalPackage.id, selectedLevel.value.id);
        notify(`Đã dùng lại mức giảm mặc định cho ${globalPackage.name}.`);
        await load();
    } catch (error) {
        handleErrorResponse(error);
    }
};

onMounted(async () => {
    resetForm();
    await load();
});
</script>

<template>
    <main class="grid gap-5 p-4 sm:p-6">
        <header class="flex flex-col gap-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-violet-600">Giá bán dùng chung, provider theo từng game</p>
                <h1 class="mt-1 text-2xl font-black text-slate-950">Gói nạp Global</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Chỉnh giá một lần; mỗi game vẫn gửi mã dịch vụ, server và dữ liệu tài khoản tới đúng provider.
                </p>
            </div>
            <button
                type="button"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-slate-950 px-4 font-bold text-white"
                @click="resetForm"
            >
                <Plus class="h-4 w-4" /> Thêm gói Global
            </button>
        </header>

        <div v-if="loading" class="grid min-h-64 place-items-center rounded-lg border border-slate-200 bg-white">
            <LoaderCircle class="h-8 w-8 animate-spin text-slate-400" />
        </div>

        <section v-else class="grid items-start gap-5 xl:grid-cols-[22rem_minmax(0,1fr)]">
            <form class="grid gap-3 rounded-lg border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="save">
                <h2 class="flex items-center gap-2 font-black">
                    <Boxes class="h-5 w-5 text-violet-600" /> {{ editingId ? 'Sửa gói Global' : 'Tạo gói Global' }}
                </h2>
                <label class="grid gap-1 text-sm font-bold">Tên<input v-model.trim="form.name" required :class="formFieldClass" /></label>
                <label class="grid gap-1 text-sm font-bold"
                    >Mã<input v-model.trim="form.code" required pattern="[a-z0-9][a-z0-9_-]*" :class="[formFieldClass, 'font-mono']"
                /></label>
                <label class="grid gap-1 text-sm font-bold"
                    >Mệnh giá<input v-model="form.denomination" required min="1" type="number" :class="formFieldClass"
                /></label>
                <label class="grid gap-1 text-sm font-bold"
                    >Giá bán chung<input v-model="form.price" required min="0" type="number" :class="formFieldClass"
                /></label>
                <label class="grid gap-1 text-sm font-bold"
                    >Giá gốc hiển thị<input v-model="form.original_price" required min="1" type="number" :class="formFieldClass"
                /></label>
                <label class="grid gap-1 text-sm font-bold"
                    >Mô tả<textarea v-model="form.description" rows="3" :class="[formFieldClass, 'font-normal']"></textarea>
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="grid gap-1 text-sm font-bold"
                        >Trạng thái<select v-model="form.status" :class="[formFieldClass, 'font-normal']">
                            <option value="active">Hoạt động</option>
                            <option value="inactive">Tạm tắt</option>
                        </select></label
                    >
                    <label class="grid gap-1 text-sm font-bold"
                        >Thứ tự<input v-model.number="form.sort_order" min="0" type="number" :class="formFieldClass"
                    /></label>
                </div>
                <button
                    :disabled="saving"
                    type="submit"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-violet-600 px-4 font-bold text-white disabled:opacity-50"
                >
                    <Save class="h-4 w-4" /> {{ saving ? 'Đang lưu...' : 'Lưu gói Global' }}
                </button>
            </form>

            <div class="grid min-w-0 gap-4">
                <div
                    class="flex flex-col gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h2 class="font-black text-slate-950">Giá theo level</h2>
                        <p class="text-xs text-slate-500">Giá sau giảm được chặn theo giá vốn của từng game đã ánh xạ.</p>
                    </div>
                    <select v-model="selectedLevelId" :class="compactFieldClass" @change="hydrateDrafts">
                        <option v-for="level in catalog.levels" :key="level.id" :value="level.id">
                            {{ level.name }} · mặc định {{ level.default_discount_bps / 100 }}%
                        </option>
                    </select>
                </div>

                <article
                    v-for="globalPackage in catalog.global_packages"
                    :key="globalPackage.id"
                    class="grid gap-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="font-black text-slate-950">{{ globalPackage.name }}</h3>
                            <p class="text-sm text-slate-500">
                                {{ money(globalPackage.denomination) }} · bán {{ money(globalPackage.price) }} ·
                                {{ globalPackage.packages_count }} gói game ánh xạ
                            </p>
                        </div>
                        <div class="flex gap-3 text-sm font-bold">
                            <button type="button" class="text-violet-700" @click="edit(globalPackage)">Sửa</button
                            ><button type="button" class="text-rose-600" @click="remove(globalPackage)"><Trash2 class="inline h-4 w-4" /> Xóa</button>
                        </div>
                    </div>
                    <div v-if="selectedLevel && drafts[globalPackage.id]" class="grid gap-3 lg:grid-cols-5">
                        <label class="grid gap-1 text-xs font-bold"
                            >Cách tính<select v-model="drafts[globalPackage.id].pricing_mode" :class="compactFieldClass">
                                <option value="discount">Giảm theo %</option>
                                <option value="fixed">Giá cố định</option>
                            </select></label
                        >
                        <label v-if="drafts[globalPackage.id].pricing_mode === 'discount'" class="grid gap-1 text-xs font-bold"
                            >Giảm (%)<input
                                v-model="drafts[globalPackage.id].discount_value"
                                min="0"
                                max="100"
                                step="0.01"
                                type="number"
                                :class="compactFieldClass"
                        /></label>
                        <label v-else class="grid gap-1 text-xs font-bold"
                            >Giá cố định<input v-model="drafts[globalPackage.id].fixed_price" min="0" type="number" :class="compactFieldClass"
                        /></label>
                        <label class="grid gap-1 text-xs font-bold"
                            >Lãi tối thiểu<input v-model="drafts[globalPackage.id].minimum_profit" min="0" type="number" :class="compactFieldClass"
                        /></label>
                        <div class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-800">
                            <span class="block font-bold">Giá sau giảm</span
                            ><strong class="text-base"
                                >{{ money(preview(globalPackage).minimum)
                                }}<template v-if="preview(globalPackage).maximum !== preview(globalPackage).minimum">
                                    – {{ money(preview(globalPackage).maximum) }}</template
                                ></strong
                            ><span v-if="preview(globalPackage).floorApplied" class="block">Có áp sàn giá vốn</span>
                        </div>
                        <div class="flex items-end gap-2">
                            <button
                                type="button"
                                class="min-h-10 flex-1 rounded-md bg-slate-950 px-3 text-sm font-bold text-white"
                                @click="saveLevelPrice(globalPackage)"
                            >
                                <BadgeDollarSign class="mr-1 inline h-4 w-4" /> Lưu giá</button
                            ><button
                                type="button"
                                class="min-h-10 rounded-md border border-slate-300 px-3 text-sm font-bold"
                                @click="resetLevelPrice(globalPackage)"
                            >
                                Mặc định
                            </button>
                        </div>
                    </div>
                </article>
                <p
                    v-if="catalog.global_packages.length === 0"
                    class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500"
                >
                    Chưa có gói nạp Global.
                </p>
            </div>
        </section>
    </main>
</template>
