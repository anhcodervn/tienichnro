<script setup lang="ts">
import UploadImage from '@/components/shared/UpladImage/index.vue';
import type { ManagedService } from '@/services/admin-service-management.service';
import { computed, ref, watch } from 'vue';
defineProps<{ saving: boolean; editing: boolean }>();
const emit = defineEmits<{ busy: [value: boolean] }>();
const draft = defineModel<ManagedService>({ required: true });
const uploader = ref<{ loading: boolean } | null>(null);
const uploading = computed(() => Boolean(uploader.value?.loading));
watch(uploading, (value) => emit('busy', value), { immediate: true });
</script>

<template>
    <fieldset :disabled="saving" class="grid min-w-0 gap-4 disabled:opacity-60">
        <div>
            <h2 class="font-bold text-slate-900 dark:text-white">{{ draft.name }}</h2>
            <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ draft.description }}</p>
            <p v-if="!(draft.route || draft.url)" class="mt-2 text-xs font-semibold text-amber-700 dark:text-amber-400">
                Đang phát triển. Bật công cụ vẫn hiển thị “Sắp ra mắt” cho đến khi có chức năng.
            </p>
        </div>
        <label class="grid gap-2 text-sm font-semibold text-slate-800 dark:text-slate-200">
            Mã công cụ
            <input
                v-model="draft.code"
                :readonly="editing"
                required
                maxlength="64"
                pattern="[a-z][a-z0-9_-]*"
                placeholder="dich_vu_moi"
                class="min-h-11 w-full rounded-[10px] border border-slate-300 bg-white px-3 font-normal read-only:bg-slate-100 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
            />
            <span class="text-xs font-normal text-slate-500">Dùng chữ thường, số, dấu gạch ngang hoặc gạch dưới. Mã không đổi sau khi tạo.</span>
        </label>
        <label class="grid gap-2 text-sm font-semibold text-slate-800 dark:text-slate-200">
            Thứ tự hiển thị
            <input
                v-model.number="draft.sort_order"
                type="number"
                min="0"
                max="1000000"
                step="1"
                required
                class="min-h-11 w-full rounded-[10px] border border-slate-300 bg-white px-3 font-normal focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
            />
            <span class="text-xs font-normal text-slate-500">Số nhỏ hiển thị trước trong danh sách công cụ.</span>
        </label>
        <label class="grid gap-2 text-sm font-semibold text-slate-800 dark:text-slate-200">
            Tên hiển thị
            <input
                v-model="draft.name"
                required
                maxlength="80"
                class="min-h-11 w-full rounded-[10px] border border-slate-300 bg-white px-3 font-normal focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
            />
        </label>
        <label class="grid gap-2 text-sm font-semibold text-slate-800 dark:text-slate-200">
            Mô tả công cụ
            <textarea
                v-model="draft.description"
                maxlength="500"
                rows="2"
                class="w-full rounded-[10px] border border-slate-300 bg-white p-3 font-normal dark:border-slate-600 dark:bg-slate-800 dark:text-white"
            ></textarea>
        </label>
        <label class="grid gap-2 text-sm font-semibold text-slate-800 dark:text-slate-200">
            Liên kết công cụ
            <input
                v-model="draft.url"
                maxlength="2048"
                placeholder="/duong-dan-dich-vu hoặc https://..."
                class="min-h-11 w-full rounded-[10px] border border-slate-300 bg-white px-3 font-normal dark:border-slate-600 dark:bg-slate-800 dark:text-white"
            />
            <span class="text-xs font-normal text-slate-500">{{
                draft.route ? 'Để trống để dùng trang công cụ có sẵn.' : 'Để trống để hiển thị Sắp ra mắt.'
            }}</span>
        </label>
        <label class="grid gap-2 text-sm font-semibold text-slate-800 dark:text-slate-200">
            Hình hiển thị
            <select
                v-model="draft.icon_type"
                class="min-h-11 w-full rounded-[10px] border border-slate-300 bg-white px-3 font-normal focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
            >
                <option value="icon">Icon</option>
                <option value="image">Ảnh vuông 1:1</option>
            </select>
        </label>
        <label v-if="draft.icon_type === 'icon'" class="grid gap-2 text-sm font-semibold text-slate-800 dark:text-slate-200">
            Mã icon Boxicons
            <input
                v-model="draft.icon"
                required
                maxlength="80"
                pattern="bx-[a-z0-9-]+"
                placeholder="bx-bell"
                class="min-h-11 w-full rounded-[10px] border border-slate-300 bg-white px-3 font-normal focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
            />
            <span class="text-xs font-normal text-slate-500 dark:text-slate-400">Ví dụ: bx-bell, bx-lock-alt, bx-shield-quarter, bx-download.</span>
        </label>
        <div v-else class="grid gap-3">
            <UploadImage
                ref="uploader"
                :accept="['image/jpeg', 'image/png', 'image/webp']"
                :square-size="256"
                :image-src="draft.image_url"
                :name-image="`service-${draft.code}`"
                @uploaded="draft.image_url = $event"
            />
            <label class="grid gap-2 text-sm font-semibold text-slate-800 dark:text-slate-200">
                URL ảnh
                <input
                    v-model="draft.image_url"
                    required
                    maxlength="2048"
                    placeholder="/storage/uploads/draft.webp"
                    class="min-h-11 w-full rounded-[10px] border border-slate-300 bg-white px-3 font-normal focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                />
            </label>
            <p class="text-xs leading-5 text-slate-500 dark:text-slate-400">
                Ảnh tải lên được cắt giữa thành ảnh vuông 256 × 256. Có thể dùng URL ảnh có sẵn.
            </p>
        </div>
        <div
            class="flex items-center gap-3 rounded-[10px] border border-slate-200 bg-slate-50 p-3 dark:border-slate-600 dark:bg-slate-800"
            aria-label="Xem trước công cụ"
        >
            <span
                class="relative grid aspect-square w-24 shrink-0 place-items-center overflow-hidden rounded-[10px] border border-emerald-200 bg-white text-4xl text-emerald-700"
            >
                <img
                    v-if="draft.icon_type === 'image' && draft.image_url"
                    :src="draft.image_url"
                    alt=""
                    class="absolute inset-0 h-full w-full object-cover"
                />
                <i v-else class="bx" :class="draft.icon" aria-hidden="true"></i>
                <span
                    v-if="!draft.is_enabled || !(draft.route || draft.url)"
                    class="absolute inset-x-1 bottom-2 rounded-[6px] border border-amber-300 bg-amber-100 py-0.5 text-center text-[10px] font-semibold leading-4 text-amber-900"
                    >{{ draft.is_enabled ? 'Sắp ra mắt' : 'Bảo trì' }}</span
                >
            </span>
            <strong class="min-w-0 break-words text-sm text-slate-900 dark:text-white">{{ draft.name }}</strong>
        </div>
        <label class="flex min-h-11 cursor-pointer items-center gap-3 text-sm font-semibold text-slate-800 dark:text-slate-200">
            <input v-model="draft.is_enabled" type="checkbox" class="size-5 accent-emerald-600" />
            {{ draft.is_enabled ? 'Bật công cụ' : 'Tắt công cụ · Bảo trì' }}
        </label>
        <label class="grid gap-2 text-sm font-semibold text-slate-800 dark:text-slate-200">
            Nội dung thông báo bảo trì
            <textarea
                v-model="draft.maintenance_message"
                required
                maxlength="1000"
                rows="3"
                class="w-full min-w-0 rounded-[10px] border border-slate-300 bg-white p-3 font-normal focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
            ></textarea>
        </label>
        <button
            type="submit"
            :disabled="saving || uploading"
            class="min-h-11 rounded-[10px] bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2"
        >
            {{ saving ? 'Đang lưu…' : 'Lưu công cụ' }}
        </button>
    </fieldset>
</template>
