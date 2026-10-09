<script setup lang="ts">
import Breadcrumb from '@/components/MasterLayouts/Breadcrumb/index.vue';
import { nroNotificationService, type NroBoss, type NroNotificationType, type NroNotify, type NroServer } from '@/services/nro-notification.service';
import { handleErrorResponse } from '@/utils/response';
import { onMounted, reactive, ref } from 'vue';

const notifies = ref<NroNotify[]>([]);
const servers = ref<NroServer[]>([]);
const bosses = ref<NroBoss[]>([]);
const types = ref<NroNotificationType[]>([]);
const filters = reactive({ server_id: '', boss_id: '', code: '', state: '', per_page: 25 });
const appliedFilters = ref({ ...filters });
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const loading = ref(true);
const failed = ref(false);
const optionsFailed = ref(false);
const selected = ref<NroNotify | null>(null);
const detail = ref<HTMLElement | null>(null);
const date = (value: string | null) =>
    value ? new Intl.DateTimeFormat('vi-VN', { timeZone: 'Asia/Ho_Chi_Minh', dateStyle: 'short', timeStyle: 'medium' }).format(new Date(value)) : '—';
const stateLabels: Record<string, string> = { notification: 'Thông báo', living: 'Đang sống', dead: 'Đã bị tiêu diệt' };
const state = (value: string) => stateLabels[value] || value;
const show = (notify: NroNotify) => {
    selected.value = notify;
    requestAnimationFrame(() => detail.value?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
};
const load = async (page = 1) => {
    loading.value = true;
    failed.value = false;
    selected.value = null;
    try {
        const params = Object.fromEntries(Object.entries({ ...appliedFilters.value, page }).filter(([, value]) => value !== ''));
        const result = await nroNotificationService.notifications(params);
        notifies.value = result.data;
        meta.value = result.meta;
    } catch (error) {
        failed.value = true;
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};
const applyFilters = () => {
    appliedFilters.value = { ...filters };
    void load();
};
const initialize = async () => {
    loading.value = true;
    optionsFailed.value = false;
    try {
        const [serverList, bossList, typeList] = await Promise.all([
            nroNotificationService.servers(),
            nroNotificationService.bosses(),
            nroNotificationService.notificationTypes(),
        ]);
        servers.value = serverList;
        bosses.value = bossList;
        types.value = typeList.codes;
        await load();
    } catch (error) {
        optionsFailed.value = true;
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};
onMounted(initialize);
</script>

<template>
    <div class="mx-auto min-w-0 max-w-7xl space-y-6">
        <Breadcrumb :items="[{ label: 'Thông báo game' }, { label: 'Quản lý thông báo' }]" />
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Quản lý thông báo</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                Thông báo được nhận tự động từ API đọc dữ liệu game. Trang này dùng để xem và tra cứu dữ liệu.
            </p>
        </div>
        <form class="rounded-[10px] border border-slate-300 bg-white p-5 dark:border-slate-600 dark:bg-slate-900" @submit.prevent="applyFilters">
            <fieldset :disabled="loading || optionsFailed" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200"
                    >Server<select
                        v-model="filters.server_id"
                        class="mt-2 h-11 w-full rounded-[10px] border border-slate-300 bg-white px-3 dark:border-slate-600 dark:bg-slate-800"
                    >
                        <option value="">Tất cả server</option>
                        <option v-for="server in servers" :key="server.id" :value="server.id">
                            {{ server.name }}{{ server.is_active ? '' : ' (tạm tắt)' }}
                        </option>
                    </select></label
                >
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200"
                    >Loại thông báo<select
                        v-model="filters.code"
                        class="mt-2 h-11 w-full rounded-[10px] border border-slate-300 bg-white px-3 dark:border-slate-600 dark:bg-slate-800"
                    >
                        <option value="">Tất cả loại</option>
                        <option v-for="type in types" :key="type.id" :value="type.code">{{ type.name }}</option>
                    </select></label
                >
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200"
                    >Boss<select
                        v-model="filters.boss_id"
                        class="mt-2 h-11 w-full rounded-[10px] border border-slate-300 bg-white px-3 dark:border-slate-600 dark:bg-slate-800"
                    >
                        <option value="">Tất cả boss</option>
                        <option v-for="boss in bosses" :key="boss.id" :value="boss.id">{{ boss.name }}</option>
                    </select></label
                >
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200"
                    >Trạng thái<select
                        v-model="filters.state"
                        class="mt-2 h-11 w-full rounded-[10px] border border-slate-300 bg-white px-3 dark:border-slate-600 dark:bg-slate-800"
                    >
                        <option value="">Tất cả thông báo</option>
                        <option value="living">Boss đang sống</option>
                        <option value="respawning">Boss sắp hồi sinh</option>
                        <option value="history">Lịch sử boss</option>
                    </select></label
                >
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200"
                    >Số mục mỗi trang<select
                        v-model.number="filters.per_page"
                        class="mt-2 h-11 w-full rounded-[10px] border border-slate-300 bg-white px-3 dark:border-slate-600 dark:bg-slate-800"
                    >
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select></label
                >
                <button class="h-11 self-end rounded-[10px] bg-emerald-700 px-4 text-sm font-semibold text-white disabled:opacity-50">
                    Lọc thông báo
                </button>
            </fieldset>
        </form>
        <section
            v-if="selected"
            ref="detail"
            class="rounded-[10px] border border-slate-300 bg-white p-5 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200"
        >
            <div class="mb-4 flex justify-between gap-4">
                <h2 class="font-bold">Chi tiết thông báo #{{ selected.id }}</h2>
                <button class="text-slate-500 underline" @click="selected = null">Đóng</button>
            </div>
            <dl class="grid gap-x-4 gap-y-2 sm:grid-cols-[10rem_1fr]">
                <dt>Server</dt>
                <dd>{{ selected.server.name }}</dd>
                <dt>Loại / Boss</dt>
                <dd>{{ selected.code }} / {{ selected.boss_name || selected.boss?.name || '—' }}</dd>
                <dt>Map / Khu</dt>
                <dd>{{ selected.map_name || '—' }} / {{ selected.zone_name ?? selected.zone ?? '—' }}</dd>
                <dt>Thời gian xuất hiện</dt>
                <dd>{{ date(selected.time_start) }}</dd>
                <dt>Thời gian chết</dt>
                <dd>{{ date(selected.death_time) }}</dd>
                <dt>Người tiêu diệt</dt>
                <dd>{{ selected.killed_by || '—' }}</dd>
                <dt>Hồi sinh dự kiến</dt>
                <dd>{{ date(selected.respawn_at) }}</dd>
                <dt>Nội dung từ game</dt>
                <dd class="whitespace-pre-wrap break-words">{{ selected.content }}</dd>
                <template v-if="selected.death_content"
                    ><dt>Nội dung tiêu diệt</dt>
                    <dd class="whitespace-pre-wrap break-words">{{ selected.death_content }}</dd></template
                >
            </dl>
        </section>
        <section class="overflow-hidden rounded-[10px] border border-slate-300 bg-white dark:border-slate-600 dark:bg-slate-900">
            <div
                class="flex items-center justify-between gap-3 border-b border-slate-200 p-5 text-sm text-slate-600 dark:border-slate-700 dark:text-slate-300"
            >
                <span>{{ meta.total }} thông báo</span
                ><button
                    :disabled="loading"
                    class="rounded-[10px] border border-slate-300 px-3 py-2 disabled:opacity-50 dark:border-slate-600"
                    @click="optionsFailed ? initialize() : load(meta.current_page)"
                >
                    Làm mới
                </button>
            </div>
            <p v-if="loading" role="status" class="p-8 text-center text-slate-500">Đang tải thông báo…</p>
            <p v-else-if="failed || optionsFailed" class="p-8 text-center text-slate-500">Không tải được dữ liệu. Bấm Làm mới để thử lại.</p>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm text-slate-700 dark:text-slate-200">
                    <thead class="border-b border-slate-300 bg-slate-50 dark:border-slate-600 dark:bg-slate-800">
                        <tr>
                            <th class="px-5 py-3">Thời gian (UTC+7)</th>
                            <th class="px-5 py-3">Server</th>
                            <th class="px-5 py-3">Loại / Boss</th>
                            <th class="px-5 py-3">Map / Khu</th>
                            <th class="px-5 py-3">Trạng thái</th>
                            <th class="px-5 py-3">Chi tiết</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        <tr v-for="notify in notifies" :key="notify.id">
                            <td class="whitespace-nowrap px-5 py-4">{{ date(notify.time_start) }}</td>
                            <td class="px-5 py-4">{{ notify.server.name }}</td>
                            <td class="px-5 py-4">
                                {{ types.find((type) => type.code === notify.code)?.name || notify.code
                                }}<span v-if="notify.boss" class="block font-semibold">{{ notify.boss.name }}</span>
                            </td>
                            <td class="px-5 py-4">
                                {{ notify.map_name || '—'
                                }}<span v-if="notify.zone_name !== null || notify.zone !== null" class="block text-xs text-slate-500">Khu {{ notify.zone_name ?? notify.zone }}</span>
                            </td>
                            <td class="px-5 py-4">{{ state(notify.state) }}</td>
                            <td class="px-5 py-4">
                                <button
                                    class="rounded-[10px] border border-slate-300 px-3 py-1.5 text-emerald-700 dark:border-slate-600 dark:text-emerald-400"
                                    @click="show(notify)"
                                >
                                    Xem
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!notifies.length">
                            <td colspan="6" class="p-8 text-center text-slate-500">
                                Chưa có thông báo phù hợp. Dữ liệu sẽ xuất hiện khi API nhận thông báo từ game.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <nav
                v-if="!failed && !optionsFailed"
                aria-label="Phân trang thông báo"
                class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 p-5 text-sm text-slate-600 dark:border-slate-700 dark:text-slate-300"
            >
                <span>Trang {{ meta.current_page }} / {{ meta.last_page }}</span>
                <div class="flex gap-3">
                    <button
                        :disabled="loading || meta.current_page <= 1"
                        class="rounded-[10px] border border-slate-300 px-3 py-2 disabled:opacity-40 dark:border-slate-600"
                        @click="load(meta.current_page - 1)"
                    >
                        Trước</button
                    ><button
                        :disabled="loading || meta.current_page >= meta.last_page"
                        class="rounded-[10px] border border-slate-300 px-3 py-2 disabled:opacity-40 dark:border-slate-600"
                        @click="load(meta.current_page + 1)"
                    >
                        Sau
                    </button>
                </div>
            </nav>
        </section>
    </div>
</template>
