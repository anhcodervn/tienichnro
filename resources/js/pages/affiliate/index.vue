<script setup lang="ts">
import { clientAffiliateService, type ClientAffiliateData } from '@/services/client-affiliate.service';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { Banknote, Check, Clock3, Copy, HandCoins, LoaderCircle, RefreshCw, ShieldCheck, Users, WalletCards } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';

type Tab = 'commissions' | 'referrals' | 'withdrawals';

const data = ref<ClientAffiliateData | null>(null);
const loading = ref(true);
const submitting = ref(false);
const copied = ref(false);
const activeTab = ref<Tab>('commissions');
const conversionAmount = ref<number | null>(null);
const withdrawalAmount = ref<number | null>(null);
const payout = reactive({ bank_name: '', bank_account_name: '', bank_account_number: '' });
const isSuspended = computed(() => data.value?.profile.status === 'suspended');
const money = (value: number | string | null | undefined): string => `${new Intl.NumberFormat('vi-VN').format(Number(value ?? 0))}đ`;
const dateTime = (value: string | null | undefined): string => (value ? new Date(value).toLocaleString('vi-VN') : '—');
const commissionStatusLabel = (status: string, availableAt: string | null): string => {
    if (status === 'pending') return availableAt ? 'Đang giữ 7 ngày' : 'Chờ đơn hoàn tất';

    return status === 'available' ? 'Đã duyệt' : status === 'reversed' ? 'Đã thu hồi' : status;
};
const withdrawalStatusLabels: Record<string, string> = {
    requested: 'Chờ duyệt',
    approved: 'Đã duyệt',
    paid: 'Đã trả',
    rejected: 'Từ chối',
    cancelled: 'Đã hủy',
};
const withdrawalStatusLabel = (status: string): string => withdrawalStatusLabels[status] ?? status;

