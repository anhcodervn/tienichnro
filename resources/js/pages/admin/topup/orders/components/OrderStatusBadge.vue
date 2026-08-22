<script setup lang="ts">
import { BadgeCheck, Ban, CircleDollarSign, CircleX, Clock3, LoaderCircle, TriangleAlert } from 'lucide-vue-next';
import { computed, type Component } from 'vue';

const props = defineProps<{
    kind: 'payment' | 'order';
    status: string;
}>();

type StatusView = {
    label: string;
    classes: string;
    icon: Component;
};

const view = computed<StatusView>(() => {
    if (props.kind === 'payment') {
        const statuses: Record<string, StatusView> = {
            paid: { label: 'Đã thanh toán', classes: 'border-emerald-200 bg-emerald-50 text-emerald-700', icon: BadgeCheck },
            pending: { label: 'Chờ thanh toán', classes: 'border-amber-200 bg-amber-50 text-amber-700', icon: Clock3 },
            refunded: { label: 'Đã hoàn tiền', classes: 'border-sky-200 bg-sky-50 text-sky-700', icon: CircleDollarSign },
            cancelled: { label: 'Đã hủy', classes: 'border-slate-200 bg-slate-100 text-slate-600', icon: Ban },
            expired: { label: 'Hết hạn', classes: 'border-slate-200 bg-slate-100 text-slate-600', icon: Ban },
        };

        return (
            statuses[props.status] ?? {
                label: props.status || 'Chưa rõ',
                classes: 'border-slate-200 bg-slate-100 text-slate-600',
                icon: TriangleAlert,
            }
        );
    }

    const statuses: Record<string, StatusView> = {
        completed: { label: 'Hoàn thành', classes: 'border-emerald-200 bg-emerald-50 text-emerald-700', icon: BadgeCheck },
        processing: { label: 'Đang xử lý', classes: 'border-indigo-200 bg-indigo-50 text-indigo-700', icon: LoaderCircle },
        failed: { label: 'Báo lỗi', classes: 'border-rose-200 bg-rose-50 text-rose-700', icon: CircleX },
        cancelled: { label: 'Đã hủy', classes: 'border-slate-200 bg-slate-100 text-slate-600', icon: Ban },
        pending: { label: 'Chờ xử lý', classes: 'border-amber-200 bg-amber-50 text-amber-700', icon: Clock3 },
    };

    return (
        statuses[props.status] ?? { label: props.status || 'Chưa rõ', classes: 'border-slate-200 bg-slate-100 text-slate-600', icon: TriangleAlert }
    );
});
</script>

<template>
    <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-bold" :class="view.classes">
        <component :is="view.icon" class="h-3.5 w-3.5" :class="status === 'processing' ? 'animate-spin' : ''" aria-hidden="true" />
        {{ view.label }}
    </span>
</template>
