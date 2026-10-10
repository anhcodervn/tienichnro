<script setup lang="ts">
import DataTable from '@/components/shared/DataTable/index.vue';
import { adminServiceCatalog, type ServiceOffering } from '@/services/admin-service-catalog.service';
import { adminServicePackages, type PackageDraft, type ServicePackage } from '@/services/admin-service-package.service';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/vue';
import { isAxiosError } from 'axios';
import Swal from 'sweetalert2';
import { computed, onMounted, reactive, ref, watch } from 'vue';

const inputClass =
    'min-h-11 w-full min-w-0 rounded-[10px] border border-slate-300 bg-white px-3 py-2 text-sm font-normal dark:border-slate-600 dark:bg-slate-800';
const packages = ref<ServicePackage[]>([]);
const services = ref<ServiceOffering[]>([]);
const loading = ref(true);
const failed = ref(false);
const saving = ref(false);
const deletingId = ref<number | null>(null);
const editingId = ref<number | null>(null);
const showForm = ref(false);
const errors = ref<string[]>([]);
const query = ref('');
const serviceFilter = ref('');
const typeFilter = ref('');
const currentPage = ref(1);
const emptyDraft = (): PackageDraft => ({
    service_code: '',
    name: '',
    description: '',
    price: 0,
    billing_type: 'time',
    duration_days: 30,
    usage_limit: null,
    is_active: true,
    sort_order: 0,
});
const draft = reactive<PackageDraft>(emptyDraft());
const labels = { usage: 'Theo lượt', time: 'Theo thời gian', lifetime: 'Vĩnh viễn' };
const serviceName = (code: string) => services.value.find((service) => service.code === code)?.name || `${code} (dịch vụ đã xoá)`;
const entitlement = (item: ServicePackage) =>
    item.billing_type === 'usage' ? `${item.usage_limit} lượt` : item.billing_type === 'time' ? `${item.duration_days} ngày` : 'Vĩnh viễn';
