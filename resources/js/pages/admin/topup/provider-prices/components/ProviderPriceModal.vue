<script setup lang="ts">
import Modal from '@/components/shared/Modal/index.vue';
import type { TaxSettingType } from '@/types/setting.type';
import { ArrowLeftRight, BadgePercent, Check, LoaderCircle, TrendingUp, WalletCards } from 'lucide-vue-next';
import { computed, reactive, watch } from 'vue';
import { formatMoney, providerQuote, type PriceRow, type Provider, type ProviderSelection } from '../types';

const props = defineProps<{
    open: boolean;
    row: PriceRow | null;
    providers: Provider[];
    initialProviderId: number | null;
    saving: boolean;
    taxSettings: TaxSettingType;
}>();

const emit = defineEmits<{
    close: [];
    submit: [selection: ProviderSelection];
}>();

const form = reactive({ providerId: 0, providerPrice: 0, salePrice: 0 });
const selectedProvider = computed(() => props.providers.find((provider) => provider.id === form.providerId) ?? null);
const currentProvider = computed(() => props.providers.find((provider) => provider.id === props.row?.provider_id) ?? null);
const salePrice = computed(() => Math.max(0, Number(form.salePrice) || 0));
const providerPrice = computed(() => Math.max(0, Number(form.providerPrice) || 0));
const grossProfit = computed(() => salePrice.value - providerPrice.value);
const estimatedVat = computed(() =>
    props.taxSettings.tax_enabled ? Math.round((salePrice.value * Number(props.taxSettings.vat_rate || 0)) / 100) : 0,
);
const estimatedPit = computed(() =>
    props.taxSettings.tax_enabled ? Math.round((salePrice.value * Number(props.taxSettings.pit_rate || 0)) / 100) : 0,
);
const estimatedTax = computed(() => estimatedVat.value + estimatedPit.value);
const netProfit = computed(() => grossProfit.value - estimatedTax.value);
const margin = computed(() => (salePrice.value > 0 ? (netProfit.value * 100) / salePrice.value : 0));
const providerSavings = computed(() => (props.row ? props.row.provider_price - form.providerPrice : 0));
const isCurrentProvider = computed(() => props.row?.provider_id === form.providerId);
const canSubmit = computed(() => form.providerId > 0 && form.providerPrice >= 0 && form.salePrice >= form.providerPrice && !props.saving);

const syncForm = (): void => {
    if (!props.row || !props.open) return;

    form.providerId = props.initialProviderId ?? props.row.provider_id ?? props.providers[0]?.id ?? 0;
    form.providerPrice = providerQuote(props.row, form.providerId) ?? (props.row.provider_id === form.providerId ? props.row.provider_price : 0);
    form.salePrice = props.row.price;
};

watch(() => [props.open, props.row, props.initialProviderId] as const, syncForm, { immediate: true });
watch(
    () => form.providerId,
    (providerId, previousProviderId) => {
        if (!props.row || !props.open || providerId === previousProviderId) return;
        form.providerPrice = providerQuote(props.row, providerId) ?? (props.row.provider_id === providerId ? props.row.provider_price : 0);
    },
);

const submit = (): void => {
    if (!canSubmit.value) return;
    emit('submit', { providerId: form.providerId, providerPrice: Number(form.providerPrice), salePrice: Number(form.salePrice) });
};
</script>

