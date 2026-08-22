<script setup lang="ts">
import { adminQueueService } from '@/services/admin-queue.service';
import type {
    AdminQueueFailedJobItem,
    AdminQueueFailedJobsResponse,
    AdminQueueItem,
    AdminQueueLogItem,
    AdminQueueLogsResponse,
    AdminQueueOverviewResponse,
} from '@/types/admin-queue.type';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { Activity, CircleAlert, Clock3, ListTodo, RefreshCw } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, onMounted, reactive, ref } from 'vue';

const loadingOverview = ref(false);
const loadingLogs = ref(false);
const loadingFailedJobs = ref(false);
const retryingUuid = ref<string | null>(null);
const deletingUuid = ref<string | null>(null);
const replayingLogId = ref<number | null>(null);

const overview = ref<AdminQueueOverviewResponse | null>(null);
const queueLogs = ref<AdminQueueLogItem[]>([]);
const failedJobs = ref<AdminQueueFailedJobItem[]>([]);

const logsMeta = reactive<AdminQueueLogsResponse['meta']>({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
});

const failedMeta = reactive<AdminQueueFailedJobsResponse['meta']>({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
});

const logFilters = reactive({
    queue: '',
    status: '',
    search: '',
    per_page: 20,
});

const failedFilters = reactive({
    queue: '',
    search: '',
    per_page: 20,
});

const queueOptions = computed(() => {
    return (overview.value?.queues ?? []).map((item: AdminQueueItem) => item.queue);
});

const statusClass = (status: string): string => {
    if (status === 'success') {
        return 'bg-emerald-50 text-emerald-600 border-emerald-200';
    }
    if (status === 'failed') {
        return 'bg-rose-50 text-rose-600 border-rose-200';
    }
    return 'bg-amber-50 text-amber-600 border-amber-200';
};

const fetchOverview = async (): Promise<void> => {
    try {
        loadingOverview.value = true;
        overview.value = await adminQueueService.overview();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loadingOverview.value = false;
    }
};

const fetchLogs = async (page = 1): Promise<void> => {
    try {
        loadingLogs.value = true;
        const response = await adminQueueService.logs({
            queue: logFilters.queue || undefined,
            status: logFilters.status || undefined,
            search: logFilters.search || undefined,
            per_page: logFilters.per_page,
            page,
        });
        queueLogs.value = response.data;
        Object.assign(logsMeta, response.meta);
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loadingLogs.value = false;
    }
};

const fetchFailedJobs = async (page = 1): Promise<void> => {
    try {
        loadingFailedJobs.value = true;
        const response = await adminQueueService.failedJobs({
            queue: failedFilters.queue || undefined,
            search: failedFilters.search || undefined,
            per_page: failedFilters.per_page,
            page,
        });
        failedJobs.value = response.data;
        Object.assign(failedMeta, response.meta);
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loadingFailedJobs.value = false;
    }
};

const refreshAll = async (): Promise<void> => {
    await Promise.all([fetchOverview(), fetchLogs(1), fetchFailedJobs(1)]);
};

const confirmReplay = async (jobLabel: string): Promise<boolean> => {
    const result = await Swal.fire({
        icon: 'warning',
        title: 'Phát lại job?',
        text: `${jobLabel} sẽ được đưa lại vào queue và worker sẽ chạy lại từ đầu.`,
        showCancelButton: true,
        confirmButtonText: 'Phát lại',
        cancelButtonText: 'Hủy',
        confirmButtonColor: '#4f46e5',
    });

    return result.isConfirmed;
};

const replayQueueLog = async (log: AdminQueueLogItem): Promise<void> => {
    if (!(await confirmReplay(`Queue log #${log.id}`))) {
        return;
    }

    try {
        replayingLogId.value = log.id;
        await adminQueueService.replayQueueLog(log.id);
        handleSuccessResponse({ data: { status: true, message: `Đã phát lại queue log #${log.id}.` } });
        await Promise.all([fetchLogs(logsMeta.current_page), fetchFailedJobs(failedMeta.current_page), fetchOverview()]);
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        replayingLogId.value = null;
    }
};

const retryFailedJob = async (uuid: string): Promise<void> => {
    if (!(await confirmReplay(`Failed job ${uuid}`))) {
        return;
    }

    try {
        retryingUuid.value = uuid;
        await adminQueueService.retryFailedJob(uuid);
        handleSuccessResponse({ data: { status: true, message: 'Đã đưa job vào queue để phát lại.' } });
        await Promise.all([fetchLogs(logsMeta.current_page), fetchFailedJobs(failedMeta.current_page), fetchOverview()]);
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        retryingUuid.value = null;
    }
};

