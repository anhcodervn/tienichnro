<script setup lang="ts">
import api from '@/config/axios';
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/vue';
import { isAxiosError } from 'axios';
import { computed, onMounted, ref, watch } from 'vue';

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
};
const tab = ref('wallets');
const rows = ref<Row[]>([]);
const rates = ref<Rate[]>([]);
const page = ref(1);
const lastPage = ref(1);
const userId = ref('');
const status = ref('');
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const notice = ref('');
const detail = ref<Row | null>(null);
const imageUrl = ref('');
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
        }
    } catch (err) {
        fail(err);
    } finally {
        loading.value = false;
    }
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
    detail.value = null;
    if (imageUrl.value) URL.revokeObjectURL(imageUrl.value);
    imageUrl.value = '';
};
const show = async (row: Row) => {
    error.value = '';
    closeDetail();
    try {
        const { data } = await api.get('/api/admin-api/captcha/solves/' + row.id);
        detail.value = data.data;
        const image = await api.get('/api/admin-api/captcha/solves/' + row.id + '/image', { responseType: 'blob' });
        imageUrl.value = URL.createObjectURL(image.data);
    } catch (err) {
        fail(err);
    }
};
watch([tab, () => props.section], () => {
    page.value = 1;
    void load();
});
onMounted(load);
</script>

<template>
    <section class="grid gap-5 text-slate-900 dark:text-slate-100">
        <h1 class="text-2xl font-bold">{{ section === 'solves' ? 'Lịch sử giải captcha NRO' : 'Ví token_captcha' }}</h1>
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
            class="flex flex-wrap items-end gap-3"
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
        <p v-if="loading">Đang tải…</p>
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
        <div v-if="endpoint !== 'rates'" class="flex items-center gap-3">
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
                    <DialogPanel class="grid w-full max-w-3xl gap-4 rounded-xl bg-white p-6 text-slate-900 dark:bg-slate-900 dark:text-white"
                        ><DialogTitle class="text-xl font-bold">Lượt giải #{{ detail?.id }}</DialogTitle>
                        <p>HTTP: {{ detail?.http_status || 'Không có' }} · {{ detail?.status }}</p>
                        <img v-if="imageUrl" :src="imageUrl" alt="Ảnh captcha lưu trong lịch sử" class="max-h-64 max-w-full rounded-lg border" />
                        <pre class="max-h-96 overflow-auto rounded-lg bg-slate-100 p-3 text-xs dark:bg-slate-800">{{
                            JSON.stringify(detail?.response, null, 2)
                        }}</pre>
                        <button type="button" class="justify-self-start rounded-lg border px-4 py-2" @click="closeDetail">Đóng</button></DialogPanel
                    >
                </div>
            </div></Dialog
        >
    </section>
</template>
