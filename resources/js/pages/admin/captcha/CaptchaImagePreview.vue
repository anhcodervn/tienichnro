<script setup lang="ts">
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/vue';
import { Expand, RotateCcw, X, ZoomIn, ZoomOut } from 'lucide-vue-next';
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { maxImageZoom, minImageZoom, moveImageDrag, startImageDrag, stopImageDrag, zoomImage } from './image-viewer.js';

const props = defineProps<{ src: string; solveId?: number }>();
const opened = ref(false);
const viewport = ref<HTMLElement | null>(null);
const size = ref({ width: 200, height: 80 });
const state = reactive({ scale: 1, drag: null as null | { x: number; y: number; left: number; top: number } });
const percent = computed(() => Math.round(state.scale * 100));
const center = async () => {
    await nextTick();
    if (!viewport.value) return;
    viewport.value.scrollLeft = (viewport.value.scrollWidth - viewport.value.clientWidth) / 2;
    viewport.value.scrollTop = (viewport.value.scrollHeight - viewport.value.clientHeight) / 2;
};
const zoom = (factor: number) => {
    stopImageDrag(state);
    zoomImage(state, factor);
    void center();
};
const reset = () => {
    state.scale = 1;
    stopImageDrag(state);
    void center();
};
const open = () => {
    opened.value = true;
    reset();
};
const close = () => {
    opened.value = false;
    stopImageDrag(state);
};
const loaded = (event: Event) => {
    const image = event.target as HTMLImageElement;
    size.value = { width: image.naturalWidth, height: image.naturalHeight };
    void center();
};
const keydown = (event: KeyboardEvent) => {
    if (event.key === '+' || event.key === '=') {
        event.preventDefault();
        zoom(1.25);
    } else if (event.key === '-') {
        event.preventDefault();
        zoom(0.8);
    } else if (event.key === '0') {
        event.preventDefault();
        reset();
    }
};
watch(() => props.src, close);
</script>

<template>
    <button
        type="button"
        class="group grid w-full justify-items-center gap-3 rounded-[10px] border border-slate-200 bg-slate-50 p-5 hover:border-emerald-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-600 dark:border-slate-700 dark:bg-slate-800"
        aria-label="Mở ảnh captcha trong lightbox"
        @click="open"
    >
        <img :src="src" alt="Ảnh captcha lưu trong lịch sử" class="max-h-48 max-w-full rounded-[10px] border bg-white" @load="loaded" />
        <span class="inline-flex items-center gap-2 text-sm font-medium text-emerald-700 dark:text-emerald-300"
            ><Expand class="h-4 w-4" />Bấm để phóng to ảnh</span
        >
    </button>
    <Dialog :open="opened" class="relative z-[70]" @close="close">
        <div class="fixed inset-0 bg-slate-950/90" aria-hidden="true"></div>
        <div class="fixed inset-0 flex items-center justify-center p-3 sm:p-6">
            <DialogPanel
                class="flex h-[90dvh] w-full max-w-6xl flex-col overflow-hidden rounded-[10px] border border-slate-700 bg-slate-900 text-white shadow-2xl"
                @keydown="keydown"
            >
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-700 p-3 sm:p-4">
                    <DialogTitle class="font-semibold">Ảnh captcha · #{{ solveId }}</DialogTitle>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            :disabled="state.scale <= minImageZoom"
                            class="rounded-[10px] border border-slate-600 p-2 hover:bg-slate-700 disabled:opacity-40"
                            aria-label="Thu nhỏ ảnh"
                            @click="zoom(0.8)"
                        >
                            <ZoomOut class="h-5 w-5" />
                        </button>
                        <span class="w-14 text-center text-sm tabular-nums" aria-live="polite">{{ percent }}%</span>
                        <button
                            type="button"
                            :disabled="state.scale >= maxImageZoom"
                            class="rounded-[10px] border border-slate-600 p-2 hover:bg-slate-700 disabled:opacity-40"
                            aria-label="Phóng to ảnh"
                            @click="zoom(1.25)"
                        >
                            <ZoomIn class="h-5 w-5" />
                        </button>
                        <button
                            type="button"
                            class="rounded-[10px] border border-slate-600 p-2 hover:bg-slate-700"
                            aria-label="Đưa ảnh về 100%"
                            @click="reset"
                        >
                            <RotateCcw class="h-5 w-5" />
                        </button>
                        <button
                            type="button"
                            class="rounded-[10px] border border-slate-600 p-2 hover:bg-slate-700"
                            aria-label="Đóng lightbox"
                            @click="close"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                </div>
                <div
                    ref="viewport"
                    class="min-h-0 flex-1 touch-none select-none overflow-auto overscroll-contain"
                    :class="state.drag ? 'cursor-grabbing' : 'cursor-grab'"
                    @wheel.prevent="zoom($event.deltaY < 0 ? 1.1 : 1 / 1.1)"
                    @pointerdown="startImageDrag(state, $event, viewport)"
                    @pointermove="moveImageDrag(state, $event, viewport)"
                    @pointerup="stopImageDrag(state)"
                    @pointercancel="stopImageDrag(state)"
                    @lostpointercapture="stopImageDrag(state)"
                >
                    <div
                        class="grid min-h-full min-w-full place-items-center p-8"
                        :style="{ width: size.width * state.scale + 64 + 'px', height: size.height * state.scale + 64 + 'px' }"
                    >
                        <img
                            :src="src"
                            alt="Ảnh captcha phóng to"
                            draggable="false"
                            class="block max-w-none bg-white"
                            :style="{ width: size.width * state.scale + 'px', height: size.height * state.scale + 'px' }"
                            @load="loaded"
                            @dblclick="reset"
                        />
                    </div>
                </div>
                <p class="border-t border-slate-700 px-4 py-3 text-center text-xs text-slate-300">
                    Cuộn để phóng to / thu nhỏ · Kéo để di chuyển · Phím + / − để zoom, 0 để đặt lại · Esc để đóng
                </p>
            </DialogPanel>
        </div>
    </Dialog>
</template>
