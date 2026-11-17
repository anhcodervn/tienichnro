<script setup lang="ts">
import Breadcrumb from '@/components/MasterLayouts/Breadcrumb/index.vue';
import DataTable from '@/components/shared/DataTable/index.vue';
import { adminGameVerificationService, gameVerificationStatuses, type VerificationOrder } from '@/services/admin-game-verification.service';
import { handleErrorResponse } from '@/utils/response';
import Swal from 'sweetalert2';
import { computed, onMounted, onUnmounted, ref } from 'vue';
const rows = ref<VerificationOrder[]>([]);
const q = ref('');
const status = ref('');
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const loading = ref(false);
const retrying = ref<number | null>(null);
const clearing = ref(false);
const retentionDays = ref(30);
const cleanupMessage = ref('');
const realtimeStatus = ref('');
const selectedIds = ref<number[]>([]);
const selectableRows = computed(() => rows.value.filter(canDelete));
const allSelected = computed(() => selectableRows.value.length > 0 && selectableRows.value.every((row) => selectedIds.value.includes(row.id)));
function canDelete(row: VerificationOrder) {
    return ['success', 'error'].includes(row.status) && Boolean(row.completed_at);
}
function syncSelection() {
    selectedIds.value = selectedIds.value.filter((id) => selectableRows.value.some((row) => row.id === id));
}
function toggleAll(event: Event) {
    selectedIds.value = (event.target as HTMLInputElement).checked ? selectableRows.value.map((row) => row.id) : [];
}
let stream: EventSource | null = null;
let retryTimer: ReturnType<typeof setTimeout> | undefined;
let disposed = false;
let revision = 0;
function closeStream() {
    stream?.close();
    stream = null;
    clearTimeout(retryTimer);
}
function connectStream(filters: { page: number; q: string; status: string }) {
    closeStream();
    if (typeof window === 'undefined' || disposed || document.hidden || !window.EventSource) return;
    const params = new URLSearchParams({ page: String(filters.page), q: filters.q, status: filters.status });
    const generation = revision;
    stream = new window.EventSource(`/api/admin-api/game-verification/stream?${params}`);
    const source = stream;
    stream.addEventListener('orders', (event) => {
        try {
            const result = JSON.parse(event.data);
            if (stream !== source || generation !== revision || disposed || document.hidden || !Array.isArray(result.data) || !result.meta) return;
            rows.value = result.data;
            syncSelection();
            page.value = result.meta.current_page;
            lastPage.value = result.meta.last_page;
            total.value = result.meta.total;
            realtimeStatus.value = 'Đang cập nhật realtime';
        } catch {
            realtimeStatus.value = 'Chưa nhận được trạng thái mới';
        }
    });
    stream.addEventListener('access-expired', () => {
        if (stream !== source) return;
        closeStream();
        realtimeStatus.value = 'Phiên truy cập đã kết thúc. Vui lòng đăng nhập lại.';
    });
    stream.onerror = () => {
        if (stream !== source) return;
        closeStream();
        if (disposed || document.hidden) return;
        realtimeStatus.value = 'Đang kết nối lại…';
        retryTimer = setTimeout(() => void load(), 5000);
    };
}
const columns = [
    { accessorKey: 'id', header: '#' },
    { accessorKey: 'member', header: 'Người dùng' },
    { accessorKey: 'username', header: 'Tài khoản' },
    { accessorKey: 'status', header: 'Trạng thái / Tiến trình' },
    { accessorKey: 'charged_amount', header: 'Phí' },
    { accessorKey: 'created_at', header: 'Thời gian' },
    { accessorKey: 'actions', header: 'Xử lý' },
];
async function load(requestedPage = page.value) {
    closeStream();
    const current = ++revision;
    const filters = { page: requestedPage, q: q.value, status: status.value };
    loading.value = true;
    try {
        const result = await adminGameVerificationService.history(filters);
        if (current !== revision || disposed) return;
        rows.value = result.data;
        syncSelection();
        page.value = result.meta.current_page;
        lastPage.value = result.meta.last_page;
        total.value = result.meta.total;
        connectStream({ ...filters, page: page.value });
    } catch (error) {
        if (current === revision) handleErrorResponse(error);
    } finally {
        if (current === revision) loading.value = false;
    }
}
async function clearHistory() {
    if (clearing.value || !Number.isInteger(retentionDays.value) || retentionDays.value < 1 || retentionDays.value > 3650) return;
    const days = retentionDays.value;
    const answer = await Swal.fire({
        title: 'Dọn lịch sử lấy cú pháp cũ?',
        text: `Xóa vĩnh viễn tối đa 1.000 đơn hoàn tất hoặc lỗi cách đây hơn ${days} ngày trên toàn hệ thống. Giữ đơn đang chờ, đang xử lý, cần kiểm tra và giao dịch ví. Thao tác không áp dụng bộ lọc hiện tại.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Dọn lịch sử',
        cancelButtonText: 'Hủy',
        confirmButtonColor: '#dc2626',
    });
    if (!answer.isConfirmed || clearing.value || disposed) return;
    clearing.value = true;
    try {
        const result = await adminGameVerificationService.clearHistory(days);
        cleanupMessage.value = `Đã dọn ${result.deleted} đơn cũ.${result.has_more ? ' Còn dữ liệu phù hợp, bấm Dọn lịch sử cũ để tiếp tục.' : ''}`;
        await load(1);
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        clearing.value = false;
    }
}
async function deleteSelected() {
    if (clearing.value) return;
    syncSelection();
    const ids = [...selectedIds.value];
    if (!ids.length) return;
    const answer = await Swal.fire({
        title: `Xóa ${ids.length} đơn lấy cú pháp đã chọn?`,
        text: `Xóa vĩnh viễn dữ liệu các ID: ${ids.map((id) => `#${id}`).join(', ')}. Giao dịch ví được giữ lại.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Xóa đã chọn',
        cancelButtonText: 'Hủy',
        confirmButtonColor: '#dc2626',
    });
    if (!answer.isConfirmed || clearing.value || disposed) return;
    clearing.value = true;
    try {
        const result = await adminGameVerificationService.clearSelected(ids);
        selectedIds.value = [];
        cleanupMessage.value = `Đã xóa ${result.deleted} đơn.${result.skipped ? ` Bỏ qua ${result.skipped} ID không còn tồn tại hoặc chưa đủ điều kiện xóa.` : ''}`;
        await load(1);
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        clearing.value = false;
    }
}
async function retry(row: VerificationOrder) {
    if (retrying.value !== null || !row.can_retry || !row.has_saved_credentials) return;
    const answer = await Swal.fire({
        title: `Chạy lại đơn #${row.id}`,
        text: 'Lấy lại cú pháp bằng tài khoản và mật khẩu đã lưu. Không thu thêm phí.',
        showCancelButton: true,
        confirmButtonText: 'Đưa vào hàng chờ',
        cancelButtonText: 'Hủy',
        confirmButtonColor: '#059669',
    });
    if (!answer.isConfirmed || retrying.value !== null || disposed) return;
    retrying.value = row.id;
    try {
        await adminGameVerificationService.retry(row.id, { attempt_version: row.attempt_version });
        await load();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        retrying.value = null;
    }
}
function visibilityChanged() {
    if (document.hidden) closeStream();
    else void load();
}
onMounted(() => {
    document.addEventListener('visibilitychange', visibilityChanged);
    void load();
});
onUnmounted(() => {
    disposed = true;
    revision++;
    closeStream();
    document.removeEventListener('visibilitychange', visibilityChanged);
});
</script>
<template>
    <div class="grid min-w-0 gap-4 text-slate-900 dark:text-slate-100">
        <Breadcrumb
            title="Lịch sử lấy cú pháp xác minh tài khoản"
            description="Chạy lại miễn phí cho đơn lỗi hoặc pending quá 30 phút. Thêm tài khoản sẽ thu phí và tạo task ngay."
        />
        <form
            class="flex flex-wrap items-end gap-3 rounded-[5px] border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
            @submit.prevent="load(1)"
        >
            <label class="grid flex-1 basis-52 gap-2 text-xs font-semibold"
                >Tài khoản<input
                    v-model="q"
                    maxlength="100"
                    class="min-h-10 rounded-[5px] border border-slate-300 bg-white px-3 dark:border-slate-600 dark:bg-slate-800"
            /></label>
            <label class="grid gap-2 text-xs font-semibold"
                >Trạng thái<select
                    v-model="status"
                    class="min-h-10 rounded-[5px] border border-slate-300 bg-white px-3 dark:border-slate-600 dark:bg-slate-800"
                >
                    <option value="">Tất cả</option>
                    <option v-for="(label, value) in gameVerificationStatuses" :key="value" :value="value">{{ label }}</option>
                </select></label
            >
            <button type="submit" class="min-h-10 rounded-[5px] bg-emerald-600 px-4 text-sm font-semibold text-white">Lọc</button
            ><button type="button" class="min-h-10 rounded-[5px] border border-slate-300 px-4 text-sm" @click="load()">Làm mới</button>
        </form>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <p class="text-xs text-slate-500">
                {{ total }} yêu cầu · 20 dòng / trang <span role="status">· {{ realtimeStatus }}</span>
            </p>
            <div class="flex flex-wrap items-end gap-2">
                <label class="grid gap-1 text-xs text-slate-500"
                    >Dọn đơn đã kết thúc hơn (ngày)
                    <input
                        v-model.number="retentionDays"
                        type="number"
                        min="1"
                        max="3650"
                        :disabled="clearing"
                        class="min-h-10 w-36 rounded-[5px] border border-slate-300 bg-white px-3 text-slate-900 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                    />
                </label>
                <button
                    type="button"
                    :disabled="clearing"
                    class="min-h-10 rounded-[5px] border border-rose-200 px-4 text-sm font-semibold text-rose-600 disabled:opacity-50 dark:border-rose-900 dark:text-rose-400"
                    @click="clearHistory"
                >
                    {{ clearing ? 'Đang dọn…' : 'Dọn dữ liệu đổi MK cũ' }}
                </button>
            </div>
        </div>
        <p v-if="cleanupMessage" role="status" class="text-sm text-emerald-700 dark:text-emerald-400">{{ cleanupMessage }}</p>
        <div
            class="flex flex-wrap items-center justify-between gap-3 rounded-[5px] border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-900"
        >
            <div>
                <label class="flex items-center gap-2 text-sm font-semibold">
                    <input
                        type="checkbox"
                        :checked="allSelected"
                        :indeterminate="selectedIds.length > 0 && !allSelected"
                        :disabled="clearing || loading || !selectableRows.length"
                        class="size-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                        @change="toggleAll"
                    />
                    Chọn tất cả đơn có thể xóa trên trang này
                </label>
                <p class="mt-1 text-xs text-slate-500">Chỉ xóa đơn đã kết thúc. Đã chọn {{ selectedIds.length }} ID.</p>
            </div>
            <button
                type="button"
                :disabled="clearing || loading || !selectedIds.length"
                class="min-h-10 rounded-[5px] bg-rose-600 px-4 text-sm font-semibold text-white hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50"
                @click="deleteSelected"
            >
                Xóa đã chọn ({{ selectedIds.length }})
            </button>
        </div>
        <DataTable
            :data="rows"
            :columns="columns"
            :loading="loading"
            :current-page="page"
            :total-pages="lastPage"
            :go-to-page="load"
            class-custom="min-w-[58rem]"
        >
            <template #id="{ row }">
                <label class="flex items-center gap-2 whitespace-nowrap">
                    <input
                        v-model="selectedIds"
                        type="checkbox"
                        :value="row.id"
                        :disabled="clearing || !canDelete(row)"
                        :aria-label="`Chọn đơn #${row.id}`"
                        class="size-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 disabled:cursor-not-allowed disabled:opacity-40"
                    />
                    #{{ row.id }}
                </label>
            </template>
            <template #username="{ row }"
                ><strong>{{ row.username }}</strong>
                <p class="mt-1 text-xs text-slate-500">Server {{ row.server_code }}</p></template
            >
            <template #status="{ row }"
                ><strong :class="row.status === 'error' ? 'text-rose-600' : 'text-emerald-700'">{{ row.status_label }}</strong>
                <p class="mt-1 max-w-80 text-xs">{{ row.progress }}</p>
                <p v-if="row.error_code" class="mt-1 max-w-80 text-xs text-rose-600">{{ row.error_code }} · {{ row.error_message }}</p></template
            >
            <template #charged_amount="{ row }">{{ Number(row.charged_amount).toLocaleString('vi-VN') }} đ </template>
            <template #created_at="{ row }"
                ><span class="whitespace-nowrap text-xs">{{ new Date(row.created_at).toLocaleString('vi-VN') }}</span></template
            >
            <template #actions="{ row }"
                ><div v-if="row.can_retry" class="grid gap-2">
                    <button
                        v-if="row.can_retry"
                        :disabled="retrying !== null || !row.has_saved_credentials"
                        class="rounded-[5px] bg-emerald-600 px-3 py-2 text-xs font-semibold text-white disabled:opacity-50"
                        @click="retry(row)"
                    >
                        {{ retrying === row.id ? 'Đang đưa vào hàng chờ…' : 'Chạy lại miễn phí' }}
                    </button>
                    <p v-if="row.can_retry && !row.has_saved_credentials" class="text-xs text-slate-500">Đơn cũ không còn mật khẩu đã lưu.</p>
                </div>
                <span v-else class="text-xs text-slate-400">—</span></template
            >
        </DataTable>
    </div>
</template>
