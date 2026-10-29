<script setup lang="ts">
import Editor from '@/components/shared/Editor/index.vue';
import UploadImage from '@/components/shared/UpladImage/index.vue';
import { adminServiceManagement } from '@/services/admin-service-management.service';
import type { ToolSeoPayload, ToolSeoSettings } from '@/types/tool-seo.type';
import { uploadEditorImages } from '@/utils/editor-image-upload';
import { handleSuccessResponse } from '@/utils/response';
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps<{ code: string }>();
const emit = defineEmits<{ busy: [value: boolean] }>();
const form = defineModel<ToolSeoSettings>({ required: true });
const editor = ref<{ flush: () => unknown[] | string; isUploadingImage: boolean } | null>(null);
const cover = ref<{ loading: boolean } | null>(null);
const editorKey = ref(0);
const coverKey = ref(0);
const saving = ref(false);
const errorMessage = ref('');
const busy = computed(() => saving.value || Boolean(editor.value?.isUploadingImage) || Boolean(cover.value?.loading));
const inputClass =
    'min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100';
const cardClass = 'min-w-0 rounded-xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-700 dark:bg-slate-900';
const save = async () => {
    if (busy.value) return;
    saving.value = true;
    errorMessage.value = '';
    try {
        const content = editor.value?.flush();
        if (Array.isArray(content)) form.value.content = content;
        await nextTick();
        form.value.content = await uploadEditorImages(form.value.content ?? []);
        const value = form.value;
        const payload: ToolSeoPayload = {
            title: value.title,
            excerpt: value.excerpt,
            language: value.language,
            content: value.content,
            faq: (value.faq ?? [])
                .map((item) => ({ question: item.question.trim(), answer: item.answer.trim() }))
                .filter((item) => item.question || item.answer),
            seo_title: value.seo_title,
            seo_description: value.seo_description,
            meta_keywords: value.meta_keywords,
            focus_keyword: value.focus_keyword,
            cover_image: value.cover_image,
            cover_alt: value.cover_alt,
            robots: value.robots,
            article_schema: value.article_schema,
            breadcrumb_schema: value.breadcrumb_schema,
            is_published: value.is_published,
            expected_version: value.lock_version,
        };
        form.value = await adminServiceManagement.updateSeo(props.code, payload);
        editorKey.value++;
        handleSuccessResponse({ data: { status: true, message: 'Đã lưu bài SEO công cụ.' } });
    } catch (error) {
        const response = error as { response?: { data?: { message?: string } }; message?: string };
        errorMessage.value = response.response?.data.message || response.message || 'Không thể lưu bài SEO.';
    } finally {
        saving.value = false;
    }
};
const clearCover = () => {
    form.value.cover_image = null;
    form.value.cover_alt = null;
    coverKey.value++;
};
const addFaq = () => form.value.faq.push({ question: '', answer: '' });
watch(busy, (value) => emit('busy', value), { immediate: true });
</script>

