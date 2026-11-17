<script setup lang="ts">
import Breadcrumb from '@/components/MasterLayouts/Breadcrumb/index.vue';
import DataTable from '@/components/shared/DataTable/index.vue';
import { adminGameImageService, gameImageStatuses, type GameImageHistoryRow } from '@/services/admin-game-image.service';
import { handleErrorResponse } from '@/utils/response';
import { onMounted, ref } from 'vue';

const rows = ref<GameImageHistoryRow[]>([]);
const q = ref('');
const status = ref('');
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const loading = ref(false);
let revision = 0;
const columns = [
    { accessorKey: 'id', header: '#' },
    { accessorKey: 'member', header: 'Người dùng' },
    { accessorKey: 'username', header: 'Tài khoản game' },
    { accessorKey: 'status', header: 'Trạng thái' },
    { accessorKey: 'charged_amount', header: 'Đã thu / hoàn' },
    { accessorKey: 'created_at', header: 'Thời gian' },
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
                    Đã hoàn {{ money(row.charged_amount) }}
                </p></template
            >
            <template #created_at="{ row }"
                ><p class="whitespace-nowrap text-xs">{{ date(row.created_at) }}</p>
                <p v-if="row.completed_at" class="mt-1 whitespace-nowrap text-xs text-slate-400">Kết thúc: {{ date(row.completed_at) }}</p></template
            >
        </DataTable>
    </div>
</template>
