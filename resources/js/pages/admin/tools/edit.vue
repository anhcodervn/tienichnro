<script setup lang="ts">
import { adminServiceManagement, type ManagedService } from '@/services/admin-service-management.service';
import type { ToolSeoSettings } from '@/types/tool-seo.type';
import { handleSuccessResponse } from '@/utils/response';
import { isAxiosError } from 'axios';
import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import ToolConfigurationForm from './ToolConfigurationForm.vue';
import ToolSeoEditor from './ToolSeoEditor.vue';

const route = useRoute();
const router = useRouter();
const code = computed(() => (typeof route.params.code === 'string' ? route.params.code : null));
const loading = ref(false);
const failed = ref(false);
const saving = ref(false);
const seoBusy = ref(false);
const configUploading = ref(false);
const tab = ref('configuration');
const errors = ref<string[]>([]);
const seo = ref<ToolSeoSettings | null>(null);
const emptyTool = (): ManagedService => ({
    code: '',
    name: '',
    sort_order: 1,
    description: '',
    icon_type: 'icon',
    icon: 'bx-grid-alt',
    image_url: null,
    url: null,
    route: null,
    is_enabled: true,
    is_available: false,
    maintenance_message: 'Công cụ đang bảo trì. Vui lòng quay lại sau.',
});
const draft = ref<ManagedService>(emptyTool());
const busy = computed(() => loading.value || saving.value || seoBusy.value || configUploading.value);
const errorMessages = (error: unknown): string[] => {
    if (isAxiosError<{ message?: string; errors?: Record<string, string[]> }>(error)) {
        return error.response?.data.errors
            ? Object.values(error.response.data.errors).flat()
            : [error.response?.data.message || 'Không thể lưu công cụ.'];
    }
    return ['Không thể thực hiện thao tác. Vui lòng thử lại.'];
};
let sequence = 0;
const load = async () => {
    const current = ++sequence;
    loading.value = true;
    failed.value = false;
    errors.value = [];
    seo.value = null;
    tab.value = route.query.tab === 'seo' && code.value ? 'seo' : 'configuration';
    try {
        if (code.value) {
            const result = await adminServiceManagement.get(code.value);
            if (current !== sequence) return;
            draft.value = result.service;
            seo.value = result.seo;
        } else {
            const tools = await adminServiceManagement.list();
            if (current !== sequence) return;
            draft.value = { ...emptyTool(), sort_order: Math.max(0, ...tools.map((tool) => tool.sort_order)) + 1 };
        }
    } catch (error) {
        if (current !== sequence) return;
        errors.value = errorMessages(error);
        failed.value = true;
    } finally {
        if (current === sequence) loading.value = false;
    }
};
const save = async () => {
    if (busy.value || failed.value) return;
    saving.value = true;
    errors.value = [];
    try {
        const creating = !code.value;
        const result = creating ? await adminServiceManagement.create(draft.value) : await adminServiceManagement.update(draft.value);
        draft.value = result;
        handleSuccessResponse({ data: { status: true, message: 'Đã lưu cấu hình công cụ.' } });
        if (creating) {
            await router.replace({ name: 'admin.tools.edit', params: { code: result.code }, query: { tab: 'seo' } });
        } else {
            const details = await adminServiceManagement.get(result.code);
            if (seo.value) {
                Object.assign(seo.value, {
                    slug: details.seo.slug,
                    path: details.seo.path,
                    canonical_url: details.seo.canonical_url,
                    is_local: details.seo.is_local,
                });
            }
        }
    } catch (error) {
        errors.value = errorMessages(error);
    } finally {
        saving.value = false;
    }
};
watch(code, load, { immediate: true });
</script>

<template>
    <div class="mx-auto grid max-w-7xl gap-5 text-slate-900 dark:text-slate-100">
        <header
            class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"
        >
            <div>
                <h1 class="text-xl font-bold">
                    {{ code ? 'Sửa công cụ' : 'Thêm công cụ' }}<span v-if="code && draft.name"> · {{ draft.name }}</span>
                </h1>
                <p class="mt-2 text-sm text-slate-500">Cấu hình hiển thị và bài SEO tại đúng liên kết của công cụ.</p>
            </div>
            <RouterLink to="/admin/tools" class="text-sm font-semibold text-emerald-700 dark:text-emerald-400">Danh sách công cụ</RouterLink>
        </header>
        <div class="flex gap-2" role="tablist" aria-label="Cấu hình công cụ">
            <button
                id="tool-config-tab"
                type="button"
                role="tab"
                aria-controls="tool-config-panel"
                :aria-selected="tab === 'configuration'"
                :disabled="busy"
                class="min-h-11 rounded-lg border px-5 py-2 text-sm font-semibold disabled:opacity-50"
                :class="
                    tab === 'configuration'
                        ? 'border-emerald-700 bg-emerald-700 text-white'
                        : 'border-slate-300 bg-white dark:border-slate-600 dark:bg-slate-900'
                "
                @click="tab = 'configuration'"
            >
                Cấu hình
            </button>
            <button
                id="tool-seo-tab"
                type="button"
                role="tab"
                aria-controls="tool-seo-panel"
                :aria-selected="tab === 'seo'"
                :disabled="busy || !code || failed"
                class="min-h-11 rounded-lg border px-5 py-2 text-sm font-semibold disabled:opacity-50"
                :class="
                    tab === 'seo'
                        ? 'border-emerald-700 bg-emerald-700 text-white'
                        : 'border-slate-300 bg-white dark:border-slate-600 dark:bg-slate-900'
                "
                @click="tab = 'seo'"
            >
                Bài SEO
            </button>
        </div>
        <p v-if="!code" class="text-sm text-slate-500">Lưu cấu hình công cụ trước để mở tab Bài SEO.</p>
        <p v-if="loading" role="status">Đang tải công cụ…</p>
        <div v-if="errors.length" role="alert" class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
            <ul class="grid gap-1">
                <li v-for="message in errors" :key="message">{{ message }}</li>
            </ul>
            <button v-if="failed" type="button" class="mt-3 min-h-11 font-semibold underline" @click="load">Tải lại</button>
        </div>
        <template v-if="!loading && !failed">
            <section
                v-show="tab === 'configuration'"
                id="tool-config-panel"
                role="tabpanel"
                aria-labelledby="tool-config-tab"
                class="max-w-3xl rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900 sm:p-6"
            >
                <form @submit.prevent="save">
                    <ToolConfigurationForm v-model="draft" :saving="saving" :editing="Boolean(code)" @busy="configUploading = $event" />
                </form>
            </section>
            <section v-if="seo && code" v-show="tab === 'seo'" id="tool-seo-panel" role="tabpanel" aria-labelledby="tool-seo-tab">
                <ToolSeoEditor v-model="seo" :code="code" @busy="seoBusy = $event" />
            </section>
        </template>
    </div>
</template>
