<script setup lang="ts">
import { clientAffiliateService, type CollaboratorFinanceData } from '@/services/client-affiliate.service';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { Banknote, Clock3, LoaderCircle, ShieldAlert, WalletCards } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';

const data = ref<CollaboratorFinanceData | null>(null);
const loading = ref(true);
const submitting = ref(false);
const withdrawalAmount = ref<number | null>(null);
const payout = reactive({ bank_name: '', bank_account_name: '', bank_account_number: '' });
const isSuspended = computed(() => data.value?.profile.status === 'suspended');
const money = (value: number): string => `${new Intl.NumberFormat('vi-VN').format(value)}đ`;
const dateTime = (value: string): string => new Date(value).toLocaleString('vi-VN');
const statusLabels: Record<string, string> = {
    requested: 'Chờ duyệt',
    approved: 'Đã duyệt',
    paid: 'Đã trả',
    rejected: 'Từ chối',
    cancelled: 'Đã hủy',
};

const load = async (): Promise<void> => {
    loading.value = true;
    try {
        data.value = await clientAffiliateService.collaboratorFinance();
        payout.bank_name = data.value.profile.bank_name ?? '';
        payout.bank_account_name = data.value.profile.bank_account_name ?? '';
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};
const savePayout = async (): Promise<void> => {
    submitting.value = true;
    try {
        const response = await clientAffiliateService.updateCollaboratorPayout(payout);
        handleSuccessResponse(response, 'Đã lưu tài khoản nhận tiền.');
        payout.bank_account_number = '';
        await load();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        submitting.value = false;
    }
};
const withdraw = async (): Promise<void> => {
    if (!withdrawalAmount.value) return;
    submitting.value = true;
    try {
        const response = await clientAffiliateService.withdrawCollaborator(withdrawalAmount.value, crypto.randomUUID());
        handleSuccessResponse(response, 'Yêu cầu rút tiền đã được gửi cho admin.');
        withdrawalAmount.value = null;
        await load();
        window.dispatchEvent(new Event('collaborator:refresh'));
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        submitting.value = false;
    }
};

onMounted(load);
</script>

<template>
    <main class="grid gap-5 p-4 sm:p-6">
        <div v-if="loading" class="grid min-h-72 place-items-center rounded-2xl border border-slate-200 bg-white">
            <LoaderCircle class="size-9 animate-spin text-emerald-600" />
        </div>
        <template v-else-if="data">
            <section v-if="isSuspended" class="flex gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-rose-900">
                <ShieldAlert class="size-5 shrink-0" />
                <div>
                    <p class="font-black">Tài khoản đang tạm khóa</p>
                    <p class="text-sm">Bạn chưa thể tạo yêu cầu rút tiền mới.</p>
                </div>
            </section>
            <section class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_360px]">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <header class="mb-5 flex items-center gap-3">
                        <span class="grid size-11 place-items-center rounded-xl bg-emerald-100 text-emerald-700"><Banknote class="size-5" /></span>
                        <div>
                            <h1 class="font-black">Rút tiền về tài khoản</h1>
                            <p class="text-sm text-slate-500">Yêu cầu sẽ được admin kiểm tra và xử lý.</p>
                        </div>
                    </header>
                    <form class="grid gap-4" @submit.prevent="withdraw">
                        <label class="grid gap-2 text-sm font-bold"
                            >Số tiền muốn rút<input
                                v-model.number="withdrawalAmount"
                                type="number"
                                :min="data.minimum_withdrawal"
                                :max="data.wallet.balance"
                                step="1000"
                                required
                                class="min-h-12 rounded-xl border-2 border-slate-200 px-4 outline-none focus:border-emerald-500"
                                placeholder="Nhập số tiền"
                        /></label>
                        <div class="grid gap-2 rounded-xl bg-slate-50 p-4 text-sm">
                            <p class="flex justify-between gap-4">
                                <span>Số dư khả dụng</span><strong>{{ money(data.wallet.balance) }}</strong>
                            </p>
                            <p class="flex justify-between gap-4">
                                <span>Mức rút tối thiểu</span><strong>{{ money(data.minimum_withdrawal) }}</strong>
                            </p>
                            <p class="flex justify-between gap-4">
                                <span>Tài khoản nhận</span><strong>{{ data.profile.bank_account_number_masked || 'Chưa thiết lập' }}</strong>
                            </p>
                        </div>
                        <button
                            :disabled="submitting || isSuspended || !data.profile.has_payout_account"
                            class="min-h-12 rounded-xl bg-emerald-600 px-5 font-black text-white disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Gửi yêu cầu rút tiền
                        </button>
                        <p v-if="!data.profile.has_payout_account" class="text-sm font-bold text-amber-700">
                            Hãy lưu tài khoản nhận tiền trước khi gửi yêu cầu.
                        </p>
                    </form>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <header class="mb-5 flex items-center gap-3">
                        <WalletCards class="size-6 text-slate-700" />
                        <div>
                            <h2 class="font-black">Tài khoản nhận tiền</h2>
                            <p class="text-xs text-slate-500">Hiện tại: {{ data.profile.bank_account_number_masked || 'chưa có' }}</p>
                        </div>
                    </header>
                    <form class="grid gap-4" @submit.prevent="savePayout">
                        <label class="grid gap-2 text-sm font-bold"
                            >Ngân hàng<input
                                v-model.trim="payout.bank_name"
                                required
                                maxlength="120"
                                class="min-h-11 rounded-xl border-2 border-slate-200 px-3"
                        /></label>
                        <label class="grid gap-2 text-sm font-bold"
                            >Tên chủ tài khoản<input
                                v-model.trim="payout.bank_account_name"
                                required
                                maxlength="150"
                                class="min-h-11 rounded-xl border-2 border-slate-200 px-3 uppercase"
                        /></label>
                        <label class="grid gap-2 text-sm font-bold"
                            >Số tài khoản mới<input
                                v-model.trim="payout.bank_account_number"
                                required
                                maxlength="50"
                                class="min-h-11 rounded-xl border-2 border-slate-200 px-3"
                        /></label>
                        <button
                            :disabled="submitting || isSuspended"
                            class="min-h-11 rounded-xl bg-slate-950 px-5 font-bold text-white disabled:opacity-50"
                        >
                            Lưu tài khoản
                        </button>
                    </form>
                </article>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <header class="flex items-center gap-3 border-b border-slate-200 p-5">
                    <Clock3 class="size-5 text-slate-600" />
                    <div>
                        <h2 class="font-black">Lịch sử rút tiền</h2>
                        <p class="text-xs text-slate-500">Theo dõi trạng thái các yêu cầu đã gửi.</p>
                    </div>
                </header>
                <div class="divide-y divide-slate-100">
                    <div
                        v-for="withdrawal in data.withdrawals"
                        :key="withdrawal.id"
                        class="flex flex-col justify-between gap-3 p-5 sm:flex-row sm:items-center"
                    >
                        <div>
                            <p class="text-lg font-black">{{ money(withdrawal.amount) }}</p>
                            <p class="text-sm text-slate-500">
                                {{ withdrawal.bank_name }} · {{ withdrawal.account_number }} · {{ dateTime(withdrawal.created_at) }}
                            </p>
                        </div>
                        <span class="w-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-bold">{{
                            statusLabels[withdrawal.status] ?? withdrawal.status
                        }}</span>
                    </div>
                    <p v-if="data.withdrawals.length === 0" class="p-10 text-center text-slate-500">Chưa có yêu cầu rút tiền.</p>
                </div>
            </section>
        </template>
    </main>
</template>