const deleteFailedJob = async (uuid: string): Promise<void> => {
    try {
        deletingUuid.value = uuid;
        await adminQueueService.deleteFailedJob(uuid);
        handleSuccessResponse({ data: { status: true, message: 'Đã xóa failed job.' } });
        await fetchFailedJobs(failedMeta.current_page);
        await fetchOverview();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        deletingUuid.value = null;
    }
};

onMounted(async () => {
    await refreshAll();
});
</script>

<template>
    <div class="space-y-4">
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h1 class="text-xl font-bold text-slate-950">Quản lý queue</h1>
                    <p class="mt-1 text-sm text-slate-500">Theo dõi trạng thái queue, log runtime và failed jobs để vận hành ổn định.</p>
                </div>
                <button
                    type="button"
                    class="rounded-[10px] bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60"
                    :disabled="loadingOverview || loadingLogs || loadingFailedJobs"
                    @click="refreshAll"
                >
                    <span class="inline-flex items-center gap-2"><RefreshCw class="h-4 w-4" aria-hidden="true" />Làm mới</span>
                </button>
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-[10px] border border-slate-200 bg-white p-3 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pending jobs</p>
                        <p class="mt-1 text-2xl font-bold text-slate-900">{{ overview?.summary.total_pending_jobs ?? 0 }}</p>
                    </div>
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-700"
                        ><ListTodo class="h-5 w-5"
                    /></span>
                </div>
            </article>
            <article class="rounded-[10px] border border-slate-200 bg-white p-3 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Failed jobs</p>
                        <p class="mt-1 text-2xl font-bold text-rose-600">{{ overview?.summary.total_failed_jobs ?? 0 }}</p>
                    </div>
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50 text-rose-600"
                        ><CircleAlert class="h-5 w-5"
                    /></span>
                </div>
            </article>
            <article class="rounded-[10px] border border-slate-200 bg-white p-3 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Processing logs</p>
                        <p class="mt-1 text-2xl font-bold text-amber-600">{{ overview?.summary.total_processing_logs ?? 0 }}</p>
                    </div>
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600"
                        ><Clock3 class="h-5 w-5"
                    /></span>
                </div>
            </article>
            <article class="rounded-[10px] border border-slate-200 bg-white p-3 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Failed logs</p>
                        <p class="mt-1 text-2xl font-bold text-rose-600">{{ overview?.summary.total_failed_logs ?? 0 }}</p>
                    </div>
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50 text-rose-600"
                        ><Activity class="h-5 w-5"
                    /></span>
                </div>
            </article>
        </section>

        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="text-base font-semibold text-slate-950">Danh sách queue</h2>
            <div class="mt-3 overflow-x-auto rounded-[10px] border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Queue</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Pending</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Failed Jobs</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Processing</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Success</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Failed Logs</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-if="loadingOverview">
                            <td colspan="6" class="px-3 py-6 text-center text-sm text-slate-500">Đang tải tổng quan queue...</td>
                        </tr>
                        <tr v-else-if="(overview?.queues?.length ?? 0) === 0">
                            <td colspan="6" class="px-3 py-6 text-center text-sm text-slate-500">Chưa có dữ liệu queue.</td>
                        </tr>
                        <tr v-for="queue in overview?.queues ?? []" :key="queue.queue">
                            <td class="px-3 py-2 text-sm font-semibold text-slate-900">{{ queue.queue }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">{{
                                    queue.pending_jobs
                                }}</span>
                            </td>
                            <td class="px-3 py-2">
                                <span class="inline-flex rounded-full bg-rose-50 px-2.5 py-1 text-xs font-bold text-rose-700">{{
                                    queue.failed_jobs
                                }}</span>
                            </td>
                            <td class="px-3 py-2">
                                <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700">{{
                                    queue.processing_logs
                                }}</span>
                            </td>
                            <td class="px-3 py-2">
                                <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">{{
                                    queue.success_logs
                                }}</span>
                            </td>
                            <td class="px-3 py-2">
                                <span class="inline-flex rounded-full bg-rose-50 px-2.5 py-1 text-xs font-bold text-rose-700">{{
                                    queue.failed_logs
                                }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="text-base font-semibold text-slate-950">Queue logs</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                <select v-model="logFilters.queue" class="rounded-[10px] border border-slate-200 px-3 py-2 text-sm">
                    <option value="">Tất cả queue</option>
                    <option v-for="queue in queueOptions" :key="queue" :value="queue">{{ queue }}</option>
                </select>
                <select v-model="logFilters.status" class="rounded-[10px] border border-slate-200 px-3 py-2 text-sm">
                    <option value="">Tất cả trạng thái</option>
                    <option value="processing">processing</option>
                    <option value="success">success</option>
                    <option value="failed">failed</option>
                </select>
                <input
                    v-model="logFilters.search"
                    type="text"
                    class="min-w-[240px] rounded-[10px] border border-slate-200 px-3 py-2 text-sm"
                    placeholder="Tìm theo uuid/job/error..."
                />
                <button
                    type="button"
                    class="rounded-[10px] border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    @click="fetchLogs(1)"
                >
                    Lọc
                </button>
            </div>

            <div class="mt-3 overflow-x-auto rounded-[10px] border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">ID</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Queue</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Job</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Attempts</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Error</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-if="loadingLogs">
                            <td colspan="7" class="px-3 py-6 text-center text-sm text-slate-500">Đang tải queue logs...</td>
                        </tr>
                        <tr v-else-if="queueLogs.length === 0">
                            <td colspan="7" class="px-3 py-6 text-center text-sm text-slate-500">Không có queue log phù hợp.</td>
                        </tr>
                        <tr v-for="log in queueLogs" :key="log.id">
                            <td class="px-3 py-2 text-xs text-slate-500">#{{ log.id }}</td>
                            <td class="px-3 py-2 text-sm font-semibold text-slate-900">{{ log.queue || '-' }}</td>
                            <td class="px-3 py-2 text-xs text-slate-600">
                                <p class="font-semibold text-slate-800">{{ log.job_name || '-' }}</p>
                                <p>{{ log.job_uuid || '-' }}</p>
                            </td>
                            <td class="px-3 py-2">
                                <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold" :class="statusClass(log.status)">
                                    {{ log.status }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-sm text-slate-700">{{ log.attempts }}</td>
                            <td class="px-3 py-2 text-xs text-rose-600">
                                <p class="line-clamp-2">{{ log.error_message || '-' }}</p>
                            </td>
                            <td class="px-3 py-2">
                                <button
                                    v-if="log.can_replay"
                                    type="button"
                                    class="rounded-[5px] bg-indigo-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 disabled:opacity-60"
                                    :disabled="replayingLogId === log.id"
                                    @click="replayQueueLog(log)"
                                >
                                    {{ replayingLogId === log.id ? 'Đang phát lại...' : 'Phát lại' }}
                                </button>
                                <span v-else-if="log.status === 'failed'" class="text-xs text-slate-400">Không còn job gốc</span>
                                <span v-else class="text-xs text-slate-400">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="text-base font-semibold text-slate-950">Failed jobs</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                <select v-model="failedFilters.queue" class="rounded-[10px] border border-slate-200 px-3 py-2 text-sm">
                    <option value="">Tất cả queue</option>
                    <option v-for="queue in queueOptions" :key="queue" :value="queue">{{ queue }}</option>
                </select>
                <input
                    v-model="failedFilters.search"
                    type="text"
                    class="min-w-[240px] rounded-[10px] border border-slate-200 px-3 py-2 text-sm"
                    placeholder="Tìm theo uuid/exception..."
                />
                <button
                    type="button"
                    class="rounded-[10px] border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    @click="fetchFailedJobs(1)"
                >
                    Lọc
                </button>
            </div>

            <div class="mt-3 overflow-x-auto rounded-[10px] border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">ID</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Queue</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">UUID</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Lỗi</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-if="loadingFailedJobs">
                            <td colspan="5" class="px-3 py-6 text-center text-sm text-slate-500">Đang tải failed jobs...</td>
                        </tr>
                        <tr v-else-if="failedJobs.length === 0">
                            <td colspan="5" class="px-3 py-6 text-center text-sm text-slate-500">Không có failed jobs.</td>
                        </tr>
                        <tr v-for="job in failedJobs" :key="job.id">
                            <td class="px-3 py-2 text-xs text-slate-500">#{{ job.id }}</td>
                            <td class="px-3 py-2 text-sm font-semibold text-slate-900">{{ job.queue }}</td>
                            <td class="px-3 py-2 text-xs text-slate-600">{{ job.uuid }}</td>
                            <td class="px-3 py-2 text-xs text-rose-600">
                                <p class="line-clamp-2">{{ job.exception }}</p>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        class="rounded-[5px] bg-indigo-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 disabled:opacity-60"
                                        :disabled="retryingUuid === job.uuid || deletingUuid === job.uuid"
                                        @click="retryFailedJob(job.uuid)"
                                    >
                                        {{ retryingUuid === job.uuid ? 'Đang phát lại...' : 'Phát lại' }}
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-[8px] border border-rose-200 px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 disabled:opacity-60"
                                        :disabled="deletingUuid === job.uuid || retryingUuid === job.uuid"
                                        @click="deleteFailedJob(job.uuid)"
                                    >
                                        {{ deletingUuid === job.uuid ? 'Đang xóa...' : 'Xóa' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
