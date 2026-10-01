<script setup lang="ts">
import { adminAuditLogService, type AdminAuditActor, type AdminAuditLog } from '@/services/admin-audit-log.service';
import { handleErrorResponse } from '@/utils/response';
import { ChevronLeft, ChevronRight, Clock3, Eye, FileClock, LoaderCircle, Search, ShieldCheck, X } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';

type PaginationMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

const loading = ref(true);
const logs = ref<AdminAuditLog[]>([]);
const admins = ref<AdminAuditActor[]>([]);
const actions = ref<string[]>([]);
const selectedLog = ref<AdminAuditLog | null>(null);
const filters = reactive({
    search: '',
    admin_id: '',
    action: '',
    method: '',
    status_code: '',
    date_from: '',
    date_to: '',
    per_page: 20,
});
const meta = reactive<PaginationMeta>({ current_page: 1, last_page: 1, per_page: 20, total: 0, from: null, to: null });

const hasFilters = computed(() => Object.entries(filters).some(([key, value]) => key !== 'per_page' && String(value).trim() !== ''));

const loadLogs = async (page = 1): Promise<void> => {
    loading.value = true;
    try {
        const response = await adminAuditLogService.list({
            page,
            per_page: filters.per_page,
            search: filters.search || undefined,
            admin_id: filters.admin_id || undefined,
            action: filters.action || undefined,
            method: filters.method || undefined,
            status_code: filters.status_code || undefined,
            date_from: filters.date_from || undefined,
            date_to: filters.date_to || undefined,
        });
        logs.value = response.logs.data;
        Object.assign(meta, {
            current_page: response.logs.current_page,
            last_page: response.logs.last_page,
            per_page: response.logs.per_page,
            total: response.logs.total,
            from: response.logs.from,
            to: response.logs.to,
        });
        admins.value = response.filter_options.admins;
        actions.value = response.filter_options.actions;
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

const resetFilters = (): void => {
    Object.assign(filters, {
        search: '',
        admin_id: '',
        action: '',
        method: '',
        status_code: '',
        date_from: '',
        date_to: '',
        per_page: 20,
    });
    void loadLogs(1);
};

const adminName = (admin: AdminAuditActor | null): string => admin?.full_name || admin?.name || admin?.username || admin?.email || 'Tài khoản đã xóa';

const actionLabel = (action: string): string => {
    if (action === 'admin_request_read') return 'Truy cập dữ liệu';
    if (action === 'admin_request_write') return 'Thay đổi dữ liệu';

    return action.replaceAll('_', ' ');
};

const formatDate = (value: string): string =>
    new Intl.DateTimeFormat('vi-VN', {
        dateStyle: 'short',
        timeStyle: 'medium',
    }).format(new Date(value));

const prettyJson = (value: Record<string, unknown> | null): string => JSON.stringify(value ?? {}, null, 2);
const statusClass = (status: number | null): string => {
    if (status === null) return 'border-slate-200 bg-slate-50 text-slate-600';
    if (status >= 500) return 'border-rose-200 bg-rose-50 text-rose-700';
    if (status >= 400) return 'border-amber-200 bg-amber-50 text-amber-700';

    return 'border-emerald-200 bg-emerald-50 text-emerald-700';
};

onMounted(() => void loadLogs());
</script>

<template>
    <div class="grid gap-5">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="flex items-center gap-2 text-xs font-black uppercase tracking-[0.16em] text-indigo-600">
                        <ShieldCheck class="h-4 w-4" /> Đối chiếu nội bộ
                    </p>
                    <h1 class="mt-2 text-2xl font-black text-slate-950">Nhật ký quản trị</h1>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-500">
                        Lưu vết truy cập và thay đổi của quản trị viên trong SQL. Dữ liệu nhạy cảm được tự động che trước khi ghi.
                    </p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-right">
                    <p class="text-xs font-bold uppercase text-slate-500">Tổng bản ghi</p>
                    <p class="mt-1 text-2xl font-black text-slate-950">{{ meta.total.toLocaleString('vi-VN') }}</p>
                </div>
            </div>
        </section>

        <form class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm" @submit.prevent="loadLogs(1)">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <label class="grid gap-1.5 text-xs font-bold text-slate-600 xl:col-span-2">
                    Tìm kiếm
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="filters.search"
                            class="ui-focus min-h-10 w-full rounded-lg border border-slate-300 bg-white pl-9 pr-3 text-sm"
                            placeholder="Route, thao tác, IP hoặc quản trị viên..."
                        />
                    </div>
                </label>
                <label class="grid gap-1.5 text-xs font-bold text-slate-600">
                    Quản trị viên
                    <select v-model="filters.admin_id" class="ui-focus min-h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm">
                        <option value="">Tất cả quản trị viên</option>
                        <option v-for="admin in admins" :key="admin.id" :value="admin.id">{{ adminName(admin) }}</option>
                    </select>
                </label>
                <label class="grid gap-1.5 text-xs font-bold text-slate-600">
                    Loại thao tác
                    <select v-model="filters.action" class="ui-focus min-h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm">
                        <option value="">Tất cả thao tác</option>
                        <option v-for="action in actions" :key="action" :value="action">{{ actionLabel(action) }}</option>
                    </select>
                </label>
                <label class="grid gap-1.5 text-xs font-bold text-slate-600">
                    HTTP method
                    <select v-model="filters.method" class="ui-focus min-h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm">
                        <option value="">Tất cả method</option>
                        <option v-for="method in ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']" :key="method" :value="method">{{ method }}</option>
                    </select>
                </label>
                <label class="grid gap-1.5 text-xs font-bold text-slate-600">
                    Trạng thái
                    <select v-model="filters.status_code" class="ui-focus min-h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm">
                        <option value="">Tất cả trạng thái</option>
                        <option value="200">200</option>
                        <option value="201">201</option>
                        <option value="204">204</option>
                        <option value="400">400</option>
                        <option value="403">403</option>
                        <option value="404">404</option>
                        <option value="422">422</option>
                        <option value="500">500</option>
                    </select>
                </label>
                <label class="grid gap-1.5 text-xs font-bold text-slate-600">
                    Từ ngày
                    <input
                        v-model="filters.date_from"
                        type="date"
                        class="ui-focus min-h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm"
                    />
                </label>
                <label class="grid gap-1.5 text-xs font-bold text-slate-600">
                    Đến ngày
                    <input v-model="filters.date_to" type="date" class="ui-focus min-h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm" />
                </label>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
                <select
                    v-model="filters.per_page"
                    class="ui-focus min-h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm"
                    @change="loadLogs(1)"
                >
                    <option :value="20">20 bản ghi</option>
                    <option :value="50">50 bản ghi</option>
                    <option :value="100">100 bản ghi</option>
                </select>
                <div class="flex gap-2">
                    <button
                        v-if="hasFilters"
                        type="button"
                        class="ui-focus min-h-10 rounded-lg border border-slate-300 px-4 text-sm font-bold text-slate-700 hover:bg-slate-50"
                        @click="resetFilters"
                    >
                        Xóa lọc
                    </button>
                    <button type="submit" class="ui-focus min-h-10 rounded-lg bg-indigo-600 px-5 text-sm font-black text-white hover:bg-indigo-700">
                        Lọc nhật ký
                    </button>
                </div>
            </div>
        </form>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1100px] border-collapse text-left">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="border-b border-slate-200 px-4 py-3">Thời gian</th>
                            <th class="border-b border-slate-200 px-4 py-3">Quản trị viên</th>
                            <th class="border-b border-slate-200 px-4 py-3">Thao tác</th>
                            <th class="border-b border-slate-200 px-4 py-3">Route</th>
                            <th class="border-b border-slate-200 px-4 py-3">Trạng thái</th>
                            <th class="border-b border-slate-200 px-4 py-3">IP</th>
                            <th class="border-b border-slate-200 px-4 py-3 text-right">Chi tiết</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="loading">
                            <td colspan="7" class="px-4 py-12 text-center text-sm text-slate-500">
                                <LoaderCircle class="mx-auto mb-2 h-6 w-6 animate-spin text-indigo-500" /> Đang tải nhật ký...
                            </td>
                        </tr>
                        <tr v-else-if="logs.length === 0">
                            <td colspan="7" class="px-4 py-12 text-center text-sm text-slate-500">
                                <FileClock class="mx-auto mb-2 h-8 w-8 text-slate-300" /> Không có bản ghi phù hợp.
                            </td>
                        </tr>
                        <tr v-for="log in logs" v-else :key="log.id" class="align-top hover:bg-slate-50/70">
                            <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-600">
                                <p class="font-bold text-slate-800">{{ formatDate(log.created_at) }}</p>
                                <p v-if="log.duration_ms !== null" class="mt-1 flex items-center gap-1">
                                    <Clock3 class="h-3 w-3" /> {{ log.duration_ms }}ms
                                </p>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <p class="font-bold text-slate-900">{{ adminName(log.admin) }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ log.admin?.email || `ID #${log.admin_id ?? '-'}` }}</p>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <p class="font-bold capitalize text-slate-800">{{ actionLabel(log.action) }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ log.subject_type }} #{{ log.subject_id }}</p>
                            </td>
                            <td class="max-w-md px-4 py-3 text-sm">
                                <div class="flex items-center gap-2">
                                    <span
                                        v-if="log.method"
                                        class="rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[11px] font-black text-slate-700"
                                        >{{ log.method }}</span
                                    >
                                    <span class="truncate font-semibold text-slate-800">{{ log.route_name || '-' }}</span>
                                </div>
                                <p class="mt-1 truncate text-xs text-slate-500">{{ log.path || '-' }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full border px-2 py-1 text-xs font-black" :class="statusClass(log.status_code)">
                                    {{ log.status_code ?? 'N/A' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-600">{{ log.ip || '-' }}</td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    class="ui-focus inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-slate-300 px-3 text-xs font-black text-slate-700 hover:bg-slate-50"
                                    @click="selectedLog = log"
                                >
                                    <Eye class="h-4 w-4" /> Xem
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                <p class="text-slate-500">Hiển thị {{ meta.from ?? 0 }}-{{ meta.to ?? 0 }} trong {{ meta.total.toLocaleString('vi-VN') }} bản ghi</p>
                <div class="flex items-center gap-2">
                    <button
                        class="ui-focus inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-300 disabled:opacity-40"
                        :disabled="loading || meta.current_page <= 1"
                        @click="loadLogs(meta.current_page - 1)"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <span class="px-2 font-bold text-slate-700">{{ meta.current_page }} / {{ meta.last_page }}</span>
                    <button
                        class="ui-focus inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-300 disabled:opacity-40"
                        :disabled="loading || meta.current_page >= meta.last_page"
                        @click="loadLogs(meta.current_page + 1)"
                    >
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </section>

        <div v-if="selectedLog" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/45 p-4">
            <section class="max-h-[90vh] w-full max-w-4xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                    <div>
                        <p class="text-xs font-bold uppercase text-indigo-600">Audit #{{ selectedLog.id }}</p>
                        <h2 class="mt-1 text-lg font-black text-slate-950">{{ actionLabel(selectedLog.action) }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ selectedLog.route_name || selectedLog.subject_type }}</p>
                    </div>
                    <button class="ui-focus rounded-lg border border-slate-200 p-2 text-slate-500 hover:bg-slate-50" @click="selectedLog = null">
                        <X class="h-5 w-5" />
                    </button>
                </header>
                <div class="grid max-h-[calc(90vh-82px)] gap-5 overflow-y-auto p-5">
                    <dl class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <dt class="text-xs font-bold text-slate-500">Admin</dt>
                            <dd class="mt-1 font-black text-slate-900">{{ adminName(selectedLog.admin) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-bold text-slate-500">Thời gian</dt>
                            <dd class="mt-1 font-black text-slate-900">{{ formatDate(selectedLog.created_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-bold text-slate-500">IP</dt>
                            <dd class="mt-1 font-mono font-bold text-slate-900">{{ selectedLog.ip || '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-bold text-slate-500">Request ID</dt>
                            <dd class="mt-1 break-all font-mono text-xs font-bold text-slate-900">{{ selectedLog.request_id || '-' }}</dd>
                        </div>
                    </dl>
                    <div class="grid gap-4 lg:grid-cols-2">
                        <article>
                            <h3 class="mb-2 text-sm font-black text-slate-800">Dữ liệu trước</h3>
                            <pre
                                class="max-h-80 overflow-auto rounded-xl border border-slate-200 bg-slate-950 p-4 text-xs leading-6 text-slate-100"
                                >{{ prettyJson(selectedLog.old_values) }}</pre
                            >
                        </article>
                        <article>
                            <h3 class="mb-2 text-sm font-black text-slate-800">Dữ liệu sau / request</h3>
                            <pre
                                class="max-h-80 overflow-auto rounded-xl border border-slate-200 bg-slate-950 p-4 text-xs leading-6 text-slate-100"
                                >{{ prettyJson(selectedLog.new_values) }}</pre
                            >
                        </article>
                    </div>
                    <article>
                        <h3 class="mb-2 text-sm font-black text-slate-800">User agent</h3>
                        <p class="break-all rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs leading-5 text-slate-600">
                            {{ selectedLog.user_agent || '-' }}
                        </p>
                    </article>
                </div>
            </section>
        </div>
    </div>
</template>
