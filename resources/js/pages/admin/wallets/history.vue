<script setup lang="ts">
import Breadcrumb from '@/components/MasterLayouts/Breadcrumb/index.vue';
import DataTable from '@/components/shared/DataTable/index.vue';
import api from '@/config/axios';
import { handleErrorResponse } from '@/utils/response';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = withDefaults(defineProps<{ kind?: 'money' | 'token' }>(), { kind: 'money' });
type Row = {
    id: number;
    user_id: number;
    username: string | null;
    actor_username: string | null;
    direction: string;
    amount: number;
    balance_before: number;
    balance_after: number;
    description: string;
    created_at: string;
};
const title = computed(() => (props.kind === 'token' ? 'Lịch sử token' : 'Lịch sử dòng tiền'));
const rows = ref<Row[]>([]);
const q = ref('');
const direction = ref('');
const from = ref('');
const to = ref('');
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const loading = ref(false);
let requestId = 0;
const columns = [
    { accessorKey: 'id', header: '#' },
    { accessorKey: 'username', header: 'Người dùng' },
    { accessorKey: 'amount', header: 'Biến động' },
    { accessorKey: 'balance_before', header: 'Số dư trước' },
    { accessorKey: 'balance_after', header: 'Số dư sau' },
    { accessorKey: 'description', header: 'Nội dung' },
    { accessorKey: 'actor_username', header: 'Thực hiện' },
    { accessorKey: 'created_at', header: 'Thời gian' },
];
const format = (value: number) => new Intl.NumberFormat('vi-VN').format(value) + (props.kind === 'money' ? 'đ' : ' token');
async function load(nextPage = 1) {
    const current = ++requestId;
    loading.value = true;
    try {
        const { data } = await api.get('/api/admin-api/wallets/history', {
            params: { kind: props.kind, page: nextPage, q: q.value, direction: direction.value, from: from.value, to: to.value },
        });
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
function reset() {
    q.value = direction.value = from.value = to.value = '';
    void load(1);
}
watch(
    () => props.kind,
    () => {
        rows.value = [];
        total.value = 0;
        reset();
    },
);
onMounted(() => load());
onBeforeUnmount(() => {
    requestId++;
});
</script>

<template>
    <div class="space-y-4">
        <Breadcrumb :title="title" />
        <form
            class="flex flex-wrap items-end gap-3 rounded-[5px] border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
            @submit.prevent="load(1)"
        >
            <label class="grid min-w-0 flex-1 basis-56 gap-2 text-sm"
                >Người dùng / nội dung
                <input
                    v-model="q"
                    maxlength="100"
                    placeholder="Nhập tên tài khoản hoặc nội dung giao dịch"
                    class="rounded-[5px] border border-slate-300 bg-transparent px-3 py-2 dark:border-slate-600"
                />
            </label>
            <label class="grid gap-2 text-sm"
                >Loại giao dịch
                <select v-model="direction" class="rounded-[5px] border border-slate-300 bg-white px-3 py-2 dark:border-slate-600 dark:bg-slate-800">
                    <option value="">Tất cả</option>
                    <option value="credit">Cộng</option>
                    <option value="debit">Trừ</option>
                </select>
            </label>
            <label class="grid gap-2 text-sm"
                >Từ ngày<input
                    v-model="from"
                    type="date"
                    class="rounded-[5px] border border-slate-300 bg-transparent px-3 py-2 dark:border-slate-600"
            /></label>
            <label class="grid gap-2 text-sm"
                >Đến ngày<input
                    v-model="to"
                    type="date"
                    :min="from || undefined"
                    class="rounded-[5px] border border-slate-300 bg-transparent px-3 py-2 dark:border-slate-600"
            /></label>
            <button :disabled="loading" class="rounded-[5px] bg-emerald-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                Lọc
            </button>
            <button
                type="button"
                :disabled="loading"
                class="rounded-[5px] border border-slate-300 px-4 py-2 text-sm dark:border-slate-600"
                @click="reset"
            >
                Đặt lại
            </button>
        </form>
        <p class="text-xs text-slate-500">{{ total }} giao dịch · 20 dòng/trang</p>
        <DataTable
            :data="rows"
            :columns="columns"
            :loading="loading"
            :current-page="page"
            :total-pages="lastPage"
            :go-to-page="load"
            class-custom="min-w-[64rem]"
        >
            <template #username="{ row }"
                ><p>{{ row.username ?? 'Tài khoản đã xóa' }}</p>
                <span class="text-xs text-slate-400">#{{ row.user_id }}</span></template
            >
            <template #amount="{ row }"
                ><strong class="whitespace-nowrap" :class="row.direction === 'credit' ? 'text-emerald-600' : 'text-rose-600'"
                    >{{ row.direction === 'credit' ? '+' : '−' }}{{ format(row.amount) }}</strong
                ></template
            >
            <template #balance_before="{ row }"
                ><span class="whitespace-nowrap">{{ format(row.balance_before) }}</span></template
            >
            <template #balance_after="{ row }"
                ><span class="whitespace-nowrap">{{ format(row.balance_after) }}</span></template
            >
            <template #description="{ row }"
                ><p class="max-w-xs whitespace-normal break-words">{{ row.description }}</p></template
            >
            <template #actor_username="{ row }">{{ row.actor_username ?? 'Hệ thống' }}</template>
            <template #created_at="{ row }"
                ><span class="whitespace-nowrap text-xs">{{ new Date(row.created_at).toLocaleString('vi-VN') }}</span></template
            >
        </DataTable>
    </div>
</template>
