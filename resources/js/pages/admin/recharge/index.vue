<script setup lang="ts">
import Breadcrumb from '@/components/MasterLayouts/Breadcrumb/index.vue';
import DataTable from '@/components/shared/DataTable/index.vue';
import {
    adminRechargeService,
    rechargeStatuses,
    type RechargeBank,
    type RechargeConfiguration,
    type RechargeRow,
} from '@/services/admin-recharge.service';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { computed, onMounted, ref, watch } from 'vue';

const props = withDefaults(defineProps<{ section?: 'configuration' | 'history' }>(), { section: 'configuration' });
const configuration = ref<RechargeConfiguration | null>(null);
const banks = ref<RechargeBank[]>([]);
const enabled = ref(false);
const bankId = ref<number | null>(null);
const apiKey = ref('');
const apiSecret = ref('');
const cronKey = ref('');
const saving = ref(false);
const loadingBanks = ref(false);
const checking = ref(false);
const loading = ref(false);
const rows = ref<RechargeRow[]>([]);
const q = ref('');
const status = ref('');
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const columns = [
    { accessorKey: 'code', header: 'Mã lệnh' },
    { accessorKey: 'user', header: 'Người dùng' },
    { accessorKey: 'amount', header: 'Số tiền' },
    { accessorKey: 'status', header: 'Trạng thái' },
    { accessorKey: 'bank_name', header: 'Ngân hàng' },
    { accessorKey: 'created_at', header: 'Thời gian' },
];
const cronExample = computed(() => `curl -X POST "${configuration.value?.cron_url ?? ''}" -H "X-Cron-Key: <KHOA_CRON>"`);
const money = (value: number) => new Intl.NumberFormat('vi-VN').format(value) + 'đ';
const date = (value: string | null) => (value ? new Date(value).toLocaleString('vi-VN') : '—');
async function loadConfiguration() {
    const data = await adminRechargeService.configuration();
    configuration.value = data;
    enabled.value = data.enabled;
    bankId.value = data.bank_id || null;
    if (data.bank.bank_id && !banks.value.some((bank) => bank.bank_id === data.bank.bank_id)) banks.value.push(data.bank as RechargeBank);
}
async function loadBanks() {
    if (loadingBanks.value) return;
    loadingBanks.value = true;
    try {
        banks.value = await adminRechargeService.banks({
            ...(apiKey.value || apiSecret.value ? { api_key: apiKey.value, api_secret: apiSecret.value } : {}),
        });
        if (!banks.value.some((bank) => bank.bank_id === bankId.value)) bankId.value = null;
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loadingBanks.value = false;
    }
}
async function save() {
    if (saving.value) return;
    saving.value = true;
    try {
        configuration.value = await adminRechargeService.save({
            enabled: enabled.value,
            bank_id: bankId.value,
            ...(apiKey.value || apiSecret.value ? { api_key: apiKey.value, api_secret: apiSecret.value } : {}),
            ...(cronKey.value ? { cron_key: cronKey.value } : {}),
        });
        apiKey.value = '';
        apiSecret.value = '';
        cronKey.value = '';
        handleSuccessResponse({ data: { status: true, message: 'Đã lưu cấu hình nạp tiền.' } });
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
}
async function load(requestedPage = page.value) {
    loading.value = true;
    try {
        const data = await adminRechargeService.history({ page: requestedPage, q: q.value, status: status.value });
        rows.value = data.data;
        page.value = data.current_page;
        lastPage.value = data.last_page;
        total.value = data.total;
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
}
async function check() {
    if (checking.value) return;
    checking.value = true;
    try {
        const data = await adminRechargeService.check();
        if (configuration.value) configuration.value.last_check = data;
        handleSuccessResponse({
            data: { status: true, message: `Đã kiểm tra ${data.checked} lệnh, cộng tiền ${data.credited} lệnh, ${data.errors} lỗi cần kiểm tra.` },
        });
        if (props.section === 'history') await load();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        checking.value = false;
    }
}
watch(
    () => props.section,
    async () => {
        try {
            if (props.section === 'configuration') await loadConfiguration();
            else await load(1);
        } catch (error) {
            handleErrorResponse(error);
        }
    },
);
onMounted(async () => {
    try {
        if (props.section === 'configuration') await loadConfiguration();
        else await load();
    } catch (error) {
        handleErrorResponse(error);
    }
});
</script>

<template>
    <div class="grid min-w-0 gap-4 text-slate-900 dark:text-slate-100">
        <Breadcrumb
            :title="section === 'history' ? 'Lịch sử nạp tiền' : 'Cấu hình bank'"
            description="Nạp tự động qua APIBankVN và kiểm tra thanh toán bằng cron request."
        />
        <form
            v-if="section === 'configuration' && configuration"
            class="grid gap-5 rounded-[5px] border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"
            @submit.prevent="save"
        >
            <div>
                <h2 class="font-bold">Kết nối APIBankVN.com</h2>
                <p class="mt-2 text-sm text-slate-500">
                    API key cần quyền xem ngân hàng, tạo và đọc lệnh nạp. Whitelist IP máy chủ website tại APIBankVN.
                </p>
            </div>
            <label class="flex items-center gap-2 text-sm font-semibold"
                ><input v-model="enabled" type="checkbox" class="rounded border-slate-300 text-emerald-600" /> Bật tạo lệnh nạp tự động</label
            >
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="grid gap-2 text-sm"
                    >API key<input
                        v-model="apiKey"
                        type="password"
                        autocomplete="new-password"
                        maxlength="500"
                        :required="enabled && !configuration.credentials_saved"
                        :placeholder="configuration.credentials_saved ? '•••••• Đã lưu — nhập để thay đổi' : 'Nhập API key'"
                        class="rounded-[5px] border border-slate-300 bg-transparent px-3 py-2 dark:border-slate-600" /></label
                ><label class="grid gap-2 text-sm"
                    >API secret<input
                        v-model="apiSecret"
                        type="password"
                        autocomplete="new-password"
                        maxlength="500"
                        :required="enabled && !configuration.credentials_saved"
                        :placeholder="configuration.credentials_saved ? '•••••• Đã lưu — nhập để thay đổi' : 'Nhập API secret'"
                        class="rounded-[5px] border border-slate-300 bg-transparent px-3 py-2 dark:border-slate-600"
                /></label>
            </div>
            <div class="flex flex-wrap items-end gap-3">
                <label class="grid min-w-0 flex-1 basis-64 gap-2 text-sm"
                    >Ngân hàng nhận tiền<select
                        v-model="bankId"
                        :required="enabled"
                        class="min-h-10 rounded-[5px] border border-slate-300 bg-white px-3 py-2 dark:border-slate-600 dark:bg-slate-800"
                    >
                        <option :value="null">Chọn ngân hàng</option>
                        <option v-for="bank in banks" :key="bank.bank_id" :value="bank.bank_id">
                            {{ bank.bank_name }} · {{ bank.account_number }} · {{ bank.account_name }}
                        </option>
                    </select></label
                ><button
                    type="button"
                    :disabled="loadingBanks || saving"
                    class="min-h-10 rounded-[5px] border border-emerald-300 px-4 text-sm font-semibold text-emerald-700 disabled:opacity-50 dark:text-emerald-400"
                    @click="loadBanks"
                >
                    {{ loadingBanks ? 'Đang lấy…' : 'Kiểm tra kết nối / Lấy ngân hàng' }}
                </button>
            </div>
            <label class="grid gap-2 text-sm"
                >Khoá bảo vệ cron<input
                    v-model="cronKey"
                    type="password"
                    autocomplete="new-password"
                    minlength="32"
                    maxlength="200"
                    :required="enabled && !configuration.cron_key_saved"
                    :placeholder="
                        configuration.cron_key_saved ? '•••••• Đã lưu — để trống để giữ nguyên' : 'Tự đặt chuỗi ngẫu nhiên tối thiểu 32 ký tự'
                    "
                    class="rounded-[5px] border border-slate-300 bg-transparent px-3 py-2 dark:border-slate-600"
            /></label>
            <p class="text-xs leading-5 text-slate-500">
                Key, secret và khoá cron được lưu mã hoá. Tắt tạo lệnh mới vẫn cho cron đối soát những lệnh đã tạo. Mỗi lệnh nạp từ 10.000đ đến
                50.000.000đ.
            </p>
            <div>
                <button
                    type="submit"
                    :disabled="saving || loadingBanks"
                    class="rounded-[5px] bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
                >
                    {{ saving ? 'Đang lưu…' : 'Lưu cấu hình' }}
                </button>
            </div>
        </form>
        <section
            v-if="section === 'configuration' && configuration"
            class="grid gap-4 rounded-[5px] border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"
        >
            <h2 class="font-bold">Cron kiểm tra giao dịch</h2>
            <p class="text-sm leading-6 text-slate-500">
                Gọi POST mỗi phút, gửi khoá đã lưu trong header X-Cron-Key. Cron kiểm tra tối đa 10 lệnh/lượt, chỉ cộng tiền khi APIBankVN xác nhận đã
                thanh toán và dữ liệu khớp. Không gửi khoá trong URL.
            </p>
            <pre class="overflow-x-auto rounded-[5px] bg-slate-950 p-4 text-xs leading-6 text-emerald-300">{{ cronExample }}</pre>
            <p v-if="configuration.last_check" class="text-xs text-slate-500">
                Lần cuối: {{ date(configuration.last_check.at) }} · Kiểm tra {{ configuration.last_check.checked }} · Đã cộng
                {{ configuration.last_check.credited }} · Lỗi {{ configuration.last_check.errors }}
            </p>
            <div>
                <button
                    type="button"
                    :disabled="checking"
                    class="rounded-[5px] border border-slate-300 px-4 py-2 text-sm disabled:opacity-50"
                    @click="check"
                >
                    {{ checking ? 'Đang kiểm tra…' : 'Kiểm tra giao dịch ngay' }}
                </button>
            </div>
        </section>
        <template v-if="section === 'history'">
            <form
                class="flex flex-wrap items-end gap-3 rounded-[5px] border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                @submit.prevent="load(1)"
            >
                <label class="grid min-w-0 flex-1 basis-56 gap-2 text-sm"
                    >Mã lệnh / người dùng<input
                        v-model="q"
                        maxlength="100"
                        placeholder="Tìm lệnh nạp"
                        class="rounded-[5px] border border-slate-300 bg-transparent px-3 py-2 dark:border-slate-600" /></label
                ><label class="grid gap-2 text-sm"
                    >Trạng thái<select
                        v-model="status"
                        class="rounded-[5px] border border-slate-300 bg-white px-3 py-2 dark:border-slate-600 dark:bg-slate-800"
                    >
                        <option value="">Tất cả</option>
                        <option v-for="(label, value) in rechargeStatuses" :key="value" :value="value">{{ label }}</option>
                    </select></label
                ><button class="rounded-[5px] bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Lọc</button
                ><button type="button" :disabled="checking" class="rounded-[5px] border border-slate-300 px-4 py-2 text-sm" @click="check">
                    Kiểm tra giao dịch
                </button>
            </form>
            <p class="text-xs text-slate-500">{{ total }} lệnh nạp · 20 lệnh/trang</p>
            <DataTable
                :data="rows"
                :columns="columns"
                :loading="loading"
                :current-page="page"
                :total-pages="lastPage"
                :go-to-page="load"
                class-custom="min-w-[54rem]"
            >
                <template #code="{ row }"
                    ><p class="font-mono text-xs">{{ row.code }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ row.provider_code }}</p></template
                >
                <template #user="{ row }">{{ row.user?.username ?? '—' }}</template>
                <template #amount="{ row }"
                    ><strong class="whitespace-nowrap">{{ money(row.amount) }}</strong></template
                >
                <template #status="{ row }"
                    ><span class="text-sm font-semibold" :class="row.status === 'paid' ? 'text-emerald-700' : 'text-slate-500'">{{
                        rechargeStatuses[row.status]
                    }}</span>
                    <p v-if="row.last_error" class="mt-1 font-mono text-xs text-rose-600">{{ row.last_error }}</p></template
                >
                <template #bank_name="{ row }"
                    ><p>{{ row.bank_name }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ row.account_number }}</p></template
                >
                <template #created_at="{ row }"
                    ><p class="whitespace-nowrap text-xs">{{ date(row.created_at) }}</p>
                    <p v-if="row.paid_at" class="mt-1 whitespace-nowrap text-xs text-emerald-700">Cộng ví: {{ date(row.paid_at) }}</p></template
                >
            </DataTable>
        </template>
    </div>
</template>
