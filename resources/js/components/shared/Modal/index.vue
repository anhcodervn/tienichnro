<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/60 p-4 backdrop-blur-sm">
                <Transition name="scale">
                    <div
                        v-if="modelValue"
                        role="dialog"
                        aria-modal="true"
                        class="relative flex max-h-[calc(100vh-2rem)] w-full flex-col overflow-hidden rounded-2xl bg-white shadow-[0_28px_90px_-20px_rgba(15,23,42,0.5)]"
                        :class="panelClass ?? 'max-w-md'"
                    >
                        <div v-if="$slots.header" class="shrink-0 text-lg font-semibold">
                            <slot name="header" />
                        </div>

                        <button
                            type="button"
                            class="ui-focus absolute right-4 top-4 z-10 rounded-xl border border-slate-200 bg-white p-2 text-slate-500 shadow-sm hover:bg-slate-50 hover:text-slate-900"
                            :class="iconClass"
                            title="Đóng"
                            aria-label="Đóng hộp thoại"
                            @click="close"
                        >
                            <X class="h-4 w-4" />
                        </button>

                        <div class="min-h-0 flex-1 overflow-y-auto">
                            <slot />
                        </div>

                        <div v-if="$slots.footer" class="flex shrink-0 justify-end gap-2">
                            <slot name="footer" />
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup lang="ts">
import { X } from 'lucide-vue-next';

defineProps<{
    modelValue: boolean;
    iconClass?: string;
    panelClass?: string;
}>();

const emit = defineEmits(['update:modelValue']);

const close = () => {
    emit('update:modelValue', false);
};
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.scale-enter-active {
    transition: all 0.2s ease;
}

.scale-enter-from {
    transform: scale(0.96);
    opacity: 0;
}

.scale-leave-active {
    transition: all 0.15s ease;
}

.scale-leave-to {
    transform: scale(0.96);
    opacity: 0;
}
</style>
