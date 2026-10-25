<script setup lang="ts">
import { adminClientGame, type ClientGameCatalog, type ClientGameKind } from '@/services/admin-client-game.service';
import { handleErrorResponse } from '@/utils/response';
import { isAxiosError } from 'axios';
import { Download, LoaderCircle, Upload } from 'lucide-vue-next';
import { onMounted, reactive, ref } from 'vue';

const kinds: ClientGameKind[] = ['original', 'update'];
const labels = { original: 'Bản gốc', update: 'Bản cập nhật' };
const catalog = ref<ClientGameCatalog>({ original: null, update: null });
const forms = reactive<Record<ClientGameKind, { version: string; file: File | null; error: string; progress: number; success: string }>>({
    original: { version: '', file: null, error: '', progress: 0, success: '' },
    update: { version: '', file: null, error: '', progress: 0, success: '' },
});
const loading = ref(true);
const loadError = ref(false);
const busy = ref<ClientGameKind | null>(null);
const fileInputs = ref<Partial<Record<ClientGameKind, HTMLInputElement>>>({});
const inputClass =
    'w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100';

async function load() {
    loading.value = true;
    loadError.value = false;
    try {
        catalog.value = await adminClientGame.catalog();
        for (const kind of kinds) forms[kind].version = catalog.value[kind]?.version ?? '';
    } catch (error) {
        loadError.value = true;
        handleErrorResponse(error as Parameters<typeof handleErrorResponse>[0]);
    } finally {
        loading.value = false;
    }
}

function selectFile(kind: ClientGameKind, event: Event) {
    const input = event.target as HTMLInputElement;
    fileInputs.value[kind] = input;
    forms[kind].file = input.files?.[0] ?? null;
    forms[kind].error = '';
    forms[kind].success = '';
}

async function upload(kind: ClientGameKind) {
    const form = forms[kind];
    if (!form.file || busy.value) return;
    form.error = '';
    form.success = '';
    if (!form.file.name.toLowerCase().endsWith('.zip') || form.file.size > 100 * 1024 * 1024) {
        form.error = 'Vui lòng chọn file ZIP tối đa 100 MB.';
        return;
    }
    busy.value = kind;
    form.progress = 0;
    try {
        catalog.value = await adminClientGame.upload(kind, form.version, form.file, (value) => (form.progress = value));
        form.file = null;
        const input = fileInputs.value[kind];
        if (input) input.value = '';
        form.success = 'Đã lưu file ZIP thành công.';
    } catch (error) {
        if (isAxiosError(error) && error.response?.status === 422) {
            const errors = error.response.data.errors as Record<string, string[]> | undefined;
            form.error = errors ? Object.values(errors).flat().join(' ') : error.response.data.message;
        } else {
            handleErrorResponse(error as Parameters<typeof handleErrorResponse>[0]);
        }
    } finally {
        busy.value = null;
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-6 p-4 text-slate-900 dark:text-slate-100 md:p-6">
        <header>
            <h1 class="text-2xl font-bold">Client Game</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Quản lý ZIP bản gốc và bản cập nhật. Upload mới sẽ thay thế file cùng loại.</p>
        </header>
        <p v-if="loading" role="status" class="text-sm text-slate-500">Đang tải dữ liệu…</p>
        <div v-else-if="loadError" role="alert" class="flex items-center gap-4">
            <p>Không thể tải dữ liệu Client Game.</p>
            <button class="rounded-lg border px-4 py-2" @click="load">Thử lại</button>
        </div>
        <div v-else class="grid gap-6 lg:grid-cols-2">
            <section
                v-for="kind in kinds"
                :key="kind"
                class="min-w-0 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"
            >
                <h2 class="text-lg font-semibold">{{ labels[kind] }}</h2>
                <div v-if="catalog[kind]" class="mt-4 space-y-2 rounded-lg bg-slate-50 p-4 text-sm dark:bg-slate-800">
                    <p class="break-all font-medium">{{ catalog[kind]!.name }}</p>
                    <p>Phiên bản {{ catalog[kind]!.version }} · {{ (catalog[kind]!.size / 1024 / 1024).toFixed(2) }} MB</p>
                    <p class="text-slate-500 dark:text-slate-400">{{ new Date(catalog[kind]!.uploaded_at).toLocaleString('vi-VN') }}</p>
                    <p class="break-all font-mono text-xs">SHA-256: {{ catalog[kind]!.sha256 }}</p>
                    <a
                        v-if="catalog[kind]!.available"
                        :href="catalog[kind]!.download_url"
                        class="inline-flex items-center gap-2 text-blue-600 hover:underline dark:text-blue-400"
                    >
                        <Download class="size-4" /> Tải ZIP hiện tại
                    </a>
                    <p v-else class="text-rose-600 dark:text-rose-400">File không còn tồn tại. Vui lòng upload lại.</p>
                </div>
                <p v-else class="mt-4 text-sm text-slate-500 dark:text-slate-400">Chưa có file ZIP.</p>
                <form class="mt-5 flex flex-col gap-4" @submit.prevent="upload(kind)">
                    <label :for="`version-${kind}`" class="text-sm font-medium">
                        Phiên bản
                        <input
                            :id="`version-${kind}`"
                            v-model="forms[kind].version"
                            :class="inputClass"
                            class="mt-2"
                            placeholder="1.0.0"
                            required
                            maxlength="32"
                            pattern="[0-9]+\.[0-9]+\.[0-9]+(\.[0-9]+)?"
                            :disabled="busy !== null"
                        />
                    </label>
                    <label :for="`zip-${kind}`" class="text-sm font-medium">
                        File ZIP (tối đa 100 MB)
                        <input
                            :id="`zip-${kind}`"
                            type="file"
                            accept=".zip,application/zip"
                            :class="inputClass"
                            class="mt-2"
                            required
                            :disabled="busy !== null"
                            @change="selectFile(kind, $event)"
                        />
                    </label>
                    <p v-if="forms[kind].error" role="alert" class="text-sm text-rose-600 dark:text-rose-400">{{ forms[kind].error }}</p>
                    <p v-if="forms[kind].success" role="status" class="text-sm text-emerald-600 dark:text-emerald-400">{{ forms[kind].success }}</p>
                    <button
                        type="submit"
                        :disabled="busy !== null || !forms[kind].file"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <LoaderCircle v-if="busy === kind" class="size-4 animate-spin" />
                        <Upload v-else class="size-4" />
                        {{ busy === kind ? (forms[kind].progress === 100 ? 'Đang lưu…' : `Đang tải ${forms[kind].progress}%`) : 'Upload ZIP' }}
                    </button>
                </form>
            </section>
        </div>
        <section class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
            <h2 class="font-semibold">API cho launcher / client</h2>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                Dùng phiên license đã kích hoạt: Authorization: Bearer SESSION_TOKEN và X-License-Signature (RSA). Mỗi GET cần query product_code,
                device_uuid, session_id, generation, timestamp và nonce mới. Ký toàn bộ path + query, giống API license /status. File chưa upload trả
                về 404.
            </p>
            <ul class="mt-4 space-y-2 break-all font-mono text-xs">
                <li>GET /api/v1/licenses/client-game</li>
                <li>GET /api/v1/licenses/client-game/original/download</li>
                <li>GET /api/v1/licenses/client-game/update/download</li>
            </ul>
        </section>
    </div>
</template>
