<template>
    <div class="space-y-4">
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
            <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Slider sản phẩm nổi bật</h2>
                    <p class="text-sm text-slate-500">
                        Mỗi domain có danh sách slide riêng, gồm ảnh, tiêu đề và liên kết chuyển hướng. Bạn có thể sắp xếp lại thứ tự hiển thị trực
                        tiếp trong danh sách này.
                    </p>
                </div>

                <button
                    class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:bg-slate-300"
                    :disabled="isSaving"
                    @click="submitForm"
                >
                    {{ isSaving ? 'Đang lưu...' : 'Lưu slider nổi bật' }}
                </button>
            </div>

            <div class="grid gap-4 xl:grid-cols-[260px_minmax(0,1fr)]">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <h3 class="text-sm font-semibold text-slate-900">Thêm slide mới</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Tải ảnh lên để tạo slide mới. Sau khi thêm xong, modal sẽ mở để bạn nhập tiêu đề và liên kết.
                    </p>

                    <div class="mt-4 overflow-hidden rounded-2xl border border-dashed border-slate-300 bg-white p-3">
                        <UploadImage :accept="['image/png', 'image/jpeg', 'image/webp']" :compress="true" @uploaded="appendImage" />
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 p-4">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">Danh sách slide</h3>
                            <p class="text-sm text-slate-500">
                                Tổng số slide:
                                <span class="font-semibold text-slate-900">{{ formData.items.length }}</span>
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="!formData.items.length"
                        class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500"
                    >
                        Chưa có slide nổi bật nào. Bạn hãy tải ảnh đầu tiên lên.
                    </div>

                    <div v-else class="space-y-3">
                        <article
                            v-for="(item, index) in formData.items"
                            :key="`${item.id ?? 'new'}-${item.image}-${index}`"
                            class="rounded-2xl border border-slate-200 bg-slate-50 p-3"
                        >
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                <div class="flex min-w-0 flex-1 items-center gap-3">
                                    <button
                                        type="button"
                                        class="h-16 w-24 shrink-0 overflow-hidden rounded-xl border border-slate-200 bg-white"
                                        @click="openEditModal(index)"
                                    >
                                        <img :src="item.image" :alt="item.title || `slide-${index + 1}`" class="h-full w-full object-cover" />
                                    </button>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="rounded-full bg-slate-900 px-2.5 py-1 text-xs font-semibold text-white">
                                                #{{ index + 1 }}
                                            </span>
                                            <p class="truncate text-sm font-medium text-slate-900">
                                                {{ item.title || `Slide nổi bật ${index + 1}` }}
                                            </p>
                                            <span
                                                class="rounded-full px-2.5 py-1 text-xs font-medium"
                                                :class="item.status ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'"
                                            >
                                                {{ item.status ? 'Đang hiển thị' : 'Đã ẩn' }}
                                            </span>
                                        </div>

                                        <p class="mt-1 truncate text-xs text-slate-500">
                                            {{ item.link_redirect || 'Chưa có liên kết chuyển hướng' }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2 sm:justify-end">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:border-slate-400 hover:bg-white"
                                        @click="openEditModal(index)"
                                    >
                                        Sửa
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:border-slate-400 hover:bg-white disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="index === 0"
                                        @click="moveItem(index, 'up')"
                                    >
                                        Lên
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:border-slate-400 hover:bg-white disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="index === formData.items.length - 1"
                                        @click="moveItem(index, 'down')"
                                    >
                                        Xuống
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-600 hover:border-rose-300 hover:bg-rose-50"
                                        @click="removeItem(index)"
                                    >
                                        Xóa
                                    </button>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <Modal :modelValue="isEditModalOpen" panelClass="max-w-4xl" @update:modelValue="closeEditModal">
            <template #header>
                <div class="border-b border-slate-200 px-4 py-3 pr-12 md:px-5">
                    <h3 class="text-base font-semibold text-slate-900">Sửa slide nổi bật</h3>
                    <p class="mt-1 text-sm text-slate-500">Chỉnh ảnh, tiêu đề và liên kết chuyển hướng cho slide đang chọn.</p>
                </div>
            </template>

            <div v-if="editingItem" class="px-4 pb-4 md:px-5 md:pb-5">
                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                            <span class="text-sm font-semibold text-slate-900">Xem trước slide</span>
                            <span class="rounded-full bg-slate-900 px-2.5 py-1 text-xs font-semibold text-white">
                                #{{ (editingIndex ?? 0) + 1 }}
                            </span>
                        </div>

                        <div class="bg-slate-100 p-3">
                            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                                <img :src="editingItem.image" :alt="editingItem.title || 'slide-preview'" class="max-h-[360px] w-full object-cover" />
                            </div>
                        </div>

                        <div class="space-y-3 px-4 py-4">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Tiêu đề ảnh</label>
                                <input
                                    v-model="editingItem.title"
                                    type="text"
                                    class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-slate-500"
                                    placeholder="Ví dụ: Gói nạp game nổi bật"
                                />
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Liên kết chuyển hướng</label>
                                <input
                                    v-model="editingItem.link_redirect"
                                    type="text"
                                    class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-slate-500"
                                    placeholder="https://domain.com/san-pham-noi-bat"
                                />
                            </div>

                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input v-model="editingItem.status" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                                Hiển thị slide này trên website
                            </label>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <h4 class="text-sm font-semibold text-slate-900">Thay ảnh</h4>
                        <p class="mt-1 text-sm text-slate-500">
                            Tải ảnh mới để thay trực tiếp vào slide hiện tại. Tiêu đề và liên kết sẽ được giữ nguyên.
                        </p>

                        <div class="mt-4 overflow-hidden rounded-2xl border border-dashed border-slate-300 bg-white p-3">
                            <UploadImage
                                :accept="['image/png', 'image/jpeg', 'image/webp']"
                                :compress="true"
                                :image-src="editingItem.image"
                                @uploaded="replaceEditingImage"
                            />
                        </div>

                        <p class="mt-4 break-all rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-500">
                            {{ editingItem.image }}
                        </p>

                        <button
                            type="button"
                            class="mt-4 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:border-slate-400 hover:bg-white"
                            @click="closeEditModal"
                        >
                            Đóng
                        </button>
                    </div>
                </div>
            </div>
        </Modal>
    </div>
</template>

<script setup lang="ts">
import Modal from '@/components/shared/Modal/index.vue';
import UploadImage from '@/components/shared/UpladImage/index.vue';
import { adminSettingService } from '@/services/admin-setting.service';
import type { FeaturedSliderItemType, SliderImageSettingType } from '@/types/setting.type';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { computed, onMounted, ref } from 'vue';

const isSaving = ref(false);
const isEditModalOpen = ref(false);
const editingIndex = ref<number | null>(null);
const formData = ref<SliderImageSettingType>({
    items: [],
});

const editingItem = computed<FeaturedSliderItemType | null>(() => {
    if (editingIndex.value === null) {
        return null;
    }

    return formData.value.items[editingIndex.value] ?? null;
});

const normalizeItem = (item: Partial<FeaturedSliderItemType>): FeaturedSliderItemType => ({
    id: item.id ?? null,
    image: item.image ?? '',
    title: item.title ?? '',
    link_redirect: item.link_redirect ?? '',
    status: item.status ?? true,
    sort_order: item.sort_order ?? 0,
});

const loadData = async (): Promise<void> => {
    try {
        const data = await adminSettingService.getFeaturedSliders();
        formData.value = {
            items: Array.isArray(data.settings.items) ? data.settings.items.map(normalizeItem) : [],
        };
    } catch (err) {
        handleErrorResponse(err);
    }
};

const appendImage = (url: string): void => {
    formData.value = {
        items: [
            ...formData.value.items,
            normalizeItem({
                image: url,
                title: '',
                link_redirect: '',
                status: true,
            }),
        ],
    };

    openEditModal(formData.value.items.length - 1);
};

const replaceEditingImage = (url: string): void => {
    if (editingIndex.value === null) {
        return;
    }

    const nextItems = [...formData.value.items];
    nextItems[editingIndex.value] = normalizeItem({
        ...nextItems[editingIndex.value],
        image: url,
    });

    formData.value = {
        items: nextItems,
    };
};

const removeItem = (index: number): void => {
    formData.value = {
        items: formData.value.items.filter((_, itemIndex) => itemIndex !== index),
    };

    if (editingIndex.value === index) {
        closeEditModal();
        return;
    }

    if (editingIndex.value !== null && editingIndex.value > index) {
        editingIndex.value -= 1;
    }
};

const moveItem = (index: number, direction: 'up' | 'down'): void => {
    const nextIndex = direction === 'up' ? index - 1 : index + 1;

    if (nextIndex < 0 || nextIndex >= formData.value.items.length) {
        return;
    }

    const nextItems = [...formData.value.items];
    [nextItems[index], nextItems[nextIndex]] = [nextItems[nextIndex], nextItems[index]];
    formData.value = { items: nextItems };

    if (editingIndex.value === index) {
        editingIndex.value = nextIndex;
    } else if (editingIndex.value === nextIndex) {
        editingIndex.value = index;
    }
};

const openEditModal = (index: number): void => {
    editingIndex.value = index;
    isEditModalOpen.value = true;
};

const closeEditModal = (): void => {
    isEditModalOpen.value = false;
    editingIndex.value = null;
};

const submitForm = async (): Promise<void> => {
    try {
        isSaving.value = true;

        const payload: SliderImageSettingType = {
            items: formData.value.items
                .filter((item) => typeof item.image === 'string' && item.image.trim() !== '')
                .map((item, index) => ({
                    id: item.id ?? null,
                    image: item.image.trim(),
                    title: item.title.trim(),
                    link_redirect: item.link_redirect.trim(),
                    status: item.status,
                    sort_order: index,
                })),
        };

        const res = await adminSettingService.updateFeaturedSliders(payload);
        formData.value = {
            items: Array.isArray(res.settings.items) ? res.settings.items.map(normalizeItem) : [],
        };

        handleSuccessResponse({
            data: {
                status: true,
                message: 'Cập nhật slider nổi bật thành công',
            },
        });

        closeEditModal();
    } catch (err) {
        handleErrorResponse(err);
    } finally {
        isSaving.value = false;
    }
};

onMounted(loadData);
</script>
