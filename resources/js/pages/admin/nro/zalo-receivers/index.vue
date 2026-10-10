<script setup lang="ts">
import DataTable from '@/components/shared/DataTable/index.vue';
import { adminZaloReceivers, type ZaloReceiver, type ZaloReceiverDraft } from '@/services/admin-zalo-receivers.service';
import { nroNotificationService, type NroNotificationType, type NroServer } from '@/services/nro-notification.service';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/vue';
import { isAxiosError } from 'axios';
import Swal from 'sweetalert2';
import { computed, onMounted, reactive, ref, watch } from 'vue';

const inputClass =
    'min-h-11 w-full min-w-0 rounded-[10px] border border-slate-300 bg-white px-3 py-2 text-sm font-normal dark:border-slate-600 dark:bg-slate-800 dark:text-white';
const receivers = ref<ZaloReceiver[]>([]);
const types = ref<NroNotificationType[]>([]);
const servers = ref<NroServer[]>([]);
const loading = ref(true);
const failed = ref(false);
const saving = ref(false);
const deletingId = ref<number | null>(null);
const editingId = ref<number | null>(null);
const showForm = ref(false);
const errors = ref<string[]>([]);
const selectedTypes = ref<string[]>([]);
const query = ref('');
const channel = ref('all');
const perPage = ref(10);
const currentPage = ref(1);
const emptyDraft = (): ZaloReceiverDraft => ({ box_zalo_id: null, zalo_id: '', char_name: '', char_server: null, type_receive: '' });
const draft = reactive<ZaloReceiverDraft>(emptyDraft());
const receiveTypes = (csv: string) => [
    ...new Set(
        csv
            .split(',')
            .map((value) => value.trim().toUpperCase())
            .filter(Boolean),
    ),
];
const typeName = (code: string) => types.value.find((item) => item.code === code)?.name || `${code} (không còn trong danh sách)`;
const serverName = (code: number | null) =>
    code === null
        ? 'Chưa chọn server'
        : servers.value.find((item) => item.server_code === code)?.name || `Server ${code} (không còn trong danh sách)`;
