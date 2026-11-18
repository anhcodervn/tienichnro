<script setup lang="ts">
import Breadcrumb from '@/components/MasterLayouts/Breadcrumb/index.vue';
import DataTable from '@/components/shared/DataTable/index.vue';
import { managedServerService, serverTitles, type ManagedServer, type ServerDraft, type ServerType } from '@/services/managed-server.service';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import Swal from 'sweetalert2';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps<{ type: ServerType }>();
const title = computed(() => serverTitles[props.type]);
const rows = ref<ManagedServer[]>([]);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const loading = ref(false);
const saving = ref(false);
const syncing = ref(false);
const q = ref('');
const active = ref('');
const editingId = ref<number | null>(null);
const form = ref<HTMLFormElement | null>(null);
const emptyDraft = (): ServerDraft => ({ server_id: '', name: '', is_active: true, sort_order: 0 });
const draft = reactive<ServerDraft>(emptyDraft());
const apiKey = ref('');
const apiSecret = ref('');
const configuration = ref<{ credentials_saved: boolean; endpoint: string } | null>(null);
let requestId = 0;
const busy = computed(() => saving.value || syncing.value);
const columns = [
    { accessorKey: 'id', header: '#' },
    { accessorKey: 'server_id', header: 'ID máy chủ' },
    { accessorKey: 'name', header: 'Tên máy chủ' },
    { accessorKey: 'is_active', header: 'Trạng thái' },
    { accessorKey: 'sort_order', header: 'Thứ tự' },
    { id: 'actions', header: 'Thao tác' },
];
function reset() {
    editingId.value = null;
    Object.assign(draft, emptyDraft());
}
function edit(row: ManagedServer) {
    editingId.value = row.id;
    Object.assign(draft, { server_id: row.server_id, name: row.name, is_active: row.is_active, sort_order: row.sort_order });
    form.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
async function load(nextPage = 1) {
    const current = ++requestId;
    loading.value = true;
    try {
        const data = await managedServerService.list(props.type, { page: nextPage, q: q.value, active: active.value });
        if (current !== requestId) return;
        rows.value = data.data;
        page.value = data.current_page;
        lastPage.value = data.last_page;
        total.value = data.total;
    } catch (error) {
        if (current === requestId) handleErrorResponse(error);
    } finally {
        if (current === requestId) loading.value = false;
    }
}
async function save() {
    if (busy.value) return;
    const type = props.type;
    saving.value = true;
    try {
        await managedServerService.save(type, { ...draft }, editingId.value);
        if (type === props.type) {
            reset();
            await load(page.value);
            handleSuccessResponse({ data: { status: true, message: 'Đã lưu máy chủ.' } });
        }
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
}
async function remove(row: ManagedServer) {
    if (busy.value) return;
    const type = props.type;
    const result = await Swal.fire({
        icon: 'warning',
        title: 'Xóa máy chủ?',
        text: row.name,
        showCancelButton: true,
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy',
    });
    if (!result.isConfirmed || busy.value || type !== props.type) return;
    saving.value = true;
    try {
        await managedServerService.remove(type, row.id);
        if (type === props.type) {
            if (editingId.value === row.id) reset();
            await load(rows.value.length === 1 && page.value > 1 ? page.value - 1 : page.value);
        }
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
}
async function loadConfiguration() {
    if (props.type !== 'carot') return;
    try {
        configuration.value = await managedServerService.configuration();
    } catch (error) {
        handleErrorResponse(error);
    }
}
async function saveCredentials() {
    if (busy.value) return;
    saving.value = true;
    try {
        configuration.value = await managedServerService.configure(
            apiKey.value || apiSecret.value ? { api_key: apiKey.value, api_secret: apiSecret.value } : {},
        );
        apiKey.value = apiSecret.value = '';
        handleSuccessResponse({ data: { status: true, message: 'Đã lưu API Nạp Carot.' } });
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
}
async function sync() {
    if (busy.value || !configuration.value?.credentials_saved) return;
    syncing.value = true;
    try {
        const data = await managedServerService.sync();
        if (props.type === 'carot') {
            await load();
            handleSuccessResponse({ data: { status: true, message: `Đã đồng bộ ${data.count} máy chủ Ngọc Rồng.` } });
        }
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        syncing.value = false;
    }
}
watch(
    () => props.type,
    () => {
        reset();
        q.value = active.value = apiKey.value = apiSecret.value = '';
        rows.value = [];
        total.value = 0;
        page.value = lastPage.value = 1;
        void load();
        void loadConfiguration();
    },
);
onMounted(() => {
    void load();
    void loadConfiguration();
});
onBeforeUnmount(() => {
    requestId++;
});
</script>

<template>
    <div class="space-y-4">
        <Breadcrumb :title="title" description="Quản lý ID, tên, trạng thái và thứ tự máy chủ theo từng nhóm." />
        <section
            v-if="type === 'carot'"
            class="grid gap-4 rounded-[5px] border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
        >
            <div>
                <h2 class="text-sm font-bold">Kết nối Nạp Carot</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">
                    Đồng bộ server Ngọc Rồng qua API catalog. Trạng thái và thứ tự đã chỉnh tại đây được giữ nguyên.
                </p>
            </div>
            <form class="grid items-end gap-3 md:grid-cols-[1fr_1fr_auto]" @submit.prevent="saveCredentials">
                <label class="grid min-w-0 gap-2 text-sm"
                    >API key<input
                        v-model="apiKey"
                        type="password"
                        autocomplete="new-password"
                        maxlength="500"
                        :required="!configuration?.credentials_saved || !!apiSecret"
                        :placeholder="configuration?.credentials_saved ? '•••••• Đã lưu — để trống để giữ nguyên' : 'API key có quyền catalog:read'"
                        class="rounded-[5px] border border-slate-300 bg-transparent px-3 py-2 dark:border-slate-600"
                /></label>
                <label class="grid min-w-0 gap-2 text-sm"
                    >API secret<input
                        v-model="apiSecret"
                        type="password"
                        autocomplete="new-password"
                        maxlength="500"
                        :required="!configuration?.credentials_saved || !!apiKey"
                        :placeholder="configuration?.credentials_saved ? '•••••• Đã lưu — để trống để giữ nguyên' : 'Nhập API secret Nạp Carot'"
                        class="rounded-[5px] border border-slate-300 bg-transparent px-3 py-2 dark:border-slate-600"
                /></label>
                <button :disabled="busy" class="rounded-[5px] border border-slate-300 px-4 py-2 text-sm font-semibold dark:border-slate-600">
                    Lưu API
                </button>
            </form>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <code class="break-all text-xs text-slate-500">{{ configuration?.endpoint ?? 'https://napcarot.com/api/v1/catalog' }}</code
                ><button
                    type="button"
                    :disabled="busy || !configuration?.credentials_saved"
                    class="rounded-[5px] bg-cyan-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-50"
                    @click="sync"
                >
                    {{ syncing ? 'Đang đồng bộ…' : 'Đồng bộ từ Nạp Carot' }}
                </button>
            </div>
        </section>
        <form
            ref="form"
            class="grid gap-4 rounded-[5px] border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
            @submit.prevent="save"
        >
            <h2 class="text-sm font-bold">{{ editingId === null ? 'Thêm máy chủ' : 'Chỉnh sửa máy chủ' }}</h2>
            <div class="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_2fr_1fr_1fr]">
                <label class="grid min-w-0 gap-2 text-sm"
                    >ID máy chủ<input
                        v-model="draft.server_id"
                        required
                        maxlength="50"
                        pattern="[A-Za-z0-9_-]+"
                        placeholder="Ví dụ: 1"
                        class="rounded-[5px] border border-slate-300 bg-transparent px-3 py-2 dark:border-slate-600"
                /></label>
                <label class="grid min-w-0 gap-2 text-sm"
                    >Tên máy chủ<input
                        v-model="draft.name"
                        required
                        maxlength="100"
                        placeholder="Ví dụ: 1 sao"
                        class="rounded-[5px] border border-slate-300 bg-transparent px-3 py-2 dark:border-slate-600"
                /></label>
                <label class="grid min-w-0 gap-2 text-sm"
                    >Thứ tự<input
                        v-model.number="draft.sort_order"
                        required
                        type="number"
                        min="0"
                        max="4294967295"
                        class="rounded-[5px] border border-slate-300 bg-transparent px-3 py-2 dark:border-slate-600"
                /></label>
                <label class="flex min-h-10 items-center gap-2 text-sm"
                    ><input v-model="draft.is_active" type="checkbox" class="rounded border-slate-300 text-cyan-700" />Đang hoạt động</label
                >
            </div>
            <div class="flex gap-2">
                <button :disabled="busy" class="rounded-[5px] bg-cyan-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-50">
                    {{ saving ? 'Đang lưu…' : editingId === null ? 'Thêm máy chủ' : 'Lưu thay đổi' }}</button
                ><button
                    v-if="editingId !== null"
                    type="button"
                    :disabled="busy"
                    class="rounded-[5px] border border-slate-300 px-4 py-2 text-sm dark:border-slate-600"
                    @click="reset"
                >
                    Hủy chỉnh sửa
                </button>
            </div>
        </form>
        <form class="flex flex-wrap items-end gap-3" @submit.prevent="load(1)">
            <label class="grid min-w-0 flex-1 basis-56 gap-2 text-sm"
                >Tìm kiếm<input
                    v-model="q"
                    maxlength="100"
                    placeholder="Tìm ID hoặc tên máy chủ"
                    class="rounded-[5px] border border-slate-300 bg-white px-3 py-2 dark:border-slate-600 dark:bg-slate-900"
            /></label>
            <label class="grid gap-2 text-sm"
                >Trạng thái<select
                    v-model="active"
                    class="rounded-[5px] border border-slate-300 bg-white px-3 py-2 dark:border-slate-600 dark:bg-slate-900"
                >
                    <option value="">Tất cả</option>
                    <option value="1">Đang hoạt động</option>
                    <option value="0">Đã tắt</option>
                </select></label
            >
            <button
                :disabled="loading"
                class="rounded-[5px] border border-slate-300 bg-white px-4 py-2 text-sm font-semibold dark:border-slate-600 dark:bg-slate-900"
            >
                Lọc
            </button>
        </form>
        <p class="text-xs text-slate-500">{{ total }} máy chủ · 20 dòng/trang</p>
        <DataTable
            :data="rows"
            :columns="columns"
            :loading="loading"
            :current-page="page"
            :total-pages="lastPage"
            :go-to-page="load"
            class-custom="min-w-[42rem]"
        >
            <template #server_id="{ row }"
                ><span class="font-mono font-semibold">{{ row.server_id }}</span></template
            >
            <template #is_active="{ row }"
                ><span
                    class="rounded px-2 py-1 text-xs font-semibold"
                    :class="
                        row.is_active
                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                            : 'bg-slate-100 text-slate-500 dark:bg-slate-800'
                    "
                    >{{ row.is_active ? 'Hoạt động' : 'Đã tắt' }}</span
                ></template
            >
            <template #actions="{ row }"
                ><div class="flex gap-3 whitespace-nowrap">
                    <button type="button" :disabled="busy" class="font-semibold text-cyan-700 dark:text-cyan-300" @click="edit(row)">Sửa</button
                    ><button type="button" :disabled="busy" class="font-semibold text-rose-600" @click="remove(row)">Xóa</button>
                </div></template
            >
        </DataTable>
    </div>
</template>
