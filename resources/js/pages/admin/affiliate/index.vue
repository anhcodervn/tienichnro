<script setup lang="ts">
import {
    adminAffiliateService,
    type AffiliateCommission,
    type AffiliateConfiguration,
    type AffiliateOverview,
    type AffiliatePartner,
    type AffiliateWithdrawal,
    type Paginated,
} from '@/services/admin-affiliate.service';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { BadgeDollarSign, Banknote, HandCoins, LoaderCircle, RefreshCw, Save, ShieldAlert, Users } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, onMounted, ref, watch } from 'vue';

type Tab = 'overview' | 'partners' | 'commissions' | 'withdrawals' | 'configuration';

const activeTab = ref<Tab>('overview');
const loading = ref(false);
const saving = ref(false);
const selectedSiteId = ref<number | null>(null);
const overview = ref<AffiliateOverview | null>(null);
const configuration = ref<AffiliateConfiguration | null>(null);
const partners = ref<Paginated<AffiliatePartner> | null>(null);
const commissions = ref<Paginated<AffiliateCommission> | null>(null);
const withdrawals = ref<Paginated<AffiliateWithdrawal> | null>(null);
const statusFilter = ref('');
const currentPage = ref(1);

const tabs: Array<{ key: Tab; label: string }> = [
    { key: 'overview', label: 'Tổng quan' },
    { key: 'partners', label: 'Cộng tác viên' },
    { key: 'commissions', label: 'Hoa hồng' },
    { key: 'withdrawals', label: 'Rút tiền' },
    { key: 'configuration', label: 'Cấu hình' },
];
const money = (value: number | string | null | undefined): string => `${new Intl.NumberFormat('vi-VN').format(Number(value ?? 0))}đ`;
const dateTime = (value: string | null | undefined): string => (value ? new Date(value).toLocaleString('vi-VN') : '—');
const commissionStatusLabel = (commission: AffiliateCommission): string => {
    if (commission.status === 'pending') return commission.available_at ? 'Đang giữ 7 ngày' : 'Chờ hoàn tất';

    return commission.status === 'available' ? 'Đã duyệt' : commission.status === 'reversed' ? 'Đã thu hồi' : commission.status;
};
const withdrawalStatusLabels: Record<string, string> = {
    requested: 'Chờ duyệt',
    approved: 'Đã duyệt',
    paid: 'Đã trả',
    rejected: 'Từ chối',
    cancelled: 'Đã hủy',
};
const withdrawalStatusLabel = (status: string): string => withdrawalStatusLabels[status] ?? status;
const query = computed<Record<string, unknown>>(() => ({
    ...(selectedSiteId.value ? { site_id: selectedSiteId.value } : {}),
    ...(statusFilter.value ? { status: statusFilter.value } : {}),
    page: currentPage.value,
}));
const activePagination = computed(() => {
    const source = activeTab.value === 'partners' ? partners.value : activeTab.value === 'commissions' ? commissions.value : withdrawals.value;

    return source ? { currentPage: source.current_page, lastPage: source.last_page, total: source.total } : null;
});

const loadConfiguration = async (): Promise<void> => {
    configuration.value = await adminAffiliateService.configuration(selectedSiteId.value || undefined);
    if (selectedSiteId.value === null) {
        selectedSiteId.value = configuration.value.sites.length === 1 ? configuration.value.site.id : 0;
    }
};