const filtered = computed(() =>
    packages.value.filter(
        (item) =>
            (!serviceFilter.value || item.service_code === serviceFilter.value) &&
            (!typeFilter.value || item.billing_type === typeFilter.value) &&
            [item.name, serviceName(item.service_code)].some((value) =>
                value.toLocaleLowerCase('vi').includes(query.value.trim().toLocaleLowerCase('vi')),
            ),
    ),
);
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / 10)));
const rows = computed(() => filtered.value.slice((currentPage.value - 1) * 10, currentPage.value * 10));
watch([query, serviceFilter, typeFilter], () => {
    currentPage.value = 1;
});
watch(totalPages, (pages) => {
    currentPage.value = Math.min(currentPage.value, pages);
});
watch(
    () => draft.billing_type,
    (type) => {
        draft.usage_limit = type === 'usage' ? draft.usage_limit || 100 : null;
        draft.duration_days = type === 'time' ? draft.duration_days || 30 : null;
    },
);
const columns = [
    { accessorKey: 'sort_order', header: 'Thứ tự' },
    { accessorKey: 'name', header: 'Gói' },
    { accessorKey: 'service_code', header: 'Dịch vụ' },
    { accessorKey: 'price', header: 'Giá VNĐ' },
    { accessorKey: 'billing_type', header: 'Loại / quyền sử dụng' },
    { accessorKey: 'is_active', header: 'Trạng thái' },
    { id: 'actions', header: 'Thao tác' },
];
const goToPage = async (page: number) => {
    currentPage.value = page;
};
const load = async () => {
    loading.value = true;
    failed.value = false;
    try {
        [packages.value, services.value] = await Promise.all([adminServicePackages.list(), adminServiceCatalog.list()]);
    } catch (error) {
        failed.value = true;
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};
const open = (item: ServicePackage | null = null) => {
    editingId.value = item?.id ?? null;
    Object.assign(
        draft,
        emptyDraft(),
        item || {
            service_code: serviceFilter.value || services.value[0]?.code || '',
            sort_order: Math.max(0, ...packages.value.map((entry) => entry.sort_order)) + 1,
        },
    );
    errors.value = [];
    showForm.value = true;
};
const close = () => {
    if (!saving.value) showForm.value = false;
};
const save = async () => {
    if (saving.value) return;
    saving.value = true;
    errors.value = [];
    try {
        const result = await adminServicePackages.save(
            {
                ...draft,
                usage_limit: draft.billing_type === 'usage' ? draft.usage_limit : null,
                duration_days: draft.billing_type === 'time' ? draft.duration_days : null,
            },
            editingId.value,
        );
        packages.value = [...packages.value.filter((item) => item.id !== result.id), result].sort(
            (a, b) => a.sort_order - b.sort_order || a.id - b.id,
        );
        showForm.value = false;
        handleSuccessResponse({ data: { status: true, message: 'Đã lưu gói dịch vụ.' } });
    } catch (error) {
        errors.value = isAxiosError(error) ? (Object.values(error.response?.data?.errors || {}).flat() as string[]) : [];
        if (!errors.value.length) handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
};
const remove = async (item: ServicePackage) => {
    if (deletingId.value !== null) return;
    const confirmation = await Swal.fire({
        title: 'Xoá gói dịch vụ?',
        text: item.name,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Xoá',
        cancelButtonText: 'Huỷ',
    });
    if (!confirmation.isConfirmed || deletingId.value !== null) return;
    deletingId.value = item.id;
    try {
        await adminServicePackages.remove(item.id);
        packages.value = packages.value.filter((entry) => entry.id !== item.id);
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        deletingId.value = null;
    }
};
onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100">Cấu hình gói</h1>
                <p class="mt-1 text-sm text-slate-500">Quản lý gói theo lượt, thời gian hoặc vĩnh viễn cho từng dịch vụ.</p>
            </div>
            <button
                class="min-h-11 rounded-[10px] bg-emerald-700 px-4 font-semibold text-white disabled:opacity-50"
                :disabled="loading || failed || !services.length"
                @click="open()"
            >
                Thêm gói
            </button>
        </div>
        <div class="grid gap-3 sm:grid-cols-3">
            <label class="grid gap-1 text-sm font-semibold"
                >Tìm kiếm<input v-model="query" :class="inputClass" placeholder="Tên gói hoặc dịch vụ"
            /></label>
            <label class="grid gap-1 text-sm font-semibold"
                >Dịch vụ<select v-model="serviceFilter" :class="inputClass">
                    <option value="">Tất cả dịch vụ</option>
                    <option v-for="service in services" :key="service.code" :value="service.code">{{ service.name }}</option>
                </select></label
            >
            <label class="grid gap-1 text-sm font-semibold"
                >Loại gói<select v-model="typeFilter" :class="inputClass">
                    <option value="">Tất cả loại gói</option>
                    <option v-for="(label, type) in labels" :key="type" :value="type">{{ label }}</option>
                </select></label
            >
        </div>
        <button v-if="failed" class="rounded-lg border border-rose-300 p-3 text-rose-700" @click="load">
            Không tải được dữ liệu. Bấm để thử lại.
        </button>
        <DataTable
            :data="rows"
            :columns="columns"
            :loading="loading"
            :current-page="currentPage"
            :total-pages="totalPages"
            :go-to-page="goToPage"
            empty-text="Chưa có gói phù hợp."
        >
            <template #name="{ row }"
                ><strong>{{ row.name }}</strong>
                <p class="mt-1 max-w-xs whitespace-pre-line text-xs text-slate-500">{{ row.description }}</p></template
            >
            <template #service_code="{ row }">{{ serviceName(row.service_code) }}</template>
            <template #price="{ row }">{{ Number(row.price).toLocaleString('vi-VN') }} đ</template>
            <template #billing_type="{ row }"
                >{{ labels[row.billing_type as keyof typeof labels] }}
                <p class="text-xs text-slate-500">{{ entitlement(row) }}</p></template
            >
            <template #is_active="{ row }">{{ row.is_active ? 'Đang bật' : 'Đã tắt' }}</template>
            <template #actions="{ row }"
                ><div class="flex gap-2">
                    <button class="min-h-11 rounded-lg border border-slate-300 px-3" @click="open(row)">Sửa</button
                    ><button
                        class="min-h-11 rounded-lg border border-rose-300 px-3 text-rose-700 disabled:opacity-50"
                        :disabled="deletingId !== null"
                        @click="remove(row)"
                    >
                        Xoá
                    </button>
                </div></template
            >
        </DataTable>
        <Dialog :open="showForm" class="relative z-[80]" @close="close">
            <div class="fixed inset-0 bg-slate-950/60" aria-hidden="true"></div>
            <div class="fixed inset-0 overflow-y-auto p-4">
                <div class="flex min-h-full items-center justify-center">
                    <DialogPanel class="w-full max-w-xl rounded-xl bg-white p-5 text-slate-900 shadow-xl dark:bg-slate-900 dark:text-slate-100">
                        <DialogTitle class="text-lg font-bold">{{ editingId === null ? 'Thêm gói' : 'Cập nhật gói' }}</DialogTitle>
                        <form class="mt-4 grid gap-4 sm:grid-cols-2" @submit.prevent="save">
                            <label class="grid gap-1 text-sm font-semibold"
                                >Dịch vụ<select v-model="draft.service_code" :class="inputClass" required>
                                    <option value="" disabled>Chọn dịch vụ</option>
                                    <option v-for="service in services" :key="service.code" :value="service.code">{{ service.name }}</option>
                                </select></label
                            >
                            <label class="grid gap-1 text-sm font-semibold"
                                >Tên gói<input v-model="draft.name" :class="inputClass" required maxlength="120"
                            /></label>
                            <label class="grid gap-1 text-sm font-semibold"
                                >Giá VNĐ<input
                                    v-model.number="draft.price"
                                    type="number"
                                    min="0"
                                    max="100000000000"
                                    step="1"
                                    :class="inputClass"
                                    required
                            /></label>
                            <label class="grid gap-1 text-sm font-semibold"
                                >Loại gói<select v-model="draft.billing_type" :class="inputClass">
                                    <option v-for="(label, type) in labels" :key="type" :value="type">{{ label }}</option>
                                </select></label
                            >

                            <label v-if="draft.billing_type === 'usage'" class="grid gap-1 text-sm font-semibold"
                                >Số lượt<input
                                    v-model.number="draft.usage_limit"
                                    type="number"
                                    min="1"
                                    max="1000000000"
                                    step="1"
                                    :class="inputClass"
                                    required
                            /></label>
                            <label v-if="draft.billing_type === 'time'" class="grid gap-1 text-sm font-semibold"
                                >Thời hạn (ngày)<input
                                    v-model.number="draft.duration_days"
                                    type="number"
                                    min="1"
                                    max="36500"
                                    step="1"
                                    :class="inputClass"
                                    required
                            /></label>
                            <p v-if="draft.billing_type === 'lifetime'" class="self-center text-sm text-slate-500">
                                Gói vĩnh viễn không giới hạn thời hạn.
                            </p>
                            <label class="grid gap-1 text-sm font-semibold"
                                >Thứ tự hiển thị<input
                                    v-model.number="draft.sort_order"
                                    type="number"
                                    min="0"
                                    max="1000000"
                                    step="1"
                                    :class="inputClass"
                                    required
                            /></label>
                            <label class="grid gap-1 text-sm font-semibold sm:col-span-2"
                                >Mô tả<textarea v-model="draft.description" :class="inputClass" rows="3" maxlength="2000"></textarea>
                            </label>
                            <label class="flex items-center gap-2 text-sm font-semibold sm:col-span-2"
                                ><input v-model="draft.is_active" type="checkbox" /> Bật gói trên website</label
                            >
                            <ul
                                v-if="errors.length"
                                role="alert"
                                class="list-inside list-disc rounded-lg bg-rose-50 p-3 text-sm text-rose-700 sm:col-span-2"
                            >
                                <li v-for="error in errors" :key="error">{{ error }}</li>
                            </ul>
                            <div class="flex justify-end gap-3 sm:col-span-2">
                                <button type="button" class="min-h-11 rounded-lg border border-slate-300 px-4" :disabled="saving" @click="close">
                                    Huỷ</button
                                ><button
                                    type="submit"
                                    class="min-h-11 rounded-lg bg-emerald-700 px-4 font-semibold text-white disabled:opacity-50"
                                    :disabled="saving"
                                >
                                    {{ saving ? 'Đang lưu…' : 'Lưu gói' }}
                                </button>
                            </div>
                        </form>
                    </DialogPanel>
                </div>
            </div>
        </Dialog>
    </div>
</template>
