<script setup lang="ts">
import DataTable from '@/components/shared/DataTable/index.vue';
import api from '@/config/axios';
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/vue';
import { isAxiosError } from 'axios';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import CaptchaImagePreview from './CaptchaImagePreview.vue';

const props = withDefaults(defineProps<{ section?: 'wallet' | 'solves' }>(), { section: 'wallet' });
type Rate = { min_tokens: number; unit_price: number };
type Row = {
    id: number;
    user_id: number;
    username?: string;
    balance?: number;
    reserved?: number;
    amount?: number;
    cost?: number;
    direction?: string;
    balance_before?: number;
    balance_after?: number;
    description?: string;
    actor_id?: number;
    status?: string;
    source?: string;
    result?: string;
    created_at?: string;
    response?: unknown;
    http_status?: number;
    provider_name?: string;
    duration_ms?: number;
    provider_attempts?: {
        id: number;
        provider_name: string;
        driver: string;
        status: string;
        http_status: number | null;
        duration_ms: number;
        response: unknown;
    }[];
};
const tab = ref('wallets');
const rows = ref<Row[]>([]);
const rates = ref<Rate[]>([]);
const page = ref(1);
const lastPage = ref(1);
const totalRows = ref(0);
const range = ref({ from: 0, to: 0 });
const solveColumns = [
    { accessorKey: 'id', header: 'Mã lượt' },
    { accessorKey: 'user_id', header: 'Người dùng' },
    { accessorKey: 'status', header: 'Trạng thái' },
    { accessorKey: 'result', header: 'Kết quả' },
    { accessorKey: 'provider_name', header: 'Nguồn giải' },
    { accessorKey: 'source', header: 'Kênh' },
    { accessorKey: 'duration_ms', header: 'Thời gian giải' },
    { accessorKey: 'created_at', header: 'Thời điểm' },
    { id: 'actions', header: 'Chi tiết' },
];
const solveStatus: Record<string, { label: string; class: string }> = {
    success: { label: 'Thành công', class: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' },
    failed: { label: 'Thất bại', class: 'bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300' },
    processing: { label: 'Đang xử lý', class: 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300' },
};
const userId = ref('');
const status = ref('');
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const notice = ref('');
const detail = ref<Row | null>(null);
const imageUrl = ref('');
const detailLoading = ref(false);
const detailError = ref('');
const imageLoading = ref(false);
const imageError = ref('');
let detailRequest = 0;
const adjusting = ref<Row | null>(null);
const adjustment = ref({ amount: 1, direction: 'credit', description: '', request_id: '' });
const endpoint = computed(() => (props.section === 'solves' ? 'solves' : tab.value));
const fail = (err: unknown) => {
    error.value = isAxiosError(err)
        ? Object.values(err.response?.data?.errors || {})
              .flat()
              .join(' ') ||
          err.response?.data?.message ||
          'Không tải được dữ liệu.'
        : 'Yêu cầu thất bại.';
};
const load = async () => {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await api.get('/api/admin-api/captcha/' + endpoint.value, {
            params: { page: page.value, user_id: userId.value || undefined, status: status.value || undefined },
        });
        if (endpoint.value === 'rates') rates.value = data.rates;
        else {
            rows.value = data.data;
            lastPage.value = data.last_page;
            totalRows.value = data.total;
            range.value = { from: data.from || 0, to: data.to || 0 };
        }
    } catch (err) {
        fail(err);
    } finally {
        loading.value = false;
    }
};
const goToPage = async (nextPage: number) => {
    if (loading.value || nextPage < 1 || nextPage > lastPage.value) return;
    page.value = nextPage;
    await load();
};
const saveRates = async () => {
    if (saving.value) return;
    saving.value = true;
    error.value = '';
    notice.value = '';
    try {
        const sorted = [...rates.value].sort((a, b) => a.min_tokens - b.min_tokens);
        const { data } = await api.put('/api/admin-api/captcha/rates', { rates: sorted });
        rates.value = data.rates;
        notice.value = 'Đã lưu cấu hình quy đổi.';
    } catch (err) {
        fail(err);
    } finally {
        saving.value = false;
    }
};
const openAdjust = (row: Row) => {
    adjusting.value = row;
    adjustment.value = { amount: 1, direction: 'credit', description: '', request_id: crypto.randomUUID() };
};
const adjust = async () => {
    if (!adjusting.value || saving.value) return;
    saving.value = true;
    error.value = '';
    try {
        await api.post('/api/admin-api/captcha/wallets/' + adjusting.value.user_id + '/adjust', adjustment.value);
        adjusting.value = null;
        notice.value = 'Đã điều chỉnh ví token.';
        await load();
    } catch (err) {
        fail(err);
    } finally {
        saving.value = false;
    }
};
const closeDetail = () => {
    detailRequest++;
    detail.value = null;
    if (imageUrl.value) URL.revokeObjectURL(imageUrl.value);
    imageUrl.value = '';
    detailLoading.value = false;
    imageLoading.value = false;
};
const show = async (row: Row) => {
    error.value = '';
    closeDetail();
    const request = detailRequest;
    detail.value = row;
    detailLoading.value = true;
    detailError.value = '';
    imageError.value = '';
    try {
        const { data } = await api.get('/api/admin-api/captcha/solves/' + row.id);
        if (request !== detailRequest) return;
        detail.value = data.data;
    } catch {
        if (request === detailRequest) detailError.value = 'Không tải được chi tiết. Đóng và mở lại để thử lại.';
    } finally {
        if (request === detailRequest) detailLoading.value = false;
    }
    if (request !== detailRequest) return;
    imageLoading.value = true;
    try {
        const image = await api.get('/api/admin-api/captcha/solves/' + row.id + '/image', { responseType: 'blob' });
        if (request !== detailRequest) return;
        imageUrl.value = URL.createObjectURL(image.data);
    } catch {
        if (request === detailRequest) imageError.value = 'Không tải được ảnh captcha. Ảnh có thể đã bị xóa hoặc không còn tồn tại.';
    } finally {
        if (request === detailRequest) imageLoading.value = false;
    }
};
watch([tab, () => props.section], () => {
    page.value = 1;
    void load();
});
onMounted(load);
onBeforeUnmount(closeDetail);
</script>

<template>
    <section class="grid gap-5 text-slate-900 dark:text-slate-100">
        <header class="rounded-[10px] border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
            <h1 class="text-2xl font-bold">{{ section === 'solves' ? 'Lịch sử giải captcha NRO' : 'Ví token_captcha' }}</h1>
            <p v-if="section === 'solves'" class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                Theo dõi kết quả, nguồn giải và thời gian xử lý. Mở chi tiết để xem ảnh và response.
            </p>
        </header>
        <p v-if="error" role="alert" class="rounded-lg bg-rose-50 p-3 text-rose-700">{{ error }}</p>
        <p v-if="notice" role="status" class="text-emerald-600">{{ notice }}</p>
        <div v-if="section === 'wallet'" class="flex flex-wrap gap-2" aria-label="Quản lý token captcha">
            <button
                v-for="item in [
                    { key: 'wallets', label: 'Quản lý ví' },
                    { key: 'transactions', label: 'Lịch sử ví' },
                    { key: 'rates', label: 'Cấu hình quy đổi' },
                ]"
                :key="item.key"
                type="button"
                class="rounded-lg border px-4 py-2"
                :class="tab === item.key ? 'bg-emerald-700 text-white' : ''"
                :aria-pressed="tab === item.key"
                @click="tab = item.key"
            >
                {{ item.label }}
            </button>
        </div>
        <form
            v-if="endpoint !== 'rates'"
            class="flex flex-wrap items-end gap-3 rounded-[10px] border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
            @submit.prevent="
                page = 1;
                load();
            "
        >
            <label class="grid gap-1 text-sm"
                >ID người dùng<input v-model="userId" type="number" min="1" class="rounded-lg border p-2 dark:bg-slate-800"
            /></label>
            <label v-if="section === 'solves'" class="grid gap-1 text-sm"
                >Trạng thái<select v-model="status" class="rounded-lg border p-2 dark:bg-slate-800">
                    <option value="">Tất cả</option>
                    <option value="success">Thành công</option>
                    <option value="failed">Thất bại</option>
                    <option value="processing">Đang xử lý</option>
                </select></label
            >
            <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-white" :disabled="loading">Lọc / làm mới</button>
        </form>
        <div v-if="section === 'solves'" class="grid gap-3">
            <p class="text-sm text-slate-500 dark:text-slate-400" aria-live="polite">
                Hiển thị {{ range.from }}–{{ range.to }} / {{ totalRows.toLocaleString('vi-VN') }} lượt giải
            </p>
            <DataTable
                :data="rows"
                :columns="solveColumns"
                :current-page="page"
                :total-pages="lastPage"
                :go-to-page="goToPage"
                :loading="loading"
                empty-text="Chưa có lượt giải phù hợp với bộ lọc."
            >
                <template #id="{ row }"
                    ><span class="font-semibold">#{{ row.id }}</span></template
                >
                <template #user_id="{ row }"
                    ><span class="whitespace-nowrap">#{{ row.user_id }}</span></template
                >
                <template #status="{ row }"
                    ><span
                        class="inline-flex whitespace-nowrap rounded-[10px] px-2.5 py-1 text-xs font-semibold"
                        :class="solveStatus[row.status]?.class"
                        >{{ solveStatus[row.status]?.label || row.status }}</span
                    ></template
                >
                <template #result="{ row }"
                    ><code class="font-mono text-base font-bold tracking-wider">{{ row.result || '—' }}</code></template
                >
                <template #provider_name="{ row }"
                    ><span class="whitespace-nowrap">{{ row.provider_name || '—' }}</span></template
                >
                <template #source="{ row }">{{ row.source === 'api' ? 'API' : 'Demo' }}</template>
                <template #duration_ms="{ row }"
                    ><span class="whitespace-nowrap tabular-nums">{{
                        row.duration_ms == null ? '—' : (row.duration_ms / 1000).toFixed(3) + ' s'
                    }}</span></template
                >
                <template #created_at="{ row }"
                    ><span class="whitespace-nowrap text-xs">{{
                        row.created_at ? new Date(row.created_at).toLocaleString('vi-VN') : '—'
                    }}</span></template
                >
                <template #actions="{ row }"
                    ><button
                        type="button"
                        class="whitespace-nowrap rounded-[10px] border border-slate-300 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50"
                        @click="show(row)"
                    >
                        Ảnh / response
                    </button></template
                >
            </DataTable>
        </div>
        <p v-else-if="loading">Đang tải…</p>
        <form v-else-if="endpoint === 'rates'" class="grid gap-4 rounded-xl border p-5" @submit.prevent="saveRates">
            <p class="text-sm">Bậc đầu tiên phải bắt đầu từ 1 token. Khi đạt ngưỡng, đơn giá áp dụng cho toàn bộ số token mua.</p>
            <div v-for="(rate, index) in rates" :key="index" class="flex flex-wrap items-end gap-3">
                <label class="grid gap-1 text-sm"
                    >Từ số token<input
                        v-model.number="rate.min_tokens"
                        type="number"
                        min="1"
                        max="10000000"
                        required
                        class="rounded-lg border p-2 dark:bg-slate-800"
                /></label>
                <label class="grid gap-1 text-sm"
                    >Đồng / token<input
                        v-model.number="rate.unit_price"
                        type="number"
                        min="1"
                        max="1000000"
                        required
                        class="rounded-lg border p-2 dark:bg-slate-800"
                /></label>
                <button v-if="index > 0" type="button" class="rounded-lg border p-2 text-rose-600" @click="rates.splice(index, 1)">Xóa bậc</button>
            </div>
            <div class="flex gap-3">
                <button type="button" class="rounded-lg border px-4 py-2" @click="rates.push({ min_tokens: 1000, unit_price: 3 })">
                    Thêm bậc giá</button
                ><button type="submit" :disabled="saving" class="rounded-lg bg-emerald-700 px-4 py-2 text-white disabled:opacity-50">
                    Lưu quy đổi
                </button>
            </div>
        </form>
        <div v-else class="overflow-x-auto rounded-xl border">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-100 dark:bg-slate-800">
                    <tr>
                        <th class="p-3">Người dùng</th>
                        <template v-if="endpoint === 'wallets'"
                            ><th class="p-3">Số dư token</th>
                            <th class="p-3">Đang giữ</th>
                            <th class="p-3">Thao tác</th></template
                        ><template v-else-if="endpoint === 'transactions'"
                            ><th class="p-3">Giao dịch</th>
                            <th class="p-3">Số dư trước → sau</th>
                            <th class="p-3">Chi phí / người thao tác</th></template
                        ><template v-else
                            ><th class="p-3">Trạng thái / kết quả</th>
                            <th class="p-3">Nguồn / thời gian</th>
                            <th class="p-3">Chi tiết</th></template
                        >
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.id || row.user_id" class="border-t">
                        <td class="p-3">#{{ row.user_id }} {{ row.username }}</td>
                        <template v-if="endpoint === 'wallets'"
                            ><td class="p-3">{{ row.balance || 0 }}</td>
                            <td class="p-3">{{ row.reserved || 0 }}</td>
                            <td class="p-3">
                                <button type="button" class="rounded-lg border px-3 py-2" @click="openAdjust(row)">Cộng / trừ token</button>
                            </td></template
                        ><template v-else-if="endpoint === 'transactions'"
                            ><td class="p-3">
                                #{{ row.id }} · {{ row.direction === 'credit' ? '+' : '-' }}{{ row.amount }}
                                <p>{{ row.description }}</p>
                            </td>
                            <td class="p-3">{{ row.balance_before }} → {{ row.balance_after }}</td>
                            <td class="p-3">
                                {{ row.cost }}đ · {{ row.actor_id || 'Hệ thống' }}
                                <p>{{ row.created_at ? new Date(row.created_at).toLocaleString('vi-VN') : '' }}</p>
                            </td></template
                        ><template v-else
                            ><td class="p-3">
                                #{{ row.id }} · {{ row.status }}
                                <p>{{ row.result }}</p>
                            </td>
                            <td class="p-3">
                                {{ row.source }}
                                <p>{{ row.provider_name || '—' }} · {{ row.duration_ms ?? 0 }} ms</p>
                                <p>{{ row.created_at ? new Date(row.created_at).toLocaleString('vi-VN') : '' }}</p>
                            </td>
                            <td class="p-3">
                                <button type="button" class="rounded-lg border px-3 py-2" @click="show(row)">Ảnh / response</button>
                            </td></template
                        >
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="4" class="p-4">Chưa có dữ liệu.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="section === 'wallet' && endpoint !== 'rates'" class="flex items-center gap-3">
            <button
                type="button"
                :disabled="page <= 1 || loading"
                class="rounded-lg border px-3 py-2 disabled:opacity-40"
                @click="
                    page--;
                    load();
                "
            >
                Trước</button
            ><span>{{ page }} / {{ lastPage }}</span
            ><button
                type="button"
                :disabled="page >= lastPage || loading"
                class="rounded-lg border px-3 py-2 disabled:opacity-40"
                @click="
                    page++;
                    load();
                "
            >
                Sau
            </button>
        </div>
        <Dialog :open="!!adjusting" class="relative z-50" @close="adjusting = null"
            ><div class="fixed inset-0 bg-black/50" aria-hidden="true"></div>
            <div class="fixed inset-0 overflow-y-auto p-5">
                <div class="flex min-h-full items-center justify-center">
                    <DialogPanel class="w-full max-w-md rounded-xl bg-white p-6 text-slate-900 dark:bg-slate-900 dark:text-white"
                        ><DialogTitle class="text-xl font-bold">Điều chỉnh token · #{{ adjusting?.user_id }}</DialogTitle>
                        <form class="mt-4 grid gap-4" @submit.prevent="adjust">
                            <label class="grid gap-1"
                                >Loại<select v-model="adjustment.direction" class="rounded-lg border p-2 dark:bg-slate-800">
                                    <option value="credit">Cộng</option>
                                    <option value="debit">Trừ</option>
                                </select></label
                            ><label class="grid gap-1"
                                >Số token<input
                                    v-model.number="adjustment.amount"
                                    type="number"
                                    min="1"
                                    max="100000000000"
                                    required
                                    class="rounded-lg border p-2 dark:bg-slate-800" /></label
                            ><label class="grid gap-1"
                                >Lý do<input
                                    v-model="adjustment.description"
                                    maxlength="500"
                                    required
                                    class="rounded-lg border p-2 dark:bg-slate-800"
                            /></label>
                            <p v-if="error" role="alert" class="text-rose-600">{{ error }}</p>
                            <div class="flex gap-3">
                                <button type="submit" :disabled="saving" class="rounded-lg bg-emerald-700 px-4 py-2 text-white">Lưu</button
                                ><button type="button" class="rounded-lg border px-4 py-2" @click="adjusting = null">Đóng</button>
                            </div>
                        </form></DialogPanel
                    >
                </div>
            </div></Dialog
        >
        <Dialog :open="!!detail" class="relative z-50" @close="closeDetail"
            ><div class="fixed inset-0 bg-black/50" aria-hidden="true"></div>
            <div class="fixed inset-0 overflow-y-auto p-5">
                <div class="flex min-h-full items-center justify-center">
                    <DialogPanel
                        class="grid w-full max-w-4xl gap-4 rounded-[10px] bg-white p-5 text-slate-900 dark:bg-slate-900 dark:text-white sm:p-6"
                        ><DialogTitle class="text-xl font-bold">Lượt giải #{{ detail?.id }}</DialogTitle>
                        <div class="grid gap-3 rounded-[10px] bg-slate-50 p-4 text-sm dark:bg-slate-800 sm:grid-cols-2">
                            <p>
                                Người dùng: <strong>#{{ detail?.user_id }}</strong>
                            </p>
                            <p>
                                Trạng thái: <strong>{{ solveStatus[detail?.status || '']?.label || detail?.status }}</strong> · HTTP
                                {{ detail?.http_status || '—' }}
                            </p>
                            <p>
                                Nguồn: <strong>{{ detail?.provider_name || '—' }}</strong>
                            </p>
                            <p>
                                Kết quả: <code class="font-bold">{{ detail?.result || '—' }}</code> · {{ detail?.duration_ms ?? 0 }} ms
                            </p>
                        </div>
                        <p v-if="detailLoading" role="status" class="text-sm text-slate-500">Đang tải chi tiết…</p>
                        <p v-if="detailError" role="alert" class="text-sm text-rose-600">{{ detailError }}</p>
                        <p v-if="imageLoading" role="status" class="rounded-[10px] border p-5 text-center text-sm text-slate-500">
                            Đang tải ảnh captcha…
                        </p>
                        <p
                            v-if="imageError"
                            role="alert"
                            class="rounded-[10px] bg-amber-50 p-4 text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-200"
                        >
                            {{ imageError }}
                        </p>
                        <CaptchaImagePreview v-if="imageUrl" :src="imageUrl" :solve-id="detail?.id" />
                        <div v-for="attempt in detail?.provider_attempts || []" :key="attempt.id" class="grid gap-2 rounded-[10px] border p-3">
                            <p class="font-semibold">{{ attempt.provider_name }} · {{ attempt.status }}</p>
                            <p class="text-sm">{{ attempt.driver }} · HTTP {{ attempt.http_status || 'Không có' }} · {{ attempt.duration_ms }} ms</p>
                            <pre class="max-h-72 overflow-auto rounded-[10px] bg-slate-100 p-3 text-xs dark:bg-slate-800">{{
                                JSON.stringify(attempt.response, null, 2)
                            }}</pre>
                        </div>
                        <pre
                            v-if="!detail?.provider_attempts?.length"
                            class="max-h-96 overflow-auto rounded-lg bg-slate-100 p-3 text-xs dark:bg-slate-800"
                            >{{ JSON.stringify(detail?.response, null, 2) }}</pre
                        >
                        <button type="button" class="justify-self-start rounded-lg border px-4 py-2" @click="closeDetail">Đóng</button></DialogPanel
                    >
                </div>
            </div></Dialog
        >
    </section>
</template>
