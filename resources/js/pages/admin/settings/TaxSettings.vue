<script setup lang="ts">
import { adminSettingService } from '@/services/admin-setting.service';
import type { TaxSettingType } from '@/types/setting.type';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { Calculator, CircleAlert, LoaderCircle, ReceiptText, Save, TrendingDown } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';

const loading = ref(true);
const saving = ref(false);
const form = reactive<TaxSettingType>({
    tax_enabled: false,
    tax_calculation_type: 'revenue',
    vat_rate: '1.0000',
    pit_rate: '0.5000',
});
const preview = reactive({ salePrice: 805_000, costPrice: 803_000 });

const roundTax = (amount: number, rate: string | number): number => Math.round((amount * Number(rate || 0)) / 100);
const estimatedVat = computed(() => (form.tax_enabled ? roundTax(preview.salePrice, form.vat_rate) : 0));
const estimatedPit = computed(() => (form.tax_enabled ? roundTax(preview.salePrice, form.pit_rate) : 0));
const estimatedTax = computed(() => estimatedVat.value + estimatedPit.value);
const grossProfit = computed(() => preview.salePrice - preview.costPrice);
const netProfit = computed(() => grossProfit.value - estimatedTax.value);
const margin = computed(() => (preview.salePrice > 0 ? (netProfit.value / preview.salePrice) * 100 : 0));
const money = (value: number): string => `${new Intl.NumberFormat('vi-VN').format(value)}đ`;

const load = async (): Promise<void> => {
    loading.value = true;
    try {
        const response = await adminSettingService.getTax();
        Object.assign(form, response.settings);
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

const save = async (): Promise<void> => {
    saving.value = true;
    try {
        const response = await adminSettingService.updateTax({
            tax_enabled: form.tax_enabled,
            tax_calculation_type: 'revenue',
            vat_rate: String(form.vat_rate),
            pit_rate: String(form.pit_rate),
        });
        Object.assign(form, response.settings);
        handleSuccessResponse({ data: { status: true, message: 'Đã lưu cấu hình thuế dự kiến.' } });
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
};

onMounted(load);
</script>

<template>
    <div v-if="loading" class="grid min-h-64 place-items-center rounded-xl border border-slate-200 bg-white">
        <LoaderCircle class="h-7 w-7 animate-spin text-indigo-500" />
    </div>
    <div v-else class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_380px]">
        <form class="grid gap-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="save">
            <div class="flex flex-col gap-3 border-b border-slate-100 pb-5 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="flex items-center gap-2 text-base font-black text-slate-950">
                        <ReceiptText class="h-5 w-5 text-indigo-600" /> Thuế dự kiến
                    </h3>
                    <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">
                        Snapshot thuế và lợi nhuận ròng ngay khi tạo đơn. Thay đổi sau này không ảnh hưởng đơn cũ.
                    </p>
                </div>
                <label
                    class="flex min-h-11 shrink-0 items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-700"
                >
                    <input
                        v-model="form.tax_enabled"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    Bật tính thuế
                </label>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                    Thuế GTGT dự kiến (%)
                    <input
                        v-model="form.vat_rate"
                        type="number"
                        min="0"
                        max="100"
                        step="0.0001"
                        class="ui-focus min-h-11 rounded-lg border border-slate-300 bg-white text-right font-black"
                    />
                </label>
                <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                    Thuế TNCN dự kiến (%)
                    <input
                        v-model="form.pit_rate"
                        type="number"
                        min="0"
                        max="100"
                        step="0.0001"
                        class="ui-focus min-h-11 rounded-lg border border-slate-300 bg-white text-right font-black"
                    />
                </label>
            </div>

            <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                Phương pháp tính
                <select v-model="form.tax_calculation_type" class="ui-focus min-h-11 rounded-lg border border-slate-300 bg-white font-semibold">
                    <option value="revenue">Theo doanh thu bán ra</option>
                    <option value="profit" disabled>Theo lợi nhuận — sẽ hỗ trợ sau</option>
                </select>
                <span class="text-xs font-medium text-slate-500">Thuế được tính trên toàn bộ giá bán của đơn, không tính trên phần chênh lệch.</span>
            </label>

            <div class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-800">
                <CircleAlert class="mt-0.5 h-4 w-4 shrink-0" />
                <p>Đây là số thuế dự kiến phục vụ quản trị lợi nhuận, không phải số thuế đã kê khai hoặc đã nộp.</p>
            </div>

            <button
                type="submit"
                :disabled="saving"
                class="ui-focus inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-indigo-600 px-5 font-black text-white hover:bg-indigo-700 disabled:opacity-50 sm:justify-self-start"
            >
                <LoaderCircle v-if="saving" class="h-4 w-4 animate-spin" /><Save v-else class="h-4 w-4" />
                {{ saving ? 'Đang lưu...' : 'Lưu cấu hình thuế' }}
            </button>
        </form>

        <aside class="rounded-xl border border-slate-200 bg-slate-50 p-5">
            <h3 class="flex items-center gap-2 text-sm font-black text-slate-950">
                <Calculator class="h-5 w-5 text-indigo-600" /> Xem trước lợi nhuận
            </h3>
            <div class="mt-4 grid grid-cols-2 gap-3">
                <label class="grid gap-1 text-xs font-bold text-slate-600"
                    >Giá bán<input
                        v-model.number="preview.salePrice"
                        type="number"
                        min="0"
                        class="ui-focus min-h-10 rounded-lg border border-slate-300 bg-white text-right text-sm font-black"
                /></label>
                <label class="grid gap-1 text-xs font-bold text-slate-600"
                    >Giá vốn<input
                        v-model.number="preview.costPrice"
                        type="number"
                        min="0"
                        class="ui-focus min-h-10 rounded-lg border border-slate-300 bg-white text-right text-sm font-black"
                /></label>
            </div>
            <dl class="mt-5 grid grid-cols-[1fr_auto] gap-x-3 gap-y-3 text-sm">
                <dt class="text-slate-500">Lãi gộp</dt>
                <dd class="font-black text-slate-900">{{ money(grossProfit) }}</dd>
                <dt class="text-slate-500">VAT dự kiến</dt>
                <dd class="font-bold text-slate-700">{{ money(estimatedVat) }}</dd>
                <dt class="text-slate-500">TNCN dự kiến</dt>
                <dd class="font-bold text-slate-700">{{ money(estimatedPit) }}</dd>
                <dt class="border-t border-slate-200 pt-3 text-slate-500">Tổng thuế dự kiến</dt>
                <dd class="border-t border-slate-200 pt-3 font-black text-amber-700">{{ money(estimatedTax) }}</dd>
            </dl>
            <div class="mt-5 rounded-xl p-4" :class="netProfit < 0 ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800'">
                <p class="flex items-center gap-1.5 text-xs font-bold uppercase"><TrendingDown class="h-4 w-4" /> Lãi ròng dự kiến</p>
                <p class="mt-1 text-2xl font-black">{{ money(netProfit) }}</p>
                <p class="mt-1 text-xs font-bold">Biên lợi nhuận {{ margin.toFixed(2) }}%</p>
            </div>
        </aside>
    </div>
</template>