<template>
    <form class="grid gap-4" @submit.prevent="save">
        <p v-if="errorMessage" role="alert" class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ errorMessage }}</p>
        <fieldset :disabled="busy" :inert="busy ? true : undefined" class="grid min-w-0 items-start gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
            <section class="grid min-w-0 gap-4" aria-label="Soạn bài SEO công cụ">
                <article :class="cardClass">
                    <h2 class="mb-4 text-base font-bold">Thông tin bài viết</h2>
                    <div class="grid gap-4">
                        <label class="grid gap-2 text-sm font-semibold"
                            >Tiêu đề bài viết<input v-model="form.title" :class="inputClass" maxlength="255" :required="form.is_published"
                        /></label>
                        <label class="grid gap-2 text-sm font-semibold"
                            >Mô tả ngắn<textarea v-model="form.excerpt" :class="inputClass" rows="2"></textarea>
                        </label>
                        <label class="grid gap-2 text-sm font-semibold"
                            >SEO title<input v-model="form.seo_title" :class="inputClass" maxlength="255"
                        /></label>
                        <label class="grid gap-2 text-sm font-semibold"
                            >SEO description<textarea v-model="form.seo_description" :class="inputClass" rows="3" maxlength="320"></textarea>
                        </label>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="grid gap-2 text-sm font-semibold"
                                >Keyword<input v-model="form.focus_keyword" :class="inputClass" maxlength="255"
                            /></label>
                            <label class="grid gap-2 text-sm font-semibold"
                                >Meta keywords<input v-model="form.meta_keywords" :class="inputClass" maxlength="1000"
                            /></label>
                        </div>
                        <label class="grid gap-2 text-sm font-semibold"
                            >Slug theo liên kết công cụ<input :value="form.slug" readonly :class="inputClass" /><span
                                class="text-xs font-normal text-slate-500"
                                >Được lấy tự động từ liên kết đã lưu ở tab Cấu hình.</span
                            ></label
                        >
                        <label class="grid gap-2 text-sm font-semibold"
                            >Ngôn ngữ bài viết<input v-model="form.language" :class="inputClass" maxlength="35" required placeholder="vi"
                        /></label>
                    </div>
                </article>
                <article :class="cardClass">
                    <h2 class="text-base font-bold">Nội dung bài viết</h2>
                    <p class="mb-4 mt-2 text-sm text-slate-500">
                        Tiêu đề bài viết ở trên được dùng làm H1 bên dưới công cụ. Dùng H2, H3 để chia mục trong nội dung.
                    </p>
                    <Editor :key="editorKey" ref="editor" :model-value="form.content" @update:model-value="form.content = $event" />
                </article>
                <article :class="cardClass">
                    <h2 class="text-base font-bold">Câu hỏi thường gặp (FAQ)</h2>
                    <div class="mt-4 grid gap-3">
                        <div
                            v-for="(item, index) in form.faq"
                            :key="index"
                            class="grid gap-2 rounded-lg border border-slate-200 p-3 dark:border-slate-700"
                        >
                            <label class="grid gap-1 text-sm">Câu hỏi<input v-model="item.question" :class="inputClass" maxlength="255" /></label>
                            <label class="grid gap-1 text-sm"
                                >Trả lời<textarea v-model="item.answer" :class="inputClass" rows="2" maxlength="2000"></textarea>
                            </label>
                            <button type="button" class="min-h-11 text-sm text-rose-600" @click="form.faq.splice(index, 1)">Xóa câu hỏi</button>
                        </div>
                        <button
                            type="button"
                            class="min-h-11 text-sm font-semibold text-emerald-700 dark:text-emerald-400"
                            :disabled="form.faq.length >= 20"
                            @click="addFaq"
                        >
                            Thêm câu hỏi
                        </button>
                    </div>
                </article>
            </section>
            <aside class="grid min-w-0 gap-4" aria-label="SEO và ảnh đại diện">
                <article :class="cardClass">
                    <h2 class="mb-4 text-base font-bold">Hiển thị trên Google</h2>
                    <div class="mb-4 rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <p class="break-all text-xs text-slate-500">{{ form.canonical_url || 'Chưa có liên kết nội bộ' }}</p>
                        <p class="mt-2 break-words text-lg text-blue-700 dark:text-blue-400">
                            {{ form.seo_title || form.title || 'Tiêu đề bài viết' }}
                        </p>
                        <p class="mt-1 break-words text-sm text-slate-600 dark:text-slate-300">
                            {{ form.seo_description || form.excerpt || 'Mô tả trên kết quả tìm kiếm.' }}
                        </p>
                    </div>
                    <div class="grid gap-4">
                        <label class="grid gap-2 text-sm font-semibold"
                            >Canonical tự động<input :value="form.canonical_url || ''" :class="inputClass" readonly
                        /></label>
                        <p v-if="!form.is_local" class="text-sm text-amber-700 dark:text-amber-400">
                            Hãy lưu liên kết nội bộ của công cụ ở tab Cấu hình trước khi xuất bản.
                        </p>
                        <label class="grid gap-2 text-sm font-semibold"
                            >Cho phép lập chỉ mục<select v-model="form.robots" :class="inputClass">
                                <option value="index,follow">index,follow</option>
                                <option value="noindex,follow">noindex,follow</option>
                            </select></label
                        >
                        <label class="flex min-h-11 items-center gap-2 text-sm"
                            ><input v-model="form.article_schema" type="checkbox" class="rounded border-slate-300 text-emerald-600" />Schema
                            Article</label
                        >
                        <label class="flex min-h-11 items-center gap-2 text-sm"
                            ><input v-model="form.breadcrumb_schema" type="checkbox" class="rounded border-slate-300 text-emerald-600" />Schema
                            Breadcrumb</label
                        >
                        <label class="flex min-h-11 items-center gap-2 text-sm"
                            ><input
                                v-model="form.is_published"
                                type="checkbox"
                                :disabled="!form.is_local"
                                class="rounded border-slate-300 text-emerald-600"
                            />Xuất bản bài SEO</label
                        >
                        <button
                            type="submit"
                            :disabled="busy"
                            class="min-h-11 w-full rounded-lg bg-emerald-700 px-4 py-3 text-sm font-bold text-white disabled:opacity-50"
                        >
                            {{ saving ? 'Đang lưu…' : 'Lưu bài SEO' }}
                        </button>
                        <p class="text-xs text-slate-500">Tắt xuất bản để lưu nháp. Bài SEO được lưu riêng với cấu hình công cụ.</p>
                    </div>
                </article>
                <article :class="cardClass">
                    <h2 class="mb-4 text-base font-bold">Ảnh đại diện SEO</h2>
                    <UploadImage
                        :key="coverKey"
                        ref="cover"
                        :image-src="form.cover_image"
                        :disabled="busy"
                        :compress="true"
                        @uploaded="form.cover_image = $event"
                    />
                    <button v-if="form.cover_image" type="button" class="mt-2 min-h-11 text-sm text-rose-600" @click="clearCover">Xóa ảnh</button>
                    <label class="mt-4 grid gap-2 text-sm font-semibold"
                        >Mô tả ảnh (alt)<input v-model="form.cover_alt" :class="inputClass" :required="Boolean(form.cover_image)" maxlength="255"
                    /></label>
                </article>
            </aside>
        </fieldset>
    </form>
</template>