<template>
    <Modal :model-value="open" panel-class="max-w-xl" @update:model-value="!$event && emit('close')">
        <template #header>
            <div class="border-b border-slate-200 px-5 py-5 pr-16 sm:px-6">
                <div class="flex items-center gap-3">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-600">
                        <ArrowLeftRight class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-lg font-black text-slate-950">{{ isCurrentProvider ? 'Cập nhật nguồn provider' : 'Chọn nguồn provider' }}</h2>
                        <p class="mt-0.5 text-xs font-medium text-slate-500">Thiết lập nguồn cung và giá bán cho gói nạp này</p>
                    </div>
                </div>
            </div>
        </template>

        <form v-if="row" class="grid gap-4 p-5 sm:p-6" @submit.prevent="submit">
            <div class="rounded-xl border border-blue-100 bg-blue-50/70 p-4">
                <p class="text-xs font-black uppercase tracking-wide text-blue-700">{{ row.scope === 'global' ? 'Gói Global' : row.game_name }}</p>
                <p class="mt-1 font-black text-slate-950">{{ row.name }} · {{ formatMoney(row.denomination) }}</p>
                <p class="mt-1 text-xs font-semibold text-slate-500">Giá bán hiện tại: {{ formatMoney(row.price) }}</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                    Provider
                    <select
                        v-model.number="form.providerId"
                        class="ui-focus min-h-11 rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold"
                    >
                        <option v-for="provider in providers" :key="provider.id" :value="provider.id">{{ provider.name }}</option>
                    </select>
                </label>
                <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                    Giá nguồn
                    <div class="relative">
                        <input
                            v-model.number="form.providerPrice"
                            type="number"
                            min="0"
                            required
                            class="ui-focus min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 pr-10 text-right font-black"
                        />
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm font-bold text-slate-400">đ</span>
                    </div>
                </label>
            </div>

            <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                Giá bán mới
                <div class="relative">
                    <input
                        v-model.number="form.salePrice"
                        type="number"
                        min="0"
                        :max="row.original_price"
                        required
                        class="ui-focus min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 pr-10 text-right font-black"
                    />
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm font-bold text-slate-400">đ</span>
                </div>
                <span class="text-xs font-medium text-slate-500">Tối đa {{ formatMoney(row.original_price) }} · Không được thấp hơn giá nguồn.</span>
            </label>

            <div
                class="grid grid-cols-2 divide-x rounded-xl border p-4"
                :class="netProfit >= 0 ? 'divide-emerald-200 border-emerald-200 bg-emerald-50' : 'divide-rose-200 border-rose-200 bg-rose-50'"
            >
                <div class="pr-4">
                    <p class="flex items-center gap-1.5 text-xs font-semibold text-slate-500">
                        <TrendingUp class="h-4 w-4" :class="netProfit >= 0 ? 'text-emerald-600' : 'text-rose-600'" /> Lãi ròng dự kiến
                    </p>
                    <p class="mt-1 text-xl font-black" :class="netProfit >= 0 ? 'text-emerald-700' : 'text-rose-600'">
                        {{ formatMoney(netProfit) }}
                    </p>
                </div>
                <div class="pl-4">
                    <p class="flex items-center gap-1.5 text-xs font-semibold text-slate-500">
                        <BadgePercent class="h-4 w-4" :class="netProfit >= 0 ? 'text-emerald-600' : 'text-rose-600'" /> Biên lợi nhuận ròng
                    </p>
                    <p class="mt-1 text-xl font-black" :class="netProfit >= 0 ? 'text-emerald-700' : 'text-rose-600'">{{ margin.toFixed(2) }}%</p>
                </div>
                <dl
                    class="col-span-2 mt-4 grid grid-cols-[1fr_auto] gap-x-3 gap-y-2 border-t pt-3 text-xs"
                    :class="netProfit >= 0 ? 'border-emerald-200' : 'border-rose-200'"
                >
                    <dt class="text-slate-500">Lãi gộp</dt>
                    <dd class="font-black text-slate-800">{{ formatMoney(grossProfit) }}</dd>
                    <dt class="text-slate-500">
                        Thuế dự kiến
                        <span v-if="taxSettings.tax_enabled" class="font-semibold">
                            (VAT {{ Number(taxSettings.vat_rate).toFixed(2) }}% + TNCN {{ Number(taxSettings.pit_rate).toFixed(2) }}%)
                        </span>
                    </dt>
                    <dd class="font-black text-amber-700">
                        {{ estimatedTax > 0 ? `-${formatMoney(estimatedTax)}` : formatMoney(estimatedTax) }}
                    </dd>
                </dl>
            </div>

            <div class="grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-2">
                <div>
                    <p class="flex items-center gap-1.5 text-xs font-semibold text-slate-500">
                        <WalletCards class="h-4 w-4 text-blue-600" /> Nguồn hiện tại
                    </p>
                    <p class="mt-1 text-sm font-black text-slate-900">
                        {{ currentProvider?.name ?? 'Chưa gắn' }} · {{ formatMoney(row.provider_id ? row.provider_price : null) }}
                    </p>
                </div>
                <div class="border-slate-200 sm:border-l sm:pl-4">
                    <p class="text-xs font-semibold text-slate-500">Chênh lệch khi chuyển</p>
                    <p
                        class="mt-1 text-sm font-black"
                        :class="providerSavings > 0 ? 'text-emerald-700' : providerSavings < 0 ? 'text-rose-600' : 'text-slate-700'"
                    >
                        {{
                            providerSavings > 0
                                ? `Tiết kiệm +${formatMoney(providerSavings)}`
                                : providerSavings < 0
                                  ? `Tăng +${formatMoney(Math.abs(providerSavings))}`
                                  : 'Không đổi giá vốn'
                        }}
                    </p>
                </div>
            </div>

            <p
                v-if="selectedProvider?.balance_status === 'failed'"
                class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs font-semibold text-rose-700"
            >
                Kết nối provider gần nhất báo lỗi. Hãy kiểm tra lại trước khi chọn nguồn này.
            </p>

            <div class="grid gap-3 border-t border-slate-200 pt-4 sm:grid-cols-2">
                <button
                    type="button"
                    class="ui-focus min-h-11 rounded-lg border border-slate-300 bg-white font-bold text-slate-700 hover:bg-slate-50"
                    @click="emit('close')"
                >
                    Hủy
                </button>
                <button
                    type="submit"
                    :disabled="!canSubmit"
                    class="ui-focus inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 font-black text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    <LoaderCircle v-if="props.saving" class="h-4 w-4 animate-spin" />
                    <Check v-else class="h-4 w-4" />
                    {{ isCurrentProvider ? 'Cập nhật & lưu' : 'Chọn nguồn & lưu' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
