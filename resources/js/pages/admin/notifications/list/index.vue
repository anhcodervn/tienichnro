<script setup lang="ts">
import { adminNotificationService } from '@/services/admin-notification.service';
import type { AdminNotificationItem, AdminNotificationListResponse } from '@/types/notification.type';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { Bell, BellRing, CalendarClock, Eye, Plus, RadioTower, Search, Trash2, UserRound } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';

const router = useRouter();
const loading = ref(false);
const response = ref<AdminNotificationListResponse | null>(null);

const filters = reactive({
    search: '',
    scope: '' as '' | 'system' | 'user',
    type: '',
    per_page: 10,
    page: 1,
});

const notifications = computed(() => response.value?.data ?? []);
const meta = computed(() => response.value?.meta ?? { current_page: 1, last_page: 1, per_page: 10, total: 0 });
const stats = computed(() => response.value?.stats ?? { total: 0, system: 0, user: 0, today: 0 });

const loadNotifications = async (): Promise<void> => {
    try {
        loading.value = true;
        response.value = await adminNotificationService.list({ ...filters });
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

const removeNotification = async (item: AdminNotificationItem): Promise<void> => {
    if (!window.confirm(`Xóa thông báo #${item.id}?`)) {
        return;
    }

    try {
        await adminNotificationService.remove(item.id);
        handleSuccessResponse({ data: { status: true, message: 'Xóa thông báo thành công.' } });

        if (notifications.value.length === 1 && filters.page > 1) {
            filters.page -= 1;
        }

        await loadNotifications();
    } catch (error) {
        handleErrorResponse(error);
    }
};

const changePage = async (page: number): Promise<void> => {
    if (page < 1 || page > meta.value.last_page || page === filters.page) {
        return;
    }

    filters.page = page;
    await loadNotifications();
};

watch(
    () => [filters.scope, filters.type, filters.per_page],
    async () => {
        filters.page = 1;
        await loadNotifications();
    },
);

let searchTimeout: ReturnType<typeof setTimeout> | null = null;
watch(
    () => filters.search,
    (value) => {
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }

        searchTimeout = setTimeout(
            async () => {
                filters.page = 1;
                if (value !== filters.search) {
                    return;
                }

                await loadNotifications();
            },
            value.trim() === '' ? 0 : 300,
        );
    },
);

onMounted(loadNotifications);
</script>

<template>
    <div class="space-y-4">
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-bold text-slate-950">Quản lý thông báo</h1>
                    <p class="text-sm text-slate-500">Danh sách thông báo hệ thống và thông báo người dùng.</p>
                </div>
                <RouterLink
                    :to="{ name: 'admin.notifications.create' }"
                    class="inline-flex items-center gap-2 rounded-[10px] bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                >
                    <Plus class="h-4 w-4" />
                    Tạo thông báo
                </RouterLink>
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <article class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold text-slate-500">Tổng thông báo</p>
                        <p class="mt-1 text-2xl font-bold text-slate-950">{{ stats.total }}</p>
                    </div>
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-700"
                        ><BellRing class="h-5 w-5"
                    /></span>
                </div>
            </article>
            <article class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold text-slate-500">Thông báo hệ thống</p>
                        <p class="mt-1 text-2xl font-bold text-indigo-600">{{ stats.system }}</p>
                    </div>
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600"
                        ><RadioTower class="h-5 w-5"
                    /></span>
                </div>
            </article>
            <article class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold text-slate-500">Thông báo người dùng</p>
                        <p class="mt-1 text-2xl font-bold text-amber-600">{{ stats.user }}</p>
                    </div>
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600"
                        ><UserRound class="h-5 w-5"
                    /></span>
                </div>
            </article>
            <article class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold text-slate-500">Hôm nay</p>
                        <p class="mt-1 text-2xl font-bold text-emerald-600">{{ stats.today }}</p>
                    </div>
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"
                        ><CalendarClock class="h-5 w-5"
                    /></span>
                </div>
            </article>
        </section>

        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid gap-3 lg:grid-cols-[minmax(0,1.2fr)_180px_220px_130px]">
                <label class="relative block">
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="filters.search"
                        type="text"
                        class="w-full rounded-[10px] border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm outline-none focus:border-indigo-400"
                        placeholder="Tìm theo tiêu đề, nội dung, người nhận..."
                    />
                </label>
                <select v-model="filters.scope" class="rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400">
                    <option value="">Phạm vi</option>
                    <option value="system">Hệ thống</option>
                    <option value="user">Người dùng</option>
                </select>
                <input
                    v-model="filters.type"
                    type="text"
                    class="rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                    placeholder="Lọc theo type"
                />
                <select
                    v-model="filters.per_page"
                    class="rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                >
                    <option :value="10">10 / trang</option>
                    <option :value="20">20 / trang</option>
                    <option :value="50">50 / trang</option>
                </select>
            </div>
        </section>

        <section class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
            <div v-if="loading" class="px-4 py-12 text-center text-sm text-slate-500">Đang tải thông báo...</div>

            <div v-else-if="notifications.length === 0" class="px-4 py-12 text-center">
                <Bell class="mx-auto h-10 w-10 text-slate-300" />
                <p class="mt-3 text-sm font-semibold text-slate-900">Chưa có thông báo</p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">ID</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Nội dung</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Người nhận</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Phạm vi</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Đã xem</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tạo lúc</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in notifications" :key="item.id" class="hover:bg-slate-50/70">
                            <td class="px-4 py-3 text-sm font-semibold text-slate-900">#{{ item.id }}</td>
                            <td class="px-4 py-3">
                                <p class="text-sm font-semibold text-slate-900">{{ item.title }}</p>
                                <p class="mt-1 line-clamp-2 text-xs text-slate-600">{{ item.content }}</p>
                                <p v-if="item.redirect_url" class="mt-1 truncate text-[11px] text-indigo-600">{{ item.redirect_url }}</p>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600">
                                <template v-if="item.scope === 'system'">
                                    <p class="font-semibold text-slate-800">Toàn hệ thống</p>
                                </template>
                                <template v-else>
                                    <p class="font-semibold text-slate-800">{{ item.user?.name || `User #${item.user_id}` }}</p>
                                    <p>{{ item.user?.email || '-' }}</p>
                                </template>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="item.scope === 'system' ? 'bg-indigo-100 text-indigo-700' : 'bg-amber-100 text-amber-700'"
                                >
                                    {{ item.scope === 'system' ? 'Hệ thống' : 'Người dùng' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ item.type || '-' }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full bg-sky-50 px-2.5 py-1 text-xs font-bold text-sky-700 ring-1 ring-inset ring-sky-600/20"
                                >
                                    <Eye class="h-3.5 w-3.5" aria-hidden="true" />
                                    {{ item.reads_count }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ item.created_at || '-' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        class="rounded-[10px] border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                        @click="router.push({ name: 'admin.notifications.edit', params: { notification_id: item.id } })"
                                    >
                                        Sửa
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-[10px] border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50"
                                        @click="removeNotification(item)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="meta.last_page > 1" class="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-sm">
                <p class="text-slate-500">Trang {{ meta.current_page }}/{{ meta.last_page }}</p>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-[10px] border border-slate-200 px-3 py-2 text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                        :disabled="meta.current_page <= 1"
                        @click="changePage(meta.current_page - 1)"
                    >
                        Trước
                    </button>
                    <button
                        type="button"
                        class="rounded-[10px] border border-slate-200 px-3 py-2 text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                        :disabled="meta.current_page >= meta.last_page"
                        @click="changePage(meta.current_page + 1)"
                    >
                        Sau
                    </button>
                </div>
            </div>
        </section>
    </div>
</template>
