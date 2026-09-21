<script setup lang="ts">
import { adminSettingService } from '@/services/admin-setting.service';
import type { TaxSettingType } from '@/types/setting.type';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { Calculator, CircleAlert, LoaderCircle, ReceiptText, Save, TrendingDown, TrendingUp } from 'lucide-vue-next';
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
    <div v-if="loading" class="grid min-h-80 place-items-center rounded-xl border border-slate-200 bg-white shadow-sm">
        <LoaderCircle class="h-7 w-7 animate-spin text-indigo-500" />
    </div>
    <section v-else class="rounded-xl border border-slate-200 bg-white p-4 shadow-[0_12px_32px_rgba(15,23,42,0.06)] sm:p-6">
        <header>
            <h2 class="text-xl font-black tracking-tight text-slate-950 sm:text-2xl">Thuế & lợi nhuận</h2>
            <p class="mt-1 text-sm text-slate-500">Cấu hình thuế dự kiến và snapshot lợi nhuận ròng của đơn hàng.</p>
        </header>

        <div class="mt-6 grid gap-4 xl:grid-cols-[minmax(0,1fr)_390px]">
            <form class="grid content-start gap-6 rounded-xl border border-slate-200 bg-white p-4 sm:p-6" @submit.prevent="save">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex min-w-0 items-start gap-4">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-indigo-50 text-indigo-600">
                            <ReceiptText class="h-6 w-6" />
                        </span>
                        <div class="min-w-0">
                            <h3 class="text-lg font-black text-slate-950">Thuế dự kiến</h3>
                            <p class="mt-0.5 max-w-2xl text-sm leading-6 text-slate-500">
                                Snapshot thuế và lợi nhuận ròng ngay khi tạo đơn. Thay đổi sau này không ảnh hưởng đơn cũ.
                            </p>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-3 sm:justify-end">
                        <label class="relative inline-flex cursor-pointer items-center">
                            <input v-model="form.tax_enabled" type="checkbox" class="peer sr-only" />
                            <span
                                class="h-7 w-12 rounded-full bg-slate-300 transition after:absolute after:left-1 after:top-1 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform after:content-[''] peer-checked:bg-indigo-600 peer-checked:after:translate-x-5 peer-focus-visible:ring-4 peer-focus-visible:ring-indigo-100"
                            ></span>
                            <span class="ml-3 text-sm font-black text-slate-800">Bật tính thuế</span>
                        </label>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="grid gap-2 text-sm font-black text-slate-800">
                        Thuế GTGT dự kiến (%)
                        <span class="relative block">
                            <input
                                v-model="form.vat_rate"
                                type="number"
                                min="0"
                                max="100"
                                step="0.0001"
                                class="ui-focus min-h-12 w-full rounded-lg border border-slate-300 bg-white px-4 pr-12 font-bold text-slate-900 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                            />
                            <span
                                class="pointer-events-none absolute inset-y-px right-px grid w-11 place-items-center rounded-r-lg bg-slate-50 font-bold text-slate-500"
                                >%</span
                            >
                        </span>
                    </label>
                    <label class="grid gap-2 text-sm font-black text-slate-800">
                        Thuế TNCN dự kiến (%)
                        <span class="relative block">
                            <input
                                v-model="form.pit_rate"
                                type="number"
                                min="0"
                                max="100"
                                step="0.0001"
                                class="ui-focus min-h-12 w-full rounded-lg border border-slate-300 bg-white px-4 pr-12 font-bold text-slate-900 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                            />
                            <span
                                class="pointer-events-none absolute inset-y-px right-px grid w-11 place-items-center rounded-r-lg bg-slate-50 font-bold text-slate-500"
                                >%</span
                            >
                        </span>
                    </label>
                </div>

                <label class="grid gap-2 text-sm font-black text-slate-800">
                    Phương pháp tính
                    <select
                        v-model="form.tax_calculation_type"
                        class="ui-focus min-h-12 rounded-lg border border-slate-300 bg-white px-4 font-semibold text-slate-900"
                    >
                        <option value="revenue">Theo doanh thu bán ra</option>
                        <option value="profit" disabled>Theo lợi nhuận — sẽ hỗ trợ sau</option>
                    </select>
                    <span class="text-xs font-medium leading-5 text-slate-500">
                        Thuế được tính trên toàn bộ giá bán của đơn, không tính trên phần chênh lệch.
                    </span>
                </label>

                <div class="flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50/70 p-4 text-sm leading-6 text-amber-800">
                    <CircleAlert class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" />
                    <p>Đây là số thuế dự kiến phục vụ quản trị lợi nhuận, không phải số thuế đã kê khai hoặc đã nộp.</p>
                </div>

                <button
                    type="submit"
                    :disabled="saving"
                    class="ui-focus inline-flex min-h-12 items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-violet-600 px-6 font-black text-white shadow-sm transition hover:from-indigo-700 hover:to-violet-700 disabled:cursor-not-allowed disabled:opacity-50 sm:justify-self-start"
                >
                    <LoaderCircle v-if="saving" class="h-5 w-5 animate-spin" />
                    <Save v-else class="h-5 w-5" />
                    {{ saving ? 'Đang lưu...' : 'Lưu cấu hình thuế' }}
                </button>
            </form>

            <aside class="rounded-xl border border-slate-200 bg-gradient-to-br from-white to-slate-50 p-4 sm:p-6">
                <div class="flex items-start gap-4">
                    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-indigo-50 text-indigo-600">
                        <Calculator class="h-6 w-6" />
                    </span>
                    <div>
                        <h3 class="text-lg font-black text-slate-950">Xem trước lợi nhuận</h3>
                        <p class="mt-0.5 text-sm text-slate-500">Ước tính kết quả sau khi áp dụng thuế</p>
                    </div>
                </div>

                <dl class="mt-7 grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-4 text-sm">
                    <dt class="text-slate-500">Giá bán</dt>
                    <dd class="text-base font-black text-slate-950">{{ money(preview.salePrice) }}</dd>
                    <dt class="text-slate-500">Giá vốn</dt>
                    <dd class="text-base font-black text-slate-950">{{ money(preview.costPrice) }}</dd>

                    <dt class="border-t border-slate-200 pt-4 text-slate-500">Lãi gộp</dt>
                    <dd class="border-t border-slate-200 pt-4 text-base font-black text-slate-950">{{ money(grossProfit) }}</dd>
                    <dt class="text-slate-500">VAT dự kiến</dt>
                    <dd class="font-bold text-slate-700">{{ money(estimatedVat) }}</dd>
                    <dt class="text-slate-500">TNCN dự kiến</dt>
                    <dd class="font-bold text-slate-700">{{ money(estimatedPit) }}</dd>

                    <dt class="border-t border-slate-200 pt-4 text-slate-500">Tổng thuế dự kiến</dt>
                    <dd class="border-t border-slate-200 pt-4 text-base font-black text-amber-700">{{ money(estimatedTax) }}</dd>
                </dl>

                <div
                    class="mt-6 rounded-xl border p-5"
                    :class="
                        netProfit < 0
                            ? 'border-rose-200 bg-gradient-to-br from-rose-50 to-rose-100 text-rose-800'
                            : 'border-emerald-200 bg-gradient-to-br from-emerald-50 to-emerald-100 text-emerald-800'
                    "
                >
                    <div class="flex items-start gap-4">
                        <TrendingDown v-if="netProfit < 0" class="mt-1 h-7 w-7 shrink-0" />
                        <TrendingUp v-else class="mt-1 h-7 w-7 shrink-0" />
                        <div>
                            <p class="text-sm font-black">Lãi ròng dự kiến</p>
                            <p class="mt-1 text-3xl font-black tracking-tight">{{ money(netProfit) }}</p>
                            <p class="mt-1 text-sm font-medium">Biên lợi nhuận {{ margin.toFixed(2) }}%</p>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </section>
</template>