const load = async (): Promise<void> => {
    loading.value = true;
    try {
        data.value = await clientAffiliateService.data();
        payout.bank_name = data.value.profile.bank_name ?? '';
        payout.bank_account_name = data.value.profile.bank_account_name ?? '';
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

const copyReferral = async (): Promise<void> => {
    if (!data.value) return;
    await navigator.clipboard.writeText(data.value.referral.url);
    copied.value = true;
    window.setTimeout(() => (copied.value = false), 1800);
};

const savePayout = async (): Promise<void> => {
    submitting.value = true;
    try {
        const response = await clientAffiliateService.updatePayout(payout);
        handleSuccessResponse(response, 'Đã lưu tài khoản nhận tiền.');
        payout.bank_account_number = '';
        await load();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        submitting.value = false;
    }
};

const convert = async (): Promise<void> => {
    if (!conversionAmount.value) return;
    submitting.value = true;
    try {
        const response = await clientAffiliateService.convert(conversionAmount.value, crypto.randomUUID());
        handleSuccessResponse(response, 'Đã chuyển hoa hồng sang ví chính.');
        conversionAmount.value = null;
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
        const response = await clientAffiliateService.withdraw(withdrawalAmount.value, crypto.randomUUID());
        handleSuccessResponse(response, 'Yêu cầu rút tiền đã được gửi cho admin.');
        withdrawalAmount.value = null;
        await load();
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
        <div v-if="loading" class="grid min-h-96 place-items-center rounded-2xl border border-slate-200 bg-white">
            <LoaderCircle class="size-9 animate-spin text-emerald-600" />
        </div>

        <template v-else-if="data">
            <section v-if="isSuspended" class="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-rose-900">
                <ShieldCheck class="mt-0.5 size-5 shrink-0" />
                <div>
                    <strong>Tài khoản affiliate đang bị tạm khóa</strong>
                    <p class="mt-1 text-sm">Bạn vẫn xem được lịch sử nhưng chưa thể quy đổi hoặc yêu cầu rút tiền.</p>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                    <WalletCards class="size-5 text-emerald-700" />
                    <p class="mt-4 text-sm font-bold text-emerald-800">Có thể sử dụng</p>
                    <p class="text-3xl font-black text-emerald-950">{{ money(data.wallets.affiliate.balance) }}</p>
                    <p class="text-xs text-emerald-700">Đang chờ rút {{ money(data.wallets.affiliate.hold_balance) }}</p>
                </article>
                <article class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                    <Clock3 class="size-5 text-amber-700" />
                    <p class="mt-4 text-sm font-bold text-amber-800">Đang giữ 7 ngày</p>
                    <p class="text-3xl font-black text-amber-950">{{ money(data.stats.pending) }}</p>
                    <p class="text-xs text-amber-700">Tự duyệt khi đơn an toàn</p>
                </article>
                <article class="rounded-2xl border border-blue-200 bg-blue-50 p-5 shadow-sm">
                    <HandCoins class="size-5 text-blue-700" />
                    <p class="mt-4 text-sm font-bold text-blue-800">Tổng hoa hồng duyệt</p>
                    <p class="text-3xl font-black text-blue-950">{{ money(data.stats.available_earned) }}</p>
                    <p class="text-xs text-blue-700">Doanh thu giới thiệu {{ money(data.stats.revenue) }}</p>
                </article>
                <article class="rounded-2xl border border-violet-200 bg-violet-50 p-5 shadow-sm">
                    <Users class="size-5 text-violet-700" />
                    <p class="mt-4 text-sm font-bold text-violet-800">Đơn đã giới thiệu</p>
                    <p class="text-3xl font-black text-violet-950">{{ data.referral.orders_count }}</p>
                    <p class="text-xs text-violet-700">
                        {{ data.referral.referrals_count }} thành viên · {{ data.referral.guest_orders_count }} đơn khách
                    </p>
                </article>
            </section>

            <section class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                <label class="grid gap-2 text-sm font-bold"
                    ><span>Link giới thiệu của bạn</span
                    ><input :value="data.referral.url" readonly class="min-h-12 rounded-xl border-2 border-slate-200 bg-slate-50 px-4 text-slate-700"
                /></label>
                <button
                    type="button"
                    class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-slate-950 px-5 font-bold text-white"
                    @click="copyReferral"
                >
                    <Check v-if="copied" class="size-5" /><Copy v-else class="size-5" />{{ copied ? 'Đã sao chép' : 'Sao chép link' }}
                </button>
            </section>

            <section class="grid gap-4 xl:grid-cols-2">
                <article class="grid content-start gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div>
                        <h2 class="font-black text-slate-950">Đổi sang ví chính</h2>
                        <p class="text-sm text-slate-500">Tối thiểu {{ money(data.program.minimum_conversion) }}, xử lý ngay lập tức.</p>
                    </div>
                    <form class="grid gap-3 sm:grid-cols-[1fr_auto]" @submit.prevent="convert">
                        <input
                            v-model.number="conversionAmount"
                            type="number"
                            :min="data.program.minimum_conversion"
                            step="1000"
                            required
                            class="min-h-12 rounded-xl border-2 border-slate-200 px-4"
                            placeholder="Số tiền muốn đổi"
                        /><button
                            :disabled="submitting || isSuspended"
                            class="min-h-12 rounded-xl bg-blue-600 px-5 font-bold text-white disabled:opacity-50"
                        >
                            Quy đổi
                        </button>
                    </form>
                    <p class="text-xs text-slate-500">
                        Số dư ví chính hiện tại: <strong>{{ money(data.wallets.main.balance) }}</strong>
                    </p>
                </article>
                <article class="grid content-start gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div>
                        <h2 class="font-black text-slate-950">Rút về tài khoản cá nhân</h2>
                        <p class="text-sm text-slate-500">Tối thiểu {{ money(data.program.minimum_withdrawal) }} và cần admin xác nhận.</p>
                    </div>
                    <form class="grid gap-3 sm:grid-cols-[1fr_auto]" @submit.prevent="withdraw">
                        <input
                            v-model.number="withdrawalAmount"
                            type="number"
                            :min="data.program.minimum_withdrawal"
                            step="1000"
                            required
                            class="min-h-12 rounded-xl border-2 border-slate-200 px-4"
                            placeholder="Số tiền muốn rút"
                        /><button
                            :disabled="submitting || isSuspended || !data.profile.has_payout_account"
                            class="min-h-12 rounded-xl bg-emerald-600 px-5 font-bold text-white disabled:opacity-50"
                        >
                            Gửi yêu cầu
                        </button>
                    </form>
                    <p v-if="!data.profile.has_payout_account" class="text-xs font-bold text-amber-700">
                        Hãy lưu tài khoản nhận tiền ở biểu mẫu bên dưới trước.
                    </p>
                </article>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <header class="mb-4 flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-emerald-100 text-emerald-700"><Banknote class="size-5" /></span>
                    <div>
                        <h2 class="font-black">Tài khoản nhận tiền</h2>
                        <p class="text-xs text-slate-500">
                            Thông tin được mã hóa; số hiện tại {{ data.profile.bank_account_number_masked || 'chưa có' }}.
                        </p>
                    </div>
                </header>
                <form class="grid gap-4 md:grid-cols-3" @submit.prevent="savePayout">
                    <label class="grid gap-2 text-sm font-bold"
                        >Ngân hàng<input
                            v-model.trim="payout.bank_name"
                            required
                            maxlength="120"
                            class="min-h-11 rounded-xl border-2 border-slate-200 px-3" /></label
                    ><label class="grid gap-2 text-sm font-bold"
                        >Chủ tài khoản<input
                            v-model.trim="payout.bank_account_name"
                            required
                            maxlength="150"
                            class="min-h-11 rounded-xl border-2 border-slate-200 px-3 uppercase" /></label
                    ><label class="grid gap-2 text-sm font-bold"
                        >Số tài khoản mới<input
                            v-model.trim="payout.bank_account_number"
                            required
                            maxlength="50"
                            class="min-h-11 rounded-xl border-2 border-slate-200 px-3" /></label
                    ><button
                        :disabled="submitting || isSuspended"
                        class="min-h-11 rounded-xl bg-slate-950 px-5 font-bold text-white disabled:opacity-50 md:col-start-3"
                    >
                        Lưu tài khoản
                    </button>
                </form>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <nav class="flex gap-2 overflow-x-auto border-b border-slate-200 p-2">
                    <button
                        v-for="tab in [
                            ['commissions', 'Hoa hồng'],
                            ['referrals', 'Khách giới thiệu'],
                            ['withdrawals', 'Rút tiền'],
                        ] as const"
                        :key="tab[0]"
                        class="min-h-10 rounded-xl px-4 text-sm font-bold"
                        :class="activeTab === tab[0] ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-emerald-50'"
                        @click="activeTab = tab[0]"
                    >
                        {{ tab[1] }}
                    </button>
                </nav>
                <div v-if="activeTab === 'commissions'" class="overflow-x-auto">
                    <table class="w-full min-w-[780px] text-sm">
                        <thead class="bg-slate-50 text-left text-slate-500">
                            <tr>
                                <th class="px-5 py-3">Đơn/gói</th>
                                <th class="px-5 py-3">Khách</th>
                                <th class="px-5 py-3">Doanh thu</th>
                                <th class="px-5 py-3">Hoa hồng</th>
                                <th class="px-5 py-3">Khả dụng</th>
                                <th class="px-5 py-3">Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="commission in data.commissions" :key="commission.id">
                                <td class="px-5 py-4">
                                    <p class="font-bold">{{ commission.order?.code }}</p>
                                    <p class="text-xs text-slate-500">{{ commission.package?.name }}</p>
                                </td>
                                <td class="px-5 py-4">{{ commission.referred_user?.username ?? 'Khách vãng lai' }}</td>
                                <td class="px-5 py-4">{{ money(commission.base_amount) }}</td>
                                <td class="px-5 py-4 font-bold text-emerald-700">{{ money(commission.amount) }}</td>
                                <td class="px-5 py-4">{{ dateTime(commission.available_at) }}</td>
                                <td class="px-5 py-4">{{ commissionStatusLabel(commission.status, commission.available_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else-if="activeTab === 'referrals'" class="divide-y divide-slate-100">
                    <div v-for="referral in data.referrals" :key="referral.id" class="flex items-center justify-between gap-4 p-5">
                        <div>
                            <p class="font-bold">{{ referral.username }}</p>
                            <p class="text-xs text-slate-500">Tham gia lúc {{ dateTime(referral.created_at) }}</p>
                        </div>
                        <Users class="size-5 text-slate-400" />
                    </div>
                    <p v-if="!data.referrals.length" class="p-8 text-center text-slate-500">Chưa có khách được giới thiệu.</p>
                </div>
                <div v-else class="divide-y divide-slate-100">
                    <div
                        v-for="withdrawal in data.withdrawals"
                        :key="withdrawal.id"
                        class="flex flex-col justify-between gap-3 p-5 sm:flex-row sm:items-center"
                    >
                        <div>
                            <p class="font-bold">{{ money(withdrawal.amount) }} · {{ withdrawal.bank_name }}</p>
                            <p class="text-xs text-slate-500">{{ withdrawal.account_number }} · {{ dateTime(withdrawal.created_at) }}</p>
                        </div>
                        <span class="w-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-bold">{{
                            withdrawalStatusLabel(withdrawal.status)
                        }}</span>
                    </div>
                    <p v-if="!data.withdrawals.length" class="p-8 text-center text-slate-500">Chưa có yêu cầu rút tiền.</p>
                </div>
            </section>

            <button
                type="button"
                class="fixed bottom-5 right-5 grid size-12 place-items-center rounded-full bg-white text-emerald-700 shadow-xl ring-1 ring-slate-200"
                aria-label="Làm mới"
                @click="load"
            >
                <RefreshCw class="size-5" />
            </button>
        </template>
    </main>
</template>
