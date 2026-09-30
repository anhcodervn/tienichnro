<script setup lang="ts">
import { clientAffiliateService, type CollaboratorWalletHistoryData } from '@/services/client-affiliate.service';
import { handleErrorResponse } from '@/utils/response';
import { CircleDollarSign, Clock3, History, LoaderCircle, WalletCards } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

const data = ref<CollaboratorWalletHistoryData | null>(null);
const loading = ref(true);
const money = (value: number): string => `${new Intl.NumberFormat('vi-VN').format(value)}đ`;
const dateTime = (value: string): string => new Date(value).toLocaleString('vi-VN');
const eventLabel = (event: string | null, type: string): string => {
    const labels: Record<string, string> = {
        game_service_order_claimed: 'Tiền công tạm giữ',
        game_service_order_released: 'Tiền công được phép rút',
        game_service_order_refunded: 'Thu hồi do hoàn tiền',
    };

    return (event && labels[event]) || ({ hold: 'Tạm giữ', release: 'Hoàn tạm giữ', credit: 'Cộng tiền', debit: 'Trừ tiền' }[type] ?? type);
};
const amountClass = (event: string | null, type: string): string =>
    event === 'game_service_order_refunded' || type === 'debit' ? 'text-rose-600' : type === 'credit' ? 'text-emerald-600' : 'text-amber-700';

const load = async (page = 1): Promise<void> => {
    loading.value = true;
    try {
        data.value = await clientAffiliateService.collaboratorWalletHistory(page);
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

onMounted(load);
</script>

<template>
    <main class="grid gap-5 p-4 sm:p-6">
        <div v-if="loading" class="grid min-h-72 place-items-center rounded-xl border border-slate-200 bg-white">
            <LoaderCircle class="size-9 animate-spin text-emerald-600" />
        </div>
        <template v-else-if="data">
            <section class="grid gap-4 sm:grid-cols-3">
                <article class="rounded-xl border border-emerald-200 bg-emerald-50 p-5">
                    <WalletCards class="size-6 text-emerald-700" />
                    <p class="mt-3 text-sm font-bold text-emerald-800">Có thể rút</p>
                    <p class="text-2xl font-black text-emerald-950">{{ money(data.wallet.balance) }}</p>
                </article>
                <article class="rounded-xl border border-amber-200 bg-amber-50 p-5">
                    <Clock3 class="size-6 text-amber-700" />
                    <p class="mt-3 text-sm font-bold text-amber-800">Tiền công đang treo</p>
                    <p class="text-2xl font-black text-amber-950">{{ money(data.wallet.work_hold_balance) }}</p>
                </article>
                <article class="rounded-xl border border-sky-200 bg-sky-50 p-5">
                    <CircleDollarSign class="size-6 text-sky-700" />
                    <p class="mt-3 text-sm font-bold text-sky-800">Đang giữ cho lệnh rút</p>
                    <p class="text-2xl font-black text-sky-950">{{ money(data.wallet.hold_balance) }}</p>
                </article>
            </section>

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <header class="flex items-center gap-3 border-b border-slate-200 p-5">
                    <span class="grid size-10 place-items-center rounded-lg bg-emerald-100 text-emerald-700"><History class="size-5" /></span>
                    <div>
                        <h1 class="font-black text-slate-950">Lịch sử ví công việc</h1>
                        <p class="text-sm text-slate-500">Ghi rõ từng lần giữ, giải ngân hoặc thu hồi tiền theo mã đơn.</p>
                    </div>
                </header>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead class="bg-slate-50 text-left text-slate-500">
                            <tr>
                                <th class="px-5 py-3">Thời gian</th>
                                <th class="px-5 py-3">Nội dung</th>
                                <th class="px-5 py-3">Mã đơn</th>
                                <th class="px-5 py-3 text-right">Số tiền</th>
                                <th class="px-5 py-3 text-right">Số dư rút được</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="transaction in data.data" :key="transaction.id">
                                <td class="whitespace-nowrap px-5 py-4 text-slate-500">{{ dateTime(transaction.created_at) }}</td>
                                <td class="px-5 py-4">
                                    <p class="font-bold text-slate-900">{{ eventLabel(transaction.event, transaction.type) }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ transaction.description }}</p>
                                </td>
                                <td class="px-5 py-4 font-mono font-bold text-emerald-700">{{ transaction.order_code || '—' }}</td>
                                <td class="px-5 py-4 text-right font-black" :class="amountClass(transaction.event, transaction.type)">
                                    {{ money(transaction.amount) }}
                                </td>
                                <td class="px-5 py-4 text-right font-bold">{{ money(transaction.balance_after) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="data.data.length === 0" class="p-10 text-center text-slate-500">Ví chưa có giao dịch nào.</p>
                </div>
                <footer v-if="data.meta.last_page > 1" class="flex items-center justify-between border-t border-slate-200 p-4">
                    <button
                        type="button"
                        :disabled="loading || data.meta.current_page <= 1"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold disabled:opacity-40"
                        @click="load(data.meta.current_page - 1)"
                    >
                        Trang trước
                    </button>
                    <span class="text-sm font-bold text-slate-600">Trang {{ data.meta.current_page }}/{{ data.meta.last_page }}</span>
                    <button
                        type="button"
                        :disabled="loading || data.meta.current_page >= data.meta.last_page"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold disabled:opacity-40"
                        @click="load(data.meta.current_page + 1)"
                    >
                        Trang sau
                    </button>
                </footer>
            </section>
        </template>
    </main>
</template>
