<script setup lang="ts">
import { ArrowDownRight, ArrowUpRight, BadgeCheck, CircleDollarSign, Minus, Zap } from 'lucide-vue-next';
import { computed } from 'vue';
import { formatMoney, providerQuote, type PriceRow, type Provider } from '../types';

const props = defineProps<{
    row: PriceRow;
    provider: Provider;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    select: [row: PriceRow, provider: Provider];
}>();

const sourcePrice = computed(() => providerQuote(props.row, props.provider.id));
const isCurrent = computed(() => props.row.provider_id === props.provider.id);
const isCheapest = computed(() => props.row.best_provider_id === props.provider.id && sourcePrice.value !== null);
const expectedProfit = computed(() => (sourcePrice.value === null ? null : props.row.price - sourcePrice.value));
const difference = computed(() => (sourcePrice.value === null || !props.row.provider_id ? null : sourcePrice.value - props.row.provider_price));
const saving = computed(() => (difference.value !== null && difference.value < 0 ? Math.abs(difference.value) : 0));
</script>

<template>
    <div
        class="flex min-h-[156px] flex-col rounded-xl border p-3 transition-colors"
        :class="[isCheapest ? 'border-emerald-200 bg-emerald-50/70' : isCurrent ? 'border-blue-200 bg-blue-50/70' : 'border-slate-200 bg-white']"
    >
        <div class="flex min-h-6 flex-wrap items-center gap-1.5">
            <span
                v-if="isCurrent"
                class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-1 text-[10px] font-black uppercase tracking-wide text-blue-700"
            >
                <BadgeCheck class="h-3 w-3" /> Đang dùng
            </span>
            <span
                v-if="isCheapest"
                class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-black uppercase tracking-wide text-emerald-700"
            >
                <CircleDollarSign class="h-3 w-3" /> Rẻ nhất
            </span>
            <span
                v-if="!isCurrent && !isCheapest && difference !== null"
                class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-600"
            >
                <ArrowUpRight v-if="difference > 0" class="h-3 w-3" />
                <ArrowDownRight v-else-if="difference < 0" class="h-3 w-3" />
                <Minus v-else class="h-3 w-3" />
                {{ difference > 0 ? 'Cao hơn' : difference < 0 ? 'Thấp hơn' : 'Bằng nhau' }}
            </span>
        </div>

        <div v-if="sourcePrice !== null" class="mt-3 grid grid-cols-2 gap-3">
            <div>
                <p class="text-[11px] font-semibold text-slate-500">Giá nguồn</p>
                <p class="mt-0.5 font-black text-slate-950">{{ formatMoney(sourcePrice) }}</p>
            </div>
            <div>
                <p class="text-[11px] font-semibold text-slate-500">Lãi dự kiến</p>
                <p class="mt-0.5 font-black" :class="(expectedProfit ?? 0) > 0 ? 'text-emerald-700' : 'text-rose-600'">
                    {{ formatMoney(expectedProfit) }}
                </p>
            </div>
        </div>
        <div v-else class="mt-3 flex flex-1 items-center text-sm font-semibold text-slate-400">Chưa có báo giá</div>

        <div v-if="sourcePrice !== null" class="mt-2 min-h-5 text-xs font-bold">
            <span v-if="saving > 0" class="text-emerald-700">Tiết kiệm +{{ formatMoney(saving) }} so với hiện tại</span>
            <span v-else-if="difference !== null && difference > 0" class="text-rose-600">Cao hơn +{{ formatMoney(difference) }}</span>
            <span v-else-if="isCurrent" class="text-blue-700">Nguồn đang áp dụng cho gói</span>
        </div>

        <button
            type="button"
            :disabled="disabled"
            class="ui-focus mt-auto inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg border px-3 text-xs font-black transition-colors disabled:cursor-not-allowed disabled:opacity-40"
            :class="
                isCurrent
                    ? 'border-blue-200 bg-blue-600 text-white hover:bg-blue-700'
                    : 'border-slate-300 bg-white text-slate-800 hover:border-blue-300 hover:text-blue-700'
            "
            @click="emit('select', row, provider)"
        >
            <Zap class="h-3.5 w-3.5" /> {{ isCurrent ? 'Cập nhật' : sourcePrice === null ? 'Nhập giá' : 'Chọn nguồn' }}
        </button>
    </div>
</template>
