<script setup lang="ts">
import DataTable from '@/components/shared/DataTable/index.vue';
import api from '@/config/axios';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/vue';
import { isAxiosError } from 'axios';
import Swal from 'sweetalert2';
import { computed, onMounted, reactive, ref, watch } from 'vue';

type WalletRow = { id: number; user_id: number; username: string; full_name: string | null; balance: number };
type Transaction = {
    id: number;
    direction: string;
    amount: number;
    description: string;
    balance_before: number;
    balance_after: number;
    created_at: string;
    actor_id: number | null;
};
const rows = ref<WalletRow[]>([]);
const loading = ref(true);
const saving = ref(false);
const failed = ref(false);
const showAdjust = ref(false);
const showHistory = ref(false);
const historyLoading = ref(false);
const history = ref<Transaction[]>([]);
const historyUser = ref(0);
const historyPage = ref(1);
const historyLastPage = ref(1);
const errors = ref<string[]>([]);
const query = ref('');
const draft = reactive({ user_id: 0, direction: 'credit', amount: 0, description: '', request_id: '' });
const inputClass =
    'min-h-11 w-full rounded-[10px] border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white';
const money = (value: number) => `${value.toLocaleString('vi-VN')} đ`;
const filtered = computed(() =>
    rows.value.filter((row) =>
        `${row.user_id} ${row.username} ${row.full_name || ''}`.toLocaleLowerCase('vi').includes(query.value.toLocaleLowerCase('vi')),
    ),
);
const currentPage = ref(1);
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / 10)));
const paginated = computed(() => filtered.value.slice((currentPage.value - 1) * 10, currentPage.value * 10));
const goToPage = async (page: number) => {
    currentPage.value = page;
};
watch(query, () => {
    currentPage.value = 1;
});
const columns = [
    { accessorKey: 'user_id', header: 'User ID' },
    { accessorKey: 'username', header: 'Tài khoản' },
    { accessorKey: 'balance', header: 'Số dư' },
    { id: 'actions', header: 'Thao tác' },
];
const load = async () => {
    loading.value = true;
    failed.value = false;
    try {
        rows.value = (await api.get('/api/admin-api/wallets')).data.data;
    } catch (error) {
        failed.value = true;
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};
const adjust = (userId = 0) => {
    Object.assign(draft, { user_id: userId, direction: 'credit', amount: 0, description: '', request_id: crypto.randomUUID() });
    errors.value = [];
    showAdjust.value = true;
};
const closeAdjust = () => {
    if (!saving.value) showAdjust.value = false;
};
const save = async () => {
    if (saving.value) return;
    saving.value = true;
    errors.value = [];
    try {
        const confirmation = await Swal.fire({
            title: 'Xác nhận điều chỉnh ví',
            text: `${draft.direction === 'credit' ? 'Cộng' : 'Trừ'} ${money(draft.amount)} cho User ID ${draft.user_id}.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Xác nhận',
            cancelButtonText: 'Huỷ',
        });
        if (!confirmation.isConfirmed) return;
        await api.post(`/api/admin-api/wallets/${draft.user_id}/adjust`, draft);
        showAdjust.value = false;
        handleSuccessResponse({ data: { status: true, message: 'Đã ghi giao dịch ví.' } });
        await load();
    } catch (error) {
        if (isAxiosError<{ errors?: Record<string, string[]>; message?: string }>(error))
            errors.value = error.response?.data.errors
                ? Object.values(error.response.data.errors).flat()
                : [error.response?.data.message || 'Không điều chỉnh được ví.'];
        else handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
};
const historyLoad = async (page: number) => {
    historyLoading.value = true;
    const userId = historyUser.value;
    try {
        const { data } = await api.get(`/api/admin-api/wallets/${userId}/transactions`, { params: { page } });
        if (userId !== historyUser.value) return;
        history.value = data.data;
        historyPage.value = data.current_page;
        historyLastPage.value = data.last_page;
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        if (userId === historyUser.value) historyLoading.value = false;
    }
};
const viewHistory = async (userId: number) => {
    historyUser.value = userId;
    history.value = [];
    showHistory.value = true;
    await historyLoad(1);
};
onMounted(load);
</script>
<template>
    <section class="grid gap-5">
        <header
            class="flex flex-wrap items-center justify-between gap-3 rounded-[10px] border border-slate-300 bg-white p-5 dark:border-slate-600 dark:bg-slate-900"
        >
            <h1 class="text-xl font-bold dark:text-white">Ví & dòng tiền</h1>
            <button
                type="button"
                :disabled="saving"
                class="min-h-11 rounded-[10px] bg-emerald-700 px-4 text-sm font-semibold text-white"
                @click="adjust()"
            >
                Cộng / trừ tiền
            </button>
        </header>
        <label class="grid gap-2 text-sm dark:text-white">Tìm tài khoản<input v-model="query" type="search" :class="inputClass" /></label>
        <button v-if="failed" type="button" class="min-h-11 text-rose-700" @click="load">Tải thất bại. Thử lại.</button>
        <DataTable
            :data="paginated"
            :current-page="currentPage"
            :total-pages="totalPages"
            :go-to-page="goToPage"
            :columns="columns"
            :loading="loading"
            empty-text="Chưa có ví phù hợp."
            ><template #balance="{ row }">{{ money(row.balance) }}</template
            ><template #actions="{ row }"
                ><div class="flex gap-2">
                    <button
                        type="button"
                        :disabled="saving"
                        class="min-h-10 rounded-[10px] border border-slate-300 px-3 text-sm text-emerald-700"
                        @click="adjust(row.user_id)"
                    >
                        Điều chỉnh</button
                    ><button
                        type="button"
                        class="min-h-10 rounded-[10px] border border-slate-300 px-3 text-sm dark:text-white"
                        @click="viewHistory(row.user_id)"
                    >
                        Dòng tiền
                    </button>
                </div></template
            ></DataTable
        >
        <Dialog :open="showAdjust" class="relative z-50" @close="closeAdjust"
            ><div class="fixed inset-0 bg-slate-950/60"></div>
            <div class="fixed inset-0 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <DialogPanel class="w-full max-w-lg rounded-[10px] bg-white p-5 dark:bg-slate-900"
                        ><DialogTitle class="mb-4 text-xl font-bold dark:text-white">Điều chỉnh số dư</DialogTitle>
                        <ul v-if="errors.length" role="alert" class="mb-4 grid gap-1 text-sm text-rose-700">
                            <li v-for="message in errors" :key="message">{{ message }}</li>
                        </ul>
                        <form @submit.prevent="save">
                            <fieldset :disabled="saving" class="grid gap-4">
                                <label class="grid gap-2 text-sm dark:text-white"
                                    >User ID<input
                                        v-model.number="draft.user_id"
                                        type="number"
                                        min="1"
                                        step="1"
                                        required
                                        :class="inputClass" /></label
                                ><label class="grid gap-2 text-sm dark:text-white"
                                    >Loại giao dịch<select v-model="draft.direction" :class="inputClass">
                                        <option value="credit">Cộng tiền</option>
                                        <option value="debit">Trừ tiền</option>
                                    </select></label
                                ><label class="grid gap-2 text-sm dark:text-white"
                                    >Số tiền VNĐ<input
                                        v-model.number="draft.amount"
                                        type="number"
                                        min="1"
                                        max="100000000000"
                                        step="1"
                                        required
                                        :class="inputClass" /></label
                                ><label class="grid gap-2 text-sm dark:text-white"
                                    >Lý do<textarea v-model="draft.description" required maxlength="500" :class="inputClass"></textarea>
                                </label>
                                <div class="flex gap-3">
                                    <button type="submit" class="min-h-11 rounded-[10px] bg-emerald-700 px-4 text-white">
                                        {{ saving ? 'Đang ghi…' : 'Ghi giao dịch' }}</button
                                    ><button
                                        type="button"
                                        class="min-h-11 rounded-[10px] border border-slate-300 px-4 dark:text-white"
                                        @click="closeAdjust"
                                    >
                                        Đóng
                                    </button>
                                </div>
                            </fieldset>
                        </form></DialogPanel
                    >
                </div>
            </div></Dialog
        >
        <Dialog :open="showHistory" class="relative z-50" @close="showHistory = false"
            ><div class="fixed inset-0 bg-slate-950/60"></div>
            <div class="fixed inset-0 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <DialogPanel class="w-full max-w-3xl rounded-[10px] bg-white p-5 dark:bg-slate-900"
                        ><div class="mb-4 flex justify-between gap-3">
                            <DialogTitle class="text-xl font-bold dark:text-white">Dòng tiền User ID {{ historyUser }}</DialogTitle
                            ><button
                                type="button"
                                class="min-h-10 rounded-[10px] border border-slate-300 px-3 dark:text-white"
                                @click="showHistory = false"
                            >
                                Đóng
                            </button>
                        </div>
                        <p v-if="historyLoading" class="dark:text-white">Đang tải…</p>
                        <div v-else class="grid gap-3">
                            <article
                                v-for="item in history"
                                :key="item.id"
                                class="rounded-[10px] border border-slate-200 p-3 text-sm dark:text-white"
                            >
                                <strong>{{ item.direction === 'credit' ? '+' : '-' }}{{ money(item.amount) }}</strong>
                                <p class="mt-1">{{ item.description }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ new Date(item.created_at).toLocaleString('vi-VN') }}</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ money(item.balance_before) }} → {{ money(item.balance_after) }} · Người thao tác:
                                    {{ item.actor_id ?? 'Hệ thống' }}
                                </p>
                            </article>
                            <p v-if="!history.length" class="text-sm dark:text-white">Chưa có giao dịch.</p>
                            <div class="flex items-center gap-3">
                                <button
                                    type="button"
                                    :disabled="historyPage <= 1"
                                    class="min-h-10 rounded-[10px] border border-slate-300 px-3 disabled:opacity-40 dark:text-white"
                                    @click="historyLoad(historyPage - 1)"
                                >
                                    Trước</button
                                ><span class="text-sm dark:text-white">{{ historyPage }} / {{ historyLastPage }}</span
                                ><button
                                    type="button"
                                    :disabled="historyPage >= historyLastPage"
                                    class="min-h-10 rounded-[10px] border border-slate-300 px-3 disabled:opacity-40 dark:text-white"
                                    @click="historyLoad(historyPage + 1)"
                                >
                                    Sau
                                </button>
                            </div>
                        </div></DialogPanel
                    >
                </div>
            </div></Dialog
        >
    </section>
</template>
