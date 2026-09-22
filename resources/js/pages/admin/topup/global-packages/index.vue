<script setup lang="ts">
import { adminGlobalPackageService, type GlobalPackageCatalog, type GlobalTopupPackage } from '@/services/admin-global-package.service';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { Boxes, LoaderCircle, Plus, Save, Trash2 } from 'lucide-vue-next';
import { onMounted, reactive, ref } from 'vue';

const catalog = ref<GlobalPackageCatalog>({ global_packages: [], providers: [] });
const loading = ref(false);
const saving = ref(false);
const editingId = ref<number | null>(null);
const form = reactive({
    provider_id: '' as string | number,
    name: '',
    code: '',
    denomination: '' as string | number,
    provider_price: '' as string | number,
    price: '' as string | number,
    description: '',
    bonus_text: '',
    status: 'active' as 'active' | 'inactive',
    sort_order: 0,
});

const formFieldClass =
    'min-h-11 w-full rounded-md border-2 border-slate-300 bg-slate-50 px-3 py-2 text-slate-950 outline-none transition hover:border-slate-400 focus:border-violet-500 focus:bg-white focus:ring-4 focus:ring-violet-100';
const money = (value: number | null): string => `${new Intl.NumberFormat('vi-VN').format(value ?? 0)}đ`;
const notify = (message: string): void => handleSuccessResponse({ data: { status: true, message } });
const resetForm = (): void => {
    editingId.value = null;
    Object.assign(form, {
        provider_id: '',
        name: '',
        code: '',
        denomination: '',
        provider_price: '',
        price: '',
        description: '',
        bonus_text: '',
        status: 'active',
        sort_order: catalog.value.global_packages.length,
    });
};

const load = async (): Promise<void> => {
    loading.value = true;
    try {
        catalog.value = await adminGlobalPackageService.catalog();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

const edit = (globalPackage: GlobalTopupPackage): void => {
    editingId.value = globalPackage.id;
    Object.assign(form, {
        provider_id: globalPackage.provider_id ?? '',
        name: globalPackage.name,
        code: globalPackage.code,
        denomination: globalPackage.denomination,
        provider_price: globalPackage.provider_price,
        price: globalPackage.price,
        description: globalPackage.description ?? '',
        bonus_text: globalPackage.bonus_text ?? '',
        status: globalPackage.status,
        sort_order: globalPackage.sort_order,
    });
};

const save = async (): Promise<void> => {
    saving.value = true;
    try {
        await adminGlobalPackageService.save(editingId.value, {
            ...form,
            provider_id: form.provider_id === '' ? null : Number(form.provider_id),
            denomination: Number(form.denomination),
            provider_price: Number(form.provider_price),
            price: Number(form.price),
            description: form.description || null,
            bonus_text: form.bonus_text || null,
            metadata: {},
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

onMounted(async () => {
    await load();
    resetForm();
});
</script>

<template>
    <main class="grid gap-5 p-4 sm:p-6">
        <header class="flex flex-col gap-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-violet-600">Gói hoàn chỉnh dùng chung cho mọi game Global</p>
                <h1 class="mt-1 text-2xl font-black text-slate-950">Gói nạp Global</h1>
                <p class="mt-1 text-sm text-slate-500">Quản lý mệnh giá, giá bán chung và giá vốn provider cho mọi game dùng Global.</p>
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

        <section v-else class="grid items-start gap-5 xl:grid-cols-[30rem_minmax(0,1fr)]">
            <form class="grid gap-3 rounded-lg border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="save">
                <h2 class="flex items-center gap-2 font-black">
                    <Boxes class="h-5 w-5 text-violet-600" /> {{ editingId ? 'Sửa gói Global' : 'Tạo gói Global' }}
                </h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="grid gap-1 text-sm font-bold">Tên<input v-model.trim="form.name" required :class="formFieldClass" /></label>
                    <label class="grid gap-1 text-sm font-bold"
                        >Mã<input v-model.trim="form.code" required pattern="[a-z0-9][a-z0-9_-]*" :class="[formFieldClass, 'font-mono']"
                    /></label>
                    <label class="grid gap-1 text-sm font-bold"
                        >Mệnh giá<input v-model="form.denomination" required min="1" type="number" :class="formFieldClass"
                    /></label>
                </div>

                <div class="grid gap-3 rounded-md border border-violet-200 bg-violet-50/60 p-3">
                    <h3 class="text-sm font-black text-violet-950">Provider dùng chung</h3>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="grid gap-1 text-sm font-bold"
                            >Provider<select v-model="form.provider_id" :class="[formFieldClass, 'font-normal']">
                                <option value="">Xử lý thủ công</option>
                                <option v-for="provider in catalog.providers" :key="provider.id" :value="provider.id">
                                    {{ provider.name }} · {{ provider.slug }}
                                </option>
                            </select></label
                        >
                        <label class="grid gap-1 text-sm font-bold"
                            >Giá vốn provider<input v-model="form.provider_price" required min="0" type="number" :class="formFieldClass"
                        /></label>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="grid gap-1 text-sm font-bold"
                        >Giá bán chung<input v-model="form.price" required min="0" type="number" :class="formFieldClass"
                    /></label>
                </div>

                <label class="grid gap-1 text-sm font-bold"
                    >Nhãn khuyến mãi<input v-model.trim="form.bonus_text" maxlength="255" :class="formFieldClass"
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
                <article
                    v-for="globalPackage in catalog.global_packages"
                    :key="globalPackage.id"
                    class="grid gap-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="font-black text-slate-950">{{ globalPackage.name }}</h3>
                            <p class="text-sm text-slate-500">
                                {{ money(globalPackage.denomination) }} · {{ globalPackage.provider_name ?? 'Thủ công' }} · vốn
                                {{ money(globalPackage.provider_price) }} · bán {{ money(globalPackage.price) }} ·
                                {{ globalPackage.packages_count }} gói game tự đồng bộ
                            </p>
                        </div>
                        <div class="flex gap-3 text-sm font-bold">
                            <button type="button" class="text-violet-700" @click="edit(globalPackage)">Sửa</button
                            ><button type="button" class="text-rose-600" @click="remove(globalPackage)"><Trash2 class="inline h-4 w-4" /> Xóa</button>
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
