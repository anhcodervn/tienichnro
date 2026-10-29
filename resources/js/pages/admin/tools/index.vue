<script setup lang="ts">
import DataTable from '@/components/shared/DataTable/index.vue';
import { adminServiceManagement, type ManagedService } from '@/services/admin-service-management.service';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import Swal from 'sweetalert2';
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();

const services = ref<ManagedService[]>([]);
const loading = ref(true);
const failed = ref(false);
const deletingCode = ref<string | null>(null);
const query = ref('');
const status = ref('all');
const sort = ref('order_asc');
const perPage = ref(10);
const currentPage = ref(1);
const columns = [
    { accessorKey: 'sort_order', header: 'Thứ tự' },
    { id: 'visual', header: 'Icon / Ảnh' },
    { accessorKey: 'name', header: 'Công cụ' },
    { accessorKey: 'code', header: 'Mã công cụ' },
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
const add = () => router.push('/admin/tools/create');
const edit = (service: ManagedService) => router.push(`/admin/tools/${encodeURIComponent(service.code)}/edit`);
const remove = async (service: ManagedService) => {
    if (deletingCode.value !== null) return;
    deletingCode.value = service.code;
    try {
        const result = await Swal.fire({
            icon: 'warning',
            title: 'Xóa công cụ?',
            text: `${service.name} sẽ bị bỏ khỏi danh sách client. Trang SEO và bài viết vẫn được giữ.`,
            showCancelButton: true,
            confirmButtonText: 'Xóa công cụ',
            cancelButtonText: 'Hủy',
            confirmButtonColor: '#dc2626',
        });
        if (!result.isConfirmed) return;
        await adminServiceManagement.remove(service.code);
        services.value = services.value.filter((item) => item.code !== service.code);
        handleSuccessResponse({ data: { status: true, message: 'Đã xóa công cụ.' } });
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
                <h1 class="text-xl font-bold text-slate-950 dark:text-white">Cấu hình công cụ</h1>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                    Quản lý danh sách, hình hiển thị và trạng thái công cụ. Nội dung SEO được giữ riêng.
                </p>
            </div>
            <button
                type="button"
                :disabled="loading || failed || deletingCode !== null"
                class="min-h-11 rounded-[10px] bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-50"
                @click="add"
            >
                Thêm công cụ
            </button>
        </header>
        <div class="flex flex-wrap gap-3 rounded-[10px] border border-slate-300 bg-white p-4 dark:border-slate-600 dark:bg-slate-900">
            <label class="grid flex-1 gap-1 text-xs font-semibold text-slate-700 dark:text-slate-200"
                >Tìm kiếm<input
                    v-model="query"
                    type="search"
                    placeholder="Tên, mã hoặc liên kết công cụ"
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
                    <option value="code_asc">Mã công cụ</option>
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
            Không tải được công cụ. Bấm để thử lại.
        </button>
        <DataTable
            :data="rows"
            :columns="columns"
            :loading="loading"
            :current-page="currentPage"
            :total-pages="totalPages"
            :go-to-page="goToPage"
            empty-text="Không có công cụ phù hợp."
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
                    row.url || (row.route ? 'Trang công cụ có sẵn' : 'Chưa có liên kết')
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
                        :disabled="deletingCode !== null"
                        class="min-h-10 rounded-[10px] border border-slate-300 px-3 text-sm font-semibold text-emerald-700 disabled:opacity-50"
                        @click="edit(row)"
                    >
                        Sửa</button
                    ><button
                        type="button"
                        :disabled="deletingCode !== null"
                        class="min-h-10 rounded-[10px] border border-rose-300 px-3 text-sm font-semibold text-rose-700 disabled:opacity-50"
                        @click="remove(row)"
                    >
                        {{ deletingCode === row.code ? 'Đang xóa…' : 'Xóa' }}
                    </button>
                </div></template
            >
        </DataTable>
        <p class="text-sm text-slate-600 dark:text-slate-300">{{ filtered.length }} công cụ · Trang {{ currentPage }} / {{ totalPages }}</p>
    </div>
</template>