const load = async (): Promise<void> => {
    loading.value = true;
    try {
        if (!configuration.value) await loadConfiguration();
        if (activeTab.value === 'overview') overview.value = await adminAffiliateService.overview(query.value);
        if (activeTab.value === 'partners') partners.value = await adminAffiliateService.partners(query.value);
        if (activeTab.value === 'commissions') commissions.value = await adminAffiliateService.commissions(query.value);
        if (activeTab.value === 'withdrawals') withdrawals.value = await adminAffiliateService.withdrawals(query.value);
        if (activeTab.value === 'configuration' && selectedSiteId.value) await loadConfiguration();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

const saveProgram = async (): Promise<void> => {
    if (!configuration.value) return;
    saving.value = true;
    try {
        const response = await adminAffiliateService.updateProgram({
            site_id: configuration.value.site.id,
            is_enabled: configuration.value.program.is_enabled,
            minimum_withdrawal: Number(configuration.value.program.minimum_withdrawal),
        });
        handleSuccessResponse(response, 'Đã cập nhật chương trình affiliate.');
        await loadConfiguration();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
};

const saveRate = async (index: number): Promise<void> => {
    const rate = configuration.value?.rates[index];
    if (!rate || !configuration.value || rate.mode === 'none') return;
    saving.value = true;
    try {
        if (rate.mode === 'global') {
            const response = await adminAffiliateService.resetRate(rate.package_id, configuration.value.site.id);
            handleSuccessResponse(response, `Gói ${rate.package} đã dùng lại hoa hồng Global.`);
            await loadConfiguration();

            return;
        }

        const response = await adminAffiliateService.updateRate(rate.package_id, {
            site_id: configuration.value.site.id,
            commission_type: rate.commission_type,
            fixed_amount: rate.commission_type === 'fixed' ? Number(rate.fixed_amount) : null,
            percentage: rate.commission_type === 'percentage' ? Number(rate.percentage) : null,
            is_active: rate.mode === 'override',
        });
        handleSuccessResponse(response, `Đã lưu hoa hồng gói ${rate.package}.`);
        await loadConfiguration();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
};

const saveGlobalRate = async (index: number): Promise<void> => {
    const rate = configuration.value?.global_rates[index];
    if (!rate || !configuration.value) return;
    saving.value = true;
    try {
        const response = await adminAffiliateService.updateGlobalRate(rate.global_package_id, {
            site_id: configuration.value.site.id,
            commission_type: rate.commission_type,
            fixed_amount: rate.commission_type === 'fixed' ? Number(rate.fixed_amount) : null,
            percentage: rate.commission_type === 'percentage' ? Number(rate.percentage) : null,
            is_active: rate.is_active,
        });
        handleSuccessResponse(response, `Đã lưu hoa hồng Global ${rate.package}.`);
        await loadConfiguration();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
};

const togglePartner = async (partner: AffiliatePartner): Promise<void> => {
    const nextStatus = partner.status === 'active' ? 'suspended' : 'active';
    const result = await Swal.fire({
        title: nextStatus === 'suspended' ? 'Tạm khóa cộng tác viên?' : 'Mở lại cộng tác viên?',
        input: 'textarea',
        inputLabel: 'Ghi chú quản trị',
        showCancelButton: true,
        confirmButtonText: 'Xác nhận',
        cancelButtonText: 'Hủy',
    });
    if (!result.isConfirmed) return;

    try {
        const response = await adminAffiliateService.updatePartner(partner.id, { status: nextStatus, admin_note: result.value || null });
        handleSuccessResponse(response, 'Đã cập nhật trạng thái cộng tác viên.');
        await load();
    } catch (error) {
        handleErrorResponse(error);
    }
};

const toggleCommissionFlag = async (commission: AffiliateCommission): Promise<void> => {
    const action = commission.is_flagged ? 'unflag' : 'flag';
    const result = await Swal.fire({
        title: action === 'flag' ? 'Tạm giữ hoa hồng để kiểm tra?' : 'Bỏ cờ và tiếp tục tự động duyệt?',
        input: action === 'flag' ? 'textarea' : undefined,
        inputLabel: action === 'flag' ? 'Lý do cần kiểm tra' : undefined,
        inputValue: commission.hold_reason ?? '',
        inputValidator: action === 'flag' ? (value) => (!value?.trim() ? 'Hãy nhập lý do tạm giữ.' : undefined) : undefined,
        showCancelButton: true,
        confirmButtonText: 'Xác nhận',
        cancelButtonText: 'Hủy',
    });
    if (!result.isConfirmed) return;

    try {
        const response = await adminAffiliateService.updateCommission(commission.id, {
            action,
            hold_reason: action === 'flag' ? result.value?.trim() : null,
        });
        handleSuccessResponse(response, action === 'flag' ? 'Đã tạm giữ hoa hồng.' : 'Đã bỏ cờ kiểm tra hoa hồng.');
        await load();
    } catch (error) {
        handleErrorResponse(error);
    }
};

const updateWithdrawal = async (withdrawal: AffiliateWithdrawal, action: 'approve' | 'reject' | 'mark_paid'): Promise<void> => {
    let bankTransactionReference: string | null = null;
    if (action === 'mark_paid') {
        const detail = await adminAffiliateService.withdrawal(withdrawal.id);
        const result = await Swal.fire({
            title: 'Xác nhận đã chuyển khoản',
            text: `${detail.bank_name} · ${detail.bank_account_number} · ${detail.bank_account_name}`,
            input: 'text',
            inputLabel: 'Mã giao dịch ngân hàng',
            inputValidator: (value) => (!value ? 'Hãy nhập mã giao dịch.' : undefined),
            showCancelButton: true,
        });
        if (!result.isConfirmed) return;
        bankTransactionReference = result.value;
    } else {
        const result = await Swal.fire({
            title: action === 'approve' ? 'Duyệt yêu cầu rút?' : 'Từ chối và hoàn tiền?',
            input: 'textarea',
            inputLabel: 'Ghi chú',
            showCancelButton: true,
        });
        if (!result.isConfirmed) return;
    }

    try {
        const response = await adminAffiliateService.updateWithdrawal(withdrawal.id, {
            action,
            bank_transaction_reference: bankTransactionReference,
        });
        handleSuccessResponse(response, 'Đã cập nhật yêu cầu rút tiền.');
        await load();
    } catch (error) {
        handleErrorResponse(error);
    }
};

watch(activeTab, () => {
    const filtersWillChange = statusFilter.value !== '' || currentPage.value !== 1;
    statusFilter.value = '';
    currentPage.value = 1;

    if (filtersWillChange) {
        return;
    }

    void load();
});
watch([selectedSiteId, statusFilter], () => {
    if (currentPage.value !== 1) {
        currentPage.value = 1;

        return;
    }

    void load();
});
watch(currentPage, () => void load());
onMounted(load);
</script>

<template>
    <main class="grid gap-5 p-4 sm:p-6">
        <header class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-start gap-3">
                <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-emerald-100 text-emerald-700"><HandCoins class="size-6" /></span>
                <div>
                    <h1 class="text-2xl font-black text-slate-950">Affiliate Control</h1>
                    <p class="text-sm text-slate-500">Theo dõi doanh thu giới thiệu, chi phí hoa hồng và trạng thái chi trả theo từng website.</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <select
                    v-if="configuration?.sites.length"
                    v-model.number="selectedSiteId"
                    class="min-h-11 rounded-xl border-2 border-slate-200 bg-white px-3 text-sm font-bold"
                >
                    <option v-if="configuration.site.is_main" :value="0">Toàn hệ thống</option>
                    <option v-for="site in configuration.sites" :key="site.id" :value="site.id">{{ site.name }}</option>
                </select>
                <button
                    type="button"
                    class="inline-flex min-h-11 items-center gap-2 rounded-xl border-2 border-slate-200 px-4 text-sm font-bold hover:border-blue-300"
                    @click="load"
                >
                    <RefreshCw class="size-4" /> Làm mới
                </button>
            </div>
        </header>

        <nav class="flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-sm" aria-label="Affiliate admin tabs">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="min-h-10 shrink-0 rounded-xl px-4 text-sm font-bold transition"
                :class="activeTab === tab.key ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-blue-50 hover:text-blue-700'"
                @click="activeTab = tab.key"
            >
                {{ tab.label }}
            </button>
        </nav>

        <div v-if="loading" class="grid min-h-72 place-items-center rounded-2xl border border-slate-200 bg-white">
            <LoaderCircle class="size-8 animate-spin text-blue-600" />
        </div>

        <template v-else-if="activeTab === 'overview' && overview">
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <Users class="size-5 text-blue-600" />
                    <p class="mt-4 text-sm font-bold text-slate-500">Cộng tác viên hoạt động</p>
                    <p class="text-3xl font-black">{{ overview.partners.active }}</p>
                    <p class="text-xs text-slate-500">Tổng {{ overview.partners.total }} · Khóa {{ overview.partners.suspended }}</p>
                </article>
                <article class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                    <HandCoins class="size-5 text-amber-700" />
                    <p class="mt-4 text-sm font-bold text-amber-800">Hoa hồng đang giữ</p>
                    <p class="text-3xl font-black text-amber-950">{{ money(overview.commissions.pending) }}</p>
                    <p class="text-xs text-amber-700">{{ overview.commissions.orders }} đơn · {{ overview.commissions.guest_orders }} đơn khách</p>
                </article>
                <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                    <BadgeDollarSign class="size-5 text-emerald-700" />
                    <p class="mt-4 text-sm font-bold text-emerald-800">Hoa hồng đã duyệt</p>
                    <p class="text-3xl font-black text-emerald-950">{{ money(overview.commissions.available) }}</p>
                    <p class="text-xs text-emerald-700">Doanh thu {{ money(overview.commissions.revenue) }}</p>
                </article>
                <article class="rounded-2xl border border-violet-200 bg-violet-50 p-5 shadow-sm">
                    <Banknote class="size-5 text-violet-700" />
                    <p class="mt-4 text-sm font-bold text-violet-800">Đã thanh toán rút tiền</p>
                    <p class="text-3xl font-black text-violet-950">{{ money(overview.withdrawals.paid) }}</p>
                    <p class="text-xs text-violet-700">Chờ xử lý {{ money(overview.withdrawals.requested + overview.withdrawals.approved) }}</p>
                </article>
            </section>
            <section
                v-if="overview.commissions.flagged"
                class="flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-rose-900"
            >
                <ShieldAlert class="size-5" /><strong>{{ overview.commissions.flagged }} khoản hoa hồng đang bị gắn cờ cần kiểm tra.</strong>
            </section>
            <section v-if="overview.by_site.length" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <header class="border-b border-slate-200 p-5"><h2 class="font-black">Tình trạng theo website</h2></header>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1050px] text-sm">
                        <thead class="bg-slate-50 text-left text-slate-500">
                            <tr>
                                <th class="px-5 py-3">Website</th>
                                <th class="px-5 py-3">Trạng thái</th>
                                <th class="px-5 py-3">CTV</th>
                                <th class="px-5 py-3">Đơn affiliate</th>
                                <th class="px-5 py-3">Doanh thu</th>
                                <th class="px-5 py-3">Chi phí hoa hồng</th>
                                <th class="px-5 py-3">Rút đang chờ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="site in overview.by_site" :key="site.tenant_id">
                                <td class="px-5 py-4 font-bold">{{ site.site }}</td>
                                <td class="px-5 py-4">
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-bold"
                                        :class="site.is_enabled ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                                        >{{ site.is_enabled ? 'Đang bật' : 'Đang tắt' }}</span
                                    >
                                </td>
                                <td class="px-5 py-4">{{ site.active_partners }}/{{ site.partners_count }}</td>
                                <td class="px-5 py-4">{{ site.commissions_count }}</td>
                                <td class="px-5 py-4">{{ money(site.revenue) }}</td>
                                <td class="px-5 py-4 font-bold text-emerald-700">{{ money(site.commission_cost) }}</td>
                                <td class="px-5 py-4 font-bold text-amber-700">{{ money(site.pending_withdrawal) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </template>

        <section v-else-if="activeTab === 'partners' && partners" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <header class="flex items-center justify-between border-b border-slate-200 p-5">
                <h2 class="font-black">Cộng tác viên</h2>
                <span class="text-sm text-slate-500">{{ partners.total }} tài khoản</span>
            </header>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Tài khoản</th>
                            <th class="px-5 py-3">Giới thiệu</th>
                            <th class="px-5 py-3">Doanh thu</th>
                            <th class="px-5 py-3">Pending</th>
                            <th class="px-5 py-3">Ví affiliate</th>
                            <th class="px-5 py-3">Trạng thái</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="partner in partners.data" :key="partner.id">
                            <td class="px-5 py-4">
                                <p class="font-bold">{{ partner.user.username }}</p>
                                <p class="text-xs text-slate-500">{{ partner.user.email }} · {{ partner.user.referral_code }}</p>
                            </td>
                            <td class="px-5 py-4">{{ partner.referrals_count }} tài khoản / {{ partner.orders_count }} đơn</td>
                            <td class="px-5 py-4 font-bold">{{ money(partner.revenue) }}</td>
                            <td class="px-5 py-4 text-amber-700">{{ money(partner.pending) }}</td>
                            <td class="px-5 py-4 text-emerald-700">{{ money(partner.wallet_balance) }}</td>
                            <td class="px-5 py-4">
                                <span
                                    class="rounded-full px-2.5 py-1 text-xs font-bold"
                                    :class="partner.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'"
                                    >{{ partner.status }}</span
                                >
                            </td>
                            <td class="px-5 py-4 text-right">
                                <button type="button" class="rounded-lg border px-3 py-2 font-bold" @click="togglePartner(partner)">
                                    {{ partner.status === 'active' ? 'Tạm khóa' : 'Mở lại' }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section
            v-else-if="activeTab === 'commissions' && commissions"
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
        >
            <header class="flex items-center justify-between border-b border-slate-200 p-5">
                <h2 class="font-black">Lịch sử hoa hồng</h2>
                <select v-model="statusFilter" class="rounded-lg border px-3 py-2 text-sm">
                    <option value="">Tất cả</option>
                    <option value="pending">Đang giữ</option>
                    <option value="available">Đã duyệt</option>
                    <option value="reversed">Thu hồi</option>
                    <option value="flagged">Cần kiểm tra</option>
                </select>
            </header>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Đơn/gói</th>
                            <th class="px-5 py-3">CTV → Khách</th>
                            <th class="px-5 py-3">Doanh thu</th>
                            <th class="px-5 py-3">Hoa hồng</th>
                            <th class="px-5 py-3">Khả dụng lúc</th>
                            <th class="px-5 py-3">Trạng thái</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="commission in commissions.data" :key="commission.id">
                            <td class="px-5 py-4">
                                <p class="font-bold">{{ commission.order?.code }}</p>
                                <p class="text-xs text-slate-500">{{ commission.package?.name }}</p>
                            </td>
                            <td class="px-5 py-4">
                                {{ commission.referrer?.username }} → {{ commission.referred_user?.username ?? 'Khách vãng lai' }}
                            </td>
                            <td class="px-5 py-4">{{ money(commission.base_amount) }}</td>
                            <td class="px-5 py-4 font-bold text-emerald-700">{{ money(commission.amount) }}</td>
                            <td class="px-5 py-4">{{ dateTime(commission.available_at) }}</td>
                            <td class="px-5 py-4">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold"
                                    >{{ commissionStatusLabel(commission) }}{{ commission.is_flagged ? ' · tạm giữ' : '' }}</span
                                >
                                <p v-if="commission.hold_reason" class="mt-2 max-w-xs text-xs text-rose-700">{{ commission.hold_reason }}</p>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <button
                                    v-if="commission.status === 'pending'"
                                    type="button"
                                    class="rounded-lg border px-3 py-2 text-xs font-bold"
                                    :class="commission.is_flagged ? 'border-emerald-200 text-emerald-700' : 'border-rose-200 text-rose-700'"
                                    @click="toggleCommissionFlag(commission)"
                                >
                                    {{ commission.is_flagged ? 'Bỏ giữ' : 'Tạm giữ' }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section
            v-else-if="activeTab === 'withdrawals' && withdrawals"
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
        >
            <header class="flex items-center justify-between border-b border-slate-200 p-5">
                <h2 class="font-black">Yêu cầu rút tiền</h2>
                <select v-model="statusFilter" class="rounded-lg border px-3 py-2 text-sm">
                    <option value="">Tất cả</option>
                    <option value="requested">Chờ duyệt</option>
                    <option value="approved">Đã duyệt</option>
                    <option value="paid">Đã trả</option>
                    <option value="rejected">Từ chối</option>
                </select>
            </header>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-5 py-3">CTV</th>
                            <th class="px-5 py-3">Số tiền</th>
                            <th class="px-5 py-3">Nhận tiền</th>
                            <th class="px-5 py-3">Thời gian</th>
                            <th class="px-5 py-3">Trạng thái</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="withdrawal in withdrawals.data" :key="withdrawal.id">
                            <td class="px-5 py-4">
                                <p class="font-bold">{{ withdrawal.user?.username }}</p>
                                <p class="text-xs text-slate-500">{{ withdrawal.user?.email }}</p>
                            </td>
                            <td class="px-5 py-4 font-black">{{ money(withdrawal.amount) }}</td>
                            <td class="px-5 py-4">{{ withdrawal.bank_name }} · {{ withdrawal.bank_account_number_masked }}</td>
                            <td class="px-5 py-4">{{ dateTime(withdrawal.created_at) }}</td>
                            <td class="px-5 py-4">{{ withdrawalStatusLabel(withdrawal.status) }}</td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <button
                                        v-if="withdrawal.status === 'requested'"
                                        class="rounded-lg bg-blue-600 px-3 py-2 font-bold text-white"
                                        @click="updateWithdrawal(withdrawal, 'approve')"
                                    >
                                        Duyệt</button
                                    ><button
                                        v-if="['requested', 'approved'].includes(withdrawal.status)"
                                        class="rounded-lg border border-rose-200 px-3 py-2 font-bold text-rose-700"
                                        @click="updateWithdrawal(withdrawal, 'reject')"
                                    >
                                        Từ chối</button
                                    ><button
                                        v-if="withdrawal.status === 'approved'"
                                        class="rounded-lg bg-emerald-600 px-3 py-2 font-bold text-white"
                                        @click="updateWithdrawal(withdrawal, 'mark_paid')"
                                    >
                                        Đã chuyển
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <template v-else-if="activeTab === 'configuration'">
            <section v-if="!selectedSiteId" class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-amber-900">
                Hãy chọn một website cụ thể để cấu hình.
            </section>
            <template v-else-if="configuration">
                <section class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:grid-cols-[1fr_16rem_auto] lg:items-end">
                    <label class="grid gap-2 text-sm font-bold"
                        ><span>Chương trình trên {{ configuration.site.name }}</span
                        ><select v-model="configuration.program.is_enabled" class="min-h-11 rounded-xl border-2 border-slate-200 px-3">
                            <option :value="true">Đang bật</option>
                            <option :value="false">Đang tắt</option>
                        </select></label
                    >
                    <label class="grid gap-2 text-sm font-bold"
                        ><span>Rút tối thiểu</span
                        ><input
                            v-model.number="configuration.program.minimum_withdrawal"
                            type="number"
                            min="1000"
                            step="1000"
                            class="min-h-11 rounded-xl border-2 border-slate-200 px-3"
                    /></label>
                    <button
                        type="button"
                        :disabled="saving"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 text-sm font-bold text-white disabled:opacity-50"
                        @click="saveProgram"
                    >
                        <Save class="size-4" /> Lưu chung
                    </button>
                    <p class="text-xs text-slate-500 lg:col-span-3">
                        Hoa hồng luôn được giữ cố định 7×24 giờ. Quy đổi sang ví chính tối thiểu
                        {{ money(configuration.program.minimum_conversion) }}.
                    </p>
                </section>
                <section class="overflow-hidden rounded-2xl border border-emerald-200 bg-white shadow-sm">
                    <header class="border-b border-emerald-100 bg-emerald-50 p-5">
                        <h2 class="font-black text-emerald-950">Hoa hồng theo gói Global</h2>
                        <p class="text-sm text-emerald-700">
                            Cấu hình một lần và tự áp dụng cho mọi game đang dùng cùng gói Global trên website này.
                        </p>
                    </header>
                    <div v-if="configuration.global_rates.length" class="overflow-x-auto">
                        <table class="w-full min-w-[920px] text-sm">
                            <thead class="bg-slate-50 text-left text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Gói Global</th>
                                    <th class="px-4 py-3">Game áp dụng</th>
                                    <th class="px-4 py-3">Lợi nhuận thấp nhất</th>
                                    <th class="px-4 py-3">Kiểu</th>
                                    <th class="px-4 py-3">Mức hoa hồng</th>
                                    <th class="px-4 py-3">Bật</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(rate, index) in configuration.global_rates" :key="rate.global_package_id">
                                    <td class="px-4 py-4">
                                        <p class="font-bold">{{ rate.package }}</p>
                                        <p class="text-xs text-slate-500">Mệnh giá {{ money(rate.denomination) }}</p>
                                    </td>
                                    <td class="max-w-xs px-4 py-4 text-slate-600">{{ rate.games.join(', ') }}</td>
                                    <td class="px-4 py-4 font-bold text-emerald-700">{{ money(rate.minimum_margin) }}</td>
                                    <td class="px-4 py-4">
                                        <select v-model="rate.commission_type" class="rounded-lg border px-3 py-2">
                                            <option value="fixed">Cố định</option>
                                            <option value="percentage">Phần trăm</option>
                                        </select>
                                    </td>
                                    <td class="px-4 py-4">
                                        <input
                                            v-if="rate.commission_type === 'fixed'"
                                            v-model.number="rate.fixed_amount"
                                            type="number"
                                            min="0"
                                            step="100"
                                            class="w-36 rounded-lg border px-3 py-2"
                                        />
                                        <div v-else class="flex items-center gap-2">
                                            <input
                                                v-model.number="rate.percentage"
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.01"
                                                class="w-28 rounded-lg border px-3 py-2"
                                            /><span>%</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <input v-model="rate.is_active" type="checkbox" class="size-5 rounded border-slate-300" />
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <button
                                            type="button"
                                            :disabled="saving || rate.mode === 'none'"
                                            class="rounded-lg bg-emerald-700 px-3 py-2 font-bold text-white disabled:opacity-50"
                                            @click="saveGlobalRate(index)"
                                        >
                                            Lưu Global
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="p-5 text-sm text-slate-500">Chưa có game nào sử dụng gói Global.</p>
                </section>
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <header class="border-b border-slate-200 p-5">
                        <h2 class="font-black">Hoa hồng theo gói game</h2>
                        <p class="text-sm text-slate-500">
                            Gói Global tự kế thừa cấu hình phía trên; chỉ cấu hình riêng khi cần ghi đè hoặc tắt một gói.
                        </p>
                    </header>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1050px] text-sm">
                            <thead class="bg-slate-50 text-left text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Game / gói</th>
                                    <th class="px-4 py-3">Giá bán</th>
                                    <th class="px-4 py-3">Lợi nhuận</th>
                                    <th class="px-4 py-3">Áp dụng</th>
                                    <th class="px-4 py-3">Kiểu</th>
                                    <th class="px-4 py-3">Mức hoa hồng</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(rate, index) in configuration.rates" :key="rate.package_id">
                                    <td class="px-4 py-4">
                                        <p class="font-bold">{{ rate.game }}</p>
                                        <p class="text-xs text-slate-500">{{ rate.package }}</p>
                                    </td>
                                    <td class="px-4 py-4">{{ money(rate.selling_price) }}</td>
                                    <td class="px-4 py-4 font-bold text-emerald-700">{{ money(rate.margin) }}</td>
                                    <td class="px-4 py-4">
                                        <select v-model="rate.mode" class="rounded-lg border px-3 py-2">
                                            <option v-if="rate.is_global" value="global">Dùng Global</option>
                                            <option value="override">Cấu hình riêng</option>
                                            <option value="disabled">Tắt riêng</option>
                                            <option v-if="!rate.is_global && rate.mode === 'none'" value="none" disabled>Chưa cấu hình</option>
                                        </select>
                                        <p v-if="rate.effective_source === 'global'" class="mt-1 text-xs font-semibold text-emerald-700">
                                            Đang kế thừa Global
                                        </p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <select
                                            v-model="rate.commission_type"
                                            :disabled="rate.mode !== 'override'"
                                            class="rounded-lg border px-3 py-2 disabled:bg-slate-100"
                                        >
                                            <option value="fixed">Cố định</option>
                                            <option value="percentage">Phần trăm</option>
                                        </select>
                                    </td>
                                    <td class="px-4 py-4">
                                        <input
                                            v-if="rate.commission_type === 'fixed'"
                                            v-model.number="rate.fixed_amount"
                                            type="number"
                                            min="0"
                                            step="100"
                                            :disabled="rate.mode !== 'override'"
                                            class="w-36 rounded-lg border px-3 py-2 disabled:bg-slate-100"
                                        />
                                        <div v-else class="flex items-center gap-2">
                                            <input
                                                v-model.number="rate.percentage"
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.01"
                                                :disabled="rate.mode !== 'override'"
                                                class="w-28 rounded-lg border px-3 py-2 disabled:bg-slate-100"
                                            /><span>%</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <button
                                            type="button"
                                            :disabled="saving"
                                            class="rounded-lg bg-slate-950 px-3 py-2 font-bold text-white disabled:opacity-50"
                                            @click="saveRate(index)"
                                        >
                                            Lưu
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </template>
        </template>

        <footer
            v-if="['partners', 'commissions', 'withdrawals'].includes(activeTab) && activePagination"
            class="flex flex-col items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-5 py-4 text-sm sm:flex-row"
        >
            <p class="font-medium text-slate-500">Tổng {{ activePagination.total }} bản ghi</p>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    :disabled="activePagination.currentPage <= 1"
                    class="min-h-10 rounded-xl border border-slate-200 px-4 font-bold disabled:opacity-40"
                    @click="currentPage--"
                >
                    Trang trước
                </button>
                <span class="px-2 font-bold">{{ activePagination.currentPage }}/{{ activePagination.lastPage }}</span>
                <button
                    type="button"
                    :disabled="activePagination.currentPage >= activePagination.lastPage"
                    class="min-h-10 rounded-xl border border-slate-200 px-4 font-bold disabled:opacity-40"
                    @click="currentPage++"
                >
                    Trang sau
                </button>
            </div>
        </footer>
    </main>
</template>
