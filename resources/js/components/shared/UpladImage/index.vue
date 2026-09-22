<template>
    <div>
        <div
            class="relative cursor-pointer rounded-xl border-2 border-dashed border-gray-300 p-6 text-center transition-all duration-300 hover:border-blue-400 hover:bg-blue-50"
            @click="triggerFile"
            @dragover.prevent
            @drop.prevent="handleDrop"
        >
            <input ref="fileInput" type="file" :accept="acceptString" class="hidden" @change="handleFile" />

            <p v-if="!preview" class="text-gray-400">Kéo ảnh vào đây hoặc click để chọn</p>

            <div v-if="preview" class="relative">
                <img
                    :src="preview"
                    class="mx-auto max-h-40 rounded-lg object-contain transition duration-300"
                    :class="{ 'scale-95 blur-sm': loading }"
                />

                <div v-if="loading" class="absolute inset-0 flex flex-col items-center justify-center rounded-lg bg-black/40">
                    <div class="mb-2 h-8 w-8 animate-spin rounded-full border-4 border-white border-t-transparent"></div>
                    <p class="text-xs text-white">Đang upload...</p>

                    <div class="mt-2 h-1 w-3/4 overflow-hidden rounded bg-gray-300">
                        <div class="h-full bg-green-400 transition-all duration-300" :style="{ width: progress + '%' }"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3 flex items-center justify-between gap-3 text-xs text-slate-500">
            <span>Ảnh sẽ được upload ngay sau khi chọn.</span>
            <span v-if="loading">Đang xử lý {{ Math.round(progress) }}%</span>
        </div>
    </div>
</template>

<script setup lang="ts">
import api from '@/config/axios';
import Swal from 'sweetalert2';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    accept?: string[];
    compress?: boolean;
    imageSrc?: string | null;
    nameImage?: string;
    squareSize?: number;
    uploadUrl?: string;
}>();

const emit = defineEmits<{
    (e: 'uploaded', url: string): void;
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const preview = ref<string | null>(null);
const file = ref<File | null>(null);
const loading = ref(false);
const imageName = ref('');
const progress = ref(0);
let progressInterval: ReturnType<typeof setInterval> | null = null;

watch(
    [() => props.imageSrc, () => props.nameImage],
    ([newSrc, newName]) => {
        if (!file.value) {
            preview.value = newSrc ?? null;
        }

        imageName.value = newName ?? '';
    },
    { immediate: true },
);

const acceptString = computed(() => props.accept?.join(',') || 'image/*');

const triggerFile = (): void => {
    if (!loading.value) {
        fileInput.value?.click();
    }
};

const handleFile = async (event: Event): Promise<void> => {
    const target = event.target as HTMLInputElement;
    const selected = target.files?.[0];

    if (selected) {
        await processFile(selected);
    }
};

const handleDrop = async (event: DragEvent): Promise<void> => {
    const dropped = event.dataTransfer?.files?.[0];

    if (dropped) {
        await processFile(dropped);
    }
};

const compressImage = (selectedFile: File): Promise<File> => {
    return new Promise((resolve) => {
        const img = new Image();
        const reader = new FileReader();

        reader.onload = (loadEvent) => {
            img.src = loadEvent.target?.result as string;
        };

        img.onload = () => {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');

            if (!ctx) {
                resolve(selectedFile);
                return;
            }

            const scale = 0.7;
            canvas.width = img.width * scale;
            canvas.height = img.height * scale;

            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

            canvas.toBlob(
                (blob) => {
                    if (!blob) {
                        resolve(selectedFile);
                        return;
                    }

                    resolve(
                        new File([blob], selectedFile.name.replace(/\.[^.]+$/, '.webp'), {
                            type: 'image/webp',
                        }),
                    );
                },
                'image/webp',
                0.82,
            );
        };

        reader.readAsDataURL(selectedFile);
    });
};

const convertToSquareWebp = (selectedFile: File, size: number): Promise<File> => {
    return new Promise((resolve, reject) => {
        const image = new Image();
        const objectUrl = URL.createObjectURL(selectedFile);
        const cleanup = (): void => URL.revokeObjectURL(objectUrl);

        image.onload = () => {
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');

            if (!context) {
                cleanup();
                reject(new Error('Trình duyệt không thể xử lý ảnh. Vui lòng thử trình duyệt khác.'));
                return;
            }

            const sourceWidth = image.naturalWidth;
            const sourceHeight = image.naturalHeight;
            const cropSize = Math.min(sourceWidth, sourceHeight);
            const sourceX = (sourceWidth - cropSize) / 2;
            const sourceY = (sourceHeight - cropSize) / 2;

            canvas.width = size;
            canvas.height = size;
            context.clearRect(0, 0, size, size);
            context.drawImage(image, sourceX, sourceY, cropSize, cropSize, 0, 0, size, size);
            canvas.toBlob(
                (blob) => {
                    cleanup();

                    if (!blob || blob.type !== 'image/webp') {
                        reject(new Error('Trình duyệt chưa hỗ trợ chuyển ảnh sang WebP. Vui lòng cập nhật trình duyệt.'));
                        return;
                    }

                    resolve(
                        new File([blob], selectedFile.name.replace(/\.[^.]+$/, '') + '.webp', {
                            type: 'image/webp',
                            lastModified: Date.now(),
                        }),
                    );
                },
                'image/webp',
                0.82,
            );
        };

        image.onerror = () => {
            cleanup();
            reject(new Error('Không thể đọc ảnh đã chọn.'));
        };
        image.src = objectUrl;
    });
};

const upload = async (): Promise<void> => {
    if (!file.value) {
        return;
    }

    loading.value = true;
    progress.value = 0;

    progressInterval = setInterval(() => {
        if (progress.value < 90) {
            progress.value += Math.random() * 10;
        }
    }, 200);

    try {
        let uploadFile = file.value;

        if (props.squareSize && props.squareSize > 0) {
            uploadFile = await convertToSquareWebp(file.value, props.squareSize);
        } else if (props.compress) {
            uploadFile = await compressImage(file.value);
        }

        const formData = new FormData();
        formData.append('image', uploadFile);
        formData.append('name', imageName.value);

        const response = await api.post(props.uploadUrl ?? '/api/uploads/image', formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        const imageUrl = response.data?.url || response.data?.data?.url;

        if (!imageUrl) {
            throw new Error('Upload failed');
        }

        progress.value = 100;
        preview.value = imageUrl;
        emit('uploaded', imageUrl);
        file.value = null;

        if (fileInput.value) {
            fileInput.value.value = '';
        }

        Swal.fire('Thành công', 'Tải ảnh lên thành công.', 'success');
    } catch (error) {
        console.error(error);
        const uploadError = error as { message?: string; response?: { data?: { message?: string } } };
        const message = uploadError.response?.data?.message || uploadError.message || 'Có lỗi xảy ra khi upload ảnh.';

        Swal.fire('Thất bại', message, 'error');
    } finally {
        if (progressInterval) {
            clearInterval(progressInterval);
        }

        setTimeout(() => {
            loading.value = false;
            progress.value = 0;
        }, 500);
    }
};

const processFile = async (selectedFile: File): Promise<void> => {
    if (!selectedFile.type.startsWith('image/')) {
        await Swal.fire('Không hợp lệ', 'Chỉ được chọn file ảnh.', 'warning');
        return;
    }

    file.value = selectedFile;
    preview.value = URL.createObjectURL(selectedFile);
    await upload();
};
</script>
