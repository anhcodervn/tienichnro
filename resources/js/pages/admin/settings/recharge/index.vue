<script setup lang="ts">
import Modal from '@/components/shared/Modal/index.vue';
import { adminRechargeConfigService } from '@/services/admin-recharge-config.service';
import type { ApiBankVnBankAccountOption, RechargeConfigType } from '@/types/recharge-config.type';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { Building2, Pencil, Plus, Power, RefreshCw, ShieldCheck, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

type RechargeConfigForm = {
    provider: 'manual' | 'apibankvn_api';
    bank_name: string;
    account_name: string;
    account_number: string;
    qr_template: string;
    transfer_prefix: string;
    api_key: string;
    api_secret: string;
    webhook_secret: string;
    api_bank_id: string;
    is_active: boolean;
};

type LocalBankOption = {
    code: string;
    name: string;
    hint: string;
};

const DEFAULT_QR_TEMPLATE =
    'https://img.vietqr.io/image/{bank_code}-{account_number}-compact2.png?amount={amount}&addInfo={nd}&accountName={account_name}';
const LOCAL_BANK_OPTIONS: LocalBankOption[] = [
    { code: 'mbbank', name: 'MBBank', hint: 'Ngân hàng local phổ biến' },
    { code: 'vietcombank', name: 'Vietcombank', hint: 'Dùng cho QR local' },
    { code: 'techcombank', name: 'Techcombank', hint: 'Dễ cấu hình chuyển khoản' },
    { code: 'acb', name: 'ACB', hint: 'Hỗ trợ thêm phương thức local' },
];

const loading = ref(true);
const saving = ref(false);
const deletingId = ref<number | null>(null);
const togglingId = ref<number | null>(null);
const verifying = ref(false);
const showEditorModal = ref(false);
const verifiedUser = ref<Record<string, unknown> | null>(null);
const configs = ref<RechargeConfigType[]>([]);
const bankAccounts = ref<ApiBankVnBankAccountOption[]>([]);
const editingId = ref<number | null>(null);
const form = ref<RechargeConfigForm>(emptyForm());

const isApiProvider = computed(() => form.value.provider === 'apibankvn_api');
const canVerify = computed(() => form.value.api_key.trim() !== '' && form.value.api_secret.trim() !== '' && !verifying.value);
const selectedBank = computed(() => bankAccounts.value.find((bank) => String(bank.bank_id) === form.value.api_bank_id) ?? null);
const selectedLocalBank = computed(
    () => LOCAL_BANK_OPTIONS.find((bank) => bank.name.toLowerCase() === form.value.bank_name.trim().toLowerCase()) ?? null,
);
const normalizedTransferPrefix = computed(() => form.value.transfer_prefix.replace(/[^a-z0-9]/gi, '').toUpperCase());
const previewTransferContent = computed(() => {
    const prefix = (normalizedTransferPrefix.value || 'NAP').slice(0, 4);

    return `${prefix}${'ABC1234567'.slice(0, 10 - prefix.length)}`;
});
const previewQrUrl = computed(() => {
    const template = form.value.qr_template.trim();
    if (!template) {
        return '';
    }

    const bankCode = selectedBank.value?.bank_code || selectedLocalBank.value?.code || 'mbbank';
    const replacements: Record<string, string> = {
        '{bank_code}': encodeURIComponent(bankCode),
        '{bank_name}': encodeURIComponent(form.value.bank_name.trim() || 'MBBank'),
        '{account_name}': encodeURIComponent(form.value.account_name.trim() || 'NGUYEN VAN A'),
        '{account_number}': encodeURIComponent(form.value.account_number.trim() || '0123456789'),
        '{amount}': encodeURIComponent('500000'),
        '{nd}': encodeURIComponent(previewTransferContent.value),
        '{prefix}': encodeURIComponent(normalizedTransferPrefix.value || 'NOIDUNG'),
        '{user_id}': encodeURIComponent('123'),
    };

    return Object.entries(replacements).reduce((result, [key, value]) => result.replaceAll(key, value), template);
});

const methodCards = computed(() =>
    isApiProvider.value
        ? bankAccounts.value.map((bank) => ({
              key: String(bank.bank_id),
              title: bank.bank_name,
              subtitle: `${bank.account_number} - ${bank.account_name}`,
              tag: 'ApiBankVn',
              selected: String(bank.bank_id) === form.value.api_bank_id,
          }))
        : LOCAL_BANK_OPTIONS.map((bank) => ({
              key: bank.code,
              title: bank.name,
              subtitle: bank.hint,
              tag: 'Local',
              selected: bank.name.toLowerCase() === form.value.bank_name.trim().toLowerCase(),
          })),
);

const activeCount = computed(() => configs.value.filter((config) => config.is_active).length);

onMounted(async () => {
    await loadConfigs();
});

async function loadConfigs(): Promise<void> {
    try {
        loading.value = true;
        configs.value = await adminRechargeConfigService.get();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
}

function emptyForm(): RechargeConfigForm {
    return {
        provider: 'manual',
        bank_name: '',
        account_name: '',
        account_number: '',
        qr_template: DEFAULT_QR_TEMPLATE,
        transfer_prefix: 'NAP',
        api_key: '',
        api_secret: '',
        webhook_secret: '',
        api_bank_id: '',
        is_active: true,
    };
}

function openCreateModal(): void {
    editingId.value = null;
    verifiedUser.value = null;
    bankAccounts.value = [];
    form.value = emptyForm();
    showEditorModal.value = true;
}

async function openEditModal(config: RechargeConfigType): Promise<void> {
    editingId.value = config.id;
    form.value = {
        provider: config.provider,
        bank_name: config.bank_name,
        account_name: config.account_name,
        account_number: config.account_number,
        qr_template: config.qr_template || DEFAULT_QR_TEMPLATE,
        transfer_prefix: config.transfer_prefix,
        api_key: config.api_key ?? '',
        api_secret: config.api_secret ?? '',
        webhook_secret: config.webhook_secret ?? '',
        api_bank_id: config.api_bank_id ? String(config.api_bank_id) : '',
        is_active: config.is_active,
    };

    verifiedUser.value = null;
    bankAccounts.value = [];
    showEditorModal.value = true;

    if (config.provider === 'apibankvn_api' && config.api_key && config.api_secret) {
        await verifyCredentials(true);
    }
}

function closeModal(): void {
    showEditorModal.value = false;
}

async function verifyCredentials(silent = false): Promise<void> {
    if (!canVerify.value && !silent) {
        return;
    }

    try {
        verifying.value = true;
        const response = await adminRechargeConfigService.verifyCredentials({
            api_key: form.value.api_key.trim(),
            api_secret: form.value.api_secret.trim(),
        });

        verifiedUser.value = response.user;
        bankAccounts.value = response.bank_accounts;

        if (form.value.api_bank_id) {
            const bank = bankAccounts.value.find((item) => String(item.bank_id) === form.value.api_bank_id);
            if (bank) {
                applySelectedBank(bank);
            }
        }

        if (!silent) {
            handleSuccessResponse({ data: { status: true, message: 'Đã xác thực API và lấy danh sách bank.' } });
        }
    } catch (error) {
        verifiedUser.value = null;
        bankAccounts.value = [];
        if (!silent) {
            handleErrorResponse(error);
        }
    } finally {
        verifying.value = false;
    }
}

function selectMethodCard(key: string): void {
    if (isApiProvider.value) {
        const bank = bankAccounts.value.find((item) => String(item.bank_id) === key);
        applySelectedBank(bank);
        return;
    }

    const bank = LOCAL_BANK_OPTIONS.find((item) => item.code === key);
    if (!bank) {
        return;
    }

    form.value.bank_name = bank.name;
    if (!form.value.qr_template.trim()) {
        form.value.qr_template = DEFAULT_QR_TEMPLATE;
    }
}

function applySelectedBank(bank?: ApiBankVnBankAccountOption | null): void {
    if (!bank) {
        return;
    }

    form.value.api_bank_id = String(bank.bank_id);
    form.value.bank_name = bank.bank_name || bank.bank_code;
    form.value.account_name = bank.account_name;
    form.value.account_number = bank.account_number;

    if (!form.value.qr_template.trim()) {
        form.value.qr_template = DEFAULT_QR_TEMPLATE;
    }
}

async function saveConfig(): Promise<void> {
    try {
        saving.value = true;
        const wasEditing = editingId.value !== null;

        const payload = {
            provider: form.value.provider,
            bank_name: form.value.bank_name.trim(),
            account_name: form.value.account_name.trim(),
            account_number: form.value.account_number.trim(),
            qr_template: form.value.qr_template.trim(),
            transfer_prefix: normalizedTransferPrefix.value,
            api_base_url: isApiProvider.value ? 'https://apibankvn.com' : null,
            api_key: form.value.api_key.trim() || null,
            api_secret: form.value.api_secret.trim() || null,
            webhook_secret: form.value.webhook_secret.trim() || null,
            api_bank_id: form.value.api_bank_id.trim() ? Number(form.value.api_bank_id) : null,
            is_active: form.value.is_active,
        };

        await (editingId.value ? adminRechargeConfigService.update(editingId.value, payload) : adminRechargeConfigService.create(payload));

        await loadConfigs();
        closeModal();
        handleSuccessResponse({ data: { status: true, message: wasEditing ? 'Đã cập nhật cấu hình nạp tiền.' : 'Đã thêm cấu hình ngân hàng.' } });
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
}

async function toggleConfig(config: RechargeConfigType): Promise<void> {
    try {
        togglingId.value = config.id;
        await adminRechargeConfigService.toggle(config.id);
        await loadConfigs();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        togglingId.value = null;
    }
}

async function removeConfig(config: RechargeConfigType): Promise<void> {
    const confirmed = window.confirm(`Xóa cấu hình ${config.bank_name}?`);
    if (!confirmed) {
        return;
    }

    try {
        deletingId.value = config.id;
        await adminRechargeConfigService.remove(config.id);
        await loadConfigs();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        deletingId.value = null;
    }
}
</script>

<template>
    <div class="space-y-4">
        <section class="rounded-[12px] border border-slate-200 bg-white px-5 py-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-2">
                    <div class="flex items-center gap-2 text-sm text-slate-400">
                        <span>Dashboard</span>
                        <span>/</span>
                        <span class="font-medium text-slate-600">Recharge Config</span>
                    </div>
                    <h1 class="text-[28px] font-black tracking-[-0.04em] text-slate-950">Cấu hình nạp tiền</h1>
                    <p class="max-w-2xl text-sm leading-6 text-slate-500">
                        Quản lý danh sách bank local hoặc ApiBankVn. Form thêm và sửa được gom vào modal để page gọn hơn.
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-[10px] border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Tổng bank</p>
                        <p class="mt-2 text-2xl font-black text-slate-950">{{ configs.length }}</p>
                    </div>
                    <div class="rounded-[10px] border border-emerald-200 bg-emerald-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-600">Đang bật</p>
                        <p class="mt-2 text-2xl font-black text-emerald-700">{{ activeCount }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-[12px] border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-black tracking-[-0.03em] text-slate-950">Danh sách bank</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-500">Chỉ giữ lại danh sách thẻ và thao tác nhanh ngay trên màn hình này.</p>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-[10px] bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500"
                    @click="openCreateModal"
                >
                    <Plus class="h-4 w-4" />
                    Add bank
                </button>
            </div>

            <div v-if="loading" class="grid gap-3 pt-4 md:grid-cols-2 xl:grid-cols-3">
                <div v-for="item in 3" :key="item" class="h-36 animate-pulse rounded-[10px] bg-slate-100"></div>
            </div>

            <div v-else-if="configs.length === 0" class="rounded-[10px] border border-dashed border-slate-200 bg-slate-50 px-4 py-10 text-center">
                <p class="text-base font-bold text-slate-900">Chưa có bank nào</p>
                <p class="mt-2 text-sm text-slate-500">Bấm `Add bank` để tạo cấu hình bank đầu tiên.</p>
            </div>

            <div v-else class="grid gap-3 pt-4 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="config in configs"
                    :key="config.id"
                    class="rounded-[10px] border border-slate-200 bg-white p-4 transition hover:border-slate-300 hover:shadow-sm"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-[10px] text-white"
                            :class="config.provider === 'apibankvn_api' ? 'bg-indigo-600' : 'bg-emerald-500'"
                        >
                            <Building2 v-if="config.provider === 'manual'" class="h-4.5 w-4.5" />
                            <ShieldCheck v-else class="h-4.5 w-4.5" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="truncate font-semibold text-slate-950">{{ config.bank_name }}</p>
                                <span
                                    class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                    :class="config.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                                >
                                    {{ config.is_active ? 'Đang bật' : 'Đã tắt' }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-slate-500">{{ config.provider === 'apibankvn_api' ? 'ApiBankVn API' : 'Local bank' }}</p>
                            <p class="mt-2 text-sm font-medium text-slate-700">{{ config.account_number }}</p>
                            <p class="mt-1 truncate text-sm text-slate-500">{{ config.account_name }}</p>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-[10px] border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                            @click="openEditModal(config)"
                        >
                            <Pencil class="h-4 w-4" />
                            Sửa
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-[10px] border px-3 py-2 text-sm font-semibold transition"
                            :class="
                                config.is_active ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'
                            "
                            :disabled="togglingId === config.id"
                            @click="toggleConfig(config)"
                        >
                            <Power class="h-4 w-4" />
                            {{ config.is_active ? 'Đóng' : 'Mở' }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-[10px] border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-100"
                            :disabled="deletingId === config.id"
                            @click="removeConfig(config)"
                        >
                            <Trash2 class="h-4 w-4" />
                            Xóa
                        </button>
                    </div>
                </article>
            </div>
        </section>

        <Modal v-model="showEditorModal" panel-class="max-w-5xl">
            <template #header>
                <div class="border-b border-slate-200 px-5 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-xl font-black tracking-[-0.03em] text-slate-950">
                                {{ editingId ? 'Sửa cấu hình bank' : 'Thêm cấu hình bank' }}
                            </h2>
                            <p class="mt-1 text-sm leading-6 text-slate-500">Chọn local hoặc ApiBankVn, nhập thông tin rồi lưu cấu hình.</p>
                        </div>
                    </div>
                </div>
            </template>

            <div class="grid gap-4 p-5 xl:grid-cols-[minmax(0,1.6fr)_280px]">
                <div class="space-y-4">
                    <div class="grid gap-0 overflow-hidden rounded-[10px] border border-slate-200 bg-slate-50 sm:grid-cols-2">
                        <button
                            type="button"
                            class="flex items-center justify-center gap-2 px-4 py-3 text-sm font-semibold transition"
                            :class="form.provider === 'manual' ? 'bg-indigo-600 text-white' : 'text-slate-700 hover:bg-white'"
                            @click="form.provider = 'manual'"
                        >
                            <Building2 class="h-4 w-4" />
                            Local bank
                        </button>
                        <button
                            type="button"
                            class="flex items-center justify-center gap-2 border-t border-slate-200 px-4 py-3 text-sm font-semibold transition sm:border-l sm:border-t-0"
                            :class="form.provider === 'apibankvn_api' ? 'bg-indigo-600 text-white' : 'text-slate-700 hover:bg-white'"
                            @click="form.provider = 'apibankvn_api'"
                        >
                            <ShieldCheck class="h-4 w-4" />
                            ApiBankVn
                        </button>
                    </div>

                    <div v-if="isApiProvider" class="space-y-3 rounded-[10px] border border-slate-200 bg-slate-50 p-4">
                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="space-y-2">
                                <span class="text-sm font-semibold text-slate-800">YOUR_API_KEY</span>
                                <input
                                    v-model="form.api_key"
                                    type="text"
                                    class="w-full rounded-[10px] border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-indigo-400"
                                />
                            </label>
                            <label class="space-y-2">
                                <span class="text-sm font-semibold text-slate-800">YOUR_API_SECRET</span>
                                <input
                                    v-model="form.api_secret"
                                    type="text"
                                    class="w-full rounded-[10px] border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-indigo-400"
                                />
                            </label>
                            <label class="space-y-2 md:col-span-2">
                                <span class="text-sm font-semibold text-slate-800">WEBHOOK_SECRET</span>
                                <input
                                    v-model="form.webhook_secret"
                                    type="text"
                                    class="w-full rounded-[10px] border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-indigo-400"
                                />
                            </label>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p v-if="verifiedUser" class="text-sm text-slate-600">
                                Đã kết nối với <span class="font-semibold">{{ verifiedUser.username || verifiedUser.email || '--' }}</span
                                >.
                            </p>
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-[10px] border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-100 disabled:opacity-60"
                                :disabled="!canVerify"
                                @click="verifyCredentials()"
                            >
                                <RefreshCw class="h-4 w-4" />
                                {{ verifying ? 'Đang xác thực...' : 'Lấy bank từ API' }}
                            </button>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <p class="text-sm font-semibold text-slate-800">List bank</p>
                        <div v-if="methodCards.length > 0" class="grid gap-3 md:grid-cols-2">
                            <button
                                v-for="method in methodCards"
                                :key="method.key"
                                type="button"
                                class="rounded-[10px] border px-4 py-4 text-left transition"
                                :class="
                                    method.selected ? 'border-indigo-300 bg-indigo-50 shadow-sm' : 'border-slate-200 bg-white hover:border-slate-300'
                                "
                                @click="selectMethodCard(method.key)"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-semibold text-slate-950">{{ method.title }}</p>
                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-500">{{
                                                method.tag
                                            }}</span>
                                        </div>
                                        <p class="mt-1 text-sm leading-6 text-slate-500">{{ method.subtitle }}</p>
                                    </div>
                                    <div v-if="method.selected" class="h-2.5 w-2.5 rounded-full bg-indigo-500"></div>
                                </div>
                            </button>
                        </div>
                        <div
                            v-else
                            class="rounded-[10px] border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500"
                        >
                            {{
                                isApiProvider
                                    ? 'Chưa có bank từ API. Hãy bấm lấy bank từ API trước.'
                                    : 'Chọn một bank local hoặc nhập tay thông tin bên dưới.'
                            }}
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <label class="space-y-2">
                            <span class="text-sm font-semibold text-slate-800">Tên ngân hàng</span>
                            <input
                                v-model="form.bank_name"
                                type="text"
                                class="w-full rounded-[10px] border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-indigo-400"
                            />
                        </label>
                        <label class="space-y-2">
                            <span class="text-sm font-semibold text-slate-800">Số tài khoản</span>
                            <input
                                v-model="form.account_number"
                                type="text"
                                class="w-full rounded-[10px] border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-indigo-400"
                            />
                        </label>
                        <label class="space-y-2 md:col-span-2">
                            <span class="text-sm font-semibold text-slate-800">Chủ tài khoản</span>
                            <input
                                v-model="form.account_name"
                                type="text"
                                class="w-full rounded-[10px] border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-indigo-400"
                            />
                        </label>
                        <label class="space-y-2 md:col-span-2">
                            <span class="text-sm font-semibold text-slate-800">QR template</span>
                            <textarea
                                v-model="form.qr_template"
                                rows="3"
                                class="w-full rounded-[10px] border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-indigo-400"
                            ></textarea>
                        </label>
                        <label class="space-y-2">
                            <span class="text-sm font-semibold text-slate-800">Tiền tố nội dung</span>
                            <input
                                v-model="form.transfer_prefix"
                                type="text"
                                maxlength="4"
                                pattern="[A-Za-z0-9]+"
                                autocomplete="off"
                                class="w-full rounded-[10px] border border-slate-200 bg-white px-4 py-3 text-sm uppercase outline-none transition focus:border-indigo-400"
                            />
                            <span class="block text-xs leading-5 text-slate-500"
                                >Chỉ dùng chữ và số, tối đa 4 ký tự. Toàn bộ nội dung chuyển khoản luôn dài đúng 10 ký tự.</span
                            >
                        </label>
                        <label class="flex items-center justify-between rounded-[10px] border border-slate-200 bg-white px-4 py-3">
                            <span class="text-sm font-semibold text-slate-800">Bật cấu hình</span>
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            />
                        </label>
                    </div>
                </div>

                <aside class="space-y-4">
                    <article class="rounded-[10px] border border-slate-200 bg-slate-50 p-4">
                        <h3 class="text-base font-bold text-slate-950">Preview client</h3>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-slate-500">Provider</dt>
                                <dd class="font-semibold text-slate-900">{{ isApiProvider ? 'Apibankvn API' : 'Local bank' }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-slate-500">Bank</dt>
                                <dd class="font-semibold text-slate-900">{{ form.bank_name || '--' }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-slate-500">Số tài khoản</dt>
                                <dd class="font-semibold text-slate-900">{{ form.account_number || '--' }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-slate-500">Nội dung</dt>
                                <dd class="font-bold text-indigo-600">{{ previewTransferContent }}</dd>
                            </div>
                        </dl>
                    </article>

                    <article class="rounded-[10px] border border-slate-200 bg-slate-50 p-4">
                        <h3 class="text-base font-bold text-slate-950">QR preview</h3>
                        <p class="mt-3 break-all text-xs leading-5 text-slate-600">{{ previewQrUrl || '--' }}</p>
                    </article>
                </aside>
            </div>

            <template #footer>
                <div class="border-t border-slate-200 px-5 py-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-[10px] border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                            @click="closeModal"
                        >
                            Đóng
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-[10px] bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:opacity-60"
                            :disabled="saving"
                            @click="saveConfig"
                        >
                            {{ saving ? 'Đang lưu...' : editingId ? 'Lưu thay đổi' : 'Thêm bank' }}
                        </button>
                    </div>
                </div>
            </template>
        </Modal>
    </div>
</template>
