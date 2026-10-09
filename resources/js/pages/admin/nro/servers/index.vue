<script setup lang="ts">
import Breadcrumb from '@/components/MasterLayouts/Breadcrumb/index.vue';
import { nroNotificationService, type NroServer } from '@/services/nro-notification.service';
import { handleErrorResponse } from '@/utils/response';
import Swal from 'sweetalert2';
import { computed, onMounted, reactive, ref } from 'vue';

const servers = ref<NroServer[]>([]);
const loading = ref(true);
const loadFailed = ref(false);
const saving = ref(false);
const togglingId = ref<number | null>(null);
const editingId = ref<number | null>(null);
const search = ref('');
const status = ref('all');
const form = ref<HTMLFormElement | null>(null);
const nameInput = ref<HTMLInputElement | null>(null);
const draft = reactive<{ name: string; server_code: number | null; code: string; sort_order: number; is_active: boolean }>({
    name: '',
    server_code: null,
    code: '',
    sort_order: 0,
    is_active: true,
});
const busy = computed(() => saving.value || togglingId.value !== null);
const activeCount = computed(() => servers.value.filter((server) => server.is_active).length);
const visibleServers = computed(() => {
    const query = search.value.trim().toLocaleLowerCase('vi');
    return servers.value.filter(
        (server) =>
            (status.value === 'all' || server.is_active === (status.value === 'active')) &&
            `${server.name} ${server.code ?? ''} ${server.server_code} ${server.id}`.toLocaleLowerCase('vi').includes(query),
    );
});
const reset = () => {
    editingId.value = null;
    Object.assign(draft, { name: '', server_code: null, code: '', sort_order: 0, is_active: true });
};
const edit = (server: NroServer) => {
    editingId.value = server.id;
    Object.assign(draft, {
        name: server.name,
        server_code: server.server_code,
        code: server.code ?? '',
        sort_order: server.sort_order,
        is_active: server.is_active,
    });
    form.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    nameInput.value?.focus({ preventScroll: true });
};
const replaceServer = (server: NroServer) => {
    servers.value = [...servers.value.filter((item) => item.id !== server.id), server].sort(
        (a, b) => a.sort_order - b.sort_order || a.server_code - b.server_code,
    );
};
const load = async () => {
    loading.value = true;
    loadFailed.value = false;
    try {
        servers.value = await nroNotificationService.servers();
    } catch (error) {
        loadFailed.value = true;
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};
const save = async () => {
    if (busy.value || draft.server_code === null) return;
    saving.value = true;
    try {
        const server = await nroNotificationService.saveServer(
            { ...draft, code: draft.code.trim() || null, server_code: Number(draft.server_code) },
            editingId.value,
        );
        replaceServer(server);
        reset();
        void Swal.fire({ icon: 'success', title: 'Đã lưu server', text: server.name, confirmButtonText: 'Đã hiểu' });
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
};
const toggle = async (server: NroServer) => {
    if (busy.value) return;
    togglingId.value = server.id;
    try {
        const updated = await nroNotificationService.saveServer({ is_active: !server.is_active }, server.id);
        replaceServer(updated);
        if (editingId.value === updated.id) draft.is_active = updated.is_active;
        void Swal.fire({
            icon: 'success',
            title: updated.is_active ? 'Đã bật server' : 'Đã tắt server',
            text: updated.name,
            confirmButtonText: 'Đã hiểu',
        });
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        togglingId.value = null;
    }
};
onMounted(load);
</script>

<template>
    <div class="mx-auto min-w-0 max-w-7xl space-y-6">
        <Breadcrumb :items="[{ label: 'Server NRO' }]" />
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Quản lý server NRO</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Thêm server, cập nhật mã và quản lý việc nhận thông báo game.</p>
            </div>
            <RouterLink
                to="/admin/nro/bosses"
                class="rounded-[10px] border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-emerald-700 hover:bg-emerald-50 dark:border-slate-600 dark:bg-slate-900 dark:text-emerald-400"
                >Quản lý boss</RouterLink
            >
        </div>
        <div class="grid min-w-0 items-start gap-6 xl:grid-cols-[21rem_minmax(0,1fr)]">
            <form
                ref="form"
                class="overflow-hidden rounded-[10px] border border-slate-300 bg-white dark:border-slate-600 dark:bg-slate-900"
                @submit.prevent="save"
            >
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ editingId === null ? 'Thêm server' : 'Sửa server' }}</h2>
                    <p v-if="editingId !== null" class="mt-1 text-xs text-slate-500">ID: #{{ editingId }}</p>
                </div>
                <fieldset :disabled="busy || loading" class="grid gap-5 p-5 disabled:opacity-60">
                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-200"
                        >Tên server
                        <input
                            ref="nameInput"
                            v-model="draft.name"
                            required
                            maxlength="100"
                            placeholder="Vũ trụ 1"
                            class="mt-2 block h-11 w-full rounded-[10px] border border-slate-300 bg-white px-3 font-normal text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-200"
                        >Mã số nhận thông báo (server_code)
                        <input
                            v-model.number="draft.server_code"
                            type="number"
                            required
                            min="0"
                            max="4294967295"
                            step="1"
                            placeholder="1"
                            class="mt-2 block h-11 w-full rounded-[10px] border border-slate-300 bg-white px-3 font-normal text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                        />
                        <span class="mt-2 block text-xs font-normal leading-5 text-slate-500 dark:text-slate-400"
                            >Bot gửi mã này qua server_code. Khi đổi mã, cập nhật mã tương ứng trong bot.</span
                        >
                    </label>
                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                        Code
                        <input
                            v-model="draft.code"
                            maxlength="50"
                            pattern="[a-zA-Z0-9_-]+"
                            placeholder="sv1"
                            class="mt-2 block h-11 w-full rounded-[10px] border border-slate-300 bg-white px-3 font-normal text-slate-900 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                        Thứ tự hiển thị
                        <input
                            v-model.number="draft.sort_order"
                            type="number"
                            required
                            min="0"
                            max="4294967295"
                            step="1"
                            class="mt-2 block h-11 w-full rounded-[10px] border border-slate-300 bg-white px-3 font-normal text-slate-900 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                        />
                    </label>
                    <label class="flex items-center gap-3 text-sm font-medium text-slate-700 dark:text-slate-200"
                        ><input v-model="draft.is_active" type="checkbox" class="size-4 accent-emerald-700" /> Nhận thông báo từ server</label
                    >
                    <p class="text-xs leading-5 text-slate-500 dark:text-slate-400">
                        Tắt server sẽ ngừng nhận thông báo mới. Lịch sử thông báo vẫn được giữ.
                    </p>
                    <div class="flex gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                        <button
                            type="submit"
                            class="flex-1 rounded-[10px] bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800"
                        >
                            {{ saving ? 'Đang lưu…' : 'Lưu server' }}
                        </button>
                        <button
                            v-if="editingId !== null"
                            type="button"
                            class="rounded-[10px] border border-slate-300 px-4 py-2.5 text-sm text-slate-600 dark:border-slate-600 dark:text-slate-300"
                            @click="reset"
                        >
                            Hủy
                        </button>
                    </div>
                </fieldset>
            </form>
            <section
                class="min-w-0 overflow-hidden rounded-[10px] border border-slate-300 bg-white dark:border-slate-600 dark:bg-slate-900"
                aria-labelledby="server-list-title"
            >
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-300 px-5 py-4 dark:border-slate-600">
                    <div>
                        <h2 id="server-list-title" class="text-lg font-bold text-slate-900 dark:text-white">Danh sách server</h2>
                        <p class="mt-1 text-xs text-slate-500">{{ activeCount }} đang bật / {{ servers.length }} server</p>
                    </div>
                    <button
                        type="button"
                        :disabled="loading || busy"
                        class="rounded-[10px] border border-slate-300 px-3 py-2 text-sm text-slate-600 disabled:opacity-50 dark:border-slate-600 dark:text-slate-300"
                        @click="load"
                    >
                        {{ loading ? 'Đang tải…' : 'Tải lại' }}
                    </button>
                </div>
                <div class="grid gap-3 border-b border-slate-200 p-4 dark:border-slate-700 sm:grid-cols-[minmax(0,1fr)_10rem]">
                    <input
                        v-model="search"
                        type="search"
                        aria-label="Tìm server"
                        placeholder="Tìm theo tên, mã hoặc ID…"
                        class="h-10 min-w-0 rounded-[10px] border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                    />
                    <select
                        v-model="status"
                        aria-label="Lọc trạng thái"
                        class="h-10 rounded-[10px] border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                    >
                        <option value="all">Tất cả trạng thái</option>
                        <option value="active">Đang bật</option>
                        <option value="inactive">Đang tắt</option>
                    </select>
                </div>
                <div v-if="loading" role="status" class="p-10 text-center text-sm text-slate-500">Đang tải danh sách server…</div>
                <p v-else-if="loadFailed" role="alert" class="p-10 text-center text-sm text-rose-600">
                    Không tải được danh sách. Bấm Tải lại để thử lại.
                </p>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[600px] text-left text-sm text-slate-700 dark:text-slate-200">
                        <thead class="border-b border-slate-300 bg-slate-50 text-xs uppercase text-slate-500 dark:border-slate-600 dark:bg-slate-800">
                            <tr class="divide-x divide-slate-300 dark:divide-slate-600">
                                <th scope="col" class="px-4 py-3">ID</th>
                                <th scope="col" class="px-4 py-3">Mã / Code</th>
                                <th scope="col" class="px-4 py-3">Tên server</th>
                                <th scope="col" class="px-4 py-3">Trạng thái</th>
                                <th scope="col" class="px-4 py-3 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            <tr
                                v-for="server in visibleServers"
                                :key="server.id"
                                class="divide-x divide-slate-200 hover:bg-slate-50 dark:divide-slate-700 dark:hover:bg-slate-800/50"
                            >
                                <td class="px-4 py-4 text-slate-500">#{{ server.id }}</td>
                                <td class="px-4 py-4 font-mono font-semibold">
                                    {{ server.server_code }}
                                    <span class="block text-xs font-normal text-slate-500">{{ server.code || '—' }} · #{{ server.sort_order }}</span>
                                </td>
                                <td class="px-4 py-4 font-semibold">{{ server.name }}</td>
                                <td class="px-4 py-4">
                                    <span
                                        :class="
                                            server.is_active
                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                                                : 'border-slate-300 bg-slate-100 text-slate-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300'
                                        "
                                        class="whitespace-nowrap rounded-[10px] border px-2.5 py-1 text-xs font-medium"
                                        >{{ server.is_active ? 'Đang bật' : 'Đang tắt' }}</span
                                    >
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            type="button"
                                            :disabled="busy"
                                            :aria-label="`Sửa ${server.name}`"
                                            class="rounded-[10px] border border-slate-300 px-3 py-2 font-semibold text-emerald-700 disabled:opacity-50 dark:border-slate-600 dark:text-emerald-400"
                                            @click="edit(server)"
                                        >
                                            Sửa</button
                                        ><button
                                            type="button"
                                            :disabled="busy"
                                            :aria-label="`${server.is_active ? 'Tắt' : 'Bật'} ${server.name}`"
                                            class="rounded-[10px] border border-slate-300 px-3 py-2 text-slate-600 disabled:opacity-50 dark:border-slate-600 dark:text-slate-300"
                                            @click="toggle(server)"
                                        >
                                            {{ togglingId === server.id ? 'Đang lưu…' : server.is_active ? 'Tắt' : 'Bật' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="visibleServers.length === 0">
                                <td colspan="5" class="p-10 text-center text-slate-500">
                                    {{
                                        servers.length
                                            ? 'Không có server phù hợp bộ lọc.'
                                            : 'Chưa có server. Thêm server đầu tiên ở biểu mẫu bên cạnh.'
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</template>
