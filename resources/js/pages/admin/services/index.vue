<script setup lang="ts">
import DataTable from '@/components/shared/DataTable/index.vue';
import UploadImage from '@/components/shared/UpladImage/index.vue';
import { adminServiceManagement, type ManagedService } from '@/services/admin-service-management.service';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/vue';
import { isAxiosError } from 'axios';
import Swal from 'sweetalert2';
import { computed, onMounted, reactive, ref, watch } from 'vue';

const services = ref<ManagedService[]>([]);
const loading = ref(true);
const failed = ref(false);
const saving = ref(false);
const deletingCode = ref<string | null>(null);
const editingCode = ref<string | null>(null);
const showForm = ref(false);
const errors = ref<string[]>([]);
const query = ref('');
const status = ref('all');
const sort = ref('order_asc');
const perPage = ref(10);
const currentPage = ref(1);
const emptyDraft = (): ManagedService => ({
    code: '',
    name: '',
    sort_order: 1,
    description: '',
    icon_type: 'icon',
    icon: 'bx-grid-alt',
    image_url: null,
    url: null,
    route: null,
    is_enabled: true,
    is_available: false,
    maintenance_message: 'Dịch vụ đang bảo trì. Vui lòng quay lại sau.',
});
const draft = reactive<ManagedService>(emptyDraft());
const columns = [
    { accessorKey: 'sort_order', header: 'Thứ tự' },
    { id: 'visual', header: 'Icon / Ảnh' },
    { accessorKey: 'name', header: 'Dịch vụ' },
    { accessorKey: 'code', header: 'Mã dịch vụ' },
    { accessorKey: 'url', header: 'Liên kết' },
    { accessorKey: 'is_enabled', header: 'Trạng thái' },
    { id: 'actions', header: 'Thao tác' },
];
const filtered = computed(() => {
    const keyword = query.value.trim().toLocaleLowerCase('vi');
    return services.value
        .filter((service) => {
            const matches = [service.name, service.code, service.description, service.url].some((value) =>
                (value || '').toLocaleLowerCase('vi').includes(keyword),
            );
            const matchesStatus =
                status.value === 'all' ||
                (status.value === 'enabled' && service.is_enabled) ||
                (status.value === 'disabled' && !service.is_enabled) ||
                (status.value === 'pending' && !service.is_available);
            return matches && matchesStatus;
        })
        .sort((left, right) =>
            sort.value === 'order_asc'
                ? left.sort_order - right.sort_order
                : sort.value === 'code_asc'
                  ? left.code.localeCompare(right.code)
                  : (sort.value === 'name_desc' ? -1 : 1) * left.name.localeCompare(right.name, 'vi') || left.code.localeCompare(right.code),
        );
});
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage.value)));
const rows = computed(() => filtered.value.slice((currentPage.value - 1) * perPage.value, currentPage.value * perPage.value));
watch([query, status, sort, perPage], () => {
    currentPage.value = 1;
});
watch(totalPages, (total) => {
    currentPage.value = Math.min(currentPage.value, total);
});
const goToPage = async (page: number) => {
    currentPage.value = Math.min(Math.max(page, 1), totalPages.value);
};
const load = async () => {
    loading.value = true;
    failed.value = false;
    try {
        services.value = await adminServiceManagement.list();
    } catch (error) {
        failed.value = true;
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};
const close = () => {
    if (!saving.value) {
        showForm.value = false;
        errors.value = [];
    }
};
const add = () => {
    editingCode.value = null;
    Object.assign(draft, emptyDraft());
    draft.sort_order = Math.max(0, ...services.value.map((service) => service.sort_order)) + 1;
    errors.value = [];
    showForm.value = true;
};
const edit = (service: ManagedService) => {
    editingCode.value = service.code;
    Object.assign(draft, emptyDraft(), service);
    errors.value = [];
    showForm.value = true;
};
const save = async () => {
    if (saving.value) return;
    saving.value = true;
    errors.value = [];
    try {
        const result = editingCode.value === null ? await adminServiceManagement.create(draft) : await adminServiceManagement.update(draft);
        services.value = [...services.value.filter((service) => service.code !== result.code), result];
        showForm.value = false;
        handleSuccessResponse({ data: { status: true, message: 'Đã lưu dịch vụ.' } });
    } catch (error) {
        if (isAxiosError<{ errors?: Record<string, string[]>; message?: string }>(error))
            errors.value = error.response?.data.errors
                ? Object.values(error.response.data.errors).flat()
                : [error.response?.data.message || 'Không lưu được dịch vụ.'];
        else handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
};
const remove = async (service: ManagedService) => {
    if (deletingCode.value !== null || saving.value) return;
    deletingCode.value = service.code;
    try {
        const result = await Swal.fire({
            icon: 'warning',
            title: 'Xóa dịch vụ?',
            text: `${service.name} sẽ bị bỏ khỏi danh sách client. Trang SEO và bài viết vẫn được giữ.`,
            showCancelButton: true,
            confirmButtonText: 'Xóa dịch vụ',
            cancelButtonText: 'Hủy',
            confirmButtonColor: '#dc2626',
        });
        if (!result.isConfirmed) return;
        await adminServiceManagement.remove(service.code);
        services.value = services.value.filter((item) => item.code !== service.code);
        handleSuccessResponse({ data: { status: true, message: 'Đã xóa dịch vụ.' } });
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        deletingCode.value = null;
    }
};
onMounted(load);
</script>

<template>
    <div class="mx-auto grid max-w-7xl gap-5">
        <header
            class="flex flex-wrap items-center justify-between gap-4 rounded-[10px] border border-slate-300 bg-white p-5 dark:border-slate-600 dark:bg-slate-900"
        >
            <div>
                <h1 class="text-xl font-bold text-slate-950 dark:text-white">Quản lý dịch vụ</h1>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                    Quản lý danh sách, hình hiển thị và trạng thái dịch vụ. Nội dung SEO được giữ riêng.
                </p>
            </div>
            <button
                type="button"
                :disabled="loading || failed || saving || deletingCode !== null"
                class="min-h-11 rounded-[10px] bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-50"
                @click="add"
            >
                Thêm dịch vụ
            </button>
        </header>
        <div class="flex flex-wrap gap-3 rounded-[10px] border border-slate-300 bg-white p-4 dark:border-slate-600 dark:bg-slate-900">
            <label class="grid flex-1 gap-1 text-xs font-semibold text-slate-700 dark:text-slate-200"
                >Tìm kiếm<input
                    v-model="query"
                    type="search"
                    placeholder="Tên, mã hoặc liên kết dịch vụ"
                    class="min-h-11 min-w-0 rounded-[10px] border border-slate-300 bg-white px-3 text-sm font-normal dark:border-slate-600 dark:bg-slate-800"
            /></label>
            <label class="grid gap-1 text-xs font-semibold text-slate-700 dark:text-slate-200"
                >Trạng thái<select
                    v-model="status"
                    class="min-h-11 rounded-[10px] border border-slate-300 bg-white px-3 text-sm dark:border-slate-600 dark:bg-slate-800"
                >
                    <option value="all">Tất cả</option>
                    <option value="enabled">Đang bật</option>
                    <option value="disabled">Bảo trì</option>
                    <option value="pending">Sắp ra mắt</option>
                </select></label
            >
            <label class="grid gap-1 text-xs font-semibold text-slate-700 dark:text-slate-200"
                >Sắp xếp<select
                    v-model="sort"
                    class="min-h-11 rounded-[10px] border border-slate-300 bg-white px-3 text-sm dark:border-slate-600 dark:bg-slate-800"
                >
                    <option value="order_asc">Thứ tự hiển thị</option>
                    <option value="name_asc">Tên A → Z</option>
                    <option value="name_desc">Tên Z → A</option>
                    <option value="code_asc">Mã dịch vụ</option>
                </select></label
            >
            <label class="grid gap-1 text-xs font-semibold text-slate-700 dark:text-slate-200"
                >Mỗi trang<select
                    v-model.number="perPage"
                    class="min-h-11 rounded-[10px] border border-slate-300 bg-white px-3 text-sm dark:border-slate-600 dark:bg-slate-800"
                >
                    <option :value="10">10</option>
                    <option :value="25">25</option>
                    <option :value="50">50</option>
                </select></label
            >
        </div>
        <button v-if="failed" type="button" class="min-h-11 rounded-[10px] border border-rose-300 p-3 text-rose-700" @click="load">
            Không tải được dịch vụ. Bấm để thử lại.
        </button>
        <DataTable
            :data="rows"
            :columns="columns"
            :loading="loading"
            :current-page="currentPage"
            :total-pages="totalPages"
            :go-to-page="goToPage"
            empty-text="Không có dịch vụ phù hợp."
        >
            <template #visual="{ row }"
                ><span
                    class="relative grid size-14 place-items-center overflow-hidden rounded-[10px] border border-slate-200 bg-emerald-50 text-2xl text-emerald-700"
                    ><img v-if="row.icon_type === 'image' && row.image_url" :src="row.image_url" alt="" class="h-full w-full object-cover" /><i
                        v-else
                        class="bx"
                        :class="row.icon"
                        aria-hidden="true"
                    ></i></span
            ></template>
            <template #name="{ row }"
                ><strong>{{ row.name }}</strong>
                <p class="mt-1 max-w-xs break-words text-xs leading-5 text-slate-500">{{ row.description }}</p></template
            >
            <template #url="{ row }"
                ><span class="block max-w-xs break-all text-xs">{{
                    row.url || (row.route ? 'Trang dịch vụ có sẵn' : 'Chưa có liên kết')
                }}</span></template
            >
            <template #is_enabled="{ row }"
                ><span
                    class="inline-flex whitespace-nowrap rounded-[10px] border px-2 py-1 text-xs font-semibold"
                    :class="
                        !row.is_enabled
                            ? 'border-rose-200 bg-rose-50 text-rose-800'
                            : row.is_available
                              ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                              : 'border-amber-200 bg-amber-50 text-amber-800'
                    "
                    >{{ !row.is_enabled ? 'Bảo trì' : row.is_available ? 'Hoạt động' : 'Sắp ra mắt' }}</span
                ></template
            >
            <template #actions="{ row }"
                ><div class="flex gap-2">
                    <button
                        type="button"
                        :disabled="saving || deletingCode !== null"
                        class="min-h-10 rounded-[10px] border border-slate-300 px-3 text-sm font-semibold text-emerald-700 disabled:opacity-50"
                        @click="edit(row)"
                    >
                        Sửa</button
                    ><button
                        type="button"
                        :disabled="saving || deletingCode !== null"
                        class="min-h-10 rounded-[10px] border border-rose-300 px-3 text-sm font-semibold text-rose-700 disabled:opacity-50"
                        @click="remove(row)"
                    >
                        {{ deletingCode === row.code ? 'Đang xóa…' : 'Xóa' }}
                    </button>
                </div></template
            >
        </DataTable>
        <p class="text-sm text-slate-600 dark:text-slate-300">{{ filtered.length }} dịch vụ · Trang {{ currentPage }} / {{ totalPages }}</p>
        <Dialog :open="showForm" class="relative z-50" @close="close">
            <div class="fixed inset-0 bg-slate-950/60" aria-hidden="true"></div>
            <div class="fixed inset-0 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <DialogPanel
                        class="w-full max-w-2xl rounded-[10px] border border-slate-300 bg-white p-5 shadow-xl dark:border-slate-600 dark:bg-slate-900 sm:p-6"
                    >
                        <div class="mb-5 flex items-center justify-between gap-3">
                            <DialogTitle class="text-xl font-bold text-slate-900 dark:text-white">{{
                                editingCode === null ? 'Thêm dịch vụ' : 'Sửa dịch vụ'
                            }}</DialogTitle
                            ><button
                                type="button"
                                :disabled="saving"
                                class="min-h-10 rounded-[10px] border border-slate-300 px-3 text-sm dark:text-white"
                                @click="close"
                            >
                                Đóng
                            </button>
                        </div>
                        <ul
                            v-if="errors.length"
                            role="alert"
                            class="mb-4 grid gap-1 rounded-[10px] border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800"
                        >
                            <li v-for="message in errors" :key="message">{{ message }}</li>
                        </ul>
                        <form @submit.prevent="save">
                            <fieldset :disabled="saving" class="grid min-w-0 gap-4 disabled:opacity-60">
                                <div>
                                    <h2 class="font-bold text-slate-900 dark:text-white">{{ draft.name }}</h2>
                                    <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ draft.description }}</p>
                                    <p v-if="!(draft.route || draft.url)" class="mt-2 text-xs font-semibold text-amber-700 dark:text-amber-400">
                                        Đang phát triển. Bật dịch vụ vẫn hiển thị “Sắp ra mắt” cho đến khi có chức năng.
                                    </p>
                                </div>
                                <label class="grid gap-2 text-sm font-semibold text-slate-800 dark:text-slate-200">
                                    Mã dịch vụ
                                    <input
                                        v-model="draft.code"
                                        :readonly="editingCode !== null"
                                        required
                                        maxlength="64"
                                        pattern="[a-z][a-z0-9_-]*"
                                        placeholder="dich_vu_moi"
                                        class="min-h-11 w-full rounded-[10px] border border-slate-300 bg-white px-3 font-normal read-only:bg-slate-100 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                                    />
                                    <span class="text-xs font-normal text-slate-500"
                                        >Dùng chữ thường, số, dấu gạch ngang hoặc gạch dưới. Mã không đổi sau khi tạo.</span
                                    >
                                </label>
                                <label class="grid gap-2 text-sm font-semibold text-slate-800 dark:text-slate-200">
                                    Thứ tự hiển thị
                                    <input
                                        v-model.number="draft.sort_order"
                                        type="number"
                                        min="0"
                                        max="1000000"
                                        step="1"
                                        required
                                        class="min-h-11 w-full rounded-[10px] border border-slate-300 bg-white px-3 font-normal focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                                    />
                                    <span class="text-xs font-normal text-slate-500">Số nhỏ hiển thị trước trong danh sách công cụ.</span>
                                </label>
                                <label class="grid gap-2 text-sm font-semibold text-slate-800 dark:text-slate-200">
                                    Tên hiển thị
                                    <input
                                        v-model="draft.name"
                                        required
                                        maxlength="80"
                                        class="min-h-11 w-full rounded-[10px] border border-slate-300 bg-white px-3 font-normal focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                                    />
                                </label>
                                <label class="grid gap-2 text-sm font-semibold text-slate-800 dark:text-slate-200">
                                    Mô tả dịch vụ
                                    <textarea
                                        v-model="draft.description"
                                        maxlength="500"
                                        rows="2"
                                        class="w-full rounded-[10px] border border-slate-300 bg-white p-3 font-normal dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                                    ></textarea>
                                </label>
                                <label class="grid gap-2 text-sm font-semibold text-slate-800 dark:text-slate-200">
                                    Liên kết dịch vụ
                                    <input
                                        v-model="draft.url"
                                        maxlength="2048"
                                        placeholder="/duong-dan-dich-vu hoặc https://..."
                                        class="min-h-11 w-full rounded-[10px] border border-slate-300 bg-white px-3 font-normal dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                                    />
                                    <span class="text-xs font-normal text-slate-500">{{
                                        draft.route ? 'Để trống để dùng trang dịch vụ có sẵn.' : 'Để trống để hiển thị Sắp ra mắt.'
                                    }}</span>
                                </label>
                                <label class="grid gap-2 text-sm font-semibold text-slate-800 dark:text-slate-200">
                                    Hình hiển thị
                                    <select
                                        v-model="draft.icon_type"
                                        class="min-h-11 w-full rounded-[10px] border border-slate-300 bg-white px-3 font-normal focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                                    >
                                        <option value="icon">Icon</option>
                                        <option value="image">Ảnh vuông 1:1</option>
                                    </select>
                                </label>
                                <label v-if="draft.icon_type === 'icon'" class="grid gap-2 text-sm font-semibold text-slate-800 dark:text-slate-200">
                                    Mã icon Boxicons
                                    <input
                                        v-model="draft.icon"
                                        required
                                        maxlength="80"
                                        pattern="bx-[a-z0-9-]+"
                                        placeholder="bx-bell"
                                        class="min-h-11 w-full rounded-[10px] border border-slate-300 bg-white px-3 font-normal focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                                    />
                                    <span class="text-xs font-normal text-slate-500 dark:text-slate-400"
                                        >Ví dụ: bx-bell, bx-lock-alt, bx-shield-quarter, bx-download.</span
                                    >
                                </label>
                                <div v-else class="grid gap-3">
                                    <UploadImage
                                        :accept="['image/jpeg', 'image/png', 'image/webp']"
                                        :square-size="256"
                                        :image-src="draft.image_url"
                                        :name-image="`service-${draft.code}`"
                                        @uploaded="draft.image_url = $event"
                                    />
                                    <label class="grid gap-2 text-sm font-semibold text-slate-800 dark:text-slate-200">
                                        URL ảnh
                                        <input
                                            v-model="draft.image_url"
                                            required
                                            maxlength="2048"
                                            placeholder="/storage/uploads/draft.webp"
                                            class="min-h-11 w-full rounded-[10px] border border-slate-300 bg-white px-3 font-normal focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                                        />
                                    </label>
                                    <p class="text-xs leading-5 text-slate-500 dark:text-slate-400">
                                        Ảnh tải lên được cắt giữa thành ảnh vuông 256 × 256. Có thể dùng URL ảnh có sẵn.
                                    </p>
                                </div>
                                <div
                                    class="flex items-center gap-3 rounded-[10px] border border-slate-200 bg-slate-50 p-3 dark:border-slate-600 dark:bg-slate-800"
                                    aria-label="Xem trước dịch vụ"
                                >
                                    <span
                                        class="relative grid aspect-square w-24 shrink-0 place-items-center overflow-hidden rounded-[10px] border border-emerald-200 bg-white text-4xl text-emerald-700"
                                    >
                                        <img
                                            v-if="draft.icon_type === 'image' && draft.image_url"
                                            :src="draft.image_url"
                                            alt=""
                                            class="absolute inset-0 h-full w-full object-cover"
                                        />
                                        <i v-else class="bx" :class="draft.icon" aria-hidden="true"></i>
                                        <span
                                            v-if="!draft.is_enabled || !(draft.route || draft.url)"
                                            class="absolute inset-x-1 bottom-2 rounded-[6px] border border-amber-300 bg-amber-100 py-0.5 text-center text-[10px] font-semibold leading-4 text-amber-900"
                                            >{{ draft.is_enabled ? 'Sắp ra mắt' : 'Bảo trì' }}</span
                                        >
                                    </span>
                                    <strong class="min-w-0 break-words text-sm text-slate-900 dark:text-white">{{ draft.name }}</strong>
                                </div>
                                <label
                                    class="flex min-h-11 cursor-pointer items-center gap-3 text-sm font-semibold text-slate-800 dark:text-slate-200"
                                >
                                    <input v-model="draft.is_enabled" type="checkbox" class="size-5 accent-emerald-600" />
                                    {{ draft.is_enabled ? 'Bật dịch vụ' : 'Tắt dịch vụ · Bảo trì' }}
                                </label>
                                <label class="grid gap-2 text-sm font-semibold text-slate-800 dark:text-slate-200">
                                    Nội dung thông báo bảo trì
                                    <textarea
                                        v-model="draft.maintenance_message"
                                        required
                                        maxlength="1000"
                                        rows="3"
                                        class="w-full min-w-0 rounded-[10px] border border-slate-300 bg-white p-3 font-normal focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                                    ></textarea>
                                </label>
                                <button
                                    type="submit"
                                    class="min-h-11 rounded-[10px] bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2"
                                >
                                    {{ saving ? 'Đang lưu…' : 'Lưu dịch vụ' }}
                                </button>
                            </fieldset>
                        </form>
                    </DialogPanel>
                </div>
            </div>
        </Dialog>
    </div>
</template>