const selectableTypes = computed(() => [
    ...types.value.map((item) => ({ code: item.code, name: item.name })),
    ...selectedTypes.value.filter((code) => !types.value.some((item) => item.code === code)).map((code) => ({ code, name: typeName(code) })),
]);
const filtered = computed(() =>
    receivers.value.filter((item) => {
        const keyword = query.value.trim().toLocaleLowerCase('vi');
        const matches = [
            item.zalo_id,
            item.box_zalo_id || '',
            item.char_name,
            serverName(item.char_server),
            item.type_receive,
            ...receiveTypes(item.type_receive).map(typeName),
        ].some((value) => value.toLocaleLowerCase('vi').includes(keyword));
        return matches && (channel.value === 'all' || (channel.value === 'box' ? !!item.box_zalo_id : !item.box_zalo_id));
    }),
);
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage.value)));
const rows = computed(() => filtered.value.slice((currentPage.value - 1) * perPage.value, currentPage.value * perPage.value));
watch([query, channel, perPage], () => {
    currentPage.value = 1;
});
watch(totalPages, (pages) => {
    currentPage.value = Math.min(currentPage.value, pages);
});
const goToPage = async (page: number) => {
    currentPage.value = Math.min(Math.max(page, 1), totalPages.value);
};
const columns = [
    { accessorKey: 'id', header: 'ID' },
    { accessorKey: 'char_name', header: 'Nhân vật' },
    { accessorKey: 'char_server', header: 'Server' },
    { accessorKey: 'zalo_id', header: 'Zalo ID' },
    { accessorKey: 'box_zalo_id', header: 'Box Zalo' },
    { accessorKey: 'type_receive', header: 'Loại thông báo' },
    { id: 'actions', header: 'Thao tác' },
];
const load = async () => {
    loading.value = true;
    failed.value = false;
    try {
        const [items, options, serverOptions] = await Promise.all([
            adminZaloReceivers.list(),
            nroNotificationService.notificationTypes(),
            nroNotificationService.servers(),
        ]);
        receivers.value = items;
        types.value = options.codes;
        servers.value = serverOptions;
    } catch (error) {
        failed.value = true;
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};
const close = () => {
    if (saving.value) return;
    showForm.value = false;
    errors.value = [];
};
const add = () => {
    editingId.value = null;
    Object.assign(draft, emptyDraft());
    selectedTypes.value = [];
    errors.value = [];
    showForm.value = true;
};
const edit = (item: ZaloReceiver) => {
    editingId.value = item.id;
    Object.assign(draft, {
        box_zalo_id: item.box_zalo_id,
        zalo_id: item.zalo_id,
        char_name: item.char_name,
        char_server: item.char_server,
        type_receive: item.type_receive,
    });
    selectedTypes.value = receiveTypes(item.type_receive);
    errors.value = [];
    showForm.value = true;
};
const save = async () => {
    if (saving.value) return;
    errors.value = [];
    if (!selectedTypes.value.length) {
        errors.value = ['Vui lòng chọn ít nhất một loại thông báo.'];
        return;
    }
    saving.value = true;
    try {
        const result = await adminZaloReceivers.save(
            { ...draft, box_zalo_id: draft.box_zalo_id?.trim() || null, type_receive: selectedTypes.value.join(',') },
            editingId.value,
        );
        receivers.value = [...receivers.value.filter((item) => item.id !== result.id), result].sort((left, right) => right.id - left.id);
        showForm.value = false;
        handleSuccessResponse({ data: { status: true, message: 'Đã lưu Zalo nhận thông báo.' } });
    } catch (error) {
        if (isAxiosError<{ errors?: Record<string, string[]>; message?: string }>(error)) {
            errors.value = error.response?.data.errors
                ? Object.values(error.response.data.errors).flat()
                : [error.response?.data.message || 'Không lưu được đăng ký.'];
        } else {
            handleErrorResponse(error);
        }
    } finally {
        saving.value = false;
    }
};
const remove = async (item: ZaloReceiver) => {
    if (deletingId.value !== null || saving.value) return;
    deletingId.value = item.id;
    try {
        const result = await Swal.fire({
            icon: 'warning',
            title: 'Xoá đăng ký nhận thông báo?',
            text: `Xoá đăng ký của ${item.char_name} cho Zalo ID ${item.zalo_id}.`,
            showCancelButton: true,
            confirmButtonText: 'Xoá',
            cancelButtonText: 'Huỷ',
            confirmButtonColor: '#be123c',
        });
        if (!result.isConfirmed) return;
        await adminZaloReceivers.remove(item.id);
        receivers.value = receivers.value.filter((receiver) => receiver.id !== item.id);
        handleSuccessResponse({ data: { status: true, message: 'Đã xoá đăng ký.' } });
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        deletingId.value = null;
    }
};
onMounted(load);
</script>

<template>
    <section class="grid gap-5">
        <header
            class="flex flex-wrap items-center justify-between gap-4 rounded-[10px] border border-slate-300 bg-white p-5 dark:border-slate-600 dark:bg-slate-900"
        >
            <div>
                <h1 class="text-xl font-bold text-slate-950 dark:text-white">Zalo nhận thông báo</h1>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Quản lý người nhận, box Zalo và nhân vật theo dõi.</p>
            </div>
            <button
                type="button"
                :disabled="loading || failed || saving || deletingId !== null"
                class="min-h-11 rounded-[10px] bg-emerald-700 px-4 text-sm font-semibold text-white disabled:opacity-50"
                @click="add"
            >
                Thêm đăng ký
            </button>
        </header>
        <div class="grid gap-3 sm:grid-cols-[1fr_auto_auto]">
            <label class="grid min-w-0 gap-1 text-xs font-semibold text-slate-700 dark:text-slate-200"
                >Tìm kiếm<input v-model="query" type="search" placeholder="Nhân vật, Zalo ID, box hoặc loại thông báo" :class="inputClass"
            /></label>
            <label class="grid gap-1 text-xs font-semibold text-slate-700 dark:text-slate-200"
                >Nơi nhận<select v-model="channel" :class="inputClass">
                    <option value="all">Tất cả</option>
                    <option value="direct">Gửi riêng</option>
                    <option value="box">Trong box</option>
                </select></label
            >
            <label class="grid gap-1 text-xs font-semibold text-slate-700 dark:text-slate-200"
                >Mỗi trang<select v-model.number="perPage" :class="inputClass">
                    <option :value="10">10</option>
                    <option :value="25">25</option>
                    <option :value="50">50</option>
                </select></label
            >
        </div>
        <button
            v-if="failed"
            type="button"
            class="min-h-11 rounded-[10px] border border-rose-300 p-3 text-sm text-rose-700 dark:text-rose-400"
            @click="load"
        >
            Không tải được dữ liệu. Bấm để thử lại.
        </button>
        <DataTable
            :data="rows"
            :columns="columns"
            :loading="loading"
            :current-page="currentPage"
            :total-pages="totalPages"
            :go-to-page="goToPage"
            empty-text="Chưa có đăng ký nhận thông báo phù hợp."
        >
            <template #char_server="{ row }"
                ><span class="text-xs">{{ serverName(row.char_server) }}</span></template
            >
            <template #zalo_id="{ row }"
                ><span class="block max-w-48 break-all font-mono text-xs">{{ row.zalo_id }}</span></template
            >
            <template #box_zalo_id="{ row }"
                ><span class="block max-w-48 break-all text-xs">{{ row.box_zalo_id || 'Gửi riêng' }}</span></template
            >
            <template #type_receive="{ row }"
                ><div class="flex max-w-sm flex-wrap gap-1">
                    <span
                        v-for="code in receiveTypes(row.type_receive)"
                        :key="code"
                        class="rounded-[6px] border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                        >{{ typeName(code) }}</span
                    >
                </div></template
            >
            <template #actions="{ row }"
                ><div class="flex gap-2">
                    <button
                        type="button"
                        :disabled="saving || deletingId !== null || failed"
                        class="min-h-10 rounded-[10px] border border-slate-300 px-3 text-sm font-semibold text-emerald-700 disabled:opacity-50 dark:text-emerald-400"
                        @click="edit(row)"
                    >
                        Sửa</button
                    ><button
                        type="button"
                        :disabled="saving || deletingId !== null"
                        class="min-h-10 rounded-[10px] border border-rose-300 px-3 text-sm font-semibold text-rose-700 disabled:opacity-50 dark:text-rose-400"
                        @click="remove(row)"
                    >
                        {{ deletingId === row.id ? 'Đang xoá…' : 'Xoá' }}
                    </button>
                </div></template
            >
        </DataTable>
        <p class="text-sm text-slate-600 dark:text-slate-300">{{ filtered.length }} đăng ký · Trang {{ currentPage }} / {{ totalPages }}</p>
        <Dialog :open="showForm" class="relative z-50" @close="close">
            <div class="fixed inset-0 bg-slate-950/60" aria-hidden="true"></div>
            <div class="fixed inset-0 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <DialogPanel
                        class="w-full max-w-xl rounded-[10px] border border-slate-300 bg-white p-5 shadow-xl dark:border-slate-600 dark:bg-slate-900 sm:p-6"
                    >
                        <div class="mb-5 flex items-center justify-between gap-3">
                            <DialogTitle class="text-xl font-bold text-slate-900 dark:text-white">{{
                                editingId === null ? 'Thêm Zalo nhận thông báo' : 'Sửa Zalo nhận thông báo'
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
                                <label class="grid gap-2 text-sm font-semibold text-slate-800 dark:text-slate-200"
                                    >Tên nhân vật<input v-model="draft.char_name" required maxlength="100" autocomplete="off" :class="inputClass"
                                /></label>
                                <label class="grid gap-2 text-sm font-semibold text-slate-800 dark:text-slate-200"
                                    >Server nhân vật
                                    <select v-model="draft.char_server" required :class="inputClass">
                                        <option :value="null" disabled>Chọn server</option>
                                        <option
                                            v-if="draft.char_server !== null && !servers.some((server) => server.server_code === draft.char_server)"
                                            :value="draft.char_server"
                                            disabled
                                        >
                                            {{ serverName(draft.char_server) }}
                                        </option>
                                        <option v-for="server in servers" :key="server.id" :value="server.server_code">
                                            {{ server.name }} ({{ server.server_code }})
                                        </option>
                                    </select>
                                    <span v-if="draft.char_server === null" class="text-xs font-normal text-amber-700 dark:text-amber-400"
                                        >Chọn server để nhận đúng thông báo của nhân vật.</span
                                    >
                                </label>
                                <label class="grid gap-2 text-sm font-semibold text-slate-800 dark:text-slate-200"
                                    >Zalo ID người nhận<input v-model="draft.zalo_id" required maxlength="128" autocomplete="off" :class="inputClass"
                                /></label>
                                <label class="grid gap-2 text-sm font-semibold text-slate-800 dark:text-slate-200"
                                    >Box Zalo ID<input
                                        v-model="draft.box_zalo_id"
                                        maxlength="128"
                                        autocomplete="off"
                                        placeholder="Để trống nếu gửi riêng"
                                        :class="inputClass"
                                    /><span class="text-xs font-normal text-slate-500 dark:text-slate-400"
                                        >Có box: gửi vào nhóm và tag Zalo ID đã nhập.</span
                                    ></label
                                >
                                <fieldset class="grid gap-3 rounded-[10px] border border-slate-200 p-3 dark:border-slate-600">
                                    <legend class="px-1 text-sm font-semibold text-slate-800 dark:text-slate-200">Loại thông báo nhận</legend>
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            class="min-h-10 rounded-[10px] border border-slate-300 px-3 text-xs dark:text-white"
                                            @click="selectedTypes = types.map((item) => item.code)"
                                        >
                                            Chọn tất cả</button
                                        ><button
                                            type="button"
                                            class="min-h-10 rounded-[10px] border border-slate-300 px-3 text-xs dark:text-white"
                                            @click="selectedTypes = []"
                                        >
                                            Bỏ chọn
                                        </button>
                                    </div>
                                    <p v-if="!selectableTypes.length" class="text-sm text-amber-700 dark:text-amber-400">
                                        Chưa có loại thông báo. Hãy cấu hình loại thông báo trước.
                                    </p>
                                    <label
                                        v-for="type in selectableTypes"
                                        :key="type.code"
                                        class="flex min-h-10 items-center gap-3 text-sm text-slate-800 dark:text-slate-200"
                                        ><input
                                            v-model="selectedTypes"
                                            type="checkbox"
                                            :value="type.code"
                                            class="size-5 shrink-0 accent-emerald-600"
                                        /><span
                                            >{{ type.name }} <span class="text-xs text-slate-500 dark:text-slate-400">({{ type.code }})</span></span
                                        ></label
                                    >
                                </fieldset>
                                <button type="submit" class="min-h-11 rounded-[10px] bg-emerald-700 px-4 text-sm font-semibold text-white">
                                    {{ saving ? 'Đang lưu…' : 'Lưu đăng ký' }}
                                </button>
                            </fieldset>
                        </form>
                    </DialogPanel>
                </div>
            </div>
        </Dialog>
    </section>
</template>
