<script setup lang="ts">
import Breadcrumb from '@/components/MasterLayouts/Breadcrumb/index.vue';
import DataTable from '@/components/shared/DataTable/index.vue';
import { adminGameImageService, gameImageStatuses, type GameImageHistoryRow } from '@/services/admin-game-image.service';
import { handleErrorResponse } from '@/utils/response';
import Swal from 'sweetalert2';
import { onMounted, ref } from 'vue';

const rows = ref<GameImageHistoryRow[]>([]);
const q = ref('');
const status = ref('');
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const loading = ref(false);
const servers = ref<{ code: string; name: string }[]>([]);
const editing = ref<GameImageHistoryRow | null>(null);
const draft = ref({ username: '', server: '', password: '' });
const saving = ref(false);
const deleting = ref<number | null>(null);
const showPassword = ref(false);
const openEdit = (row: GameImageHistoryRow) => {
    editing.value = row;
    draft.value = { username: row.username, server: row.server, password: '' };
    showPassword.value = false;
};
const closeEdit = () => {
    if (saving.value) return;
    editing.value = null;
    draft.value.password = '';
};
const saveAccount = async () => {
    if (!editing.value || saving.value) return;
    saving.value = true;
    try {
        await adminGameImageService.update(editing.value.id, {
            username: draft.value.username,
            server: draft.value.server,
            capture_version: editing.value.capture_version,
            ...(draft.value.password ? { password: draft.value.password } : {}),
        });
        editing.value = null;
        draft.value.password = '';
        await load(page.value);
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
};
const removeAccount = async (row: GameImageHistoryRow) => {
    if (deleting.value !== null) return;
    const result = await Swal.fire({
        title: 'Xoá tài khoản ghép ảnh?',
        text: 'Ảnh, bố cục và log sẽ bị xoá. Lượt chụp đang chờ hoặc đang chạy được huỷ và hoàn phí. Giao dịch ví vẫn được giữ.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Xoá tài khoản',
        cancelButtonText: 'Huỷ',
    });
    if (!result.isConfirmed) return;
    deleting.value = row.id;
    try {
        await adminGameImageService.remove(row.id, row.capture_version);
        await load(rows.value.length === 1 && page.value > 1 ? page.value - 1 : page.value);
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        deleting.value = null;
    }
};
let revision = 0;
const columns = [
    { accessorKey: 'id', header: '#' },
    { accessorKey: 'member', header: 'Người dùng' },
    { accessorKey: 'username', header: 'Tài khoản game' },
    { accessorKey: 'status', header: 'Trạng thái' },
    { accessorKey: 'charged_amount', header: 'Đã thu / hoàn' },
    { accessorKey: 'created_at', header: 'Thời gian' },
    { accessorKey: 'actions', header: 'Thao tác' },
];
const money = (value: number) => new Intl.NumberFormat('vi-VN').format(value) + ' đ';
const date = (value: string | null) => (value ? new Date(value).toLocaleString('vi-VN') : '—');
const load = async (requestedPage = page.value) => {
    const current = ++revision;
    loading.value = true;
    try {
        const result = await adminGameImageService.history({ page: requestedPage, q: q.value, status: status.value });
        if (current !== revision) return;
        rows.value = result.data;
        servers.value = result.servers ?? [];
        page.value = result.meta.current_page;
        lastPage.value = result.meta.last_page;
        total.value = result.meta.total;
    } catch (error) {
        if (current === revision) handleErrorResponse(error);
    } finally {
        if (current === revision) loading.value = false;
    }
};
onMounted(() => load());
</script>

<template>
    <div class="grid min-w-0 gap-4 text-slate-900 dark:text-slate-100">
        <Breadcrumb title="Lịch sử ghép ảnh" description="Theo dõi tài khoản, trạng thái chụp ảnh và giao dịch thu / hoàn tiền." />
        <form
            class="flex flex-wrap items-end gap-3 rounded-[5px] border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
            @submit.prevent="load(1)"
        >
            <label class="grid min-w-0 flex-1 basis-56 gap-1.5 text-xs font-semibold"
                >Tìm người dùng, tài khoản hoặc server<input
                    v-model="q"
                    maxlength="100"
                    class="min-h-10 rounded-[5px] border border-slate-300 bg-white px-3 text-sm dark:border-slate-600 dark:bg-slate-800"
            /></label>
            <label class="grid gap-1.5 text-xs font-semibold"
                >Trạng thái<select
                    v-model="status"
                    class="min-h-10 rounded-[5px] border border-slate-300 bg-white px-3 text-sm dark:border-slate-600 dark:bg-slate-800"
                >
                    <option value="">Tất cả</option>
                    <option v-for="(label, value) in gameImageStatuses" :key="value" :value="value">{{ label }}</option>
                </select></label
            >
            <button type="submit" class="min-h-10 rounded-[5px] bg-emerald-600 px-4 text-sm font-semibold text-white">Lọc</button>
            <button type="button" class="min-h-10 rounded-[5px] border border-slate-300 px-4 text-sm" @click="load(page)">Làm mới</button>
        </form>
        <p class="text-xs text-slate-500">{{ total }} lượt ghép ảnh · 20 lượt/trang</p>
        <DataTable
            :data="rows"
            :columns="columns"
            :loading="loading"
            :current-page="page"
            :total-pages="lastPage"
            :go-to-page="load"
            class-custom="min-w-[52rem]"
        >
            <template #member="{ row }"
                ><strong>{{ row.member }}</strong>
                <p class="mt-1 text-xs text-slate-400">#{{ row.user_id }}</p></template
            >
            <template #username="{ row }"
                ><span class="break-all font-semibold">{{ row.username }}</span>
                <p class="mt-1 text-xs text-slate-500">{{ row.server }}</p></template
            >
            <template #status="{ row }"
                ><span
                    class="inline-flex whitespace-nowrap rounded-[5px] px-2.5 py-1 text-xs font-semibold"
                    :class="{
                        'bg-amber-50 text-amber-700': row.status === 'pending',
                        'bg-sky-50 text-sky-700': row.status === 'processing',
                        'bg-emerald-50 text-emerald-700': row.status === 'success',
                        'bg-rose-50 text-rose-700': row.status === 'error',
                        'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300': row.status === 'paused' || row.status === 'cancelled',
                    }"
                    >{{ gameImageStatuses[row.status as keyof typeof gameImageStatuses] }}</span
                >
                <p v-if="row.status === 'error' && row.error_code" class="mt-2 break-all font-mono text-xs font-semibold text-rose-700">
                    {{ row.error_code }}
                </p>
                <p v-if="row.status === 'error'" class="mt-2 max-w-64 whitespace-pre-line break-words text-xs leading-5 text-rose-600">
                    {{ row.error_message }}
                </p></template
            >
            <template #charged_amount="{ row }"
                ><p class="whitespace-nowrap font-semibold tabular-nums">{{ money(row.charged_amount) }}</p>
                <p v-if="row.refunded_at" class="mt-1 whitespace-nowrap text-xs font-semibold text-emerald-700">
                    Đã hoàn {{ money(row.refunded_amount ?? row.charged_amount) }}
                </p></template
            >
            <template #created_at="{ row }"
                ><p class="whitespace-nowrap text-xs">{{ date(row.created_at) }}</p>
                <p v-if="row.completed_at" class="mt-1 whitespace-nowrap text-xs text-slate-400">Kết thúc: {{ date(row.completed_at) }}</p></template
            >
            <template #actions="{ row }"
                ><div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        :disabled="row.status === 'processing' || loading"
                        class="rounded-[5px] border border-emerald-300 px-3 py-2 text-xs font-semibold text-emerald-700 disabled:opacity-40 dark:text-emerald-400"
                        @click="openEdit(row)"
                    >
                        Điều chỉnh</button
                    ><button
                        type="button"
                        :disabled="deleting !== null || loading"
                        class="rounded-[5px] border border-rose-300 px-3 py-2 text-xs font-semibold text-rose-600 disabled:opacity-40"
                        @click="removeAccount(row)"
                    >
                        {{ deleting === row.id ? 'Đang xoá…' : 'Xoá' }}
                    </button>
                </div></template
            >
        </DataTable>
        <div
            v-if="editing"
            class="fixed inset-0 z-[80] grid place-items-center overflow-y-auto bg-slate-950/60 p-4"
            @click.self="closeEdit"
            @keydown.esc="closeEdit"
        >
            <form
                role="dialog"
                aria-modal="true"
                aria-labelledby="capture-edit-title"
                class="grid w-full max-w-lg gap-4 rounded-[5px] border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900"
                @submit.prevent="saveAccount"
            >
                <div class="flex items-center justify-between gap-3">
                    <h2 id="capture-edit-title" class="font-bold">Điều chỉnh tài khoản #{{ editing.id }}</h2>
                    <button type="button" :disabled="saving" aria-label="Đóng" @click="closeEdit">Đóng ×</button>
                </div>
                <label class="grid gap-2 text-sm"
                    >Tài khoản<input
                        v-model="draft.username"
                        autofocus
                        required
                        maxlength="100"
                        class="rounded-[5px] border border-slate-300 bg-transparent px-3 py-2 dark:border-slate-600"
                /></label>
                <label class="grid gap-2 text-sm"
                    >Server<select
                        v-model="draft.server"
                        required
                        class="rounded-[5px] border border-slate-300 bg-white px-3 py-2 dark:border-slate-600 dark:bg-slate-800"
                    >
                        <option v-if="!servers.some((server) => server.code === draft.server)" :value="draft.server">{{ draft.server }}</option>
                        <option v-for="server in servers" :key="server.code" :value="server.code">{{ server.name }} · {{ server.code }}</option>
                    </select></label
                >
                <label class="grid gap-2 text-sm"
                    >Mật khẩu mới<span class="flex gap-2"
                        ><input
                            v-model="draft.password"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="new-password"
                            maxlength="200"
                            placeholder="Để trống để giữ mật khẩu đang lưu"
                            class="min-w-0 flex-1 rounded-[5px] border border-slate-300 bg-transparent px-3 py-2 dark:border-slate-600"
                        /><button
                            type="button"
                            :aria-pressed="showPassword"
                            class="rounded-[5px] border border-slate-300 px-3 dark:border-slate-600"
                            @click="showPassword = !showPassword"
                        >
                            {{ showPassword ? 'Ẩn' : 'Hiện' }}
                        </button></span
                    ></label
                >
                <p class="text-xs text-slate-500">Thay đổi thông tin không tạo lượt chụp hoặc thu phí mới.</p>
                <div class="flex justify-end gap-2">
                    <button type="button" :disabled="saving" class="rounded-[5px] border px-4 py-2 text-sm" @click="closeEdit">Huỷ</button
                    ><button
                        type="submit"
                        :disabled="saving"
                        class="rounded-[5px] bg-emerald-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        {{ saving ? 'Đang lưu…' : 'Lưu thay đổi' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
